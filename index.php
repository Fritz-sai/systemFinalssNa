<?php
$pageTitle = 'ServiceLink - Connecting You with Trusted Service Professionals';
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'assets/css/style.css';

?>

<style>
/* === NEW LANDING PAGE DESIGN === */
.landing-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.landing-hero {
    background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    padding: 6rem 2rem 4rem;
    overflow: hidden;
}

.hero-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #eef3fb;
    color: #3A86FF;
    padding: 0.5rem 1rem;
    border-radius: 99px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.1;
    color: #1a1a2e;
    margin-bottom: 1.5rem;
}

.hero-title .text-primary {
    color: #3A86FF;
}

.hero-subtitle {
    font-size: 1.15rem;
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 2.5rem;
    max-width: 90%;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 3rem;
}

.btn-large {
    padding: 1rem 1.75rem;
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.btn-outline {
    background: white;
    border: 1.5px solid #e2e8f0;
    color: #1a1a2e;
}
.btn-outline:hover {
    border-color: #3A86FF;
    color: #3A86FF;
}

.hero-social-proof {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.avatar-group {
    display: flex;
}
.avatar-group img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 3px solid white;
    margin-left: -12px;
}
.avatar-group img:first-child { margin-left: 0; }

.social-proof-text {
    display: flex;
    flex-direction: column;
}
.proof-count {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}
.proof-rating {
    font-size: 0.9rem;
    font-weight: 600;
}
.proof-rating .stars { color: #FFB703; }

/* Hero Right Side - Imagery & Floating Cards */
.hero-right {
    position: relative;
    display: flex;
    justify-content: center;
}

.hero-image-circle {
    width: 450px;
    height: 450px;
    background: #eef3fb;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    z-index: 1;
}

.hero-image-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.float-card {
    position: absolute;
    background: white;
    border-radius: 12px;
    padding: 0.85rem 1.25rem;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    z-index: 2;
    animation: float 6s ease-in-out infinite;
}

.float-verified { top: 10%; right: -5%; }
.float-booking { top: 35%; left: -10%; animation-delay: 1s; }
.float-secure { top: 20%; right: -15%; animation-delay: 2s; }
.float-rating { bottom: 20%; right: -5%; animation-delay: 3s; }

.float-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.float-icon.blue { background: #eef3fb; color: #3A86FF; }
.float-icon.green { background: #e6f7ec; color: #2ECC71; }
.float-icon svg { width: 18px; height: 18px; }

.float-card-content {
    display: flex;
    flex-direction: column;
}
.float-title { font-weight: 700; color: #1a1a2e; font-size: 1.1rem; }
.float-subtitle { font-size: 0.8rem; color: #64748b; }
.float-stars { color: #FFB703; font-size: 1.1rem; margin-top: 2px; }

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-15px); }
    100% { transform: translateY(0px); }
}

/* Features Row */
.landing-features {
    background: white;
    padding: 4rem 2rem;
    border-top: 1px solid #f1f5f9;
}
.features-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}
.feature-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}
.feature-icon {
    width: 48px;
    height: 48px;
    background: #eef3fb;
    color: #3A86FF;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.feature-text h3 {
    font-size: 1.05rem;
    margin-bottom: 0.4rem;
    color: #1a1a2e;
}
.feature-text p {
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.5;
}

/* Popular Services */
.landing-services, .landing-how {
    padding: 5rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 3rem;
}
.section-header h2 {
    font-size: 2.2rem;
    font-weight: 800;
    color: #1a1a2e;
}
.view-all-link {
    color: #3A86FF;
    font-weight: 600;
    text-decoration: none;
}
.view-all-link:hover { text-decoration: underline; }

.services-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}
.service-card {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.5rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}
.service-card:hover {
    border-color: #3A86FF;
    box-shadow: 0 10px 25px rgba(58, 134, 255, 0.1);
    transform: translateY(-3px);
}
.service-icon {
    width: 56px;
    height: 56px;
    background: #f8fafc;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.service-icon svg { width: 28px; height: 28px; }
.service-info h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 0.25rem;
}
.service-info p {
    font-size: 0.85rem;
    color: #64748b;
}

/* How It Works */
.steps-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
    position: relative;
}
.step-item {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    text-align: center;
    position: relative;
    border: 1px solid #e2e8f0;
}
.step-number {
    position: absolute;
    top: -15px;
    left: 20px;
    width: 32px;
    height: 32px;
    background: #3A86FF;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    border: 3px solid white;
}
.step-icon {
    width: 64px;
    height: 64px;
    background: #eef3fb;
    color: #3A86FF;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}
.step-icon svg { width: 32px; height: 32px; }
.step-text h3 {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 0.5rem;
}
.step-text p {
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.5;
}
.step-arrow {
    position: absolute;
    right: -24px;
    top: 50%;
    transform: translateY(-50%);
    color: #cbd5e1;
    z-index: 10;
}
.step-item:last-child .step-arrow { display: none; }

/* Responsive adjustments for Landing Page */
@media (max-width: 1024px) {
    .hero-container { grid-template-columns: 1fr; text-align: center; }
    .hero-buttons { justify-content: center; }
    .hero-social-proof { justify-content: center; }
    .features-container { grid-template-columns: repeat(2, 1fr); }
    .services-grid { grid-template-columns: repeat(2, 1fr); }
    .steps-container { grid-template-columns: repeat(2, 1fr); }
    .step-arrow { display: none; }
}

@media (max-width: 640px) {
    .hero-title { font-size: 2.5rem; }
    .hero-buttons { flex-direction: column; }
    .features-container, .services-grid, .steps-container { grid-template-columns: 1fr; }
    .hero-image-circle { width: 100%; height: auto; aspect-ratio: 1; }
    .float-card { display: none; }
}
</style>

<div class="landing-page">
    <!-- HERO SECTION -->
    <section class="landing-hero">
        <div class="hero-container">
            <div class="hero-left">
                <div class="hero-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Trusted. Verified. Professional.</span>
                </div>
                <h1 class="hero-title">Connecting You with <span class="text-primary">Trusted Service</span> Professionals</h1>
                <p class="hero-subtitle">ServiceLink makes it easy to find and book verified local professionals you can trust. Quality service, every time.</p>
                <div class="hero-buttons">
                    <a href="filter_results.php" class="btn btn-primary btn-large">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        Find Services
                    </a>
                    <a href="register.php" class="btn btn-outline btn-large">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Become a Provider
                    </a>
                </div>
                <div class="hero-social-proof">
                    <div class="avatar-group">
                        <img src="https://ui-avatars.com/api/?name=Jane+Doe&background=random" alt="Customer">
                        <img src="https://ui-avatars.com/api/?name=John+Smith&background=random" alt="Customer">
                        <img src="https://ui-avatars.com/api/?name=Alice+M&background=random" alt="Customer">
                    </div>
                    <div class="social-proof-text">
                        <span class="proof-count">Join 5,000+ satisfied customers</span>
                        <div class="proof-rating">
                            <span class="stars">★★★★★</span> <span class="rating-num">4.9/5 rating</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="hero-right">
                <div class="hero-image-wrapper">
                    <!-- Placeholder professional image -->
                    <div class="hero-image-circle">
                        <img src="assets/img/hero-professional.png" onerror="this.src='https://images.unsplash.com/photo-1621905251189-08b45d6a269e?q=80&w=800&auto=format&fit=crop'" alt="Professional Worker">
                    </div>
                    
                    <!-- Floating Cards -->
                    <div class="float-card float-verified">
                        <div class="float-card-content">
                            <span class="float-title">100%</span>
                            <span class="float-subtitle">Verified Providers</span>
                        </div>
                        <div class="float-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
                    </div>
                    
                    <div class="float-card float-booking">
                        <div class="float-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                        <div class="float-card-content">
                            <span class="float-title">Fast & Easy</span>
                            <span class="float-subtitle">Booking</span>
                        </div>
                    </div>
                    
                    <div class="float-card float-secure">
                        <div class="float-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div>
                        <div class="float-card-content">
                            <span class="float-title">Secure</span>
                            <span class="float-subtitle">Payments</span>
                        </div>
                    </div>
                    
                    <div class="float-card float-rating">
                        <div class="float-card-content">
                            <span class="float-title">4.9/5</span>
                            <span class="float-subtitle">Customer Rating</span>
                            <span class="float-stars">★★★★★</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES ROW -->
    <section class="landing-features">
        <div class="features-container">
            <div class="feature-item">
                <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg></div>
                <div class="feature-text">
                    <h3>Verified Professionals</h3>
                    <p>All providers are verified and background-checked</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
                <div class="feature-text">
                    <h3>Quality Service</h3>
                    <p>High-quality service you can depend on</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div>
                <div class="feature-text">
                    <h3>Secure & Safe</h3>
                    <p>Secure payments and your safety are our priority</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg></div>
                <div class="feature-text">
                    <h3>Support 24/7</h3>
                    <p>We're here to help anytime you need us</p>
                </div>
            </div>
        </div>
    </section>

    <!-- POPULAR SERVICES -->
    <section class="landing-services">
        <div class="section-header">
            <h2>Popular Services</h2>
            <a href="filter_results.php" class="view-all-link">View all services</a>
        </div>
        <div class="services-grid">
            <a href="filter_results.php?category=plumber" class="service-card">
                <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3A86FF" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg></div>
                <div class="service-info">
                    <h3>Plumbing</h3>
                    <p>Fix leaks, install fixtures, and more</p>
                </div>
            </a>
            <a href="filter_results.php?category=electrician" class="service-card">
                <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#FFB703" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></div>
                <div class="service-info">
                    <h3>Electrical</h3>
                    <p>Wiring, repairs, installations</p>
                </div>
            </a>
            <a href="filter_results.php?category=tutor" class="service-card">
                <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#2ECC71" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></div>
                <div class="service-info">
                    <h3>Tutoring</h3>
                    <p>Academic help for all subjects</p>
                </div>
            </a>
            <a href="filter_results.php?category=cleaner" class="service-card">
                <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#9B5DE5" stroke-width="2"><path d="M21.5 2v6h-19V2h19zM2.5 8v14h19V8"></path><path d="M12 12v6"></path></svg></div>
                <div class="service-info">
                    <h3>Cleaning</h3>
                    <p>Home & office cleaning services</p>
                </div>
            </a>
            <a href="filter_results.php?category=carpenter" class="service-card">
                <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#F4A261" stroke-width="2"><path d="M14.5 17.5L3 6"></path><path d="M16.5 15.5l5.5-5.5a2.828 2.828 0 1 0-4-4l-5.5 5.5"></path></svg></div>
                <div class="service-info">
                    <h3>Carpentry</h3>
                    <p>Furniture, repairs, woodwork</p>
                </div>
            </a>
            <a href="filter_results.php?category=appliance" class="service-card">
                <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#00B4D8" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><circle cx="12" cy="14" r="4"></circle><line x1="12" y1="6" x2="12.01" y2="6"></line></svg></div>
                <div class="service-info">
                    <h3>Appliance Repair</h3>
                    <p>Fix and maintain your appliances</p>
                </div>
            </a>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="landing-how">
        <div class="section-header">
            <h2>How It Works</h2>
        </div>
        <div class="steps-container">
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></div>
                <div class="step-text">
                    <h3>Find a Service</h3>
                    <p>Search for the service you need</p>
                </div>
                <div class="step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></div>
            </div>
            
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></div>
                <div class="step-text">
                    <h3>Choose a Provider</h3>
                    <p>Pick a verified provider that fits your needs</p>
                </div>
                <div class="step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></div>
            </div>
            
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                <div class="step-text">
                    <h3>Book & Relax</h3>
                    <p>Book easily and let the professional handle it</p>
                </div>
                <div class="step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></div>
            </div>
            
            <div class="step-item">
                <div class="step-number">4</div>
                <div class="step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div>
                <div class="step-text">
                    <h3>Rate & Review</h3>
                    <p>Share your experience to help others</p>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?>
