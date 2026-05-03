<?php
$pageTitle = 'Create Account';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'provider_profile.php?id=' . $_SESSION['provider_id'] : 'providers.php'));
    exit;
}

if (empty($_SESSION['reg_email_verified'])) {
    header('Location: email_verification.php');
    exit;
}

if (empty($_SESSION['reg_phone_verified'])) {
    header('Location: phone_verification.php');
    exit;
}

$pdo = getDBConnection();
$email_for_display = $_SESSION['reg_email'] ?? '';
$phone_for_display = $_SESSION['reg_phone'] ?? '';



$error = '';
$success = '';
$role = $_SESSION['reg_role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'select_role') {
        $postedRole = trim($_POST['role'] ?? '');
        if (!in_array($postedRole, ['customer', 'provider'])) {
            $error = 'Please select Customer or Provider.';
        } else {
            $_SESSION['reg_role'] = $postedRole;
            $role = $postedRole;
        }
    } elseif ($_POST['action'] === 'register') {
        $role = trim($_POST['role'] ?? $_SESSION['reg_role'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $city = trim($_POST['city'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');

        if (!in_array($role, ['customer', 'provider'])) {
            $error = 'Please select Customer or Provider.';
        } elseif (empty($full_name) || empty($username) || empty($password) || empty($password_confirm)) {
            $error = 'All fields are required.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already taken.';
                }
            } catch (PDOException $e) {
                // username may not exist yet
            }

            if (!$error) {
                if ($role === 'provider') {
                    if (empty($city) || empty($barangay)) {
                        $error = 'City and Barangay are required for providers.';
                    }
                } else {
                    // require customer location at signup
                    if (empty($city) || empty($barangay)) {
                        $error = 'City and Barangay are required for customers.';
                    }
                }
            }

            if (!$error) {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Customer: create account directly and redirect to home page
                if ($role === 'customer') {
                    try {
                        $pdo->prepare('INSERT INTO users (email, username, password_hash, full_name, phone, role, city, barangay, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)')
                            ->execute([$email_for_display, $username, $hash, $full_name, $phone_for_display, $role, $city, $barangay]);
                    } catch (PDOException $e) {
                        $pdo->prepare('INSERT INTO users (email, password_hash, full_name, phone, role, city, barangay, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)')
                            ->execute([$email_for_display, $hash, $full_name, $phone_for_display, $role, $city, $barangay]);
                    }

                    $userId = $pdo->lastInsertId();

                    $_SESSION['user_city'] = $city;
                    $_SESSION['user_barangay'] = $barangay;

                    unset($_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_role'], $_SESSION['reg_email_verified'], $_SESSION['reg_phone_verified'], $_SESSION['reg_email_code'], $_SESSION['reg_phone_code']);

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['role'] = $role;
                    $_SESSION['full_name'] = $full_name;

                    header('Location: customer_face_setup.php');
                    exit;
                }

                // Provider: create immediately (existing behavior)
                try {
                    $pdo->prepare('INSERT INTO users (email, username, password_hash, full_name, phone, role, city, barangay, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)')
                        ->execute([$email_for_display, $username, $hash, $full_name, $phone_for_display, $role, $city, $barangay]);
                } catch (PDOException $e) {
                    $pdo->prepare('INSERT INTO users (email, password_hash, full_name, phone, role, city, barangay, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)')
                        ->execute([$email_for_display, $hash, $full_name, $phone_for_display, $role, $city, $barangay]);
                }

                $userId = $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO providers (user_id, city, barangay, verification_status) VALUES (?, ?, ?, "pending")')
                    ->execute([$userId, $city, $barangay]);

                $_SESSION['user_city'] = $city;
                $_SESSION['user_barangay'] = $barangay;

                unset($_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_role'], $_SESSION['reg_email_verified'], $_SESSION['reg_phone_verified'], $_SESSION['reg_email_code'], $_SESSION['reg_phone_code']);

                $_SESSION['user_id'] = $userId;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $full_name;

                $prov = $pdo->prepare('SELECT id FROM providers WHERE user_id = ?');
                $prov->execute([$userId]);
                $provRow = $prov->fetch();
                $_SESSION['provider_id'] = $provRow ? $provRow['id'] : null;

                header('Location: face_verification.php');
                exit;
            }
        }
    }
}

require_once 'includes/header.php';
?>
<style>
    body {
        background-color: #f0f2f5;
    }
    .register-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 80px);
        padding: 2rem;
    }
    .register-card {
        display: flex;
        width: 100%;
        max-width: 1100px;
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .register-sidebar {
        flex: 0 0 380px;
        background: #f8fafc;
        padding: 3rem;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    .register-sidebar h2 {
        color: #1e293b;
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 1rem;
        line-height: 1.2;
    }
    .register-sidebar > p {
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 2rem;
        line-height: 1.5;
    }
    .sidebar-illustration {
        text-align: center;
        margin-bottom: 2.5rem;
    }
    .sidebar-illustration svg {
        max-width: 180px;
        height: auto;
    }
    .benefit-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .benefit-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #e0e7ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .benefit-icon svg {
        width: 20px;
        height: 20px;
    }
    .benefit-text h4 {
        margin: 0 0 0.25rem 0;
        font-size: 0.95rem;
        color: #1e293b;
        font-weight: 600;
    }
    .benefit-text p {
        margin: 0;
        font-size: 0.85rem;
        color: #64748b;
        line-height: 1.4;
    }

    .register-main {
        flex: 1;
        padding: 3rem 4rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .register-main-header {
        margin-bottom: 2rem;
    }
    .register-main-header h1 {
        font-size: 2rem;
        color: #0f172a;
        margin-bottom: 0.5rem;
        font-weight: 700;
    }
    .register-main-header p {
        color: #2563eb;
        font-weight: 600;
        font-size: 1rem;
        margin: 0;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem 1.5rem;
    }
    .form-group {
        margin-bottom: 0;
    }
    .form-group.full-width {
        grid-column: span 2;
    }
    .form-group label {
        display: block;
        font-size: 0.9rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 0.5rem;
    }
    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-wrapper svg {
        position: absolute;
        left: 1rem;
        color: #94a3b8;
        width: 18px;
        height: 18px;
        pointer-events: none;
    }
    .input-wrapper .input-right-icon {
        left: auto;
        right: 1rem;
        color: #10b981;
    }
    .input-wrapper input,
    .input-wrapper select {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.95rem;
        color: #1e293b;
        background: #fff;
        transition: all 0.2s;
    }
    .input-wrapper select {
        padding-left: 2.75rem;
        appearance: none;
    }
    .input-wrapper::after {
        content: "";
    }
    .select-chevron {
        position: absolute;
        right: 1rem;
        pointer-events: none;
        width: 16px;
        height: 16px;
        color: #64748b;
    }
    .input-wrapper input:focus,
    .input-wrapper select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .btn-submit {
        background: #2563eb;
        color: white;
        border: none;
        padding: 1rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        width: 100%;
        margin-top: 1.5rem;
        transition: background 0.2s, transform 0.1s;
    }
    .btn-submit:hover {
        background: #1d4ed8;
    }
    .btn-submit:active {
        transform: scale(0.98);
    }
    .register-footer {
        margin-top: 2.5rem;
        text-align: center;
        color: #64748b;
        font-size: 0.9rem;
        position: relative;
    }
    .register-footer::before {
        content: "";
        display: block;
        width: 100%;
        height: 1px;
        background: #e2e8f0;
        margin-bottom: 1.5rem;
    }
    .register-footer .or-badge {
        position: absolute;
        top: -10px;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        padding: 0 15px;
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .register-footer a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
        margin: 0 0.5rem;
    }
    .register-footer a:hover {
        text-decoration: underline;
    }
    .role-selector input:checked + .role-content {
        border-color: #2563eb;
        background-color: #eff6ff;
    }
    .role-content {
        border: 2px solid #e2e8f0; 
        border-radius: 12px; 
        padding: 1.5rem; 
        cursor: pointer; 
        transition: all 0.2s;
        display: block;
    }
    .role-content:hover {
        border-color: #cbd5e1;
    }
    
    @media (max-width: 900px) {
        .register-card {
            flex-direction: column;
        }
        .register-sidebar {
            flex: none;
            padding: 2rem;
            border-right: none;
            border-bottom: 1px solid #e2e8f0;
        }
        .register-main {
            padding: 2rem;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .form-group.full-width {
            grid-column: span 1;
        }
    }
</style>

<div class="register-wrapper fade-in">
    <div class="register-card">
        <!-- Sidebar Info -->
        <div class="register-sidebar">
            <h2>Create Your <?= $role ? ucfirst($role) : 'Platform' ?> Account</h2>
            <p>Join our platform to connect with communities and grow your impact.</p>
            
            <div class="sidebar-illustration">
                <!-- Customized Illustration based on the uploaded image -->
                <svg viewBox="0 0 240 220" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Decorative background leaves/elements -->
                    <path d="M40 100 C30 80, 50 60, 70 80 C80 90, 70 110, 50 110 Z" fill="#D1E8FF" opacity="0.6"/>
                    <path d="M200 90 C210 70, 190 50, 170 70 C160 80, 170 100, 190 100 Z" fill="#D1E8FF" opacity="0.6"/>
                    <circle cx="30" cy="140" r="4" fill="#D1E8FF"/>
                    <circle cx="210" cy="130" r="4" fill="#D1E8FF"/>
                    <path d="M45 130 L50 125 L55 130 L50 135 Z" fill="#D1E8FF"/>
                    <path d="M195 110 L200 105 L205 110 L200 115 Z" fill="#D1E8FF"/>
                    
                    <!-- Clipboard Body -->
                    <rect x="65" y="45" width="110" height="140" rx="8" fill="white" stroke="#3B82F6" stroke-width="6"/>
                    
                    <!-- Clipboard Header / Clip -->
                    <rect x="95" y="30" width="50" height="20" rx="6" fill="#3B82F6"/>
                    <circle cx="120" cy="40" r="4" fill="white"/>
                    
                    <!-- Document Content Lines -->
                    <rect x="85" y="115" width="70" height="6" rx="3" fill="#BFDBFE"/>
                    <rect x="85" y="130" width="70" height="6" rx="3" fill="#BFDBFE"/>
                    <rect x="85" y="145" width="40" height="6" rx="3" fill="#BFDBFE"/>
                    
                    <!-- User Profile Icon on Clipboard -->
                    <circle cx="120" cy="80" r="22" fill="#DBEAFE"/>
                    <circle cx="120" cy="73" r="8" fill="#60A5FA"/>
                    <path d="M106 93 C106 87, 112 84, 120 84 C128 84, 134 87, 134 93" stroke="#60A5FA" stroke-width="5" stroke-linecap="round"/>

                    <!-- Shield Element -->
                    <path d="M135 125 L175 135 V160 C175 180 155 195 135 200 C115 195 95 180 95 160 V135 L135 125 Z" fill="#60A5FA"/>
                    <path d="M135 125 L175 135 V160 C175 180 155 195 135 200 C135 200 135 125 135 125 Z" fill="#3B82F6"/>
                    <path d="M120 160 L130 170 L155 145" stroke="white" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <div class="benefit-item">
                <div class="benefit-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div class="benefit-text">
                    <h4>Secure & Verified</h4>
                    <p>Your information is verified and protected.</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <div class="benefit-text">
                    <h4>Trusted Community</h4>
                    <p>Join a network of trusted providers and organizations.</p>
                </div>
            </div>

            <div class="benefit-item">
                <div class="benefit-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <div class="benefit-text">
                    <h4>Grow Your Impact</h4>
                    <p>Reach more people and make a difference.</p>
                </div>
            </div>
        </div>

        <!-- Main Form Area -->
        <div class="register-main">
            <div class="register-main-header">
                <h1>Create Account</h1>
                <?php if ($role): ?>
                <p>Role: <?= htmlspecialchars(ucfirst($role)) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?><div style="background:#fee2e2; color:#b91c1c; padding:1rem; border-radius:8px; margin-bottom:1.5rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div style="background:#d1fae5; color:#047857; padding:1rem; border-radius:8px; margin-bottom:1.5rem;"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if (empty($role)): ?>
                <form method="POST" style="max-width: 100%; margin: 0 auto; width: 100%;">
                    <input type="hidden" name="action" value="select_role">
                    <h3 style="margin-bottom: 1.5rem; text-align: center; color: #1e293b;">Please choose account type</h3>
                    
                    <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                        <label class="role-selector" style="flex:1;">
                            <input type="radio" name="role" value="customer" required style="display:none;">
                            <span class="role-content">
                                <div style="font-size: 2.5rem; margin-bottom: 1rem; text-align:center;">👤</div>
                                <div style="font-weight: 600; color: #1e293b; text-align:center; font-size: 1.1rem;">Customer</div>
                            </span>
                        </label>
                        <label class="role-selector" style="flex:1;">
                            <input type="radio" name="role" value="provider" style="display:none;">
                            <span class="role-content">
                                <div style="font-size: 2.5rem; margin-bottom: 1rem; text-align:center;">🛠️</div>
                                <div style="font-weight: 600; color: #1e293b; text-align:center; font-size: 1.1rem;">Provider</div>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">Continue</button>
                </form>
            <?php else: ?>
                <form method="POST" id="registerForm">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Email (verified)</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <input type="email" readonly value="<?= htmlspecialchars($email_for_display) ?>">
                                <svg class="input-right-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone (verified)</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                <input type="tel" readonly value="<?= htmlspecialchars($phone_for_display) ?>">
                                <svg class="input-right-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Full Name</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                <input type="text" name="full_name" required placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Username</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path></svg>
                                <input type="text" name="username" required minlength="3" placeholder="@ youruser123" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                <input type="password" name="password" id="password" required minlength="6" placeholder="At least 6 characters">
                                <svg class="select-chevron" style="cursor:pointer; pointer-events:auto;" onclick="const p=document.getElementById('password'); p.type=p.type==='password'?'text':'password';" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                <input type="password" name="password_confirm" id="password_confirm" required minlength="6" placeholder="Confirm password">
                                <svg class="select-chevron" style="cursor:pointer; pointer-events:auto;" onclick="const p=document.getElementById('password_confirm'); p.type=p.type==='password'?'text':'password';" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>City / Municipality (Pampanga)</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <select name="city" id="city-select" required>
                                    <option value="">Choose city / municipality</option>
                                    <?php
                                    $cities = ['Angeles City', 'City of San Fernando', 'Mabalacat City', 'Apalit', 'Arayat', 'Bacolor', 'Candaba', 'Floridablanca', 'Guagua', 'Lubao', 'Macabebe', 'Magalang', 'Masantol', 'Mexico', 'Minalin', 'Porac', 'San Luis', 'San Simon', 'Santa Ana', 'Santa Rita', 'Santo Tomas', 'Sasmuan'];
                                    $selectedCity = $_POST['city'] ?? '';
                                    foreach ($cities as $cityItem) : ?>
                                        <option value="<?= htmlspecialchars($cityItem) ?>" <?= $selectedCity === $cityItem ? 'selected' : '' ?>><?= htmlspecialchars($cityItem) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <svg class="select-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Barangay</label>
                            <div class="input-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                <select name="barangay" id="barangay-select" required>
                                    <option value="">Choose barangay</option>
                                </select>
                                <svg class="select-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>

                    </div> <!-- form-grid -->

                    <button type="submit" class="btn-submit">Create Account</button>
                    
                    <div class="register-footer">
                        <span class="or-badge">OR</span>
                        <div style="margin-top: 1rem;">
                            <a href="email_verification.php">Back to email verification</a> | <a href="login.php">Login</a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const cityBarangays = {
        "Angeles City": ["Amsic","Anunas","Balibago","Capaya","Cuayan","Cutcut","Cutud","Lourdes North West","Lourdes Sur","Lourdes Sur East","Malabañas","Margot","Mining","Ninoy Aquino","Pampang","Pandan","Pulungbulu","Pulung Cacutud","Pulung Maragul","Salmac","San Jose","San Nicolas","Santa Teresita","Santo Cristo","Santo Domingo","Santo Rosario","Sapangbato","Tabun"],
        "City of San Fernando": ["Alasas","Baliti","Bulaon","Calulut","Dela Paz Norte","Dela Paz Sur","Del Carmen","Del Pilar","Del Rosario","Dolores","Juliana","Lara","Lourdes","Magliman","Maimpis","Malino","Malpitic","Pandaras","Panipuan","Quebiawan","Saguin","San Agustin","San Felipe","San Isidro","San Jose","San Juan","San Nicolas","San Pedro","Santa Lucia","Santa Teresita","Santo Niño","Sindalan","Sto. Rosario (Poblacion)"],
        "Mabalacat City": ["Atlu-Bola","Bical","Bundagul","Cacutud","Camachiles","Dapdap","Dolores","Dau","Lakandula","Mabiga","Macapagal Village","Mamatitang","Mangalit","Marcos Village","Mawaque","Paralaya","Poblacion","San Francisco","San Joaquin","San Jose","San Vicente","Santa Ines","Santa Maria","Santo Rosario","Sucad"],
        "Apalit": ["Balucuc","Calantipe","Cansinala","Capalangan","Colgante","Paligui","Sampaloc","San Juan","San Vicente","Sucad","Sulipan","Tabuyuc"],
        "Arayat": ["Arenas","Baliti","Batasan","Candating","Camba","Cupang","Gatiawin","Guemasan","Kaledian","La Paz","Lacmit","Mangga-Cacutud","Paralaya","Plazang Luma","San Agustin Norte","San Agustin Sur","San Antonio","San Juan Bano","San Mateo","Santo Niño Tabuan","Sapa","Sucad"],
        "Bacolor": ["Balas","Cabambangan (Poblacion)","Calibutbut","Cabetican","Dela Paz","Dolores","Duat","Macabacle","Magliman","Maliwalu","Mesalipit","Parulog","Potrero","San Antonio","San Isidro","San Vicente","Santa Barbara","Santa Ines","Santa Lucia","Santa Maria","Talba"],
        "Candaba": ["Bahay Pare","Buas","Cuayan","Dalayap","Dulong Ilog","Gulap","Lanang","Magumbali","Mandasig","Mangga","Mapaniqui","Pansumaloc","Paralaya","Pasig","Pescadores","Pulung Gubat","San Agustin","San Isidro","San Luis","San Nicolas","Santa Lucia","Santo Rosario","Talang","Tenejero"],
        "Floridablanca": ["Anapul","Benedicto","Bodega","Cabanawan","Calantas","Dela Paz","Fortuna","Gutad","Mawacat","Pabanlag","Paguiruan","Palmayo","Poblacion","San Antonio","San Isidro","San Jose","San Nicolas","Santa Monica","Santo Rosario","Valdez"],
        "Guagua": ["Ascom","Bancal","Betis","Jose Abad Santos","Lambac","Maquiapo","Natividad","Pale-Pale","Pulongmasle","Rizal","San Agustin","San Antonio","San Isidro","San Juan Bautista","San Matias","San Miguel","San Nicolas 1st","San Nicolas 2nd","San Pablo","San Pedro","San Rafael","San Roque","San Vicente","Santo Cristo","Santo Niño","Santo Rosario","Sapang Uwak"],
        "Lubao": ["Bancal Pugad","Bancal Sinubli","Barangay 1 (Poblacion)","Barangay 2","Barangay 3","Barangay 4","Barangay 5","Barangay 6","Calangain","Candelaria","Concepcion","De La Paz","Don Ignacio Dimson","Prado Siongco","Prado Aranguren","Remedios","San Agustin","San Antonio","San Isidro","San Jose Apunan","San Miguel","San Nicolas 1st","San Nicolas 2nd","San Pablo 1st","San Pablo 2nd","San Pedro Palcarangan","San Roque Arbol","Santa Barbara","Santa Cruz","Santa Lucia","Santa Monica","Santa Rita","Santo Domingo","Santo Niño","Santo Tomas","Sapang Balas"],
        "Macabebe": ["Batasan","Caduang Tete","Castuli","Consuelo","Dalayap","Mataguiti","San Esteban","San Francisco","San Gabriel","San Isidro","San Jose","San Juan","San Nicolas","San Rafael","San Roque","San Vicente","Santa Cruz","Santa Maria","Santo Niño","Santo Rosario","Saplad David"],
        "Magalang": ["Alauli","Camias","Dolores","Escaler","San Agustin","San Antonio","San Isidro","San Jose","San Miguel","San Nicolas 1st","San Nicolas 2nd","San Pablo","San Pedro","San Roque","Santa Cruz","Santa Maria","Santo Niño","Turu"],
        "Masantol": ["Alauli","Bagang","Balibago","Bebe Anac","Bebe Matua","Bulacus","Cambasi","Malauli","Nigui","Palimpe","Puti","San Agustin","San Isidro","San Nicolas","San Pedro","Santa Lucia","Santo Niño","Saplad"],
        "Mexico": ["Anao","Balas","Buenavista","Cawayan","Concepcion","Dolores","Eden","Lagundi","Laug","Masamat","Malauli","Poblacion","Pampang","Parian","Panipuan","Sabanilla","San Antonio","San Jose","San Juan","San Miguel","San Nicolas","San Pablo","San Roque","Santa Cruz","Santa Maria","Santo Rosario","Sucad","Tangle"],
        "Minalin": ["Bulac","Dawe","Lourdes","Maniago","San Francisco 1st","San Francisco 2nd","San Isidro","San Nicolas","San Pedro","Santa Catalina","Santa Cruz","Santa Rita","Santo Domingo","Santo Rosario"],
        "Porac": ["Babo Pangulo","Babo Sacan","Balubad","Camias","Dolores","Inararo","Jalung","Mancatian","Manuali","Mitla Proper","Pias","Pio","Poblacion","Pulung Santol","Salu","San Jose Mitla","Sapang Uwak","Siñura","Villa Maria"],
        "San Luis": ["Balud","Capas","Clavolera","Del Rosario","Guerrero","Jagobiao","Lamao","Libutad","Majas","Mangas","Mayantoc","Poblacion","San Isidro","San Jose","San Nicolas","San Pablo","San Roque","San Vicente","Santa Catalina","Santa Cruz","Santo Domingo"],
        "San Simon": ["San Agustin","San Antonio","San Francisco","San Juan","San Pablo","San Pedro","San Roque","San Vicente","Santa Ana","Santa Lucia","Santa Maria","Santo Domingo"],
        "Santa Ana": ["Bagong Silang","Bakal 1","Bakal 2","Bakal 3","Bakal 4","Balaogan","Balubad","Bical","Bulaon","Bulac","Bulo","Camarang","Dila","Macapsing","Mahipus","Maming","Malabanan","Mapalad","Masantol","Matua",
                 "Pili","Pipiat","Poblacion","Pugad","San Buenaventura","San Carlos","San Isidro","San Juan","San Nicolas","San Pedro","San Rafael","San Roque","Santa Lucia","Santa Rita","Sapan","Santo Cristo","Santo Rosario","Sapang Pingping"],
        "Santa Rita": ["Abulug","Balas","Buburbura","Bulo","Culo","Dipawe","District 1"," district 2","District 3","District 4","District 5","District 6","District 7","Eloy Casanas","Felizardo","Imelda","Liputan","Maligaya","Mapurao","Masanting","Miguel Magno","Pampang","Pandayatan","Poblacion","San Agustin","San Salvador","Santo Cristo","Sapang Palay","Saspulan","Talabiga","Tibig"],
        "Santo Tomas": ["Babutan","Bulac","Carinuan","Dayap","Iaas","Kabatete","Macho","Maligaya","Masanting","Miguel Magno","Nambi","Niugan","Poblacion","San Agustin","San Francisco","San Jose","San Pablo","San Vicente","Santa Catalina","Santa Lucia","Santa Rita","Santo Cristo","Sapang Dau","Sapang Pamintuan"],
        "Sasmuan": ["Balucuc","Basiao","Bebe Anac","Bebe Matua","Camay","Dawe","Dela Paz","Lawa","Maligaya","Ninoy Aquino","Puta Bato","Punta Delgada","Sagrada","San Agustin","San Jose","San Nicolas","Santa Cruz","Santa Rita","Sapang Palay","Santo Niño","Tibag"]
    };

    const citySelect = document.getElementById('city-select');
    const barangaySelect = document.getElementById('barangay-select');
    const savedBarangay = <?= json_encode($_POST['barangay'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    function populateBarangays() {
        while (barangaySelect.firstChild) {
            barangaySelect.removeChild(barangaySelect.firstChild);
        }

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Choose barangay';
        barangaySelect.appendChild(placeholder);

        const city = citySelect.value;
        if (!city || !cityBarangays[city]) {
            return;
        }

        const list = cityBarangays[city];
        list.forEach(b => {
            const option = document.createElement('option');
            option.value = b;
            option.textContent = b;
            if (b === savedBarangay) {
                option.selected = true;
            }
            barangaySelect.appendChild(option);
        });
    }

    citySelect.addEventListener('change', populateBarangays);
    populateBarangays();
</script>

<?php require_once 'includes/footer.php'; ?>