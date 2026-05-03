<?php
/**
 * Add Service modal — include only when the logged-in user is a provider
 * and $addServiceModalCategories is a non-empty array of category rows.
 */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider') {
    return;
}
if (empty($addServiceModalCategories) || !is_array($addServiceModalCategories)) {
    return;
}
?>
<style>
.add-service-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10050;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
}
.add-service-modal-overlay.is-open { display: flex; }
body.add-service-modal-open { overflow: hidden; }
.add-service-modal-dialog {
    position: relative;
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
    border: 1px solid #e7edf5;
    padding: 1.5rem 1.5rem 1.25rem;
    animation: addServiceModalIn 0.22s ease;
}
@keyframes addServiceModalIn {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.add-service-modal-dialog h2 {
    margin: 0 0 0.35rem;
    font-size: 1.25rem;
    color: #0f172a;
}
.add-service-modal-dialog .asm-sub {
    margin: 0 0 1.1rem;
    font-size: 0.9rem;
    color: #64748b;
}
.add-service-modal-close {
    position: absolute;
    top: 0.85rem;
    right: 0.85rem;
    width: 2.25rem;
    height: 2.25rem;
    border: none;
    background: #f1f5f9;
    color: #475569;
    border-radius: 10px;
    font-size: 1.35rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, color 0.15s ease;
}
.add-service-modal-close:hover { background: #e2e8f0; color: #0f172a; }
.add-service-modal-dialog .form-group { margin-bottom: 1rem; }
.add-service-modal-dialog .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.88rem; color: #334155; }
.add-service-modal-dialog select,
.add-service-modal-dialog input[type="number"] {
    width: 100%;
    padding: 0.65rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 0.95rem;
}
.add-service-modal-price-row { display: flex; gap: 0.75rem; }
.add-service-modal-price-row .form-group { flex: 1; margin-bottom: 0; }
.add-service-modal-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; justify-content: flex-end; margin-top: 1.25rem; }
.add-service-modal-error {
    display: none;
    margin-bottom: 0.75rem;
    padding: 0.55rem 0.7rem;
    border-radius: 10px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 0.85rem;
}
.add-service-modal-error.is-visible { display: block; }
</style>
<div id="add-service-modal-overlay" class="add-service-modal-overlay" aria-hidden="true">
    <div class="add-service-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="add-service-modal-title">
        <button type="button" class="add-service-modal-close" id="add-service-modal-close-x" aria-label="Close">&times;</button>
        <h2 id="add-service-modal-title">Add Service</h2>
        <p class="asm-sub">Choose a category and set your price range.</p>
        <div id="add-service-modal-error" class="add-service-modal-error" role="alert"></div>
        <form id="add-service-modal-form" novalidate>
            <div class="form-group">
                <label for="add-service-category">Category</label>
                <select id="add-service-category" name="category_id" required>
                    <?php foreach ($addServiceModalCategories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Price range (₱)</label>
                <div class="add-service-modal-price-row">
                    <div class="form-group">
                        <label for="add-service-price-min" style="font-weight:500;font-size:0.8rem;">Min</label>
                        <input type="number" id="add-service-price-min" name="price_min" min="0" step="0.01" required placeholder="Min">
                    </div>
                    <div class="form-group">
                        <label for="add-service-price-max" style="font-weight:500;font-size:0.8rem;">Max</label>
                        <input type="number" id="add-service-price-max" name="price_max" min="0" step="0.01" placeholder="Max (optional)">
                    </div>
                </div>
            </div>
            <div class="add-service-modal-actions">
                <button type="button" class="btn btn-ghost" id="add-service-modal-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary" id="add-service-modal-submit">Add Service</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('add-service-modal-overlay');
    if (!overlay) return;
    var dialog = overlay.querySelector('.add-service-modal-dialog');
    var form = document.getElementById('add-service-modal-form');
    var errEl = document.getElementById('add-service-modal-error');
    var submitBtn = document.getElementById('add-service-modal-submit');

    function showError(msg) {
        if (!errEl) return;
        errEl.textContent = msg || '';
        errEl.classList.toggle('is-visible', !!msg);
    }

    function openModal() {
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('add-service-modal-open');
        showError('');
        if (form) form.reset();
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('add-service-modal-open');
        showError('');
        if (submitBtn) submitBtn.disabled = false;
    }

    document.querySelectorAll('.js-open-add-service-modal').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openModal();
        });
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });
    document.getElementById('add-service-modal-close-x').addEventListener('click', closeModal);
    document.getElementById('add-service-modal-cancel').addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeModal();
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            showError('');
            var fd = new FormData(form);
            var min = parseFloat(String(fd.get('price_min') || ''));
            if (isNaN(min) || min < 0) {
                showError('Please enter a valid minimum price.');
                return;
            }
            var maxRaw = String(fd.get('price_max') || '').trim();
            if (maxRaw !== '') {
                var max = parseFloat(maxRaw);
                if (!isNaN(max) && max > 0 && max < min) {
                    showError('Maximum price must be greater than or equal to minimum.');
                    return;
                }
            }
            if (submitBtn) submitBtn.disabled = true;
            fetch('api/add_service.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        closeModal();
                        window.location.reload();
                        return;
                    }
                    showError(data.error || 'Could not add service.');
                    if (submitBtn) submitBtn.disabled = false;
                })
                .catch(function () {
                    showError('Network error. Please try again.');
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    }
})();
</script>
