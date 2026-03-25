const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

const PORT = 3001;

console.log('Starting Simple ID Verification API on port', PORT);
console.log('Note: This is a mock API for testing. Install Node.js and dependencies for full functionality.');

// Simple multipart form data parser
function parseMultipart(buffer, boundary) {
    const parts = {};
    const boundaryStr = '--' + boundary;
    const sections = buffer.toString().split(boundaryStr);

    for (const section of sections) {
        if (section.includes('Content-Disposition: form-data;')) {
            const lines = section.split('\r\n');
            const disposition = lines[1];
            const nameMatch = disposition.match(/name="([^"]+)"/);

            if (nameMatch) {
                const name = nameMatch[1];
                let value = '';

                // Find the content after headers
                let contentStart = 2;
                while (contentStart < lines.length && !lines[contentStart].trim()) {
                    contentStart++;
                }

                // Get content until boundary
                for (let i = contentStart; i < lines.length; i++) {
                    if (lines[i].includes(boundaryStr)) break;
                    if (value) value += '\r\n';
                    value += lines[i];
                }

                parts[name] = value.trim();
            }
        }
    }

    return parts;
}

// Mock OCR function
function extractTextFromImage(imageBuffer) {
    // Mock OCR result - simulates successful ID detection
    const mockText = "REPUBLIC OF THE PHILIPPINES\nDRIVER'S LICENSE\nName: JOHN DOE\nID Number: A12-3456789\nAddress: Sample Address\nBirth Date: 01/01/1990";

    return {
        text: mockText,
        confidence: 85
    };
}

// Mock face comparison function
function compareFaces(referenceImage, idImage) {
    // Mock successful face match
    return {
        match: true,
        confidence: 92.5
    };
}

// Validate ID text structure
function validateIDText(text) {
    const upperText = text.toUpperCase();

    // Check for required elements
    const hasName = /\b(NAME|FIRST NAME|LAST NAME)\b/.test(upperText) ||
                   /\b[A-Z]{2,}\s+[A-Z]{2,}\b/.test(upperText);

    const hasIdNumber = /\b(ID|NUMBER|LICENSE|CARD)\b.*[\d\w\-]{5,}/.test(upperText) ||
                       /\b[A-Z]\d{2}-\d{7}\b/.test(upperText);

    const hasKeywords = /\b(REPUBLIC|PHILIPPINES|DRIVER|LICENSE|PASSPORT|SSS|TIN)\b/.test(upperText);

    return {
        valid: hasName && hasIdNumber && hasKeywords,
        hasName,
        hasIdNumber,
        hasKeywords
    };
}

// Mock database update (logs to console)
function updateProviderStatus(providerId, verified) {
    const status = verified ? 'approved' : 'pending';
    console.log(`Mock DB Update: Provider ${providerId} status set to ${status}`);
    return true;
}

const server = http.createServer(async (req, res) => {
    // Enable CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
    }

    if (req.method === 'POST' && req.url === '/api/verify-id') {
        let body = [];

        req.on('data', chunk => {
            body.push(chunk);
        });

        req.on('end', async () => {
            try {
                const buffer = Buffer.concat(body);
                const contentType = req.headers['content-type'] || '';
                const boundaryMatch = contentType.match(/boundary=(.+)/);

                if (!boundaryMatch) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ success: false, error: 'Invalid content type' }));
                    return;
                }

                const boundary = boundaryMatch[1];
                const parts = parseMultipart(buffer, boundary);

                const providerId = parts.providerId;
                const idImageData = parts.idImage;

                if (!providerId || !idImageData) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ success: false, error: 'Missing providerId or idImage' }));
                    return;
                }

                console.log(`Processing verification for provider ${providerId}`);

                // Mock OCR processing
                const ocrResult = extractTextFromImage(Buffer.from(idImageData, 'binary'));
                const validation = validateIDText(ocrResult.text);

                // Mock face comparison
                const faceMatch = compareFaces(null, idImageData);

                // Determine verification result
                const ocrValid = validation.valid;
                const faceValid = faceMatch.match;
                const verified = ocrValid && faceValid;

                // Mock database update
                updateProviderStatus(providerId, verified);

                const result = {
                    success: true,
                    verified: verified,
                    details: {
                        ocrValid: ocrValid,
                        faceValid: faceValid,
                        ocrConfidence: ocrResult.confidence,
                        faceConfidence: faceMatch.confidence,
                        extractedText: ocrResult.text
                    }
                };

                console.log(`Verification result for provider ${providerId}:`, result);

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify(result));

            } catch (error) {
                console.error('Error processing verification:', error);
                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: false, error: 'Internal server error' }));
            }
        });
    } else {
        res.writeHead(404, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, error: 'Endpoint not found' }));
    }
});

server.listen(PORT, () => {
    console.log(`Simple ID Verification API listening on port ${PORT}`);
    console.log('Available endpoints:');
    console.log('POST /api/verify-id - Mock verify ID document with OCR and face matching');
    console.log('');
    console.log('NOTE: This is a mock API for testing purposes.');
    console.log('To use real OCR and face recognition:');
    console.log('1. Install Node.js from https://nodejs.org/');
    console.log('2. Run: npm install');
    console.log('3. Use id_verification_api.js instead');
});