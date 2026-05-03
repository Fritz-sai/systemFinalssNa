<?php
$pageTitle = 'Setup Profile Picture';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Check if already has a profile image, but maybe we allow them to recapture if they came here?
// Actually, if it's mandatory, they shouldn't be able to access the site until they have one.

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['live_selfie']) && $_FILES['live_selfie']['error'] === UPLOAD_ERR_OK) {
        // Enforce maximum file size (5MB)
        if ($_FILES['live_selfie']['size'] > 5 * 1024 * 1024) {
            $error = "Image is too large. Maximum size is 5MB.";
        } else {
            $liveSelfiePath = 'uploads/profiles/customer_' . $userId . '_' . time() . '.jpg';
            if (move_uploaded_file($_FILES['live_selfie']['tmp_name'], $liveSelfiePath)) {
                
                // Automatically set the captured selfie as their profile image
                $pdo->prepare("UPDATE users SET profile_image_path = ? WHERE id = ?")
                    ->execute([$liveSelfiePath, $userId]);
                
                // Set success message in session so it shows after redirect
                $_SESSION['success_message'] = 'Profile picture successfully captured and updated!';
                
                // Redirect to homepage
                header('Location: index.php');
                exit;
            } else {
                $error = 'Failed to upload photo. Please ensure the uploads directory is writable.';
            }
        }
    } else {
        $error = 'No photo received or upload error occurred.';
    }
}

require_once 'includes/header.php';
?>

