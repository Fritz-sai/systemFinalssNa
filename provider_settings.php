<?php
$pageTitle = 'Provider Settings';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'];
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT p.*, u.full_name, u.email, u.phone
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ? AND u.id = ?
");
$stmt->execute([$providerId, $userId]);
$provider = $stmt->fetch();

if (!$provider) {
    header('Location: dashboard_provider.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $profileImagePath = $provider['profile_image_path'] ?? null;

    // Optional profile picture upload
    if (!empty($_FILES['profile_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $error = 'Profile picture must be JPG, PNG, or WEBP.';
        } else {
            $fileName = $providerId . '_' . time() . '_profile.' . $ext;
            $dest = 'uploads/profiles/' . $fileName;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                $profileImagePath = $dest;
            } else {
                $error = 'Failed to upload profile picture.';
            }
        }
    }

    if ($fullName === '' || $phone === '' || $city === '' || $barangay === '') {
        $error = 'Full name, phone, city, and barangay are required.';
    } else {
        $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")
            ->execute([$fullName, $phone, $userId]);

        $pdo->prepare("UPDATE providers SET city = ?, barangay = ?, profile_image_path = ? WHERE id = ?")
            ->execute([$city, $barangay, $profileImagePath, $providerId]);

        $_SESSION['full_name'] = $fullName;

        $success = 'Profile updated successfully.';

        // Refresh data
        $stmt->execute([$providerId, $userId]);
        $provider = $stmt->fetch();
    }
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 720px; margin: 0 auto;">
    <h1 class="section-title">Provider Profile Settings</h1>

    <?php if ($success): ?>
        <div class="card" style="padding: 1rem; border-left: 4px solid #2ECC71; margin-bottom: 1rem;">
            <strong style="color:#2ECC71;"><?= htmlspecialchars($success) ?></strong>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color:#e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="card" style="padding: 2rem;">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($provider['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Email (cannot be changed)</label>
                <input type="email" value="<?= htmlspecialchars($provider['email']) ?>" disabled>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($provider['phone']) ?>" required>
            </div>

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
                    $selectedCity = $provider['city'] ?? '';
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

            <div class="form-group">
                <label>Profile Picture</label>
                <?php if (!empty($provider['profile_image_path'])): ?>
                    <div style="margin-bottom: 0.75rem;">
                        <img src="<?= htmlspecialchars($provider['profile_image_path']) ?>" alt="Profile picture" style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);">
                    </div>
                <?php endif; ?>
                <input type="file" name="profile_image" accept="image/*">
                <small style="color: var(--text-muted);">Optional. This will be shown to customers.</small>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="dashboard_provider.php" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
        </form>
    </div>
</section>
<script>
// Pampanga city -> barangays (same mapping as register)
const cityBarangays = <?= json_encode([
    "Angeles City" => [
        "Amsic","Anunas","Balibago","Capaya","Cuayan","Cutcut","Cutud","Lourdes North West",
        "Lourdes Sur","Lourdes Sur East","Malabañas","Margot","Mining","Ninoy Aquino",
        "Pampang","Pandan","Pulungbulu","Pulung Cacutud","Pulung Maragul","Salmac",
        "San Jose","San Nicolas","Santa Teresita","Santo Cristo","Santo Domingo",
        "Santo Rosario","Sapangbato","Tabun"
    ],
    "City of San Fernando" => [
        "Alasas","Baliti","Bulaon","Calulut","Dela Paz Norte","Dela Paz Sur","Del Carmen",
        "Del Pilar","Del Rosario","Dolores","Juliana","Lara","Lourdes","Magliman",
        "Maimpis","Malino","Malpitic","Pandaras","Panipuan","Quebiawan","Saguin",
        "San Agustin","San Felipe","San Isidro","San Jose","San Juan","San Nicolas",
        "San Pedro","Santa Lucia","Santa Teresita","Santo Niño","Sindalan","Sto. Rosario (Poblacion)"
    ],
    "Mabalacat City" => [
        "Atlu-Bola","Bical","Bundagul","Cacutud","Camachiles","Dapdap","Dolores",
        "Dau","Lakandula","Mabiga","Macapagal Village","Mamatitang","Mangalit",
        "Marcos Village","Mawaque","Paralaya","Poblacion","San Francisco","San Joaquin",
        "San Jose","San Vicente","Santa Ines","Santa Maria","Santo Rosario","Sucad"
    ],
    "Apalit" => [
        "Balucuc","Calantipe","Cansinala","Capalangan","Colgante","Paligui",
        "Sampaloc","San Juan","San Vicente","Sucad","Sulipan","Tabuyuc"
    ],
    "Arayat" => [
        "Arenas","Baliti","Batasan","Candating","Camba","Cupang","Gatiawin",
        "Guemasan","Kaledian","La Paz","Lacmit","Mangga-Cacutud","Paralaya",
        "Plazang Luma","San Agustin Norte","San Agustin Sur","San Antonio",
        "San Juan Bano","San Mateo","Santo Niño Tabuan","Sapa","Sucad"
    ],
    "Bacolor" => [
        "Balas","Cabambangan (Poblacion)","Calibutbut","Cabetican","Dela Paz",
        "Dolores","Duat","Macabacle","Magliman","Maliwalu","Mesalipit",
        "Parulog","Potrero","San Antonio","San Isidro","San Vicente","Santa Barbara",
        "Santa Ines","Santa Lucia","Santa Maria","Talba"
    ],
    "Candaba" => [
        "Bahay Pare","Buas","Cuayan","Dalayap","Dulong Ilog","Gulap","Lanang",
        "Magumbali","Mandasig","Mangga","Mapaniqui","Pansumaloc","Paralaya",
        "Pasig","Pescadores","Pulung Gubat","San Agustin","San Isidro","San Luis",
        "San Nicolas","Santa Lucia","Santo Rosario","Talang","Tenejero"
    ],
    "Floridablanca" => [
        "Anapul","Benedicto","Bodega","Cabanawan","Calantas","Dela Paz","Fortuna",
        "Gutad","Mawacat","Pabanlag","Paguiruan","Palmayo","Poblacion","San Antonio",
        "San Isidro","San Jose","San Nicolas","Santa Monica","Santo Rosario","Valdez"
    ],
    "Guagua" => [
        "Ascom","Bancal","Betis","Jose Abad Santos","Lambac","Maquiapo","Natividad",
        "Pale-Pale","Pulongmasle","Rizal","San Agustin","San Antonio","San Isidro",
        "San Juan Bautista","San Matias","San Miguel","San Nicolas 1st","San Nicolas 2nd",
        "San Pablo","San Pedro","San Rafael","San Roque","San Vicente","Santo Cristo",
        "Santo Niño","Santo Rosario","Sapang Uwak"
    ],
    "Lubao" => [
        "Bancal Pugad","Bancal Sinubli","Barangay 1 (Poblacion)","Barangay 2","Barangay 3",
        "Barangay 4","Barangay 5","Barangay 6","Calangain","Candelaria","Concepcion",
        "De La Paz","Don Ignacio Dimson","Prado Siongco","Prado Aranguren","Remedios",
        "San Agustin","San Antonio","San Isidro","San Jose Apunan","San Miguel","San Nicolas 1st",
        "San Nicolas 2nd","San Pablo 1st","San Pablo 2nd","San Pedro Palcarangan","San Roque Arbol",
        "Santa Barbara","Santa Cruz","Santa Lucia","Santa Monica","Santa Rita","Santo Domingo",
        "Santo Niño","Santo Tomas","Sapang Balas"
    ],
    "Macabebe" => [
        "Batasan","Caduang Tete","Castuli","Consuelo","Dalayap","Mataguiti",
        "San Esteban","San Francisco","San Gabriel","San Isidro","San Jose",
        "San Juan","San Nicolas","San Rafael","San Roque","San Vicente",
        "Santa Cruz","Santa Maria","Santo Niño","Santo Rosario","Saplad David"
    ],
    "Magalang" => [
        "Alauli","Camias","Dolores","Escaler","San Agustin","San Antonio","San Isidro",
        "San Jose","San Miguel","San Nicolas 1st","San Nicolas 2nd","San Pablo","San Pedro",
        "San Roque","Santa Cruz","Santa Maria","Santo Niño","Turu"
    ],
    "Masantol" => [
        "Alauli","Bagang","Balibago","Bebe Anac","Bebe Matua","Bulacus","Cambasi",
        "Malauli","Nigui","Palimpe","Puti","San Agustin","San Isidro","San Nicolas",
        "San Pedro","Santa Lucia","Santo Niño","Saplad"
    ],
    "Mexico" => [
        "Anao","Balas","Buenavista","Cawayan","Concepcion","Dolores","Eden",
        "Lagundi","Laug","Masamat","Malauli","Poblacion","Pampang","Parian",
        "Panipuan","Sabanilla","San Antonio","San Jose","San Juan","San Miguel",
        "San Nicolas","San Pablo","San Roque","Santa Cruz","Santa Maria",
        "Santo Rosario","Sucad","Tangle"
    ],
    "Minalin" => [
        "Bulac","Dawe","Lourdes","Maniago","San Francisco 1st","San Francisco 2nd",
        "San Isidro","San Nicolas","San Pedro","Santa Catalina","Santa Cruz",
        "Santa Rita","Santo Domingo","Santo Rosario"
    ],
    "Porac" => [
        "Babo Pangulo","Babo Sacan","Balubad","Camias","Dolores","Inararo","Jalung",
        "Mancatian","Manuali","Mitla Proper","Pias","Pio","Poblacion","Pulung Santol",
        "Salu","San Jose Mitla","Sapang Uwak","Siñura","Villa Maria"
    ],
    "San Luis" => [
        "San Agustin","San Carlos","San Isidro","San Jose","San Juan","San Nicolas",
        "San Roque","Santa Catalina","Santa Cruz","Santa Lucia","Santa Monica",
        "Santo Niño","Santo Rosario"
    ],
    "San Simon" => [
        "Concepcion","De La Paz","San Agustin","San Isidro","San Jose","San Juan",
        "San Miguel","San Nicolas","San Pablo Libutad","San Pablo Proper","San Pedro",
        "Santa Cruz","Santa Monica","Santo Niño"
    ],
    "Santa Ana" => [
        "San Agustin","San Bartolome","San Isidro","San Joaquin","San Jose",
        "San Nicolas","San Pablo","San Pedro","Santa Lucia","Santa Maria",
        "Santo Rosario"
    ],
    "Santa Rita" => [
        "Dila-Dila","San Agustin","San Basilio","San Isidro","San Jose",
        "San Juan","San Matias","San Roque","Santa Monica","Santo Niño"
    ],
    "Santo Tomas" => [
        "Morlaco","Poblacion","San Bartolome","San Matias","San Vicente","Santo Rosario"
    ],
    "Sasmuan" => [
        "Bebe Anac","Bebe Matua","Mabuanbuan","Malusac","San Antonio","San Nicolas 1st",
        "San Nicolas 2nd","Santa Lucia","Santa Monica","Santo Tomas","Sebitanan",
        "Sucad"
    ]
], JSON_UNESCAPED_UNICODE) ?>;

function populateBarangays(selectedCity, selectedBarangay) {
    const barangaySelect = document.getElementById('barangay-select');
    if (!barangaySelect) return;
    barangaySelect.innerHTML = '<option value="">Select barangay</option>';
    const list = cityBarangays[selectedCity] || [];
    list.forEach(function (b) {
        const opt = document.createElement('option');
        opt.value = b;
        opt.textContent = b;
        if (selectedBarangay === b) opt.selected = true;
        barangaySelect.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const citySelect = document.getElementById('city-select');
    const savedCity = <?= json_encode($provider['city'] ?? '') ?>;
    const savedBarangay = <?= json_encode($provider['barangay'] ?? '') ?>;

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

