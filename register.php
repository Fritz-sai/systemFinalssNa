<?php
$pageTitle = 'Create Account';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'dashboard_provider.php' : 'dashboard_customer.php'));
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

$error = '';
$success = '';
$role = $_SESSION['reg_role'] ?? '';
$pdo = getDBConnection();
$email_for_display = $_SESSION['reg_email'] ?? '';
$phone_for_display = $_SESSION['reg_phone'] ?? '';

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
                try {
                    $pdo->prepare('INSERT INTO users (email, username, password_hash, full_name, phone, role, city, barangay, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)')
                        ->execute([$email_for_display, $username, $hash, $full_name, $phone_for_display, $role, $city, $barangay]);
                } catch (PDOException $e) {
                    $pdo->prepare('INSERT INTO users (email, password_hash, full_name, phone, role, city, barangay, email_verified, phone_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)')
                        ->execute([$email_for_display, $hash, $full_name, $phone_for_display, $role, $city, $barangay]);
                }

                $userId = $pdo->lastInsertId();
                if ($role === 'provider') {
                    $pdo->prepare('INSERT INTO providers (user_id, city, barangay, verification_status) VALUES (?, ?, ?, "pending")')
                        ->execute([$userId, $city, $barangay]);
                    $_SESSION['user_city'] = $city;
                    $_SESSION['user_barangay'] = $barangay;
                } else {
                    $_SESSION['user_city'] = $city;
                    $_SESSION['user_barangay'] = $barangay;
                }

                unset($_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_role'], $_SESSION['reg_email_verified'], $_SESSION['reg_phone_verified'], $_SESSION['reg_email_code'], $_SESSION['reg_phone_code']);

                $_SESSION['user_id'] = $userId;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $full_name;
                if ($role === 'provider') {
                    $prov = $pdo->prepare('SELECT id FROM providers WHERE user_id = ?');
                    $prov->execute([$userId]);
                    $provRow = $prov->fetch();
                    $_SESSION['provider_id'] = $provRow ? $provRow['id'] : null;
                }

                header('Location: ' . ($role === 'provider' ? 'provider_add_service.php' : 'dashboard_customer.php'));
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
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success-message"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <?php if (empty($role)): ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="select_role">
                <p>Please choose account type</p>
                <label><input type="radio" name="role" value="customer" required> Customer</label><br>
                <label><input type="radio" name="role" value="provider"> Provider</label><br>
                <button type="submit" class="btn btn-primary">Continue</button>
            </form>
        <?php else: ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">

                <p style="margin-bottom:1rem; color:#6c757d;">Role: <strong><?= htmlspecialchars(ucfirst($role)) ?></strong></p>

                <div class="form-group">
                    <label>Email (verified)</label>
                    <input type="email" readonly value="<?= htmlspecialchars($email_for_display) ?>">
                </div>
                <div class="form-group">
                    <label>Phone (verified)</label>
                    <input type="tel" readonly value="<?= htmlspecialchars($phone_for_display) ?>">
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required minlength="3" placeholder="youruser123" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="6" placeholder="At least 6 characters">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required minlength="6" placeholder="Confirm password">
                </div>

                <?php if ($role === 'provider'): ?>
                    <div class="form-group">
                        <label>City / Municipality (Pampanga)</label>
                        <select name="city" id="city-select" required>
                            <option value="">Choose city / municipality</option>
                            <?php
                            $cities = ['Angeles City', 'City of San Fernando', 'Mabalacat City', 'Apalit', 'Arayat', 'Bacolor', 'Candaba', 'Floridablanca', 'Guagua', 'Lubao', 'Macabebe', 'Magalang', 'Masantol', 'Mexico', 'Minalin', 'Porac', 'San Luis', 'San Simon', 'Santa Ana', 'Santa Rita', 'Santo Tomas', 'Sasmuan'];
                            $selectedCity = $_POST['city'] ?? '';
                            foreach ($cities as $cityItem) : ?>
                                <option value="<?= htmlspecialchars($cityItem) ?>" <?= $selectedCity === $cityItem ? 'selected' : '' ?>><?= htmlspecialchars($cityItem) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Barangay</label>
                        <select name="barangay" id="barangay-select" required>
                            <option value="">Choose barangay</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($role === 'customer'): ?>
                        <div class="form-group">
                            <label>City / Municipality (Pampanga)</label>
                            <select name="city" id="city-select" required>
                                <option value="">Choose city / municipality</option>
                                <?php
                                $cities = ['Angeles City', 'City of San Fernando', 'Mabalacat City', 'Apalit', 'Arayat', 'Bacolor', 'Candaba', 'Floridablanca', 'Guagua', 'Lubao', 'Macabebe', 'Magalang', 'Masantol', 'Mexico', 'Minalin', 'Porac', 'San Luis', 'San Simon', 'Santa Ana', 'Santa Rita', 'Santo Tomas', 'Sasmuan'];
                                $selectedCity = $_POST['city'] ?? '';
                                foreach ($cities as $cityItem) : ?>
                                    <option value="<?= htmlspecialchars($cityItem) ?>" <?= $selectedCity === $cityItem ? 'selected' : '' ?>><?= htmlspecialchars($cityItem) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Barangay</label>
                            <select name="barangay" id="barangay-select" required>
                                <option value="">Choose barangay</option>
                            </select>
                        </div>
                    <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-side">Create Account</button>
            </form>
        <?php endif; ?>

        <p style="text-align:center; margin-top:1rem; color:var(--text-muted);">
            <a href="email_verification.php">Back to email verification</a> | <a href="login.php">Login</a>
        </p>
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
