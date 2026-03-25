const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const mysql = require('mysql2/promise');
const { createWorker } = require('tesseract.js');
const faceapi = require('face-api.js');
const { Canvas, Image, ImageData } = require('canvas');
const cors = require('cors');

// Configure face-api.js to use canvas
faceapi.env.monkeyPatch({ Canvas, Image, ImageData });

const app = express();
const PORT = process.env.PORT || 3001; // Different port to avoid conflicts

// Database configuration
const dbConfig = {
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'servicelink'
};

// Middleware
app.use(cors());
app.use(express.json());

// Configure multer for file uploads
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        const uploadDir = path.join(__dirname, 'uploads', 'temp');
        if (!fs.existsSync(uploadDir)) {
            fs.mkdirSync(uploadDir, { recursive: true });
        }
        cb(null, uploadDir);
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        cb(null, file.fieldname + '-' + uniqueSuffix + path.extname(file.originalname));
    }
});

const upload = multer({
    storage: storage,
    limits: {
        fileSize: 10 * 1024 * 1024 // 10MB limit
    },
    fileFilter: (req, file, cb) => {
        if (file.mimetype.startsWith('image/')) {
            cb(null, true);
        } else {
            cb(new Error('Only image files are allowed!'), false);
        }
    }
});

// Database connection
async function getDBConnection() {
    return await mysql.createConnection(dbConfig);
}

// Load face-api.js models
async function loadModels() {
    try {
        const modelPath = path.join(__dirname, 'models');

        // Check if models exist
        const requiredModels = ['ssdMobilenetv1Model', 'faceLandmark68Net', 'faceRecognitionNet'];
        const modelsExist = requiredModels.every(model => {
            const modelDir = path.join(modelPath, model);
            return fs.existsSync(modelDir) && fs.readdirSync(modelDir).length > 0;
        });

        if (!modelsExist) {
            console.log('⚠️  Face-api.js models not found. Face verification will be skipped.');
            return false;
        }

        // Load models
        await faceapi.nets.ssdMobilenetv1.loadFromDisk(path.join(modelPath, 'ssdMobilenetv1Model'));
        await faceapi.nets.faceLandmark68Net.loadFromDisk(path.join(modelPath, 'faceLandmark68Net'));
        await faceapi.nets.faceRecognitionNet.loadFromDisk(path.join(modelPath, 'faceRecognitionNet'));

        console.log('✅ Face-api.js models loaded successfully');
        return true;
    } catch (error) {
        console.error('❌ Error loading face-api.js models:', error);
        return false;
    }
}

// OCR function using Tesseract
async function extractTextFromImage(imagePath) {
    try {
        const worker = await createWorker('eng');
        const { data: { text } } = await worker.recognize(imagePath);
        await worker.terminate();
        return text.trim();
    } catch (error) {
        console.error('OCR Error:', error);
        return '';
    }
}

// Validate ID text
function validateIDText(text) {
    const lines = text.split('\n').map(line => line.trim()).filter(line => line.length > 0);

    let hasFullName = false;
    let hasNumber = false;
    let hasKeywords = false;

    // Check for full name (at least 2 words)
    for (const line of lines) {
        const words = line.split(/\s+/).filter(word => word.length > 1);
        if (words.length >= 2) {
            // Check if it looks like a name (contains letters, possibly with spaces/hyphens)
            const nameRegex = /^[a-zA-Z\s\-']+$/;
            if (nameRegex.test(line) && line.length > 3) {
                hasFullName = true;
                break;
            }
        }
    }

    // Check for numbers (ID numbers)
    const numberRegex = /\d{3,}/; // At least 3 consecutive digits
    hasNumber = numberRegex.test(text);

    // Check for keywords
    const keywords = ['ID', 'Republic', 'License', 'Driver', 'Card', 'Number', 'Valid', 'Philippines', 'Government'];
    hasKeywords = keywords.some(keyword =>
        text.toLowerCase().includes(keyword.toLowerCase())
    );

    return {
        isValid: hasFullName && hasNumber && hasKeywords,
        hasFullName,
        hasNumber,
        hasKeywords,
        extractedText: text,
        lineCount: lines.length,
        confidence: (hasFullName ? 0.4 : 0) + (hasNumber ? 0.3 : 0) + (hasKeywords ? 0.3 : 0)
    };
}

// Face comparison function
async function compareFaces(idImagePath, selfieImagePath) {
    try {
        // Check if models are loaded
        if (!global.modelsLoaded) {
            return {
                similarity: 0,
                error: 'Face recognition models not available'
            };
        }

        // Load the images
        const idImage = await Canvas.loadImage(idImagePath);
        const selfieImage = await Canvas.loadImage(selfieImagePath);

        // Detect faces
        const idDetection = await faceapi.detectSingleFace(idImage)
            .withFaceLandmarks()
            .withFaceDescriptor();

        const selfieDetection = await faceapi.detectSingleFace(selfieImage)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!idDetection || !selfieDetection) {
            return {
                similarity: 0,
                error: 'Could not detect faces in one or both images'
            };
        }

        // Calculate similarity
        const distance = faceapi.euclideanDistance(
            idDetection.descriptor,
            selfieDetection.descriptor
        );

        // Convert distance to similarity score (0-1, where 1 is perfect match)
        const similarity = Math.max(0, Math.min(1, 1 - distance));

        return {
            similarity: similarity,
            confidence: similarity > 0.6 ? 'High' : similarity > 0.4 ? 'Medium' : 'Low',
            distance: distance
        };

    } catch (error) {
        console.error('Face comparison error:', error);
        return {
            similarity: 0,
            error: error.message
        };
    }
}

