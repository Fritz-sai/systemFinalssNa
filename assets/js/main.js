/**
 * ServiceLink - Main JavaScript
 */

// Page transition helper
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('fade-in');
    
    // Intercept internal links for smooth page transition
    document.querySelectorAll('a[href]').forEach(link => {
        const href = link.getAttribute('href');
        if (href && !href.startsWith('#') && !href.startsWith('javascript:') && !href.startsWith('http') && !link.target && !link.hasAttribute('data-no-transition')) {
            link.addEventListener('click', (e) => {
                if (!link.target || link.target === '_self') {
                    e.preventDefault();
                    fadeOutAndNavigate(href);
                }
            });
        }
    });
});

function fadeOutAndNavigate(url) {
    document.body.classList.add('fade-out', 'page-transitioning');
    setTimeout(() => {
        window.location.href = url;
    }, 300);
}

function fadeInPage() {
    document.body.classList.remove('page-transitioning');
    document.body.classList.add('fade-in');
}

// Toast notifications
function showToast(message, type = 'info') {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Form validation
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validatePhone(phone) {
    return /^[0-9]{10,11}$/.test(phone.replace(/\s/g, ''));
}

// Loading spinner
function showLoading(container) {
    const spinner = document.createElement('div');
    spinner.className = 'spinner';
    spinner.id = 'loading-spinner';
    container.innerHTML = '';
    container.appendChild(spinner);
}

function hideLoading(container) {
    const spinner = document.getElementById('loading-spinner');
    if (spinner) spinner.remove();
}

// Search form submit
document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            const location = document.getElementById('search-location')?.value;
            const category = document.getElementById('search-category')?.value;
            if (!location && !category) {
                e.preventDefault();
                showToast('Please enter a location or select a category', 'error');
            }
        });
    }
});
