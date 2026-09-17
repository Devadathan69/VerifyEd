<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login();
$id = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare('SELECT c.*, u.id AS owner_id FROM certificates c JOIN users u ON c.user_id = u.id WHERE c.id = ?');
$statement->execute([$id]); $certificate = $statement->fetch();
if (!$certificate || (!is_verifier() && (int) $certificate['owner_id'] !== (int) current_user()['id'])) { http_response_code(403); exit('You do not have access to this certificate file.'); }
$path = dirname(__DIR__) . '/storage/certificates/' . $certificate['stored_filename'];
if (!is_file($path)) { http_response_code(404); exit('The stored file is unavailable.'); }
header('Content-Type: ' . $certificate['mime_type']); header('Content-Length: ' . filesize($path)); header('Content-Disposition: inline; filename="' . rawurlencode($certificate['original_filename']) . '"'); readfile($path);
