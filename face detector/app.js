const videoEl = document.getElementById("video");
const canvasEl = document.getElementById("overlay");
const ringEl = document.getElementById("faceRing");
const statusPillEl = document.getElementById("statusPill");
const progressBarEl = document.getElementById("progressBar");
const progressTextEl = document.getElementById("progressText");
const instructionEl = document.getElementById("instruction");
const stepsListEl = document.getElementById("stepsList");
const capturedImageContainerEl = document.getElementById("capturedImageContainer");
const capturedImageEl = document.getElementById("capturedImage");
const downloadBtn = document.getElementById("downloadBtn");
const retakeBtn = document.getElementById("retakeBtn");

const ctx = canvasEl.getContext("2d");

const livenessSteps = ["left", "right", "up", "down"];
const stepInstruction = {
  left: "Slowly turn your head to the left",
  right: "Slowly turn your head to the right",
  up: "Slowly tilt your head up",
  down: "Slowly tilt your head down",
};

const state = {
  startedAt: performance.now(),
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

const threshold = {
  yaw: 0.06,
  pitch: 0.08,
};

const steadyThreshold = {
  yaw: 0.02,
  pitch: 0.02,
  scale: 0.05,
};

const steadyDuration = 2000; // 2 seconds

function getStepElements() {
  return [...stepsListEl.querySelectorAll("li")];
}

function setStatus(text, locked = false) {
  statusPillEl.textContent = text;
  ringEl.classList.toggle("locked", locked);
}

function setInstruction(text) {
  instructionEl.textContent = text;
}

function updateProgress() {
  const pct = Math.round((state.completed.size / livenessSteps.length) * 100);
  progressBarEl.style.width = `${pct}%`;
  progressTextEl.textContent = `${pct}%`;

  const items = getStepElements();
  items.forEach((li) => {
    const key = li.dataset.step;
    li.classList.toggle("done", state.completed.has(key));
  });

  const active = livenessSteps[state.currentStepIndex];
  items.forEach((li) => li.classList.remove("active"));
  if (active) {
    const activeItem = items.find((li) => li.dataset.step === active);
    if (activeItem) activeItem.classList.add("active");
  }
}

function mirrorX(x) {
  return 1 - x;
}

function drawFaceHints(landmarks) {
  ctx.save();
  ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

  ctx.strokeStyle = "rgba(131, 225, 255, 0.85)";
  ctx.lineWidth = 1.5;
  ctx.beginPath();
  const pathPoints = [33, 263, 1, 61, 291, 199];
  pathPoints.forEach((i, idx) => {
    const p = landmarks[i];
    const px = mirrorX(p.x) * canvasEl.width;
    const py = p.y * canvasEl.height;
    if (idx === 0) ctx.moveTo(px, py);
    else ctx.lineTo(px, py);
  });
  ctx.closePath();
  ctx.stroke();

  ctx.fillStyle = "rgba(156, 232, 255, 0.85)";
  [1, 33, 263].forEach((i) => {
    const p = landmarks[i];
    ctx.beginPath();
    ctx.arc(mirrorX(p.x) * canvasEl.width, p.y * canvasEl.height, 3, 0, Math.PI * 2);
    ctx.fill();
  });
  ctx.restore();
}

function detectDirection(metrics) {
  const target = livenessSteps[state.currentStepIndex];
  if (!target || state.done) return false;

  if (performance.now() - state.lastStepTime < 900) return false;

  switch (target) {
    case "left":
      return metrics.yaw < (state.neutral?.yaw || 0) - threshold.yaw;
    case "right":
      return metrics.yaw > (state.neutral?.yaw || 0) + threshold.yaw;
    case "up":
      return metrics.pitch < (state.neutral?.pitch || 0) - threshold.pitch;
    case "down":
      return metrics.pitch > (state.neutral?.pitch || 0) + threshold.pitch;
    default:
      return false;
  }
}

function capturePhoto() {
  const tempCanvas = document.createElement("canvas");
  tempCanvas.width = videoEl.videoWidth;
  tempCanvas.height = videoEl.videoHeight;
  const tempCtx = tempCanvas.getContext("2d");
  
  // Mirror the video (flip horizontally) for display
  tempCtx.scale(-1, 1);
  tempCtx.drawImage(videoEl, -tempCanvas.width, 0);
  
  const imageData = tempCanvas.toDataURL("image/jpeg", 0.95);
  capturedImageEl.src = imageData;
  capturedImageContainerEl.classList.remove("hidden");
  return imageData;
}

function showCaptureUI() {
  capturedImageContainerEl.classList.remove("hidden");
}

function hideCaptureUI() {
  capturedImageContainerEl.classList.add("hidden");
}

function downloadPhoto() {
  const link = document.createElement("a");
  link.href = capturedImageEl.src;
  link.download = `face-capture-${Date.now()}.jpg`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
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
    // Face moved, reset steady timer
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
  updateProgress();

  if (state.currentStepIndex >= livenessSteps.length) {
    state.done = true;
    state.waitingForSteady = true;
    state.steadyStartTime = performance.now();
    state.lastStableMetrics = null;
    setStatus("Liveness verified", true);
    setInstruction("Please hold still for capture...");
    return;
  }

  const next = livenessSteps[state.currentStepIndex];
  setStatus("Live face detected", true);
  setInstruction(stepInstruction[next]);
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

function updateRing(metrics) {
  const dx = (metrics.centerX - 0.5) * 120;
  const dy = (metrics.centerY - 0.5) * 120;
  const scale = metrics.faceScale;
  ringEl.style.transform = `translate(${dx.toFixed(1)}px, ${dy.toFixed(1)}px) scale(${scale.toFixed(3)})`;
}

function resetFaceLostState() {
  setStatus("Face not detected");
  setInstruction("Center your face in the ring and keep good lighting.");
  state.hasFace = false;
  ringEl.classList.remove("locked");
}

async function setupCamera() {
  const stream = await navigator.mediaDevices.getUserMedia({
    video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: "user" },
    audio: false,
  });
  videoEl.srcObject = stream;
  await videoEl.play();
}

function resizeCanvas() {
  canvasEl.width = videoEl.videoWidth;
  canvasEl.height = videoEl.videoHeight;
}

function onFaceResults(results) {
  if (!videoEl.videoWidth || !videoEl.videoHeight) return;

  if (canvasEl.width !== videoEl.videoWidth || canvasEl.height !== videoEl.videoHeight) {
    resizeCanvas();
  }

  const landmarks = results.multiFaceLandmarks?.[0];
  if (!landmarks) {
    ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
    if (state.hasFace) resetFaceLostState();
    return;
  }

  const metrics = getMetrics(landmarks);
  drawFaceHints(landmarks);
  updateRing(metrics);

  if (!state.hasFace) {
    state.hasFace = true;
    setStatus("Face detected", true);
    setInstruction("Hold still to calibrate your neutral pose...");
  }

  // Calibrate a neutral pose from early stable frames.
  if (!state.neutral && performance.now() - state.startedAt > 1800) {
    state.neutral = { yaw: metrics.yaw, pitch: metrics.pitch };
    setInstruction(stepInstruction[livenessSteps[state.currentStepIndex]]);
  }

  if (state.waitingForSteady && state.done) {
    if (checkIfSteady(metrics)) {
      state.waitingForSteady = false;
      capturePhoto();
    } else {
      const steadyElapsed = performance.now() - state.steadyStartTime;
      const steadyPercent = Math.round((steadyElapsed / steadyDuration) * 100);
      setInstruction(`Hold still... ${steadyPercent}%`);
    }
    return;
  }

  if (!state.neutral || state.done) return;

  if (detectDirection(metrics)) {
    consumeStep();
  }
}

async function init() {
  updateProgress();
  setInstruction("Please allow camera access.");
  setStatus("Initializing camera...");

  try {
    await setupCamera();
  } catch (error) {
    setStatus("Camera blocked");
    setInstruction("Unable to access camera. Check browser permission settings.");
    console.error(error);
    return;
  }

  const faceMesh = new FaceMesh({
    locateFile: (file) =>
      `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`,
  });

  faceMesh.setOptions({
    maxNumFaces: 1,
    refineLandmarks: true,
    minDetectionConfidence: 0.6,
    minTrackingConfidence: 0.6,
  });
  faceMesh.onResults(onFaceResults);

  const camera = new Camera(videoEl, {
    onFrame: async () => {
      await faceMesh.send({ image: videoEl });
    },
    width: 1280,
    height: 720,
  });
  camera.start();

  // Setup download button
  downloadBtn.addEventListener("click", downloadPhoto);

  // Setup retake button
  retakeBtn.addEventListener("click", () => {
    // Reset state for retake
    state.startedAt = performance.now();
    state.hasFace = false;
    state.neutral = null;
    state.currentStepIndex = 0;
    state.completed = new Set();
    state.lastStepTime = 0;
    state.done = false;
    state.waitingForSteady = false;
    state.steadyStartTime = 0;
    state.lastStableMetrics = null;
    
    updateProgress();
    setStatus("Face not detected");
    setInstruction("Center your face in the ring and keep good lighting.");
    hideCaptureUI();
  });
}

init();
