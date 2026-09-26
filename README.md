  # CCIS Connect

A native PHP faculty community and engagement platform for the College of Computing and Information Sciences.

## Requirements

- PHP 8.1+
- MySQL 8 / MariaDB 10.6+
- Apache or Nginx
- Composer is optional for this project because the backend uses native PHP only.

## Setup

1. Create a MySQL database named `ccis_connect`.
2. Import the schema from `config/ccis_connect.sql`.
3. Copy `.env.example` to `.env` and update the values for your local environment. Never commit `.env`.
4. Run `php config/migrate.php` to safely apply additive migrations to the imported database.
5. Point your web server document root to this project folder.
6. Open the application in a browser.

## Demo credentials

- Admin: `admin@ccis.local` / `Ccis!Orbit2026#Pine`
- Faculty: `juan.delacruz@ccis.local` / `Ccis!Orbit2026#Pine`

## Configuration

Set `APP_URL` to the base URL where CCIS Connect is served (for local development, `http://127.0.0.1:8000`). Configure `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` to enable password-reset email delivery over SMTP. Google OAuth and PayMongo remain disabled until their credentials are configured. Store credentials only in `.env`, never in source, SQL, or documentation.

## Notes

- Password reset email delivery uses authenticated SMTP and does not report a reset as sent when delivery fails.
- Google sign-in and PayMongo checkout use server-side provider requests and fail closed when not configured; no fake payment success is generated.
- All application logic is native PHP and database-driven.
