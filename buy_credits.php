<?php
$pageTitle = 'Buy Credits';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

require_provider_documents();

$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'];

// Get current credits
$creditsStmt = $pdo->prepare("SELECT credits FROM providers WHERE id = ?");
$creditsStmt->execute([$providerId]);
$currentCredits = (int)($creditsStmt->fetchColumn() ?: 0);

$success = '';
$error = '';

// Handle purchase form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageIdx = (int)($_POST['package'] ?? -1);
    $packages = CREDIT_PACKAGES;

    if ($packageIdx < 0 || $packageIdx >= count($packages)) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid package.']);
            exit;
        }
        $error = 'Invalid package.';
    } else {
        $pkg = $packages[$packageIdx];
        $credits = (int)$pkg['credits'];
        $amount = (float)$pkg['price'];
        
        // Auto-generate reference number for seamless GCash simulation
        $reference = 'GC' . date('YmdHis') . rand(1000, 9999);

        $pdo->prepare("UPDATE providers SET credits = credits + ? WHERE id = ?")->execute([$credits, $providerId]);
        $pdo->prepare("INSERT INTO credit_purchases (provider_id, credits, amount, reference_no, status) VALUES (?, ?, ?, ?, 'completed')")
            ->execute([$providerId, $credits, $amount, $reference]);

        $currentCredits += $credits;
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'credits' => $credits,
                'amount' => $amount,
                'reference' => $reference,
                'date' => date('M d, Y h:i A')
            ]);
            exit;
        }

        $success = 'Credits added! Your new balance is ' . $currentCredits . ' credits.';
    }
}

require_once 'includes/header.php';
?>