// Main verification endpoint
app.post('/api/verify-id', upload.single('idImage'), async (req, res) => {
    try {
        if (!req.file) {
            return res.status(400).json({
                success: false,
                error: 'ID image is required'
            });
        }

        const providerId = req.body.providerId;
        if (!providerId) {
            return res.status(400).json({
                success: false,
                error: 'Provider ID is required'
            });
        }

        const idImagePath = req.file.path;
        const connection = await getDBConnection();

        try {
            // Get provider data
            const [providers] = await connection.execute(
                'SELECT * FROM providers WHERE id = ?',
                [providerId]
            );

            if (providers.length === 0) {
                return res.status(404).json({
                    success: false,
                    error: 'Provider not found'
                });
            }

            const provider = providers[0];

            // Check if provider has a selfie for face comparison
            if (!provider.selfie_path) {
                return res.status(400).json({
                    success: false,
                    error: 'Provider must complete selfie verification first'
                });
            }

            // Step 1: OCR on ID
            console.log('Starting OCR analysis...');
            const extractedText = await extractTextFromImage(idImagePath);
            const ocrValidation = validateIDText(extractedText);

            // Step 2: Face comparison (if selfie exists)
            let faceResult = null;
            if (provider.selfie_path) {
                const selfiePath = path.join(__dirname, provider.selfie_path);
                if (fs.existsSync(selfiePath)) {
                    console.log('Starting face comparison...');
                    faceResult = await compareFaces(idImagePath, selfiePath);
                }
            }

            // Step 3: Final verification logic
            const ocrValid = ocrValidation.isValid;
            const faceValid = faceResult && faceResult.similarity > 0.6;
            const overallVerified = ocrValid && faceValid;

            // Always set pending for admin review
            const verificationStatus = 'pending';

            // Update database
            const idImageRelativePath = 'uploads/ids/' + path.basename(idImagePath);
            const finalPath = path.join(__dirname, idImageRelativePath);

            // Move file to permanent location
            if (!fs.existsSync(path.dirname(finalPath))) {
                fs.mkdirSync(path.dirname(finalPath), { recursive: true });
            }
            fs.renameSync(idImagePath, finalPath);

            // Update provider record (admin review workflow)
            await connection.execute(
                `UPDATE providers SET
                id_image_path = ?,
                verification_status = ?,
                face_verified = 0,
                updated_at = NOW()
                WHERE id = ?`,
                [
                    idImageRelativePath,
                    verificationStatus,
                    providerId
                ]
            );

            // Clean up temp file if it still exists
            if (fs.existsSync(idImagePath)) {
                fs.unlinkSync(idImagePath);
            }

            res.json({
                success: true,
                verified: overallVerified,
                pendingAdminReview: true,
                ocr: {
                    extractedText: ocrValidation.extractedText,
                    validation: ocrValidation
                },
                face: faceResult ? {
                    similarity: faceResult.similarity,
                    confidence: faceResult.confidence,
                    similarityPercentage: Math.round(faceResult.similarity * 100),
                    error: faceResult.error || null
                } : null,
                details: {
                    ocrValid,
                    faceValid: faceValid || false,
                    threshold: 0.6,
                    status: verificationStatus
                },
                message: 'ID review completed. Your record is now set to PENDING and awaiting admin approval.'
            });

        } finally {
            await connection.end();
        }

    } catch (error) {
        console.error('Verification error:', error);

        // Clean up uploaded file on error
        if (req.file && fs.existsSync(req.file.path)) {
            fs.unlinkSync(req.file.path);
        }

        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Health check endpoint
app.get('/api/health', (req, res) => {
    res.json({
        status: 'ok',
        timestamp: new Date().toISOString(),
        modelsLoaded: global.modelsLoaded || false
    });
});

// Initialize and start server
async function startServer() {
    console.log('🚀 Starting ID Verification Backend...');

    global.modelsLoaded = await loadModels();

    if (!global.modelsLoaded) {
        console.log('⚠️  WARNING: Face recognition will not work until models are downloaded.');
        console.log('📥 Run: node download-models.js');
    }

    app.listen(PORT, () => {
        console.log(`🌐 ID Verification API running on http://localhost:${PORT}`);
        console.log('📋 Endpoints:');
        console.log('   POST /api/verify-id - Verify ID document');
        console.log('   GET  /api/health - Health check');
        console.log('');
        console.log('🎯 Ready for ID verification requests!');
    });
}

startServer().catch(console.error);