<?php
$pageTitle = 'Register';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'dashboard_provider.php' : 'dashboard_customer.php'));
    exit;
}

$error = '';
$success = '';
$role = $_GET['role'] ?? 'customer';
$pdo = getDBConnection();
$otp_sent = isset($_SESSION['reg_email_code']) && isset($_SESSION['reg_phone_code']);
$email_for_display = $_SESSION['reg_email'] ?? '';
$phone_for_display = $_SESSION['reg_phone'] ?? '';

// SEND OTP - Verify email/phone exist and send codes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $otp_type = $_POST['otp_type'] ?? 'both'; // both, email, or phone

    if (empty($email) || empty($phone)) {
        $error = 'Email and phone are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered.';
        } else {
            // Check if phone already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'This phone number is already registered.';
            } else {
                // Store in session
                $_SESSION['reg_email'] = $email;
                $_SESSION['reg_phone'] = $phone;
                $_SESSION['reg_role'] = $role;
                $_SESSION['reg_code_expires'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Send email OTP if requested
                if ($otp_type === 'both' || $otp_type === 'email') {
                    $email_code = str_pad(rand(100000, 999999), 6, '0');
                    $_SESSION['reg_email_code'] = $email_code;
                    $_SESSION['reg_email_verified'] = false;
                    
                    $subject = 'ServiceLink - Email Verification Code';
                    $message = "Your email verification code is: $email_code\n\nThis code expires in 15 minutes.";
                    @mail($email, $subject, $message);
                }

                // Send phone OTP if requested
                if ($otp_type === 'both' || $otp_type === 'phone') {
                    $phone_code = str_pad(rand(100000, 999999), 6, '0');
                    $_SESSION['reg_phone_code'] = $phone_code;
                    $_SESSION['reg_phone_verified'] = false;
                    
                    // SMS would be sent here in production
                }

                $otp_sent = true;
                $email_for_display = $email;
                $phone_for_display = $phone;
                $success = 'OTP code(s) sent! Check your email and/or SMS for the verification codes.';
            }
        }
    }
}

// Change Email/Phone - Reset and start over
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    unset($_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_role'], $_SESSION['reg_email_token'], $_SESSION['reg_phone_code'], $_SESSION['reg_code_expires'], $_SESSION['reg_email_verified']);
    $otp_sent = false;
    $email_for_display = '';
    $phone_for_display = '';
    $_GET['role'] = $_POST['role'] ?? 'customer';
    $role = $_GET['role'];
}

