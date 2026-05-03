<?php
$pageTitle = 'Verify Email';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'dashboard_provider.php' : 'dashboard_customer.php'));
    exit;
}

$error = '';
$success = '';
$pdo = getDBConnection();
$reg_email = $_SESSION['reg_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'change_email') {
        // Clear the code and show email input form again
        unset($_SESSION['reg_email_code']);
        unset($_SESSION['reg_email_code_expires']);
        $_SESSION['reg_email_verified'] = false;
        $success = 'You can now enter a different email address.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'send_email_code') {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered.';
            } else {
                $_SESSION['reg_email'] = $email;
                $_SESSION['reg_email_verified'] = false;
                $_SESSION['reg_email_code'] = str_pad(rand(100000, 999999), 6, '0');
                $_SESSION['reg_email_code_expires'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $subject = 'Your verification code';
                $body = "Your email verification code is: " . $_SESSION['reg_email_code'];
                // Use send_email helper (will use PHPMailer SMTP if configured)
                $sent = send_email($email, $subject, $body, false);

                if ($sent) {
                    $success = 'Verification code sent. Please check your email.';
                } else {
                    $success = 'Unable to send via SMTP. Demo code: ' . htmlspecialchars($_SESSION['reg_email_code']);
                }
                $reg_email = $email;
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_email_code') {
        $code = trim($_POST['email_code'] ?? '');

        if (empty($code)) {
            $error = 'Please enter the verification code.';
        } elseif (!isset($_SESSION['reg_email_code']) || !isset($_SESSION['reg_email_code_expires'])) {
            $error = 'Please send a code first.';
        } elseif (strtotime($_SESSION['reg_email_code_expires']) <= time()) {
            $error = 'Code expired. Please resend.';
        } elseif ($code !== ($_SESSION['reg_email_code'] ?? '')) {
            $error = 'Incorrect code.';
        } else {
            $_SESSION['reg_email_verified'] = true;
            header('Location: phone_verification.php');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Verify Your Email</h1>
        <p style="color: #6c757d; margin: 0.5rem 0 1rem;">We sent an OTP to:</p>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <strong><?= htmlspecialchars($reg_email ?: 'your@email.com') ?></strong>
            <?php if (!empty($reg_email) && !empty($_SESSION['reg_email_code'])): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="action" value="change_email" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Change Email</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success-message"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <form method="POST" class="auth-form">
            
            <?php if (empty($_SESSION['reg_email_code'])): ?>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($reg_email) ?>" placeholder="Enter your email" required>
                </div>
                <button type="submit" name="action" value="send_email_code" class="btn btn-primary">Send Verification Code</button>
            <?php endif; ?>
            
            <?php if (!empty($_SESSION['reg_email_code'])): ?>
                <div class="otp-inputs">
                    <input type="hidden" name="email_code" id="email_code" value="" required pattern="\d{6}">
                    <input type="text" class="otp-box" inputmode="numeric" autocomplete="one-time-code" pattern="\d" maxlength="1" aria-label="Digit 1" required>
                    <input type="text" class="otp-box" inputmode="numeric" autocomplete="one-time-code" pattern="\d" maxlength="1" aria-label="Digit 2" required>
                    <input type="text" class="otp-box" inputmode="numeric" autocomplete="one-time-code" pattern="\d" maxlength="1" aria-label="Digit 3" required>
                    <input type="text" class="otp-box" inputmode="numeric" autocomplete="one-time-code" pattern="\d" maxlength="1" aria-label="Digit 4" required>
                    <input type="text" class="otp-box" inputmode="numeric" autocomplete="one-time-code" pattern="\d" maxlength="1" aria-label="Digit 5" required>
                    <input type="text" class="otp-box" inputmode="numeric" autocomplete="one-time-code" pattern="\d" maxlength="1" aria-label="Digit 6" required>
                </div>
                <button type="submit" name="action" value="verify_email_code" class="btn btn-primary">Verify Email</button>
            <?php endif; ?>

            
            <p class="toggle-link">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>
<?php if (!empty($_SESSION['reg_email_code'])): ?>
<script>
(() => {
  const boxes = Array.from(document.querySelectorAll('.otp-inputs .otp-box'));
  const hidden = document.getElementById('email_code');
  const form = hidden?.closest('form');
  if (!boxes.length || !hidden || !form) return;

  const isDigit = (c) => /^[0-9]$/.test(c);
  const syncHidden = () => { hidden.value = boxes.map(b => b.value).join(''); };
  const focusAt = (i) => { if (boxes[i]) boxes[i].focus(); };

  boxes.forEach((box, idx) => {
    box.addEventListener('focus', () => box.select());

    box.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && box.value === '' && idx > 0) {
        boxes[idx - 1].value = '';
        syncHidden();
        focusAt(idx - 1);
        e.preventDefault();
      }
      if (e.key === 'ArrowLeft' && idx > 0) { focusAt(idx - 1); e.preventDefault(); }
      if (e.key === 'ArrowRight' && idx < boxes.length - 1) { focusAt(idx + 1); e.preventDefault(); }
    });

    box.addEventListener('input', () => {
      const v = (box.value || '').replace(/\D/g, '');
      box.value = v.slice(0, 1);
      syncHidden();
      if (box.value && idx < boxes.length - 1) focusAt(idx + 1);
    });

    box.addEventListener('paste', (e) => {
      const text = (e.clipboardData?.getData('text') || '').replace(/\s+/g, '');
      if (!text) return;
      const digits = text.split('').filter(isDigit).slice(0, boxes.length);
      if (!digits.length) return;
      digits.forEach((d, i) => { boxes[i].value = d; });
      syncHidden();
      focusAt(Math.min(digits.length, boxes.length - 1));
      e.preventDefault();
    });
  });

  form.addEventListener('submit', () => syncHidden());
  focusAt(0);
})();
</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>