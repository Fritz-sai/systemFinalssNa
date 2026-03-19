<?php
$pageTitle = 'Providers';
include __DIR__ . '/includes/header.php';

// Sample data — replace with real DB query (e.g. get_providers.php)
$providers = [
    [
        'id' => 1,
        'name' => 'Joseph Rannson',
        'avatar' => 'https://i.pravatar.cc/180?img=12',
        'title' => 'Handyman',
        'bio' => 'Experienced handyman offering home repairs and installations.',
        'service' => 12,
        'location' => 'Porac, Siñura',
        'rate' => 4.5,
        'sponsored' => true,
        'face_verified' => true,
    ],
    [
        'id' => 2,
        'name' => 'Erik Jonson',
        'avatar' => 'https://i.pravatar.cc/180?img=32',
        'title' => 'Plumber',
        'bio' => 'Professional plumbing services and emergency repairs.',
        'service' => 8,
        'location' => 'Angeles City, Cutcut',
        'rate' => 4.5,
    ],
    [
        'id' => 3,
        'name' => 'Melanie Palmer',
        'avatar' => 'https://i.pravatar.cc/180?img=45',
        'title' => 'Photographer',
        'bio' => 'Portrait and event photographer available for bookings.',
        'service' => 5,
        'location' => 'Makati, Poblacion',
        'rate' => 4.7,
    ],
    [
        'id' => 4,
        'name' => 'Josh Gordon',
        'avatar' => 'https://i.pravatar.cc/180?img=66',
        'title' => 'Electrician',
        'bio' => 'Licensed electrician for residential and commercial jobs.',
        'service' => 10,
        'location' => 'Quezon City, Project 4',
        'rate' => 4.6,
    ],
];
?>
<section>
    <div class="section-title">Providers</div>
    <div class="provider-grid">
        <?php foreach ($providers as $provider): ?>
            <?php include __DIR__ . '/includes/provider_card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php';
