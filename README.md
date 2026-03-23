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

## SMTP / Email Setup

To send verification codes to Gmail reliably, configure SMTP and (optionally) install PHPMailer.

1. Install PHPMailer via Composer (recommended):

```bash
composer require phpmailer/phpmailer
```

2. In `config/config.php` update the mail settings near the top of the file:

- Set `SMTP_ENABLED` to `true`.
- Set `SMTP_HOST` to `smtp.gmail.com` and `SMTP_PORT` to `587`.
- Set `SMTP_USER` to your Gmail address and `SMTP_PASS` to a Gmail App Password.
- Ensure `SMTP_SECURE` is `tls` and `MAIL_FROM_EMAIL`/`MAIL_FROM_NAME` are set as you prefer.

Example (do NOT commit real credentials):

```php
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_SECURE', 'tls');
define('MAIL_FROM_EMAIL', 'no-reply@yourdomain.com');
define('MAIL_FROM_NAME', SITE_NAME);
```

3. Gmail requirements:

- Enable 2-Step Verification on the Google account.
- Create an App Password for "Mail" and copy it into `SMTP_PASS`.

4. Test the verification flow:

- Open `http://localhost/system/email_verification.php`.
- Enter the email address you configured and click "Send Code".
- Check the recipient inbox for the OTP.

Notes:

- If `composer` and PHPMailer are not available, the app will fall back to PHP's `mail()` function, which requires a local mail agent configured in XAMPP/Windows and is not recommended for sending to Gmail.
- Never commit real SMTP credentials to version control. Use environment variables or protected config in production.
