# VerifyED: Micro-Certification Credibility Portal

VerifyED is a PHP and MySQL web application that makes informal learning easier to trust. Students create public profiles, securely upload certificates for review, and share a unique credential-verification link once an authorized verifier approves their evidence.

## Included workflows

- Student registration, login, session management, profile editing, and public-profile privacy setting.
- Certificate submission with safe PDF/JPG/PNG validation, randomized storage filenames, and a 5 MB limit.
- Authorized verifier queue with review notes, verification, or revision requests, plus review history in MySQL.
- Public talent search limited to learner profiles with verified credentials.
- Shareable credential pages and access-controlled original-certificate viewing.

## Local setup

1. Install PHP 8.1+ and MySQL 8+ (or compatible MariaDB).
2. Import `schema.sql` using MySQL Workbench, phpMyAdmin, or the MySQL command-line client.
3. Copy `config.example.php` to `config.php` and set the MySQL credentials and `base_url`.
4. Ensure PHP can write to `storage/certificates/`.
5. Start the site from this directory with `php -S localhost:8000 -t public` and visit `http://localhost:8000`.
6. Register a normal learner. To create a reviewer, promote a trusted registered account using the SQL statement at the end of `schema.sql`.

## Structure

- `public/` contains the HTML/CSS/JavaScript experience and public PHP pages.
- `app/` provides the PDO connection, sessions, CSRF protection, and upload helpers.
- `storage/certificates/` is deliberately outside the public web root; the protected `certificate-file.php` route controls access.
- `schema.sql` contains normalized MySQL tables for users, certificates, and review history.
