<?php
$pageTitle = 'Face Verification';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$providerId = $_SESSION['provider_id'];
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM providers WHERE id = ? AND user_id = ?");
$stmt->execute([$providerId, $_SESSION['user_id']]);
$provider = $stmt->fetch();

if (!$provider) {
    header('Location: provider_profile.php?id=' . $providerId);
    exit;
}

// Ensure provider has location and at least one service before allowing document upload
try {
    $svcStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE provider_id = ?");
    $svcStmt->execute([$providerId]);
    $servicesCount = (int)$svcStmt->fetchColumn();
} catch (Throwable $e) {
    $servicesCount = 0;
}

if (empty($provider['city']) || empty($provider['barangay']) || $servicesCount === 0) {
    header('Location: provider_add_service.php?setup_required=1');
    exit;
}

$error = '';
$success = '';

// Check current verification step
$currentStep = 1; // Default to step 1
if (!empty($provider['reference_photo_path'])) {
    $currentStep = 2; // Has reference photo, move to step 2
}
if (!empty($provider['selfie_path'])) {
    $currentStep = 3; // Has selfie, move to step 3 (ID upload)
}
if (!empty($provider['id_image_path'])) {
    $currentStep = 4; // Has ID, move to step 4 (business permit)
}
if ($provider['verification_status'] === 'approved') {
    $currentStep = 5; // Already verified
} elseif (!empty($provider['business_permit_path'])) {
    $currentStep = 5; // All documents submitted, pending review or rejected
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reset'])) {
    // Reset verification process
    $pdo->prepare("UPDATE providers SET reference_photo_path = NULL, selfie_path = NULL, id_image_path = NULL, business_permit_path = NULL, verification_status = 'pending', face_verified = 0, face_verification_rejected = 0 WHERE id = ?")
        ->execute([$providerId]);
    $provider['reference_photo_path'] = null;
    $provider['selfie_path'] = null;
    $provider['id_image_path'] = null;
    $provider['business_permit_path'] = null;
    $provider['verification_status'] = 'pending';
    $provider['face_verified'] = 0;
    $provider['face_verification_rejected'] = 0;
    $currentStep = 1;
    header('Location: face_verification.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $provider['verification_status'] !== 'approved') {
    if (isset($_POST['step']) && $_POST['step'] == 1) {
        // Step 1: Upload reference photo
        if (empty($_FILES['reference_photo']['name'])) {
            $error = 'Please upload a reference photo of yourself.';
        } else {
            $referencePath = 'uploads/selfies/' . $provider['user_id'] . '_reference_' . time() . '_' . basename($_FILES['reference_photo']['name']);
            if (move_uploaded_file($_FILES['reference_photo']['tmp_name'], $referencePath)) {
                $pdo->prepare("UPDATE providers SET reference_photo_path = ? WHERE id = ?")
                    ->execute([$referencePath, $providerId]);
                $provider['reference_photo_path'] = $referencePath;
                $currentStep = 2;
                $success = 'Reference photo uploaded successfully. Now take your live selfie.';
            } else {
                $error = 'Failed to upload reference photo.';
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == 2) {
        // Step 2: Take live selfie and compare
        if (empty($_FILES['live_selfie']['name'])) {
            $error = 'Please take a live selfie.';
        } elseif (empty($provider['reference_photo_path'])) {
            $error = 'Reference photo not found. Please start over.';
            $currentStep = 1;
        } else {
            $liveSelfiePath = 'uploads/selfies/' . $provider['user_id'] . '_live_' . time() . '_' . basename($_FILES['live_selfie']['name']);
            if (move_uploaded_file($_FILES['live_selfie']['tmp_name'], $liveSelfiePath)) {
                // Face verification is now handled client-side with face-api.js
                // If we reach here, face detection has already passed
                $pdo->prepare("UPDATE providers SET selfie_path = ? WHERE id = ?")
                    ->execute([$liveSelfiePath, $providerId]);
                $provider['selfie_path'] = $liveSelfiePath;
                $currentStep = 3;
                $success = 'Face verification successful! Now upload your ID document.';
            } else {
                $error = 'Failed to upload live selfie.';
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == 3) {
        // Step 3: Upload ID document
        if (empty($_FILES['id_document']['name'])) {
            $error = 'Please upload a valid ID document.';
        } else {
            $idPath = 'uploads/ids/' . $provider['user_id'] . '_id_' . time() . '_' . basename($_FILES['id_document']['name']);
            if (move_uploaded_file($_FILES['id_document']['tmp_name'], $idPath)) {
                $pdo->prepare("UPDATE providers SET id_image_path = ? WHERE id = ?")
                    ->execute([$idPath, $providerId]);
                $provider['id_image_path'] = $idPath;
                $currentStep = 4;
                $success = 'ID document uploaded successfully. Now upload your business permit.';
            } else {
                $error = 'Failed to upload ID document.';
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == 4) {
        // Step 4: Upload business permit and submit for admin review
        if (empty($_FILES['business_permit']['name'])) {
            $error = 'Please upload your business permit.';
        } elseif (empty($provider['selfie_path']) || empty($provider['id_image_path'])) {
            $error = 'Missing required documents. Please start over.';
            $currentStep = 1;
        } else {
            $permitPath = 'uploads/payments/' . $provider['user_id'] . '_permit_' . time() . '_' . basename($_FILES['business_permit']['name']);
            if (move_uploaded_file($_FILES['business_permit']['tmp_name'], $permitPath)) {
                // Submit for admin review instead of auto-approving
                $pdo->prepare("UPDATE providers SET business_permit_path = ?, verification_status = 'pending' WHERE id = ?")
                    ->execute([$permitPath, $providerId]);
                $provider['business_permit_path'] = $permitPath;
                $provider['verification_status'] = 'pending';
                $currentStep = 5; // Move to completion step
                $success = 'All documents uploaded successfully! Your verification is now pending admin review. You will be notified once approved.';
            } else {
                $error = 'Failed to upload business permit.';
            }
        }
    }
}

$statusMessage = '';
if ($provider['verification_status'] === 'approved') {
    $statusMessage = 'verified';
} elseif ($provider['verification_status'] === 'pending') {
    $statusMessage = 'pending';
} elseif ($provider['verification_status'] === 'rejected') {
    $statusMessage = 'rejected';
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 600px; margin: 0 auto;">
    <h1 class="section-title">Provider Verification</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Get the <strong>Verified</strong> badge to build trust with customers. Complete the verification process by uploading all required documents.</p>

    <?php if ($success): ?>
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid #2ECC71;">
        <p style="color: #2ECC71; margin: 0;"><?= htmlspecialchars($success) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <p style="color: #e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($currentStep == 5): // Documents submitted or verified ?>
    <div class="card" style="padding: 2rem; text-align: center;">
        <?php if ($provider['verification_status'] === 'approved'): ?>
        <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
        <h2 style="color: #2ECC71;">Verification Complete!</h2>
        <p style="color: var(--text-muted);">You're now verified and visible to customers! 🎉</p>
        <?php elseif ($provider['verification_status'] === 'pending'): ?>
        <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
        <h2 style="color: #F39C12;">Verification Pending Review</h2>
        <p style="color: var(--text-muted);">Your documents have been submitted and are under admin review. You'll receive the Verified badge once approved.</p>
        <?php elseif ($provider['verification_status'] === 'rejected'): ?>
        <div style="font-size: 3rem; margin-bottom: 1rem;">✗</div>
        <h2 style="color: #E74C3C;">Verification Rejected</h2>
        <p style="color: var(--text-muted);">Your verification was rejected. Please check your email for details or contact support.</p>
        <a href="?reset=1" class="btn btn-outline" style="margin-top: 1rem;">Resubmit Documents</a>
        <?php endif; ?>
        <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-primary" style="margin-top: 1rem;">Back to Profile</a>
    </div>
    <?php elseif ($currentStep == 1): // Step 1: Upload reference photo ?>
    <div class="card" style="padding: 2rem;">
        <h3>Step 1 of 4: Upload Reference Photo</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">First, upload a clear photo of yourself that will be used as reference for face verification.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="1">
            <div class="form-group">
                <label>Reference Photo</label>
                <input type="file" name="reference_photo" accept="image/*" required>
                <small style="color: var(--text-muted);">Upload a clear, well-lit photo of your face</small>
            </div>
            <button type="submit" class="btn btn-primary">Upload & Continue</button>
            <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
        </form>
    </div>
    <?php elseif ($currentStep == 2): // Step 2: Take live selfie ?>
    <div class="card" style="padding: 2rem;">
        <h3>Step 2 of 4: Take Live Selfie</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Now take a live selfie. We'll compare it with your reference photo.</p>
        
        <?php if (!empty($provider['reference_photo_path'])): ?>
        <div style="margin-bottom: 1.5rem;">
            <p><strong>Your Reference Photo:</strong></p>
            <img src="<?= htmlspecialchars($provider['reference_photo_path']) ?>" alt="Reference Photo" style="max-width: 200px; border: 1px solid var(--border-color); border-radius: 8px;">
        </div>
        <?php endif; ?>

        <div id="camera-container" style="margin-bottom: 1.5rem;">
            <video id="camera" autoplay playsinline style="width: 100%; max-width: 400px; border: 1px solid var(--border-color); border-radius: 8px; display: none;"></video>
            <canvas id="canvas" style="display: none;"></canvas>
            <div id="camera-placeholder" style="width: 100%; max-width: 400px; height: 300px; border: 2px dashed var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; background-color: var(--bg-secondary);">
                <div style="text-align: center; color: var(--text-muted);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📷</div>
                    <p>Camera access required for face verification</p>
                    <button type="button" id="request-camera-btn" class="btn btn-primary" style="margin-top: 1rem;">Allow Camera Access</button>
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <button type="button" id="capture-btn" class="btn btn-primary" style="display: none;">Take Selfie</button>
                <button type="button" id="retake-btn" class="btn btn-outline" style="display: none;">Retake</button>
            </div>
        </div>

        <div id="preview-container" style="display: none; margin-bottom: 1.5rem;">
            <p><strong>Selfie Preview:</strong></p>
            <img id="selfie-preview" style="max-width: 200px; border: 1px solid var(--border-color); border-radius: 8px;">
        </div>

        <form method="POST" enctype="multipart/form-data" id="selfie-form">
            <input type="hidden" name="step" value="2">
            <input type="file" id="live_selfie_input" name="live_selfie" accept="image/*" style="display: none;" required>
            <button type="submit" id="verify-btn" class="btn btn-primary" style="display: none;">Verify Face</button>
            <a href="?reset=1" class="btn btn-outline" style="margin-left: 0.5rem;">Start Over</a>
            <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
        </form>
    </div>
    <?php elseif ($currentStep == 3): // Step 3: Upload ID document ?>
    <div class="card" style="padding: 2rem;">
        <h3>Step 3 of 4: Upload ID Document</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Upload a valid government-issued ID (Driver's License, Passport, etc.) for identity verification.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="3">
            <div class="form-group">
                <label>ID Document</label>
                <input type="file" name="id_document" accept="image/*,.pdf" required>
                <small style="color: var(--text-muted);">Upload a clear photo or scan of your ID. PDF files are also accepted.</small>
                <br><small style="color: #e74c3c; font-weight: bold;">Please upload a valid ID document containing a visible face; submissions without an identifiable ID or face will not be accepted.</small>
            </div>
            <button type="submit" class="btn btn-primary">Upload & Continue</button>
            <a href="?reset=1" class="btn btn-outline" style="margin-left: 0.5rem;">Start Over</a>
            <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
        </form>
    </div>
    <?php elseif ($currentStep == 4): // Step 4: Upload business permit ?>
    <div class="card" style="padding: 2rem;">
        <h3>Step 4 of 4: Upload Business Permit</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Finally, upload your business permit or registration document to complete the verification process.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="4">
            <div class="form-group">
                <label>Business Permit</label>
                <input type="file" name="business_permit" accept="image/*,.pdf" required>
                <small style="color: var(--text-muted);">Upload your business permit, certificate of registration, or other business documentation.</small>
            </div>
            <button type="submit" class="btn btn-primary">Complete Verification</button>
            <a href="?reset=1" class="btn btn-outline" style="margin-left: 0.5rem;">Start Over</a>
            <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
        </form>
    </div>
    <?php elseif ($currentStep == 4): // Step 4: Upload business permit ?>
    <div class="card" style="padding: 2rem;">
        <h3>Step 4 of 4: Upload Business Permit</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Finally, upload your business permit or registration document to complete the verification process.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="4">
            <div class="form-group">
                <label>Business Permit</label>
                <input type="file" name="business_permit" accept="image/*,.pdf" required>
                <small style="color: var(--text-muted);">Upload your business permit, certificate of registration, or other business documentation.</small>
            </div>
            <button type="submit" class="btn btn-primary">Complete Verification</button>
            <a href="?reset=1" class="btn btn-outline" style="margin-left: 0.5rem;">Start Over</a>
            <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- Face API Loading Overlay -->
    <div id="face-api-loading" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; flex-direction: column;">
        <div style="background-color: var(--bg-white); padding: 2rem; border-radius: 12px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div style="font-size: 2rem; margin-bottom: 1rem;">🤖</div>
            <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Loading Face Detection</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Please wait while we load the face recognition models...</p>
            <div style="width: 200px; height: 4px; background-color: var(--border-color); border-radius: 2px; overflow: hidden;">
                <div id="loading-progress" style="width: 0%; height: 100%; background-color: var(--primary-color); transition: width 0.3s ease;"></div>
            </div>
        </div>
    </div>

    <!-- Camera Permission Modal -->
    <div id="camera-modal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background-color: var(--bg-white); padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📷</div>
            <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Camera Access Required</h3>
            <p style="color: var(--text-muted); margin-bottom: 2rem; line-height: 1.6;">
                To complete face verification, we need access to your camera to take a live selfie. 
                Your camera will only be used for this verification process and no images are stored without your consent.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="button" id="allow-camera-btn" class="btn btn-primary">Allow Camera Access</button>
                <button type="button" id="deny-camera-btn" class="btn btn-outline">Deny Access</button>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 1.5rem;">
                You can change camera permissions in your browser settings at any time.
            </p>
        </div>
    </div>

    <script>
    // Face API.js initialization and face detection functions
    let modelsLoaded = false;
    let modelsLoading = false;

    async function loadFaceAPIModels() {
        if (modelsLoaded) return true;
        if (modelsLoading) {
            // Wait for loading to complete
            while (modelsLoading && !modelsLoaded) {
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            return modelsLoaded;
        }
        
        // Check if face-api.js is loaded
        if (!window.faceAPIAvailable || typeof faceapi === 'undefined') {
            console.error('face-api.js is not loaded');
            alert('Face detection library is not available. Please refresh the page or contact support.');
            return false;
        }
        
        modelsLoading = true;
        const loadingOverlay = document.getElementById('face-api-loading');
        const progressBar = document.getElementById('loading-progress');
        
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
        }
        
        try {
            console.log('Loading face detection models...');
            
            // Update progress
            if (progressBar) progressBar.style.width = '25%';
            
            // Try local models first
            await faceapi.nets.ssdMobilenetv1.loadFromUri('models/');
            if (progressBar) progressBar.style.width = '50%';
            
            await faceapi.nets.faceLandmark68Net.loadFromUri('models/');
            if (progressBar) progressBar.style.width = '75%';
            
            await faceapi.nets.faceRecognitionNet.loadFromUri('models/');
            if (progressBar) progressBar.style.width = '100%';
            
            modelsLoaded = true;
            console.log('Face API models loaded successfully from local');
            
            // Hide loading overlay
            setTimeout(() => {
                if (loadingOverlay) loadingOverlay.style.display = 'none';
            }, 500);
            
            return true;
        } catch (error) {
            console.warn('Local models failed, trying CDN:', error);
            try {
                // Reset progress for CDN loading
                if (progressBar) progressBar.style.width = '25%';
                
                // Fallback to CDN
                await faceapi.nets.ssdMobilenetv1.loadFromUri('https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/');
                if (progressBar) progressBar.style.width = '50%';
                
                await faceapi.nets.faceLandmark68Net.loadFromUri('https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/');
                if (progressBar) progressBar.style.width = '75%';
                
                await faceapi.nets.faceRecognitionNet.loadFromUri('https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/');
                if (progressBar) progressBar.style.width = '100%';
                
                modelsLoaded = true;
                console.log('Face API models loaded successfully from CDN');
                
                // Hide loading overlay
                setTimeout(() => {
                    if (loadingOverlay) loadingOverlay.style.display = 'none';
                }, 500);
                
                return true;
            } catch (cdnError) {
                console.error('Failed to load models from both local and CDN:', cdnError);
                if (loadingOverlay) loadingOverlay.style.display = 'none';
                alert('Face detection is not available. Please check your internet connection and refresh the page.');
                return false;
            }
        } finally {
            modelsLoading = false;
        }
    }

    async function detectFaceInImage(imageElement) {
        if (!modelsLoaded) {
            await loadFaceAPIModels();
        }
        
        try {
            const detections = await faceapi.detectAllFaces(imageElement, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }));
            return detections.length > 0;
        } catch (error) {
            console.error('Face detection error:', error);
            return false;
        }
    }

    async function validateImageFile(file) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = async () => {
                const hasFace = await detectFaceInImage(img);
                resolve(hasFace);
            };
            img.onerror = () => resolve(false);
            img.src = URL.createObjectURL(file);
        });
    }

    // ID validation function (basic checks for ID-like features)
    async function validateIDDocument(file) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = async () => {
                // Check if image has reasonable dimensions for an ID
                const minWidth = 300;
                const minHeight = 200;
                const aspectRatio = img.width / img.height;
                
                // IDs typically have aspect ratios between 1.3 and 2.0
                const validAspectRatio = aspectRatio >= 1.3 && aspectRatio <= 2.0;
                const validDimensions = img.width >= minWidth && img.height >= minHeight;
                
                // Check for text-like content (basic OCR simulation)
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0);
                
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                
                // Simple check for text-like patterns (high contrast areas)
                let textLikePixels = 0;
                let totalPixels = data.length / 4;
                
                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];
                    const brightness = (r + g + b) / 3;
                    
                    // Check for high contrast (potential text)
                    if (brightness < 100 || brightness > 200) {
                        textLikePixels++;
                    }
                }
                
                const textRatio = textLikePixels / totalPixels;
                const hasTextContent = textRatio > 0.1; // At least 10% text-like content
                
                resolve(validDimensions && validAspectRatio && hasTextContent);
            };
            img.onerror = () => resolve(false);
            img.src = URL.createObjectURL(file);
        });
    }

    // Form validation functions
    async function validateStep1Form(formData) {
        const fileInput = document.getElementById('reference_photo');
        if (!fileInput || !fileInput.files[0]) {
            alert('Please select a reference photo.');
            return false;
        }
        
        const file = fileInput.files[0];
        if (!file.type.startsWith('image/')) {
            alert('Please upload a valid image file.');
            return false;
        }
        
        // Show loading message
        const submitBtn = formData.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Analyzing face...';
        submitBtn.disabled = true;
        
        try {
            const hasFace = await validateImageFile(file);
            if (!hasFace) {
                alert('No face detected in the reference photo. Please upload a clear photo of your face.');
                return false;
            }
            return true;
        } catch (error) {
            console.error('Validation error:', error);
            alert('Error validating image. Please try again.');
            return false;
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }

    async function validateStep2Form(formData) {
        const fileInput = document.getElementById('live_selfie_input');
        if (!fileInput || !fileInput.files[0]) {
            alert('Please take a live selfie.');
            return false;
        }
        
        const file = fileInput.files[0];
        if (!file.type.startsWith('image/')) {
            alert('Please upload a valid image file.');
            return false;
        }
        
        // Show loading message
        const submitBtn = document.getElementById('verify-btn');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Verifying face...';
        submitBtn.disabled = true;
        
        try {
            const hasFace = await validateImageFile(file);
            if (!hasFace) {
                alert('No face detected in the selfie. Please take a clear photo of your face.');
                return false;
            }
            return true;
        } catch (error) {
            console.error('Validation error:', error);
            alert('Error validating image. Please try again.');
            return false;
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }

    async function validateStep3Form(formData) {
        const fileInput = document.getElementById('id_document');
        if (!fileInput || !fileInput.files[0]) {
            alert('Please upload an ID document.');
            return false;
        }
        
        const file = fileInput.files[0];
        if (!file.type.startsWith('image/') && !file.type.includes('pdf')) {
            alert('Please upload a valid image or PDF file.');
            return false;
        }
        
        // Show loading message
        const submitBtn = formData.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Validating ID...';
        submitBtn.disabled = true;
        
        try {
            if (file.type.startsWith('image/')) {
                const isValidID = await validateIDDocument(file);
                if (!isValidID) {
                    alert('The uploaded document does not appear to be a valid ID. Please ensure it contains clear text and has proper ID dimensions.');
                    return false;
                }
            }
            return true;
        } catch (error) {
            console.error('Validation error:', error);
            alert('Error validating document. Please try again.');
            return false;
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }
    </script>

    <script>
    let stream = null;
    const video = document.getElementById('camera');
    const canvas = document.getElementById('canvas');
    const captureBtn = document.getElementById('capture-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const previewContainer = document.getElementById('preview-container');
    const selfiePreview = document.getElementById('selfie-preview');
    const verifyBtn = document.getElementById('verify-btn');
    const liveSelfieInput = document.getElementById('live_selfie_input');
    const cameraPlaceholder = document.getElementById('camera-placeholder');
    const requestCameraBtn = document.getElementById('request-camera-btn');
    const cameraModal = document.getElementById('camera-modal');
    const allowCameraBtn = document.getElementById('allow-camera-btn');
    const denyCameraBtn = document.getElementById('deny-camera-btn');

    // Check camera permission status
    async function checkCameraPermission() {
        if (!navigator.permissions) {
            // Fallback for browsers that don't support permissions API
            return 'prompt';
        }
        
        try {
            const permission = await navigator.permissions.query({ name: 'camera' });
            return permission.state;
        } catch (err) {
            console.log('Permission API not supported, assuming prompt needed');
            return 'prompt';
        }
    }

    // Show camera permission modal
    function showCameraModal() {
        cameraModal.style.display = 'flex';
    }

    // Hide camera permission modal
    function hideCameraModal() {
        cameraModal.style.display = 'none';
    }

    // Start camera
    async function startCamera() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                } 
            });
            video.srcObject = stream;
            video.style.display = 'block';
            cameraPlaceholder.style.display = 'none';
            captureBtn.style.display = 'inline-block';
            hideCameraModal();
        } catch (err) {
            console.error('Error accessing camera:', err);
            
            // Show appropriate error message based on error type
            let errorMessage = 'Unable to access camera. ';
            
            if (err.name === 'NotAllowedError') {
                errorMessage += 'Camera access was denied. Please allow camera access in your browser settings and try again.';
            } else if (err.name === 'NotFoundError') {
                errorMessage += 'No camera found on this device.';
            } else if (err.name === 'NotReadableError') {
                errorMessage += 'Camera is already in use by another application.';
            } else if (err.name === 'OverconstrainedError') {
                errorMessage += 'Camera does not meet the required specifications.';
            } else if (err.name === 'SecurityError') {
                errorMessage += 'Camera access blocked due to security restrictions.';
            } else {
                errorMessage += 'Please check your camera permissions and try again.';
            }
            
            alert(errorMessage);
            
            // Reset to placeholder state
            video.style.display = 'none';
            cameraPlaceholder.style.display = 'flex';
            captureBtn.style.display = 'none';
            requestCameraBtn.style.display = 'inline-block';
        }
    }

    // Request camera permission
    async function requestCameraPermission() {
        const permissionState = await checkCameraPermission();
        
        if (permissionState === 'granted') {
            // Permission already granted, start camera
            startCamera();
        } else if (permissionState === 'denied') {
            // Permission denied, show error
            alert('Camera access has been blocked. Please enable camera access in your browser settings and refresh the page.');
        } else {
            // Permission prompt needed, show modal
            showCameraModal();
        }
    }

    // Capture photo
    function capturePhoto() {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert to blob
        canvas.toBlob((blob) => {
            const file = new File([blob], 'selfie.jpg', { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            liveSelfieInput.files = dataTransfer.files;
            
            // Show preview
            const imageUrl = canvas.toDataURL('image/jpeg');
            selfiePreview.src = imageUrl;
            previewContainer.style.display = 'block';
            verifyBtn.style.display = 'inline-block';
            
            // Hide camera
            video.style.display = 'none';
            captureBtn.style.display = 'none';
            retakeBtn.style.display = 'inline-block';
            
            // Stop camera stream
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        }, 'image/jpeg', 0.8);
    }

    // Retake photo
    function retakePhoto() {
        previewContainer.style.display = 'none';
        verifyBtn.style.display = 'none';
        video.style.display = 'block';
        retakeBtn.style.display = 'none';
        cameraPlaceholder.style.display = 'none';
        startCamera();
    }

    // Event listeners
    requestCameraBtn.addEventListener('click', requestCameraPermission);
    allowCameraBtn.addEventListener('click', () => {
        hideCameraModal();
        startCamera();
    });
    denyCameraBtn.addEventListener('click', () => {
        hideCameraModal();
        alert('Camera access is required to complete face verification. You can try again by clicking "Allow Camera Access".');
    });
    captureBtn.addEventListener('click', capturePhoto);
    retakeBtn.addEventListener('click', retakePhoto);

    // Form validation event listeners
    document.addEventListener('DOMContentLoaded', async () => {
        // Show loading overlay initially
        const loadingOverlay = document.getElementById('face-api-loading');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
        }
        
        // Load face API models on page load
        const modelsReady = await loadFaceAPIModels();
        
        if (!modelsReady) {
            // If models failed to load, hide overlay and show error
            if (loadingOverlay) loadingOverlay.style.display = 'none';
            return;
        }
        
        // Step 1 form validation
        const step1Form = document.querySelector('form input[name="step"][value="1"]');
        if (step1Form) {
            step1Form.closest('form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const isValid = await validateStep1Form(e.target);
                if (isValid) {
                    e.target.submit();
                }
            });
        }
        
        // Step 2 form validation
        const step2Form = document.getElementById('selfie-form');
        if (step2Form) {
            step2Form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const isValid = await validateStep2Form(e.target);
                if (isValid) {
                    e.target.submit();
                }
            });
        }
        
        // Step 3 form validation
        const step3Form = document.querySelector('form input[name="step"][value="3"]');
        if (step3Form) {
            step3Form.closest('form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const isValid = await validateStep3Form(e.target);
                if (isValid) {
                    e.target.submit();
                }
            });
        }
        
        // Initialize camera
        requestCameraPermission();
    });
    </script>

    <p style="text-align: center; margin-top: 2rem;">
        <a href="provider_profile.php?id=<?= $providerId ?>">← Back to Profile</a>
    </p>
</section>
<?php require_once 'includes/footer.php'; ?>
