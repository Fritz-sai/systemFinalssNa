<?php
/**
 * Provider Diagnostic Tool
 * Check why a provider isn't showing up in results
 */
require_once 'config/config.php';
session_start();

// Only allow admin or the provider themselves
$adminOnly = true; // Set to false temporarily for testing

$searchEmail = trim($_GET['email'] ?? $_POST['email'] ?? '');
$searchName = trim($_GET['name'] ?? $_POST['name'] ?? '');

$pdo = getDBConnection();
$results = [];
$errors = [];

if ($searchEmail || $searchName) {
    try {
        // Find user
        $sql = "SELECT * FROM users WHERE email = ? OR full_name LIKE ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$searchEmail, "%{$searchName}%"]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = "❌ No user found with that email or name";
        } else {
            $results['user'] = $user;
            
            // Check if they have a provider account
            $provStmt = $pdo->prepare("SELECT * FROM providers WHERE user_id = ?");
            $provStmt->execute([$user['id']]);
            $provider = $provStmt->fetch();

            if (!$provider) {
                $errors[] = "❌ User exists but has NO provider record";
            } else {
                $results['provider'] = $provider;

                // Get their services
                $svcStmt = $pdo->prepare(
                    "SELECT s.*, c.name as category_name FROM services s 
                     LEFT JOIN service_categories c ON s.category_id = c.id 
                     WHERE s.provider_id = ?"
                );
                $svcStmt->execute([$provider['id']]);
                $services = $svcStmt->fetchAll();
                $results['services'] = $services;

                // Check verification status
                if ($provider['verification_status'] !== 'approved') {
                    $errors[] = "⚠️ Provider verification_status = '{$provider['verification_status']}' (must be 'approved')";
                }

                if (!$provider['face_verified']) {
                    $errors[] = "⚠️ Provider face_verified = 0 (not face verified)";
                }

                if (empty($services)) {
                    $errors[] = "⚠️ Provider has NO SERVICES listed (won't show in search without services)";
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
    }
}

// Only show results if authorized
$canView = true; // In production, check if user is admin or the provider
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        form { margin-bottom: 20px; }
        input { padding: 8px; width: 300px; margin-right: 10px; }
        button { padding: 8px 20px; background: #3A86FF; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #2563eb; }
        .error { color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { color: #388e3c; background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info-box { background: #f0f4ff; border-left: 4px solid #3A86FF; padding: 12px; margin: 10px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .status-approved { color: #388e3c; }
        .status-pending { color: #f57c00; }
        .status-rejected { color: #d32f2f; }
        h2 { color: #333; margin-top: 20px; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { padding: 8px; margin: 5px 0; border-radius: 4px; }
        .checklist li.pass { background: #e8f5e9; color: #388e3c; }
        .checklist li.fail { background: #ffebee; color: #d32f2f; }
    </style>
</head>
<body>
<div class="container">
    <h1>📋 Provider Diagnostic Tool</h1>
    
    <form method="GET">
        <div style="margin-bottom: 10px;">
            <label>Search by Email:</label><br>
            <input type="text" name="email" placeholder="Email address" value="<?= htmlspecialchars($searchEmail) ?>">
        </div>
        <div style="margin-bottom: 10px;">
            <label>OR Search by Name:</label><br>
            <input type="text" name="name" placeholder="Full name" value="<?= htmlspecialchars($searchName) ?>">
        </div>
        <button type="submit">Search</button>
    </form>

    <?php if ($errors): ?>
        <div>
            <?php foreach ($errors as $error): ?>
                <div class="error"><?= $error ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($results['user'])): ?>
        <h2>✅ User Found</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>ID</td>
                <td><?= $results['user']['id'] ?></td>
            </tr>
            <tr>
                <td>Email</td>
                <td><?= htmlspecialchars($results['user']['email']) ?></td>
            </tr>
            <tr>
                <td>Full Name</td>
                <td><?= htmlspecialchars($results['user']['full_name']) ?></td>
            </tr>
            <tr>
                <td>Role</td>
                <td><?= htmlspecialchars($results['user']['role']) ?></td>
            </tr>
            <tr>
                <td>Email Verified</td>
                <td><?= $results['user']['email_verified'] ? '✅ Yes' : '❌ No' ?></td>
            </tr>
            <tr>
                <td>Phone Verified</td>
                <td><?= $results['user']['phone_verified'] ? '✅ Yes' : '❌ No' ?></td>
            </tr>
            <tr>
                <td>Created</td>
                <td><?= $results['user']['created_at'] ?></td>
            </tr>
        </table>

        <?php if (!empty($results['provider'])): ?>
            <h2>✅ Provider Account Found</h2>
            
            <ul class="checklist">
                <li class="<?= $results['provider']['verification_status'] === 'approved' ? 'pass' : 'fail' ?>">
                    <strong>Verification Status:</strong> 
                    <span class="status-<?= $results['provider']['verification_status'] ?>">
                        <?= htmlspecialchars($results['provider']['verification_status']) ?>
                    </span>
                </li>
                <li class="<?= $results['provider']['face_verified'] ? 'pass' : 'fail' ?>">
                    <strong>Face Verified:</strong> 
                    <?= $results['provider']['face_verified'] ? '✅ Yes' : '❌ No' ?>
                </li>
                <li class="<?= empty($results['services']) ? 'fail' : 'pass' ?>">
                    <strong>Services Listed:</strong> 
                    <?= count($results['services']) ?> service(s)
                </li>
            </ul>

            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Provider ID</td>
                    <td><?= $results['provider']['id'] ?></td>
                </tr>
                <tr>
                    <td>City</td>
                    <td><?= htmlspecialchars($results['provider']['city']) ?></td>
                </tr>
                <tr>
                    <td>Barangay</td>
                    <td><?= htmlspecialchars($results['provider']['barangay']) ?></td>
                </tr>
                <tr>
                    <td>Bio</td>
                    <td><?= htmlspecialchars($results['provider']['bio'] ?? '(not set)') ?></td>
                </tr>
                <tr>
                    <td>Face Verification Rejected</td>
                    <td><?= $results['provider']['face_verification_rejected'] ? 'Yes' : 'No' ?></td>
                </tr>
                <tr>
                    <td>Profile Image</td>
                    <td><?= $results['provider']['profile_image_path'] ? 'Yes' : 'No' ?></td>
                </tr>
                <tr>
                    <td>Credits</td>
                    <td><?= $results['provider']['credits'] ?? 0 ?></td>
                </tr>
                <tr>
                    <td>Created</td>
                    <td><?= $results['provider']['created_at'] ?></td>
                </tr>
            </table>

            <?php if (!empty($results['services'])): ?>
                <h2>📋 Services</h2>
                <table>
                    <tr>
                        <th>Service</th>
                        <th>Category</th>
                        <th>Price Range</th>
                        <th>Created</th>
                    </tr>
                    <?php foreach ($results['services'] as $svc): ?>
                        <tr>
                            <td><?= htmlspecialchars($svc['title']) ?></td>
                            <td><?= htmlspecialchars($svc['category_name'] ?? 'Unknown') ?></td>
                            <td>₱<?= $svc['price_min'] ?> - ₱<?= $svc['price_max'] ?></td>
                            <td><?= $svc['created_at'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div class="error">⚠️ No services found. Provider needs to add services to be visible in search results.</div>
            <?php endif; ?>

            <?php if ($results['provider']['verification_status'] !== 'approved'): ?>
                <div class="info-box" style="border-left-color: #f57c00;">
                    <strong>⚠️ Action Required:</strong><br>
                    Run this SQL to approve the provider:
                    <pre style="background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;">
UPDATE providers 
SET verification_status = 'approved' 
WHERE id = <?= $results['provider']['id'] ?>;
                    </pre>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="error">❌ No provider account found. User needs to register as a provider.</div>
        <?php endif; ?>

    <?php elseif ($searchEmail || $searchName): ?>
        <div class="error">No results found. Try a different search term.</div>
    <?php endif; ?>

    <hr style="margin: 30px 0;">
    <h3>Why providers might not show:</h3>
    <ul>
        <li>❌ User doesn't have a provider account</li>
        <li>❌ Provider verification_status ≠ 'approved'</li>
        <li>❌ Provider has no services listed</li>
        <li>❌ Provider city/barangay doesn't match search filters</li>
        <li>❌ No bookings with ratings (if filtering by rating)</li>
    </ul>

    <p style="margin-top: 30px; font-size: 0.9em; color: #666;">
        <a href="filter_results.php?debug_providers=1">View all approved providers with debug info</a>
    </p>
</div>
</body>
</html>