// REGISTER - Verify OTPs and create account
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    if (!isset($_SESSION['reg_email_code']) || !isset($_SESSION['reg_phone_code'])) {
        $error = 'Please send OTP codes first.';
    } elseif (strtotime($_SESSION['reg_code_expires']) <= time()) {
        $error = 'OTP expired. Please resend.';
        unset($_SESSION['reg_email_code'], $_SESSION['reg_phone_code'], $_SESSION['reg_email'], $_SESSION['reg_phone']);
    } else {
        $email_otp = trim($_POST['email_otp'] ?? '');
        $phone_otp = trim($_POST['phone_otp'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $role = $_SESSION['reg_role'] ?? 'customer';

        // Validate OTPs
        if (empty($email_otp) || empty($phone_otp)) {
            $error = 'Both email and phone OTP codes are required.';
        } elseif ($email_otp !== $_SESSION['reg_email_code']) {
            $error = 'Invalid email OTP.';
        } elseif ($phone_otp !== $_SESSION['reg_phone_code']) {
            $error = 'Invalid phone OTP.';
        } elseif (empty($full_name) || empty($password) || empty($password_confirm)) {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            $email = $_SESSION['reg_email'];
            $phone = $_SESSION['reg_phone'];

            // Validate provider fields
            if ($role === 'provider') {
                $city = trim($_POST['city'] ?? '');
                $barangay = trim($_POST['barangay'] ?? '');
                if (empty($city) || empty($barangay)) {
                    $error = 'City and Barangay are required for providers.';
                }
            }

            if (empty($error)) {
                // Create user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone, role, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, 1, 1)")
                    ->execute([$email, $hash, $full_name, $phone, $role]);

                $userId = $pdo->lastInsertId();

                if ($role === 'provider') {
                    $city = trim($_POST['city'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $pdo->prepare("INSERT INTO providers (user_id, city, barangay) VALUES (?, ?, ?)")
                        ->execute([$userId, $city, $barangay]);
                }

                // Clean up session
                unset($_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_role'], $_SESSION['reg_email_code'], $_SESSION['reg_phone_code'], $_SESSION['reg_code_expires'], $_SESSION['reg_email_verified']);

                $_SESSION['user_id'] = $userId;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $full_name;
                if ($role === 'provider') {
                    $prov = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
                    $prov->execute([$userId]);
                    $provRow = $prov->fetch();
                    $_SESSION['provider_id'] = $provRow ? $provRow['id'] : null;
                }

                header('Location: ' . ($role === 'provider' ? 'dashboard_provider.php' : 'dashboard_customer.php'));
                exit;
            }
        }
    }
}

require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Create Account</h1>
        <?php if ($error): ?><p style="color: #e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p style="color: #27ae60; margin-bottom: 1rem;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <div class="auth-tabs">
            <button type="button" class="<?= $role === 'customer' ? 'active' : '' ?>" onclick="switchRole('customer')">Customer</button>
            <button type="button" class="<?= $role === 'provider' ? 'active' : '' ?>" onclick="switchRole('provider')">Provider</button>
        </div>

        <form method="POST" id="register-form" class="register-form-layout">
            <div class="form-main-content">
                <!-- Email & Phone Section with inline buttons -->
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Email</label>
                        <input type="email" name="email" required placeholder="your@email.com" 
                            value="<?= htmlspecialchars($_POST['email'] ?? $email_for_display) ?>">
                    </div>
                    <button type="button" name="action" value="send_otp" class="btn btn-primary" onclick="sendEmailOTP()" style="padding: 0.6rem 1.2rem; font-size: 0.9rem;">Send OTP</button>
                </div>

                <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" required placeholder="09xxxxxxxxx" 
                            value="<?= htmlspecialchars($_POST['phone'] ?? $phone_for_display) ?>">
                    </div>
                    <button type="button" name="action" value="send_otp" class="btn btn-primary" onclick="sendPhoneOTP()" style="padding: 0.6rem 1.2rem; font-size: 0.9rem;">Send OTP</button>
                </div>

                <!-- OTP Section (shown after sending) -->
                <?php if ($otp_sent): ?>
                    <div class="otp-section" style="border-top: 2px solid var(--border-color); padding-top: 1.5rem; margin-top: 1.5rem;">
                        <div class="form-group">
                            <label>Email OTP (6 digits)</label>
                            <input type="text" name="email_otp" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>
                        </div>

                        <div class="form-group">
                            <label>Phone OTP (6 digits)</label>
                            <input type="text" name="phone_otp" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>
                        </div>

                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; background: var(--bg-light); padding: 0.75rem; border-radius: var(--radius-sm);">
                            Demo Phone OTP: <strong><?= htmlspecialchars($_SESSION['reg_phone_code'] ?? '') ?></strong>
                        </p>
                    </div>

                    <!-- Account Details Section -->
                    <div class="account-details-section" style="border-top: 2px solid var(--border-color); padding-top: 1.5rem; margin-top: 1.5rem;">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" required placeholder="John Doe" 
                                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required minlength="6" placeholder="At least 6 characters">
                        </div>

                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="password_confirm" required minlength="6" placeholder="Confirm your password">
                        </div>
                    </div>

                    <!-- Provider Fields -->
                    <div id="provider-fields" style="<?= $otp_sent && $_SESSION['reg_role'] === 'provider' ? '' : 'display:none' ?>; border-top: 2px solid var(--border-color); padding-top: 1.5rem; margin-top: 1.5rem;">
                        <div class="form-group">
                            <label>City / Municipality (Pampanga)</label>
                            <select name="city" id="city-select">
                                <option value="">Select city / municipality</option>
                                <?php
                                $cities = [
                                    'Angeles City', 'City of San Fernando', 'Mabalacat City', 'Apalit', 'Arayat', 'Bacolor',
                                    'Candaba', 'Floridablanca', 'Guagua', 'Lubao', 'Macabebe', 'Magalang', 'Masantol',
                                    'Mexico', 'Minalin', 'Porac', 'San Luis', 'San Simon', 'Santa Ana', 'Santa Rita',
                                    'Santo Tomas', 'Sasmuan'
                                ];
                                $selectedCity = $_POST['city'] ?? '';
                                foreach ($cities as $cityOption): ?>
                                    <option value="<?= htmlspecialchars($cityOption) ?>" <?= $selectedCity === $cityOption ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cityOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Barangay</label>
                            <select name="barangay" id="barangay-select">
                                <option value="">Select barangay</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Side Button -->
            <div class="form-side-button">
                <?php if (!$otp_sent): ?>
                    <input type="hidden" name="action" value="send_otp">
                    <input type="hidden" name="role" id="form-role" value="<?= htmlspecialchars($role) ?>">
                    <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center;">
                        Click "Send OTP" buttons<br>next to email & phone
                    </p>
                <?php else: ?>
                    <input type="hidden" name="action" value="register">
                    <button type="submit" class="btn btn-primary btn-side" style="width: 100%;">Create Account</button>
                    <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 1rem;">
                        <form method="POST" style="margin: 0.5rem 0 0 0;">
                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="role" value="<?= htmlspecialchars($_SESSION['reg_role']) ?>">
                            <button type="submit" style="background: none; border: none; color: var(--accent); text-decoration: underline; cursor: pointer; font-size: 0.8rem; padding: 0;">Change Email/Phone</button>
                        </form>
                    </p>
                <?php endif; ?>
            </div>
        </form>

        <p style="text-align:center; margin-top:1rem; color:var(--text-muted);">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</div>
<script>

function sendEmailOTP() {
    const email = document.querySelector('input[name="email"]').value.trim();
    const phone = document.querySelector('input[name="phone"]').value.trim();
    const role = document.getElementById('form-role')?.value || 'customer';

    if (!email || !phone) {
        alert('Please enter both email and phone');
        return;
    }

    // Create hidden form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    form.innerHTML = `
        <input type="hidden" name="email" value="${email}">
        <input type="hidden" name="phone" value="${phone}">
        <input type="hidden" name="role" value="${role}">
        <input type="hidden" name="action" value="send_otp">
        <input type="hidden" name="otp_type" value="email">
    `;

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function sendPhoneOTP() {
    const email = document.querySelector('input[name="email"]').value.trim();
    const phone = document.querySelector('input[name="phone"]').value.trim();
    const role = document.getElementById('form-role')?.value || 'customer';

    if (!email || !phone) {
        alert('Please enter both email and phone');
        return;
    }

    // Create hidden form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    form.innerHTML = `
        <input type="hidden" name="email" value="${email}">
        <input type="hidden" name="phone" value="${phone}">
        <input type="hidden" name="role" value="${role}">
        <input type="hidden" name="action" value="send_otp">
        <input type="hidden" name="otp_type" value="phone">
    `;

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function switchRole(r) {
    document.getElementById('form-role').value = r;
    const providerFields = document.getElementById('provider-fields');
    if (providerFields) {
        providerFields.style.display = r === 'provider' ? 'block' : 'none';
    }
    document.querySelectorAll('.auth-tabs button').forEach((b, i) => {
        b.classList.toggle('active', (r === 'customer' && i === 0) || (r === 'provider' && i === 1));
    });
}

// City / Municipality (Pampanga) -> Barangay options
// Lists based on official barangays per LGU in Pampanga.
const cityBarangays = {
    "Angeles City": [
        "Amsic","Anunas","Balibago","Capaya","Cuayan","Cutcut","Cutud","Lourdes North West",
        "Lourdes Sur","Lourdes Sur East","Malabañas","Margot","Mining","Ninoy Aquino",
        "Pampang","Pandan","Pulungbulu","Pulung Cacutud","Pulung Maragul","Salmac",
        "San Jose","San Nicolas","Santa Teresita","Santo Cristo","Santo Domingo",
        "Santo Rosario","Sapangbato","Tabun"
    ],
    "City of San Fernando": [
        "Alasas","Baliti","Bulaon","Calulut","Dela Paz Norte","Dela Paz Sur","Del Carmen",
        "Del Pilar","Del Rosario","Dolores","Juliana","Lara","Lourdes","Magliman",
        "Maimpis","Malino","Malpitic","Pandaras","Panipuan","Quebiawan","Saguin",
        "San Agustin","San Felipe","San Isidro","San Jose","San Juan","San Nicolas",
        "San Pedro","Santa Lucia","Santa Teresita","Santo Niño","Sindalan","Sto. Rosario (Poblacion)"
    ],
    "Mabalacat City": [
        "Atlu-Bola","Bical","Bundagul","Cacutud","Camachiles","Dapdap","Dolores",
        "Dau","Lakandula","Mabiga","Macapagal Village","Mamatitang","Mangalit",
        "Marcos Village","Mawaque","Paralaya","Poblacion","San Francisco","San Joaquin",
        "San Jose","San Vicente","Santa Ines","Santa Maria","Santo Rosario","Sucad"
    ],
    "Apalit": [
        "Balucuc","Calantipe","Cansinala","Capalangan","Colgante","Paligui",
        "Sampaloc","San Juan","San Vicente","Sucad","Sulipan","Tabuyuc"
    ],
    "Arayat": [
        "Arenas","Baliti","Batasan","Candating","Camba","Cupang","Gatiawin",
        "Guemasan","Kaledian","La Paz","Lacmit","Mangga-Cacutud","Paralaya",
        "Plazang Luma","San Agustin Norte","San Agustin Sur","San Antonio",
        "San Juan Bano","San Mateo","Santo Niño Tabuan","Sapa","Sucad"
    ],
    "Bacolor": [
        "Balas","Cabambangan (Poblacion)","Calibutbut","Cabetican","Dela Paz",
        "Dolores","Duat","Macabacle","Magliman","Maliwalu","Mesalipit",
        "Parulog","Potrero","San Antonio","San Isidro","San Vicente","Santa Barbara",
        "Santa Ines","Santa Lucia","Santa Maria","Talba"
    ],
    "Candaba": [
        "Bahay Pare","Buas","Cuayan","Dalayap","Dulong Ilog","Gulap","Lanang",
        "Magumbali","Mandasig","Mangga","Mapaniqui","Pansumaloc","Paralaya",
        "Pasig","Pescadores","Pulung Gubat","San Agustin","San Isidro","San Luis",
        "San Nicolas","Santa Lucia","Santo Rosario","Talang","Tenejero"
    ],
    "Floridablanca": [
        "Anapul","Benedicto","Bodega","Cabanawan","Calantas","Dela Paz","Fortuna",
        "Gutad","Mawacat","Pabanlag","Paguiruan","Palmayo","Poblacion","San Antonio",
        "San Isidro","San Jose","San Nicolas","Santa Monica","Santo Rosario","Valdez"
    ],
    "Guagua": [
        "Ascom","Bancal","Betis","Jose Abad Santos","Lambac","Maquiapo","Natividad",
        "Pale-Pale","Pulongmasle","Rizal","San Agustin","San Antonio","San Isidro",
        "San Juan Bautista","San Matias","San Miguel","San Nicolas 1st","San Nicolas 2nd",
        "San Pablo","San Pedro","San Rafael","San Roque","San Vicente","Santo Cristo",
        "Santo Niño","Santo Rosario","Sapang Uwak"
    ],
    "Lubao": [
        "Bancal Pugad","Bancal Sinubli","Barangay 1 (Poblacion)","Barangay 2","Barangay 3",
        "Barangay 4","Barangay 5","Barangay 6","Calangain","Candelaria","Concepcion",
        "De La Paz","Don Ignacio Dimson","Prado Siongco","Prado Aranguren","Remedios",
        "San Agustin","San Antonio","San Isidro","San Jose Apunan","San Miguel","San Nicolas 1st",
        "San Nicolas 2nd","San Pablo 1st","San Pablo 2nd","San Pedro Palcarangan","San Roque Arbol",
        "Santa Barbara","Santa Cruz","Santa Lucia","Santa Monica","Santa Rita","Santo Domingo",
        "Santo Niño","Santo Tomas","Sapang Balas"
    ],
    "Macabebe": [
        "Batasan","Caduang Tete","Castuli","Consuelo","Dalayap","Mataguiti",
        "San Esteban","San Francisco","San Gabriel","San Isidro","San Jose",
        "San Juan","San Nicolas","San Rafael","San Roque","San Vicente",
        "Santa Cruz","Santa Maria","Santo Niño","Santo Rosario","Saplad David"
    ],
    "Magalang": [
        "Alauli","Camias","Dolores","Escaler","San Agustin","San Antonio","San Isidro",
        "San Jose","San Miguel","San Nicolas 1st","San Nicolas 2nd","San Pablo","San Pedro",
        "San Roque","Santa Cruz","Santa Maria","Santo Niño","Turu"
    ],
    "Masantol": [
        "Alauli","Bagang","Balibago","Bebe Anac","Bebe Matua","Bulacus","Cambasi",
        "Malauli","Nigui","Palimpe","Puti","San Agustin","San Isidro","San Nicolas",
        "San Pedro","Santa Lucia","Santo Niño","Saplad"
    ],
    "Mexico": [
        "Anao","Balas","Buenavista","Cawayan","Concepcion","Dolores","Eden",
        "Lagundi","Laug","Masamat","Malauli","Poblacion","Pampang","Parian",
        "Panipuan","Sabanilla","San Antonio","San Jose","San Juan","San Miguel",
        "San Nicolas","San Pablo","San Roque","Santa Cruz","Santa Maria",
        "Santo Rosario","Sucad","Tangle"
    ],
    "Minalin": [
        "Bulac","Dawe","Lourdes","Maniago","San Francisco 1st","San Francisco 2nd",
        "San Isidro","San Nicolas","San Pedro","Santa Catalina","Santa Cruz",
        "Santa Rita","Santo Domingo","Santo Rosario"
    ],
    "Porac": [
        "Babo Pangulo","Babo Sacan","Balubad","Camias","Dolores","Inararo","Jalung",
        "Mancatian","Manuali","Mitla Proper","Pias","Pio","Poblacion","Pulung Santol",
        "Salu","San Jose Mitla","Sapang Uwak","Siñura","Villa Maria"
    ],
    "San Luis": [
        "San Agustin","San Carlos","San Isidro","San Jose","San Juan","San Nicolas",
        "San Roque","Santa Catalina","Santa Cruz","Santa Lucia","Santa Monica",
        "Santo Niño","Santo Rosario"
    ],
    "San Simon": [
        "Concepcion","De La Paz","San Agustin","San Isidro","San Jose","San Juan",
        "San Miguel","San Nicolas","San Pablo Libutad","San Pablo Proper","San Pedro",
        "Santa Cruz","Santa Monica","Santo Niño"
    ],
    "Santa Ana": [
        "San Agustin","San Bartolome","San Isidro","San Joaquin","San Jose",
        "San Nicolas","San Pablo","San Pedro","Santa Lucia","Santa Maria",
        "Santo Rosario"
    ],
    "Santa Rita": [
        "Dila-Dila","San Agustin","San Basilio","San Isidro","San Jose",
        "San Juan","San Matias","San Roque","Santa Monica","Santo Niño"
    ],
    "Santo Tomas": [
        "Morlaco","Poblacion","San Bartolome","San Matias","San Vicente","Santo Rosario"
    ],
    "Sasmuan": [
        "Bebe Anac","Bebe Matua","Mabuanbuan","Malusac","San Antonio","San Nicolas 1st",
        "San Nicolas 2nd","Santa Lucia","Santa Monica","Santo Tomas","Sebitanan",
        "Sucad"
    ]
};

function populateBarangays(selectedCity, selectedBarangay) {
    const barangaySelect = document.getElementById('barangay-select');
    if (!barangaySelect) return;
    barangaySelect.innerHTML = '<option value=\"\">Select barangay</option>';
    const list = cityBarangays[selectedCity] || [];
    list.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b;
        opt.textContent = b;
        if (selectedBarangay === b) opt.selected = true;
        barangaySelect.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const citySelect = document.getElementById('city-select');
    const savedCity = "<?= htmlspecialchars($_POST['city'] ?? '') ?>";
    const savedBarangay = "<?= htmlspecialchars($_POST['barangay'] ?? '') ?>";

    if (citySelect) {
        if (savedCity) {
            citySelect.value = savedCity;
            populateBarangays(savedCity, savedBarangay);
        }
        citySelect.addEventListener('change', function () {
            populateBarangays(this.value, "");
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
