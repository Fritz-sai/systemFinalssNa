# ServiceLink

A platform for finding and booking verified local service providers.

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript (no frameworks)
- **Backend:** PHP
- **Database:** MySQL

## Setup

1. Ensure XAMPP (Apache + MySQL) is running.
2. Run the installer: Open `http://localhost/system/install.php` in your browser.
3. Delete `install.php` after setup for security.
4. Login as admin: `admin@servicelink.com` / `admin123`

## Structure

```
system/
├── api/           # API endpoints (chat, providers, etc.)
├── assets/
│   ├── css/       # Styles
│   └── js/        # Scripts
├── config/        # Database & app config
├── database/      # SQL schema
├── includes/      # Header, footer
├── uploads/       # User uploads (selfies, IDs)
├── index.php      # Homepage
├── register.php   # Registration
├── login.php      # Login
├── dashboard_customer.php
├── dashboard_provider.php
├── provider_profile.php
├── chat.php
├── filter_results.php
├── admin_panel.php
├── promote_service.php
└── book_service.php
```

## Features

- **Customers:** Search providers, filter by location/category, chat, book services
- **Providers:** Register with verification (email, phone OTP, selfie + ID), create listings, promote with paid ads
- **Admin:** Approve/reject provider verifications
- **Chat:** Real-time style messaging (AJAX polling)
- **Ads:** Sponsored listings for providers who pay