<style>
    /* Responsive layout tweaks */
    .payment-layout {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 2rem;
        align-items: start;
    }
    @media (max-width: 768px) {
        .payment-layout {
            grid-template-columns: 1fr;
        }
    }
    
    .package-card.selected {
        border-color: #007bff !important;
        background: #f8fbff;
    }
    .package-card.selected .package-check {
        display: block !important;
    }
    .package-card:hover {
        border-color: #93c5fd !important;
    }

    input:focus {
        border-color: #007bff !important;
    }
    .input-wrap:focus-within {
        border-color: #007bff !important;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }
    
    @keyframes modalPop {
        0% { opacity: 0; transform: scale(0.9); }
        100% { opacity: 1; transform: scale(1); }
    }
    .modal-show {
        display: flex !important;
        animation: modalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
</style>

<section style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 class="section-title" style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1.8rem;">
            <svg style="width:32px;height:32px;color:#007bff;" fill="currentColor" viewBox="0 0 24 24"><path d="M21 4H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H3V6h18v12zm-9-2h9v-2h-9v2zm0-4h9v-2h-9v2zm0-4h9V6h-9v2zM4 6h6v10H4V6z"/></svg>
            Buy Credits
        </h1>
        <p style="color: var(--text-muted); font-size: 1.05rem;">Use credits to unlock customer contact info (<?= CREDITS_PER_UNLOCK ?> credits per unlock).</p>
    </div>

    <div class="payment-layout">
        <!-- Left Column -->
        <div>
            <!-- Balance Card -->
            <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, #007bff 0%, #00d2ff 100%); color: white; border-radius: 16px; box-shadow: 0 10px 20px rgba(0,123,255,0.2); display: flex; align-items: center; gap: 1rem;">
                <div style="background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 12px;">
                    <svg style="width:32px;height:32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; font-weight: 500;">Your balance</h3>
                    <div style="font-size: 1.8rem; font-weight: 700;" id="current-credits-display"><?= $currentCredits ?> credits</div>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" id="buy-form" onsubmit="handlePayment(event)">
                <input type="hidden" name="ajax" value="1">
                
                <div class="card" style="padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="width: 28px; height: 28px; background: #007bff; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">1</div>
                        <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">Choose a package</h3>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem;">
                        <?php 
                        $packagesJson = json_encode(CREDIT_PACKAGES);
                        foreach (CREDIT_PACKAGES as $i => $pkg): 
                            $isSelected = $i === 0;
                        ?>
                        <label class="package-card <?= $isSelected ? 'selected' : '' ?>" style="display: block; padding: 1.25rem 1rem; cursor: pointer; border: 2px solid #e2e8f0; border-radius: 12px; transition: all 0.2s; text-align: center; position: relative;" onclick="selectPackage(this, <?= $i ?>)">
                            <input type="radio" name="package" value="<?= $i ?>" <?= $isSelected ? 'checked' : '' ?> style="position: absolute; opacity: 0;">
                            <div class="package-check" style="position: absolute; top: 0.5rem; right: 0.5rem; color: #007bff; display: <?= $isSelected ? 'block' : 'none' ?>;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            </div>
                            <strong style="display: block; font-size: 1.1rem; margin-bottom: 0.25rem; color: #0f172a;"><?= (int)$pkg['credits'] ?> credits</strong>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">₱<?= number_format($pkg['price'], 2) ?></div>
                            <div style="font-size: 0.8rem; color: #007bff; margin-top: 0.5rem; background: #e6f2ff; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block;">₱<?= number_format($pkg['price'] / $pkg['credits'], 2) ?> / credit</div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card" style="padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <div style="width: 28px; height: 28px; background: #007bff; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">2</div>
                        <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">Pay with GCash</h3>
                    </div>
                    <p style="color: #64748b; margin-bottom: 1.5rem; margin-left: 2.5rem; font-size: 0.95rem;">Enter your GCash details to continue.</p>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                            <!-- GCash Icon logo representation -->
                            <div style="background: #0052cc; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-style: italic; font-size: 1.5rem; box-shadow: 0 4px 6px rgba(0,82,204,0.3);">G</div>
                            <div>
                                <div style="font-size: 0.9rem; color: #64748b;">Total to pay</div>
                                <div style="font-size: 1.8rem; font-weight: 700; color: #0f172a;" id="gcash-total">₱<?= number_format(CREDIT_PACKAGES[0]['price'], 2) ?></div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.9rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem; display: block;">Mobile Number</label>
                            <div class="input-wrap" style="display: flex; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: white; transition: all 0.2s;">
                                <div style="padding: 0.875rem 1rem; background: #f1f5f9; border-right: 1px solid #cbd5e1; color: #475569; font-weight: 600; font-size: 1.05rem;">+63</div>
                                <input type="text" id="mobile_number" placeholder="912 345 6789" required style="border: none; padding: 0.875rem 1rem; width: 100%; outline: none; font-size: 1.05rem; color: #0f172a; font-weight: 500;" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label style="font-size: 0.9rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem; display: block;">MPIN</label>
                            <div class="input-wrap" style="display: flex; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: white; transition: all 0.2s; position: relative;">
                                <input type="password" id="mpin" placeholder="Enter your 4-digit MPIN" required style="border: none; padding: 0.875rem 1rem; width: 100%; outline: none; font-size: 1.1rem; letter-spacing: 4px; color: #0f172a; font-weight: 600;" maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <button type="button" onclick="toggleMpin()" style="background: none; border: none; padding: 0 1rem; color: #64748b; cursor: pointer; display: flex; align-items: center; position: absolute; right: 0; top: 0; bottom: 0; transition: color 0.2s;" onmouseover="this.style.color='#007bff'" onmouseout="this.style.color='#64748b'">
                                    <svg id="eye-icon" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.5rem;">Your MPIN is used to securely authorize the payment.</div>
                        </div>

                        <button type="submit" id="pay-btn" style="width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #0052cc 0%, #007bff 100%); color: white; border: none; border-radius: 8px; font-size: 1.15rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,123,255,0.3);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 16px rgba(0,123,255,0.4)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 12px rgba(0,123,255,0.3)';">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <span>Pay ₱<span id="btn-total"><?= number_format(CREDIT_PACKAGES[0]['price'], 2) ?></span></span>
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.85rem; margin-top: 1.25rem; justify-content: center;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Your payment is secure and encrypted.
                    </div>
                </div>
            </form>
        </div>

        <!-- Right Column (Order Summary) -->
        <div>
            <div class="card" style="padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; top: 2rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <svg style="width:22px;height:22px;color:#007bff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a;">Order Summary</h3>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; color: #475569;">
                    <span>Selected Package</span>
                    <strong id="summary-credits" style="color: #0f172a;"><?= CREDIT_PACKAGES[0]['credits'] ?> credits</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.25rem; color: #475569;">
                    <span>Price</span>
                    <strong id="summary-price" style="color: #0f172a;">₱<?= number_format(CREDIT_PACKAGES[0]['price'], 2) ?></strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1.25rem; border-top: 1px dashed #cbd5e1;">
                    <span style="font-weight: 600; color: #0f172a; font-size: 1.1rem;">Total</span>
                    <strong id="summary-total" style="font-size: 1.75rem; color: #007bff; line-height: 1;">₱<?= number_format(CREDIT_PACKAGES[0]['price'], 2) ?></strong>
                </div>

                <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px; font-size: 0.85rem; color: #475569; display: flex; gap: 0.75rem;">
                    <svg style="width:20px;height:20px;color:#10b981;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <div>
                        <strong style="display: block; color: #0f172a; margin-bottom: 0.25rem;">Secure Payment</strong>
                        All transactions are protected with industry-standard encryption.
                    </div>
                </div>

                <div style="margin-top: 1rem; padding: 1rem; background: #eff6ff; border-radius: 8px; font-size: 0.85rem; color: #475569; display: flex; gap: 0.75rem;">
                    <svg style="width:20px;height:20px;color:#007bff;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <div>
                        <strong style="display: block; color: #0f172a; margin-bottom: 0.25rem;">Instant Delivery</strong>
                        Credits are added to your account instantly upon payment.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Success Modal Overlay -->
<div id="success-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 20px; padding: 2.5rem 2rem; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <div style="width: 70px; height: 70px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin: 0 auto 1.5rem; box-shadow: 0 0 0 10px #ecfdf5; position: relative;">
            <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
        </div>
        
        <h2 style="margin: 0 0 0.5rem; font-size: 1.5rem; color: #0f172a; font-weight: 700;">Payment Successful</h2>
        <p style="color: #64748b; margin-bottom: 2rem; font-size: 0.95rem;">Your credits have been added instantly.</p>

        <div style="background: #f8fafc; border-radius: 12px; padding: 1.5rem; text-align: left; margin-bottom: 2rem; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Reference No.</span>
                <strong style="color: #0f172a; font-family: monospace; font-size: 1rem;" id="modal-ref">...</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Amount Paid</span>
                <strong style="color: #0f172a;" id="modal-amount">...</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Credits Added</span>
                <strong style="color: #007bff; font-size: 1.05rem;" id="modal-credits">...</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Payment Method</span>
                <strong style="color: #0f172a; display: flex; align-items: center; gap: 0.35rem;">
                    <span style="background: #0052cc; color: white; font-size: 0.65rem; padding: 2px 5px; border-radius: 4px; font-weight: bold; font-style: italic;">G</span>
                    GCash
                </strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                <span style="color: #64748b;">Date & Time</span>
                <strong style="color: #0f172a;" id="modal-date">...</strong>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <a href="provider_profile.php?id=<?= $providerId ?>" class="btn" style="background: #007bff; color: white; padding: 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; display: block; box-shadow: 0 4px 6px rgba(0,123,255,0.2);">Done / Back to Dashboard</a>
            <button onclick="window.print()" class="btn" style="background: white; color: #0f172a; border: 1px solid #cbd5e1; padding: 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Download Receipt
            </button>
        </div>
    </div>
</div>

<script>
const packages = <?= $packagesJson ?>;

function selectPackage(element, index) {
    // Update visual selection
    document.querySelectorAll('.package-card').forEach(card => {
        card.classList.remove('selected');
        card.querySelector('.package-check').style.display = 'none';
    });
    element.classList.add('selected');
    element.querySelector('.package-check').style.display = 'block';

    // Update prices and summary
    const pkg = packages[index];
    const formattedPrice = parseFloat(pkg.price).toFixed(2);
    
    document.getElementById('gcash-total').innerText = '₱' + formattedPrice;
    document.getElementById('btn-total').innerText = formattedPrice;
    
    document.getElementById('summary-credits').innerText = pkg.credits + ' credits';
    document.getElementById('summary-price').innerText = '₱' + formattedPrice;
    document.getElementById('summary-total').innerText = '₱' + formattedPrice;
}

function toggleMpin() {
    const input = document.getElementById('mpin');
    const icon = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
    }
}

async function handlePayment(e) {
    e.preventDefault();
    
    const mobile = document.getElementById('mobile_number').value;
    const mpin = document.getElementById('mpin').value;

    // Basic inline validation
    if (mobile.length !== 10) {
        alert('Please enter a valid 10-digit mobile number.');
        return;
    }
    if (mpin.length !== 4) {
        alert('Please enter your 4-digit MPIN.');
        return;
    }

    const btn = document.getElementById('pay-btn');
    const originalText = btn.innerHTML;
    
    // Loading state
    btn.innerHTML = '<svg style="width:20px;height:20px;animation:spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Processing...';
    btn.disabled = true;
    btn.style.opacity = '0.8';

    try {
        const formData = new FormData(e.target);
        
        const response = await fetch('buy_credits.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Populate modal
            document.getElementById('modal-ref').innerText = result.reference;
            document.getElementById('modal-amount').innerText = '₱' + parseFloat(result.amount).toFixed(2);
            document.getElementById('modal-credits').innerText = result.credits;
            document.getElementById('modal-date').innerText = result.date;
            
            // Show success modal
            const modal = document.getElementById('success-modal');
            modal.style.display = 'flex';
            
            // Trigger reflow for animation
            void modal.offsetWidth;
            modal.classList.add('modal-show');
        } else {
            alert(result.error || 'Payment failed. Please try again.');
            btn.innerHTML = originalText;
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    } catch (error) {
        alert('An error occurred during payment. Please try again.');
        btn.innerHTML = originalText;
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}
</script>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<?php require_once 'includes/footer.php'; ?>
