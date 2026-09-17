<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return current_user() !== null; }
function is_verifier(): bool { return (current_user()['role'] ?? '') === 'verifier'; }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) { $_SESSION['flash'][$key] = $message; return null; }
    $value = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $value;
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Your form session expired. Please return and try again.'); } }
function require_login(): void { if (!is_logged_in()) { flash('error', 'Please sign in to continue.'); redirect('index.php#account'); } }
function require_verifier(): void { if (!is_verifier()) { http_response_code(403); exit('Verifier access is required.'); } }
function status_label(string $status): string { return ['pending' => 'Pending review', 'verified' => 'Verified', 'needs_revision' => 'Revision requested'][$status] ?? ucfirst($status); }
function status_class(string $status): string { return 'status-' . preg_replace('/[^a-z_]/', '', $status); }

function hydrate_session_user(PDO $pdo, int $id): void {
    $statement = $pdo->prepare('SELECT id, full_name, email, role, profile_headline, biography, location, is_public FROM users WHERE id = ?');
    $statement->execute([$id]);
    $_SESSION['user'] = $statement->fetch() ?: null;
}

function save_certificate_upload(array $file, array $config): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Choose a certificate file to upload.');
    if ($file['size'] > $config['max_upload_bytes']) throw new RuntimeException('Certificate files must be 5 MB or smaller.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Upload a PDF, JPG, or PNG certificate.');
    $filename = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certificates';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Unable to prepare secure storage.');
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) throw new RuntimeException('The certificate could not be stored.');
    return [$filename, $mime];
}
