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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'customer';

    if (empty($email) || empty($password) || empty($full_name) || empty($phone)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            if ($role === 'provider') {
                $city = trim($_POST['city'] ?? '');
                $barangay = trim($_POST['barangay'] ?? '');
                if (empty($city) || empty($barangay)) {
                    $error = 'City and Barangay are required for providers.';
                }
            }

            if (empty($error)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone, role) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$email, $hash, $full_name, $phone, $role]);

                $userId = $pdo->lastInsertId();

                if ($role === 'provider') {
                    $city = trim($_POST['city'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $pdo->prepare("INSERT INTO providers (user_id, city, barangay) VALUES (?, ?, ?)")
                        ->execute([$userId, $city, $barangay]);
                }
                // Email verification token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $pdo->prepare("INSERT INTO verifications (user_id, type, token, expires_at) VALUES (?, 'email', ?, ?)")
                    ->execute([$userId, $token, $expires]);
                $verifyLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/verify_email.php?token=$token";
                @mail($email, 'Verify your ServiceLink account', "Click to verify: $verifyLink");

                $_SESSION['user_id'] = $userId;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $full_name;
                if ($role === 'provider') {
                    $prov = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
                    $prov->execute([$userId]);
                    $provRow = $prov->fetch();
                    $_SESSION['provider_id'] = $provRow ? $provRow['id'] : null;
                }
                header('Location: ' . ($role === 'provider' ? 'verify_phone.php' : 'dashboard_customer.php'));
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

        <div class="auth-tabs">
            <button type="button" class="<?= $role === 'customer' ? 'active' : '' ?>" onclick="switchRole('customer')">Customer</button>
            <button type="button" class="<?= $role === 'provider' ? 'active' : '' ?>" onclick="switchRole('provider')">Provider</button>
        </div>

        <form method="POST" id="register-form">
            <input type="hidden" name="role" id="form-role" value="<?= htmlspecialchars($role) ?>">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" required placeholder="09xxxxxxxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="6">
            </div>

            <div id="provider-fields" style="<?= $role === 'provider' ? '' : 'display:none' ?>">
                <div class="form-group">
                    <label>City / Municipality (Pampanga)</label>
                    <select name="city" id="city-select">
                        <option value="">Select city / municipality</option>
                        <?php
                        $cities = [
                            'Angeles City',
                            'City of San Fernando',
                            'Mabalacat City',
                            'Apalit',
                            'Arayat',
                            'Bacolor',
                            'Candaba',
                            'Floridablanca',
                            'Guagua',
                            'Lubao',
                            'Macabebe',
                            'Magalang',
                            'Masantol',
                            'Mexico',
                            'Minalin',
                            'Porac',
                            'San Luis',
                            'San Simon',
                            'Santa Ana',
                            'Santa Rita',
                            'Santo Tomas',
                            'Sasmuan'
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
                <p style="font-size: 0.9rem; color: var(--text-muted);">Face verification is optional. Verify later in your dashboard to get the <strong>Verified</strong> badge.</p>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Register</button>
        </form>
        <p style="text-align:center; margin-top:1rem; color:var(--text-muted);">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</div>
<script>
function switchRole(r) {
    document.getElementById('form-role').value = r;
    document.getElementById('provider-fields').style.display = r === 'provider' ? 'block' : 'none';
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
