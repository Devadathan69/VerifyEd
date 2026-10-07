<?php
require_once __DIR__ . '/../app/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare("SELECT id, full_name, profile_headline, biography, location FROM users WHERE id = ? AND role = 'student' AND is_public = 1");
$statement->execute([$id]);
$profile = $statement->fetch();
if (!$profile) {
    http_response_code(404);
    exit('This public profile could not be found.');
}

$statement = $pdo->prepare("SELECT title, provider, completion_date, skills, share_token, reviewed_at, status FROM certificates WHERE user_id = ? AND status = 'verified' ORDER BY reviewed_at DESC, created_at DESC");
$statement->execute([$id]);
$certificates = $statement->fetchAll();
$verifiedCount = count(array_filter($certificates, fn(array $certificate): bool => $certificate['status'] === 'verified'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($profile['full_name']) ?> | VerifyED</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Newsreader:opsz,wght@6..72,500;6..72,600;6..72,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css"><link rel="stylesheet" href="assets/profile.css">
</head>
<body>
  <header class="site-header"><a class="logo" href="index.php"><span class="logo-symbol">✓</span><span>verify<span>ED</span></span></a><a href="index.php#find-talent" class="button button-outline">Browse talent</a></header>
  <main class="public-profile section-wrap">
    <a class="back-link" href="index.php#find-talent">← Back to talent directory</a>
    <section class="profile-hero">
      <div class="large-avatar"><?= e(strtoupper(mb_substr($profile['full_name'], 0, 1))) ?></div>
      <div><p class="eyebrow">Learner profile</p><h1><?= e($profile['full_name']) ?></h1><p class="profile-headline"><?= e($profile['profile_headline'] ?: 'Independent learner') ?></p><p class="location">⌖ <?= e($profile['location'] ?: 'Location not specified') ?></p></div>
      <div class="profile-count"><strong><?= $verifiedCount ?></strong><span>verified credential<?= $verifiedCount === 1 ? '' : 's' ?></span></div>
    </section>
    <?php if ($profile['biography']): ?><section class="profile-about"><p class="eyebrow">About</p><p><?= nl2br(e($profile['biography'])) ?></p></section><?php endif; ?>
    <section class="verified-credentials"><p class="eyebrow">Credential record</p><h2>Skills &amp; credentials</h2><div class="verified-grid">
      <?php if (!$certificates): ?><div class="empty-directory">No credentials have been added to this profile yet.</div><?php endif; ?>
      <?php foreach ($certificates as $certificate): ?>
        <article class="verified-card">
          <span class="verified-medallion">✓</span>
          <p class="tiny-label">VERIFIED</p>
          <h3><?= e($certificate['title']) ?></h3>
          <p><?= e($certificate['provider']) ?> · <?= e(date('M Y', strtotime($certificate['completion_date']))) ?></p>
          <?php if ($certificate['skills']): ?><div class="skill-tags"><?php foreach (array_slice(array_filter(array_map('trim', explode(',', $certificate['skills']))), 0, 5) as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?></div><?php endif; ?>
          <a class="card-link" href="certificate.php?t=<?= e($certificate['share_token']) ?>">View verification <span>→</span></a>
        </article>
      <?php endforeach; ?>
    </div></section>
  </main>
  <footer><a class="logo" href="index.php"><span class="logo-symbol">✓</span><span>verify<span>ED</span></span></a><p>Credibility for learning beyond the classroom.</p><p>© <?= date('Y') ?> VerifyED</p></footer>
</body>
</html>
