# VerifyED: Micro-Certification Credibility Portal

VerifyED is a PHP and MySQL web application that makes informal learning easier to trust. Students create public profiles, securely upload certificates for review, and share a unique credential-verification link once an authorized verifier approves their evidence.

## Included workflows

- Student registration, login, session management, profile editing, and public-profile privacy setting.
- Certificate submission with safe PDF/JPG/PNG validation, randomized storage filenames, and a 5 MB limit.
- Authorized verifier queue with review notes, verification, or revision requests, plus review history in MySQL.
- Public talent search and profiles show only verified credentials; pending evidence stays private.
- Revision requests let learners replace evidence and return it to the review queue.
- Shareable credential pages only for verified evidence, plus access-controlled original-certificate viewing.
- Hardened session cookies, CSRF checks, strict PDO prepared statements, security headers, and private error handling.

## Local setup

1. Install PHP 8.1+ and MySQL 8+ (or compatible MariaDB).
2. Import `schema.sql` using MySQL Workbench, phpMyAdmin, or the MySQL command-line client.
3. Copy `config.example.php` to `config.php` and set the MySQL credentials and `base_url`.
4. Ensure PHP can write to `storage/certificates/`.
5. With XAMPP/MySQL, start the site from this directory using XAMPP's PHP: `& "C:\xampp\php\php.exe" -S localhost:8000`. Then visit `http://localhost:8000`. The root entry point forwards the browser to the secure `public/` web root. Do not use a separate PHP installation unless its `pdo_mysql` driver is enabled.
6. Register a normal learner. To create a reviewer, promote a trusted registered account using the SQL statement at the end of `schema.sql`.

## Production checklist

- Point the web server document root to `public/`; never expose `app/`, `storage/`, or `config.php`.
- Set `base_url` to the HTTPS site address and set `session_secure` to `true` in `config.php`.
- Keep `app_debug` set to `false`, use a dedicated database user, and make `storage/certificates/` writable only by PHP.
- Configure PHP upload limits at or above 5 MB and use HTTPS before accepting real certificates.

## Structure

- `public/` contains the HTML/CSS/JavaScript experience and public PHP pages.
- `app/` provides the PDO connection, sessions, CSRF protection, and upload helpers.
- `storage/certificates/` is deliberately outside the public web root; the protected `certificate-file.php` route controls access.
- `schema.sql` contains normalized MySQL tables for users, certificates, and review history.