<style>
    body { background-color: #f8faff; font-family: 'Inter', sans-serif; }
    .setup-shell { max-width: 800px; margin: 3rem auto; padding: 0 1rem; }
    .setup-header { text-align: center; margin-bottom: 2rem; }
    .setup-header h1 { color: #1a1a2e; font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
    .setup-header p { color: #64748b; font-size: 1.05rem; max-width: 600px; margin: 0 auto; }
    
    .setup-card { background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.04); }
    
    .stepper-wrapper { display: flex; justify-content: space-between; align-items: center; max-width: 600px; margin: 0 auto 2.5rem; }
    .step { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
    .step-num { width: 32px; height: 32px; border-radius: 50%; border: 2px solid #e2e8f0; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; background: white; transition: all 0.3s ease; }
    .step span { font-size: 0.85rem; color: #94a3b8; font-weight: 500; transition: all 0.3s ease; }
    .step.active .step-num { background: #3A86FF; border-color: #3A86FF; color: white; box-shadow: 0 0 0 4px rgba(58, 134, 255, 0.15); }
    .step.active span { color: #3A86FF; font-weight: 600; }
    .step.completed .step-num { background: white; border-color: #3A86FF; color: #3A86FF; }
    .step.completed span { color: #1a1a2e; }
    .step-line { flex-grow: 1; height: 2px; border-top: 2px dashed #e2e8f0; margin: -20px 15px 0; }
    .step-line.completed { border-top-color: #3A86FF; border-top-style: solid; }

    .instruction-banner { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 2.5rem; }
    .instruction-icon { width: 48px; height: 48px; background: #3A86FF; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .instruction-text-content h4 { color: #3A86FF; margin: 0 0 0.25rem 0; font-size: 1.1rem; font-weight: 600; }
    .instruction-text-content p { margin: 0; color: #64748b; font-size: 0.9rem; }
    
    .camera-section { display: flex; justify-content: center; align-items: center; gap: 2rem; margin-bottom: 2.5rem; position: relative; }
    .side-helper { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem 1rem; text-align: center; width: 120px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
    .side-helper.visible { opacity: 1; }
    .side-helper p { font-size: 0.85rem; font-weight: 500; color: #1a1a2e; margin: 0.75rem 0 0 0; line-height: 1.3; }
    .right-helper { opacity: 1; background: transparent; border: none; box-shadow: none; }
    
    .camera-container-outer { position: relative; width: 340px; height: 340px; display: flex; align-items: center; justify-content: center; }
    .progress-ring { position: absolute; top: 0; left: 0; transform: rotate(-90deg); z-index: 1; pointer-events: none; }
    .ring-bg { fill: none; stroke: #e2e8f0; stroke-width: 6; }
    .ring-progress { fill: none; stroke: #3A86FF; stroke-width: 6; stroke-linecap: round; stroke-dasharray: 1005; stroke-dashoffset: 1005; transition: stroke-dashoffset 0.5s ease; }
    
    .camera-wrapper { position: relative; width: 300px; height: 300px; border-radius: 50%; overflow: hidden; background: #e2e8f0; z-index: 2; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    #camera { position: absolute; width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    #canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 3; pointer-events: none; }
    
    .face-outline { position: absolute; inset: 0; z-index: 4; pointer-events: none; display: flex; justify-content: center; align-items: center; }
    
    #camera-placeholder { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; flex-direction: column; background: #f1f5f9; color: #64748b; z-index: 5; }
    #flash-effect { position: absolute; inset: 0; background: white; opacity: 0; z-index: 20; pointer-events: none; transition: opacity 0.15s ease; }
    
    .liveness-progress { display: flex; justify-content: center; gap: 12px; margin-bottom: 1.5rem; }
    .progress-dot { width: 10px; height: 10px; border-radius: 50%; background: #e2e8f0; transition: all 0.3s ease; }
    .progress-dot.active { background: #3A86FF; transform: scale(1.2); }
    .progress-dot.completed { background: #3A86FF; opacity: 0.5; }
    
    .security-badge { display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.85rem; color: #64748b; margin-bottom: 2.5rem; }
    .tips-footer { border-top: 1px solid #e2e8f0; padding-top: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: #64748b; }
    .tips-footer strong { color: #1a1a2e; }
    
    @media (max-width: 768px) {
        .stepper-wrapper { display: none; } /* Hide complex stepper on mobile */
        .camera-section { flex-direction: column; }
        .side-helper { display: none; } /* Hide side helper on mobile to save space */
    }
</style>

<section class="setup-shell fade-in">
    <div class="setup-header">
        <h1>Setup Your Profile Picture</h1>
        <p>Before you start booking, let's capture a quick profile photo to help providers recognize you!</p>
    </div>

    <?php if ($error): ?>
        <div class="toast error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="setup-card">
        <!-- Top Stepper -->
        <div class="stepper-wrapper">
            <div class="step active" id="step-1"><div class="step-num">1</div><span>Left</span></div>
            <div class="step-line" id="line-1"></div>
            <div class="step" id="step-2"><div class="step-num">2</div><span>Right</span></div>
            <div class="step-line" id="line-2"></div>
            <div class="step" id="step-3"><div class="step-num">3</div><span>Up</span></div>
            <div class="step-line" id="line-3"></div>
            <div class="step" id="step-4"><div class="step-num">4</div><span>Down</span></div>
            <div class="step-line" id="line-4"></div>
            <div class="step" id="step-5"><div class="step-num">5</div><span>Complete</span></div>
        </div>

        <!-- Instruction Banner -->
        <div class="instruction-banner">
            <div class="instruction-icon" id="instruction-icon-box">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="instruction-icon-svg"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
            </div>
            <div class="instruction-text-content">
                <h4 id="instruction-text">Click 'Start Camera' when you're ready</h4>
                <p id="instruction-sub">Follow the outline guide</p>
            </div>
        </div>

        <!-- Camera Area -->
        <div class="camera-section">
            <!-- Left Helper -->
            <div class="side-helper" id="side-helper-box">
                <svg id="helper-icon-svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#3A86FF" stroke-width="1.5">
                    <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/>
                    <path d="M12 12h.01"/>
                </svg>
                <p id="helper-text">Ready</p>
            </div>

            <!-- Center Camera -->
            <div class="camera-container-outer">
                <!-- SVG Progress Ring -->
                <svg class="progress-ring" width="340" height="340">
                    <circle class="ring-bg" cx="170" cy="170" r="160" />
                    <circle class="ring-progress" id="progress-circle" cx="170" cy="170" r="160" />
                </svg>

                <div class="camera-wrapper">
                    <!-- Face Outline Overlay -->
                    <div class="face-outline">
                        <svg width="100%" height="100%" viewBox="0 0 320 320" preserveAspectRatio="none">
                            <path d="M160,40 C110,40 70,80 70,140 C70,200 110,240 130,260 C150,280 170,280 190,260 C210,240 250,200 250,140 C250,80 210,40 160,40 Z" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="3" stroke-dasharray="10,10" />
                        </svg>
                    </div>

                    <div id="flash-effect"></div>

                    <div id="camera-placeholder">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" style="margin-bottom:1rem;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                        <button type="button" id="request-camera-btn" class="btn btn-primary">Start Camera</button>
                    </div>

                    <video id="camera" autoplay playsinline></video>
                    <canvas id="canvas"></canvas>
                </div>
            </div>

            <!-- Right Helper -->
            <div class="side-helper right-helper"></div>
        </div>

        <!-- Progress Indicator (Dots) -->
        <div class="liveness-progress">
            <div class="progress-dot active" id="dot-0"></div>
            <div class="progress-dot" id="dot-1"></div>
            <div class="progress-dot" id="dot-2"></div>
            <div class="progress-dot" id="dot-3"></div>
            <div class="progress-dot" id="dot-4"></div>
        </div>
        
        <div class="security-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3A86FF" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            Make sure your face is clearly visible and well-lit
        </div>

        <div class="tips-footer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3A86FF" stroke-width="2"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2v1"/><path d="M12 6a6 6 0 0 1 6 6c0 1.66-.67 3.16-1.76 4.24-.58.58-.98 1.4-.98 2.26H8.74c0-.86-.4-1.68-.98-2.26A5.97 5.97 0 0 1 6 12a6 6 0 0 1 6-6z"/></svg>
            <strong>Tips for best results:</strong> Avoid wearing glasses • Find a well-lit area • Keep your face within the outline
        </div>

        <form method="POST" enctype="multipart/form-data" id="selfie-form" style="display: none;">
            <input type="file" name="live_selfie" id="live_selfie_input" accept="image/jpeg">
        </form>
    </div>

    <!-- MediaPipe Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js" crossorigin="anonymous"></script>

    <script>
    document.addEventListener('DOMContentLoaded', async () => {
        const videoElement = document.getElementById('camera');
        const canvasElement = document.getElementById('canvas');
        const canvasCtx = canvasElement.getContext('2d');
        const instructionText = document.getElementById('instruction-text');
        const faceRing = document.getElementById('face-ring');
        const placeholder = document.getElementById('camera-placeholder');
        const requestBtn = document.getElementById('request-camera-btn');
        const flashEffect = document.getElementById('flash-effect');
        const selfieForm = document.getElementById('selfie-form');
        const liveSelfieInput = document.getElementById('live_selfie_input');

        let isVerifying = false;
        
        // Custom face detector state
        const livenessSteps = ["left", "right", "up", "down"];
        const stepInstruction = {
          left: "Slowly turn your head to the left",
          right: "Slowly turn your head to the right",
          up: "Slowly tilt your head up",
          down: "Slowly tilt your head down",
        };

        const state = {
          startedAt: 0,
          hasFace: false,
          neutral: null,
          currentStepIndex: 0,
          completed: new Set(),
          lastStepTime: 0,
          done: false,
          waitingForSteady: false,
          steadyStartTime: 0,
          lastStableMetrics: null,
        };

        const threshold = { yaw: 0.06, pitch: 0.08 };
        const steadyThreshold = { yaw: 0.02, pitch: 0.02, scale: 0.05 };
        const steadyDuration = 2000;

        const svgIcons = {
            default: `<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>`,
            left: `<path d="M14 20.9A9 9 0 1 0 5 12a9 9 0 0 0 9 8.9Z"/><path d="M10 12h.01"/>`,
            right: `<path d="M10 20.9A9 9 0 1 1 19 12a9 9 0 0 1-9 8.9Z"/><path d="M14 12h.01"/>`,
            up: `<path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/><path d="M12 10h.01"/>`,
            down: `<path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/><path d="M12 14h.01"/>`,
            success: `<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>`
        };

        function updateDots() {
            const currentProgress = state.currentStepIndex + (state.neutral ? 1 : 0);
            
            // Bottom small dots
            for (let i = 0; i < 5; i++) {
                const dot = document.getElementById(`dot-${i}`);
                if (dot) {
                    dot.className = 'progress-dot';
                    if (i < currentProgress) dot.classList.add('completed');
                    else if (i === currentProgress) dot.classList.add('active');
                }
            }

            // Top Stepper
            for (let i = 1; i <= 5; i++) {
                const stepEl = document.getElementById(`step-${i}`);
                const lineEl = document.getElementById(`line-${i-1}`);
                if (stepEl) {
                    stepEl.className = 'step';
                    if (i - 1 < currentProgress) stepEl.classList.add('completed');
                    else if (i - 1 === currentProgress) stepEl.classList.add('active');
                }
                if (lineEl) {
                    if (i - 1 <= currentProgress) lineEl.classList.add('completed');
                    else lineEl.classList.remove('completed');
                }
            }

            // SVG Progress Ring (circumference is ~1005 for r=160)
            const ring = document.getElementById('progress-circle');
            if (ring) {
                const percent = currentProgress / 5;
                const offset = 1005 - (percent * 1005);
                ring.style.strokeDashoffset = offset;
            }
        }

        function setInstruction(text, success = false) {
            instructionText.textContent = text;
            
            const helperIconSvg = document.getElementById('helper-icon-svg');
            const helperText = document.getElementById('helper-text');
            const sideHelperBox = document.getElementById('side-helper-box');
            const instructionIconSvg = document.getElementById('instruction-icon-svg');
            
            const target = livenessSteps[state.currentStepIndex];
            
            if (success && state.done) {
                if (instructionIconSvg) instructionIconSvg.innerHTML = svgIcons.success;
                if (helperIconSvg) helperIconSvg.innerHTML = svgIcons.success;
                if (helperText) helperText.textContent = "Verified";
            } else if (state.neutral && target) {
                if (sideHelperBox) sideHelperBox.classList.add('visible');
                if (helperText) helperText.textContent = `Turn ${target}`;
                if (instructionIconSvg) instructionIconSvg.innerHTML = svgIcons[target] || svgIcons.default;
                if (helperIconSvg) helperIconSvg.innerHTML = svgIcons[target] || svgIcons.default;
            } else {
                if (instructionIconSvg) instructionIconSvg.innerHTML = svgIcons.default;
                if (sideHelperBox) sideHelperBox.classList.remove('visible');
            }
        }

        function mirrorX(x) { return 1 - x; }

        function drawFaceHints(landmarks) {
            canvasCtx.save();
            canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);

            canvasCtx.strokeStyle = "rgba(131, 225, 255, 0.85)";
            canvasCtx.lineWidth = 1.5;
            canvasCtx.beginPath();
            const pathPoints = [33, 263, 1, 61, 291, 199];
            pathPoints.forEach((i, idx) => {
                const p = landmarks[i];
                const px = mirrorX(p.x) * canvasElement.width;
                const py = p.y * canvasElement.height;
                if (idx === 0) canvasCtx.moveTo(px, py);
                else canvasCtx.lineTo(px, py);
            });
            canvasCtx.closePath();
            canvasCtx.stroke();

            canvasCtx.fillStyle = "rgba(156, 232, 255, 0.85)";
            [1, 33, 263].forEach((i) => {
                const p = landmarks[i];
                canvasCtx.beginPath();
                canvasCtx.arc(mirrorX(p.x) * canvasElement.width, p.y * canvasElement.height, 3, 0, Math.PI * 2);
                canvasCtx.fill();
            });
            canvasCtx.restore();
        }

        function getMetrics(landmarks) {
            const leftEye = landmarks[33];
            const rightEye = landmarks[263];
            const nose = landmarks[1];
            const forehead = landmarks[10];
            const chin = landmarks[152];

            const eyeMidX = (leftEye.x + rightEye.x) / 2;
            const eyeDist = Math.abs(rightEye.x - leftEye.x) || 0.0001;
            const faceHeight = Math.abs(chin.y - forehead.y) || 0.0001;

            const yaw = (nose.x - eyeMidX) / eyeDist;
            const pitch = (nose.y - (forehead.y + chin.y) / 2) / faceHeight;

            return {
                yaw,
                pitch,
                centerX: mirrorX(nose.x),
                centerY: nose.y,
                faceScale: Math.min(1.2, Math.max(0.75, 0.18 / eyeDist)),
            };
        }

        function detectDirection(metrics) {
            const target = livenessSteps[state.currentStepIndex];
            if (!target || state.done) return false;

            if (performance.now() - state.lastStepTime < 900) return false;

            switch (target) {
                case "left": return metrics.yaw < (state.neutral?.yaw || 0) - threshold.yaw;
                case "right": return metrics.yaw > (state.neutral?.yaw || 0) + threshold.yaw;
                case "up": return metrics.pitch < (state.neutral?.pitch || 0) - threshold.pitch;
                case "down": return metrics.pitch > (state.neutral?.pitch || 0) + threshold.pitch;
                default: return false;
            }
        }

        function checkIfSteady(metrics) {
            if (!state.lastStableMetrics) {
                state.lastStableMetrics = { ...metrics };
                state.steadyStartTime = performance.now();
                return false;
            }

            const yawDiff = Math.abs(metrics.yaw - state.lastStableMetrics.yaw);
            const pitchDiff = Math.abs(metrics.pitch - state.lastStableMetrics.pitch);
            const scaleDiff = Math.abs(metrics.faceScale - state.lastStableMetrics.faceScale);

            if (yawDiff < steadyThreshold.yaw && pitchDiff < steadyThreshold.pitch && scaleDiff < steadyThreshold.scale) {
                const steadyTime = performance.now() - state.steadyStartTime;
                return steadyTime >= steadyDuration;
            } else {
                state.lastStableMetrics = { ...metrics };
                state.steadyStartTime = performance.now();
                return false;
            }
        }

        function consumeStep() {
            const step = livenessSteps[state.currentStepIndex];
            state.completed.add(step);
            state.currentStepIndex += 1;
            state.lastStepTime = performance.now();
            updateDots();

            if (state.currentStepIndex >= livenessSteps.length) {
                state.done = true;
                state.waitingForSteady = true;
                state.steadyStartTime = performance.now();
                state.lastStableMetrics = null;
                setInstruction("Liveness verified! Please hold still for capture...", true);
                return;
            }

            const next = livenessSteps[state.currentStepIndex];
            setInstruction(stepInstruction[next], true);
        }

        function capturePhoto() {
            flashEffect.style.opacity = "1"; // flash
            setTimeout(() => {
                flashEffect.style.opacity = "0";
                
                const tempCanvas = document.createElement("canvas");
                tempCanvas.width = videoElement.videoWidth;
                tempCanvas.height = videoElement.videoHeight;
                const tempCtx = tempCanvas.getContext("2d");
                tempCtx.scale(-1, 1);
                tempCtx.drawImage(videoElement, -tempCanvas.width, 0);
                
                tempCanvas.toBlob((blob) => {
                    const file = new File([blob], 'liveness_selfie.jpg', { type: 'image/jpeg' });
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    liveSelfieInput.files = dataTransfer.files;
                    
                    if (camera) camera.stop();
                    
                    instructionText.textContent = "Uploading profile picture...";
                    selfieForm.submit();
                }, 'image/jpeg', 0.9);
            }, 200);
        }

        function onResults(results) {
            if (!isVerifying) return;
            
            if (canvasElement.width !== videoElement.videoWidth) {
                canvasElement.width = videoElement.videoWidth;
                canvasElement.height = videoElement.videoHeight;
            }
            
            const landmarks = results.multiFaceLandmarks?.[0];
            if (!landmarks) {
                canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
                if (state.hasFace) {
                    setInstruction("Face not detected. Center your face and keep good lighting.");
                    state.hasFace = false;
                }
                return;
            }

            const metrics = getMetrics(landmarks);
            drawFaceHints(landmarks);

            if (!state.hasFace) {
                state.hasFace = true;
                setInstruction("Face detected. Hold still to calibrate...", true);
            }

            // Calibrate neutral pose
            if (!state.neutral && performance.now() - state.startedAt > 1800) {
                state.neutral = { yaw: metrics.yaw, pitch: metrics.pitch };
                setInstruction(stepInstruction[livenessSteps[state.currentStepIndex]], true);
                updateDots();
            }

            if (state.waitingForSteady && state.done) {
                if (checkIfSteady(metrics)) {
                    state.waitingForSteady = false;
                    capturePhoto();
                } else {
                    const steadyElapsed = performance.now() - state.steadyStartTime;
                    const steadyPercent = Math.min(100, Math.round((steadyElapsed / steadyDuration) * 100));
                    setInstruction(`Hold still... ${steadyPercent}%`);
                }
                return;
            }

            if (!state.neutral || state.done) return;

            if (detectDirection(metrics)) {
                consumeStep();
            }
        }

        const faceMesh = new FaceMesh({locateFile: (file) => {
            return `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`;
        }});
        
        faceMesh.setOptions({
            maxNumFaces: 1,
            refineLandmarks: true,
            minDetectionConfidence: 0.6,
            minTrackingConfidence: 0.6
        });
        
        faceMesh.onResults(onResults);

        let camera = null;

        requestBtn.addEventListener('click', async () => {
            try {
                instructionText.textContent = "Starting camera...";
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                placeholder.style.display = 'none';
                
                camera = new Camera(videoElement, {
                    onFrame: async () => {
                        await faceMesh.send({image: videoElement});
                    },
                    width: 640,
                    height: 480
                });
                
                state.startedAt = performance.now();
                await camera.start();
                isVerifying = true;
                updateDots();
                setInstruction("Please look straight ahead to calibrate");
            } catch (error) {
                console.error("Camera error:", error);
                alert("Could not access camera. Please allow camera permissions and try again.");
                instructionText.textContent = "Camera access denied.";
            }
        });
    });
    </script>
</section>
<?php require_once 'includes/footer.php'; ?>
