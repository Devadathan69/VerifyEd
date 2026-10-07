<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $authReturn = in_array($_POST['return_to'] ?? '', ['auth.php?mode=login', 'auth.php?mode=signup'], true)
        ? $_POST['return_to']
        : 'auth.php?mode=login';

    try {
        if ($action === 'register') {
            $name = trim($_POST['full_name'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $password = $_POST['password'] ?? '';
            if (mb_strlen($name) < 2 || !$email || strlen($password) < 8) throw new RuntimeException('Use your name, a valid email, and a password of at least 8 characters.');
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $check->execute([$email]);
            if ($check->fetch()) throw new RuntimeException('An account already exists for that email.');
            $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            hydrate_session_user($pdo, (int) $pdo->lastInsertId());
            flash('success', 'Your learner profile is ready. Add your first certificate when you are ready.');
            redirect('index.php?dashboard=1#dashboard');
        }

        if ($action === 'login') {
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $statement = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?'); $statement->execute([$email]); $account = $statement->fetch();
            if (!$account || !password_verify($_POST['password'] ?? '', $account['password_hash'])) throw new RuntimeException('Email or password is incorrect.');
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            hydrate_session_user($pdo, (int) $account['id']);
            redirect('index.php?dashboard=1#dashboard');
        }

        if ($action === 'logout') {
            $_SESSION = []; session_destroy(); redirect('index.php');
        }

        require_login();
        $user = current_user();
        if ($action === 'update_profile') {
            $headline = trim($_POST['profile_headline'] ?? ''); $bio = trim($_POST['biography'] ?? ''); $location = trim($_POST['location'] ?? '');
            $update = $pdo->prepare('UPDATE users SET profile_headline = ?, biography = ?, location = ?, is_public = ? WHERE id = ?');
            $update->execute([$headline ?: null, $bio ?: null, $location ?: null, isset($_POST['is_public']) ? 1 : 0, $user['id']]);
            hydrate_session_user($pdo, (int) $user['id']); flash('success', 'Your public profile has been updated.'); redirect('index.php?dashboard=1#profile');
        }

        if ($action === 'submit_certificate') {
            if (is_verifier()) throw new RuntimeException('Verifier accounts cannot submit learner certificates.');
            $title = trim($_POST['title'] ?? ''); $provider = trim($_POST['provider'] ?? ''); $completion = $_POST['completion_date'] ?? '';
            if (!$title || !$provider || !valid_completion_date($completion)) throw new RuntimeException('Enter a title, provider, and a valid completion date that is not in the future.');
            [$storedFilename, $mime] = save_certificate_upload($_FILES['certificate'] ?? [], $config);
            $insert = $pdo->prepare('INSERT INTO certificates (user_id, title, provider, completion_date, credential_id, skills, original_filename, stored_filename, mime_type, share_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$user['id'], $title, $provider, $completion, trim($_POST['credential_id'] ?? '') ?: null, trim($_POST['skills'] ?? '') ?: null, $_FILES['certificate']['name'], $storedFilename, $mime, bin2hex(random_bytes(16))]);
            flash('success', 'Certificate submitted. A verifier will review it shortly.'); redirect('index.php?dashboard=1#credentials');
        }

        if ($action === 'resubmit_certificate') {
            if (is_verifier()) throw new RuntimeException('Verifier accounts cannot resubmit learner certificates.');
            $certificateId = (int) ($_POST['certificate_id'] ?? 0);
            $certificate = $pdo->prepare("SELECT stored_filename FROM certificates WHERE id = ? AND user_id = ? AND status = 'needs_revision'");
            $certificate->execute([$certificateId, $user['id']]);
            $existing = $certificate->fetch();
            if (!$existing) throw new RuntimeException('This certificate is not available for revision.');

            [$storedFilename, $mime] = save_certificate_upload($_FILES['certificate'] ?? [], $config);
            try {
                $pdo->beginTransaction();
                $update = $pdo->prepare("UPDATE certificates SET original_filename = ?, stored_filename = ?, mime_type = ?, status = 'pending', verifier_id = NULL, reviewed_at = NULL, review_notes = NULL WHERE id = ? AND user_id = ? AND status = 'needs_revision'");
                $update->execute([$_FILES['certificate']['name'], $storedFilename, $mime, $certificateId, $user['id']]);
                if ($update->rowCount() !== 1) throw new RuntimeException('This certificate is no longer available for revision.');
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $newPath = certificate_storage_path($storedFilename);
                if (is_file($newPath)) unlink($newPath);
                throw $error;
            }
            $oldPath = certificate_storage_path($existing['stored_filename']);
            if (is_file($oldPath)) unlink($oldPath);
            flash('success', 'Your revised certificate has been submitted for review.'); redirect('index.php?dashboard=1#credentials');
        }

        if ($action === 'review_certificate') {
            require_verifier();
            $certificateId = (int) ($_POST['certificate_id'] ?? 0); $outcome = $_POST['outcome'] ?? ''; $notes = trim($_POST['review_notes'] ?? '');
            if (!in_array($outcome, ['verified', 'needs_revision'], true)) throw new RuntimeException('Choose a review outcome.');
            $pdo->beginTransaction();
            $update = $pdo->prepare("UPDATE certificates SET status = ?, verifier_id = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ? AND status = 'pending'");
            $update->execute([$outcome, $user['id'], $notes ?: null, $certificateId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('This certificate is no longer awaiting review.');
            $history = $pdo->prepare('INSERT INTO certificate_reviews (certificate_id, verifier_id, outcome, notes) VALUES (?, ?, ?, ?)');
            $history->execute([$certificateId, $user['id'], $outcome, $notes ?: null]);
            $pdo->commit(); flash('success', 'Review has been recorded.'); redirect('index.php?dashboard=1#review-queue');
        }
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('VerifyED database request failed: ' . $error->getMessage());
        flash('error', 'We could not complete that request. Please try again.'); redirect(is_logged_in() ? 'index.php?dashboard=1' : $authReturn);
    } catch (RuntimeException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $error->getMessage()); redirect(is_logged_in() ? 'index.php?dashboard=1' : $authReturn);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('VerifyED request failed: ' . $error->getMessage());
        flash('error', 'We could not complete that request. Please try again.'); redirect(is_logged_in() ? 'index.php?dashboard=1' : $authReturn);
    }
}

$query = trim($_GET['q'] ?? '');
$term = '%' . $query . '%';
$profiles = $pdo->prepare("SELECT u.id, u.full_name, u.profile_headline, u.location, COUNT(c.id) AS verified_count, GROUP_CONCAT(DISTINCT c.skills SEPARATOR ', ') AS skill_list FROM users u JOIN certificates c ON c.user_id = u.id AND c.status = 'verified' WHERE u.role = 'student' AND u.is_public = 1 AND (u.full_name LIKE ? OR u.profile_headline LIKE ? OR c.skills LIKE ?) GROUP BY u.id HAVING verified_count > 0 ORDER BY verified_count DESC, u.updated_at DESC LIMIT 12");
$profiles->execute([$term, $term, $term]); $profiles = $profiles->fetchAll();
$user = current_user();
$dashboard = is_logged_in();
$certificates = []; $pendingReviews = [];
if ($user && !is_verifier()) { $statement = $pdo->prepare('SELECT * FROM certificates WHERE user_id = ? ORDER BY created_at DESC'); $statement->execute([$user['id']]); $certificates = $statement->fetchAll(); }
if (is_verifier()) { $statement = $pdo->query("SELECT c.*, u.full_name AS student_name, u.email AS student_email FROM certificates c JOIN users u ON c.user_id = u.id WHERE c.status = 'pending' ORDER BY c.created_at ASC"); $pendingReviews = $statement->fetchAll(); }
$credentialTotal = count($certificates);
$verifiedTotal = count(array_filter($certificates, fn(array $certificate): bool => $certificate['status'] === 'verified'));
$awaitingTotal = count(array_filter($certificates, fn(array $certificate): bool => $certificate['status'] === 'pending'));
$success = flash('success'); $error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="VerifyED makes independently learned skills visible and credible.">
  <title>VerifyED | Skills worth believing in</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Newsreader:opsz,wght@6..72,500;6..72,600;6..72,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="site-header"><a class="logo" href="index.php"><span class="logo-symbol">✓</span><span>verify<span>ED</span></span></a><nav><?php if ($user): ?><a href="#dashboard" data-scroll>My workspace</a><?php else: ?><a href="#find-talent" data-scroll>Find talent</a><a href="#how-it-works" data-scroll>How it works</a><a href="#mission" data-scroll>Our mission</a><?php endif; ?></nav><div class="header-account"><?php if ($user): ?><a class="button button-dark" href="#dashboard" data-scroll>My dashboard</a><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="signout" title="Sign out" type="submit">↗</button></form><?php else: ?><a class="button button-outline header-signin" href="auth.php?mode=login">Sign in</a><a class="button button-dark" href="auth.php?mode=signup">Create account <span>→</span></a><?php endif; ?></div></header>

<?php if ($success || $error): ?><div class="flash <?= $error ? 'flash-error' : '' ?>"><?= e($error ?: $success) ?></div><?php endif; ?>

<main>
  <?php if (!$dashboard): ?>
  <section class="hero"><div class="hero-copy"><p class="eyebrow">Skills deserve proof</p><h1>Learned anywhere.<br><em>Trusted everywhere.</em></h1><p>VerifyED gives self-taught learners a credible home for the skills they have earned beyond the classroom.</p><div class="hero-actions"><a href="auth.php?mode=signup" class="button button-coral">Build your profile <span>→</span></a><a href="#find-talent" data-scroll class="underlink">Explore verified talent</a></div><div class="trust-line"><span class="check-dot">✓</span> Reviewed by real people, not an algorithm.</div></div><div class="hero-visual"><div class="orb orb-one"></div><div class="orb orb-two"></div><div class="hero-card card-back"><span class="tiny-label">CERTIFICATE</span><b>Web Design<br>Fundamentals</b><small>Skillshare · 2025</small></div><div class="hero-card card-main"><div class="avatar">AC</div><p class="tiny-label">VERIFIED LEARNER</p><h3>Aisha Cherian</h3><p>Frontend developer<br>Kerala, India</p><div class="verified-line"><span>✓</span> 3 verified skills</div></div><div class="hero-check">✓</div><div class="hero-scribble">proof<br>matters</div></div></section>

  <section class="logos"><span>BUILT FOR LEARNERS FROM</span><b>Udemy</b><b>coursera</b><b>YouTube</b><b>freeCodeCamp</b><b>bootcamps</b></section>

  <section id="find-talent" class="talent section-wrap"><div class="section-title"><div><p class="eyebrow">The talent directory</p><h2>Look beyond the degree.</h2></div><p>Search public learner profiles and independently verified credentials.</p></div><form class="search-panel" method="get"><label><span>⌕</span><input name="q" value="<?= e($query) ?>" placeholder="Search skills, names, or roles"></label><button class="button button-dark" type="submit">Search talent</button></form><div class="profile-grid"><?php if ($profiles): foreach ($profiles as $profile): ?><article class="profile-card"><div class="profile-top"><div class="avatar avatar-small"><?= e(strtoupper(mb_substr($profile['full_name'], 0, 1))) ?></div><span class="badge">✓ Verified evidence</span></div><h3><?= e($profile['full_name']) ?></h3><p class="headline"><?= e($profile['profile_headline'] ?: 'Independent learner') ?></p><p class="location">⌖ <?= e($profile['location'] ?: 'Location shared on profile') ?></p><div class="skill-tags"><?php foreach (array_slice(array_filter(array_map('trim', explode(',', $profile['skill_list'] ?? ''))), 0, 3) as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?></div><a class="card-link" href="profile.php?id=<?= (int) $profile['id'] ?>">View profile · <?= (int) $profile['verified_count'] ?> verified <span>→</span></a></article><?php endforeach; else: ?><div class="empty-directory"><strong>No verified learner profiles match that search yet.</strong><br>Try a broader term, or check back when the next reviews are complete.</div><?php endif; ?></div></section>

  <section id="how-it-works" class="how"><div class="section-wrap"><div class="how-heading"><p class="eyebrow">A clear review path</p><h2>From earned skill<br>to credible signal.</h2></div><div class="how-steps"><article><span>01</span><h3>Show what you learned</h3><p>Create a public profile and submit a certificate from the course, channel, or bootcamp where you gained the skill.</p></article><article><span>02</span><h3>A person reviews it</h3><p>An authorized verifier examines the evidence, records review notes, and asks for more when it is needed.</p></article><article><span>03</span><h3>Share trusted proof</h3><p>Verified credentials appear on your profile with a unique link that employers can inspect at any time.</p></article></div></div></section>

  <section id="mission" class="mission section-wrap"><div><p class="eyebrow">Why VerifyED exists</p><h2>Ability is everywhere.<br><em>Opportunity should be too.</em></h2></div><p>Great work is learned in late-night tutorials, local bootcamps, community groups, and kitchens turned into study spaces. We help that work travel further by making informal learning visible, reviewable, and easier to trust.</p><div class="sdg-line"><span>SDG 4</span><span>SDG 8</span><small>Quality Education · Decent Work & Economic Growth</small></div></section>

  <?php endif; ?>

  <?php if ($dashboard): ?><section id="dashboard" class="dashboard"><div class="section-wrap"><div class="dashboard-title"><div><p class="eyebrow">Your private workspace</p><h2>Hello, <?= e(explode(' ', $user['full_name'])[0]) ?>.</h2><p class="dashboard-subtitle"><?= is_verifier() ? 'Review evidence carefully and keep learning credible.' : 'Build a clear record of the skills you have earned.' ?></p></div><?php if (is_verifier()): ?><span class="role-label">Authorized verifier</span><?php else: ?><div class="credential-summary"><div><strong><?= $credentialTotal ?></strong><span>Submitted</span></div><div><strong><?= $verifiedTotal ?></strong><span>Verified</span></div><div><strong><?= $awaitingTotal ?></strong><span>In review</span></div></div><?php endif; ?></div>
  <?php if (is_verifier()): ?><div id="review-queue" class="review-queue"><div class="dashboard-intro"><h3>Review queue</h3><p><?= count($pendingReviews) ?> certificate<?= count($pendingReviews) === 1 ? '' : 's' ?> waiting for a decision.</p></div><?php if ($pendingReviews): foreach ($pendingReviews as $certificate): ?><article class="review-card"><div><span class="tiny-label">Submitted by <?= e($certificate['student_name']) ?></span><h3><?= e($certificate['title']) ?></h3><p><?= e($certificate['provider']) ?> · completed <?= e(date('M Y', strtotime($certificate['completion_date']))) ?></p><p class="review-skills"><?= e($certificate['skills'] ?: 'No skills provided') ?></p><a class="file-link" href="certificate-file.php?id=<?= (int) $certificate['id'] ?>">Open submitted certificate ↗</a></div><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_certificate"><input type="hidden" name="certificate_id" value="<?= (int) $certificate['id'] ?>"><label>Review notes<textarea name="review_notes" rows="4" placeholder="Record the evidence reviewed or what the learner should clarify."></textarea></label><div class="review-actions"><button class="button button-outline" type="submit" name="outcome" value="needs_revision">Request revision</button><button class="button button-verified" type="submit" name="outcome" value="verified">Verify credential ✓</button></div></form></article><?php endforeach; else: ?><div class="nothing-pending">The queue is clear. New submissions will appear here.</div><?php endif; ?></div>
  <?php else: ?><div class="dashboard-grid"><section id="credentials" class="credentials"><div class="dashboard-intro"><h3>My credentials</h3><p>Track each submission from upload to verified proof.</p></div><?php if ($certificates): ?><div class="credential-list"><?php foreach ($certificates as $certificate): ?><article class="credential"><div class="certificate-stamp">✦</div><div class="credential-info"><span class="badge <?= e(status_class($certificate['status'])) ?>"><?= $certificate['status'] === 'verified' ? '✓ ' : '' ?><?= e(status_label($certificate['status'])) ?></span><h3><?= e($certificate['title']) ?></h3><p><?= e($certificate['provider']) ?> · <?= e(date('M Y', strtotime($certificate['completion_date']))) ?></p><?php if ($certificate['review_notes']): ?><p class="review-note"><b>Review note:</b> <?= e($certificate['review_notes']) ?></p><?php endif; ?><?php if ($certificate['status'] === 'needs_revision'): ?><form class="revision-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resubmit_certificate"><input type="hidden" name="certificate_id" value="<?= (int) $certificate['id'] ?>"><label>Replace certificate file<input required name="certificate" type="file" accept="application/pdf,image/jpeg,image/png"></label><button class="button button-outline" type="submit">Resubmit for review</button></form><?php endif; ?></div><div class="credential-actions"><?php if ($certificate['status'] === 'verified'): $shareUrl = rtrim($config['base_url'], '/') . '/certificate.php?t=' . $certificate['share_token']; ?><a href="certificate.php?t=<?= e($certificate['share_token']) ?>" target="_blank" rel="noopener">View proof</a><button type="button" data-copy="<?= e($shareUrl) ?>">Copy link</button><?php elseif ($certificate['status'] === 'needs_revision'): ?><span>Action needed</span><?php else: ?><span>Under review</span><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="nothing-pending">No credentials yet. Submit your first proof below.</div><?php endif; ?></section><aside class="side-panel"><section class="upload-panel"><p class="eyebrow">Add a credential</p><h3>Submit for review</h3><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="submit_certificate"><label>Certificate title<input required name="title" maxlength="180" placeholder="e.g. Python for Everybody"></label><label>Learning provider<input required name="provider" maxlength="160" placeholder="e.g. Coursera"></label><label>Completion date<input required name="completion_date" type="date" max="<?= date('Y-m-d') ?>"></label><label>Credential ID <small>Optional</small><input name="credential_id" maxlength="120" placeholder="Provider-issued ID"></label><label>Skills <small>Comma-separated</small><input name="skills" maxlength="500" placeholder="Python, data analysis"></label><label class="file-input">Certificate file<input id="certificateFile" required name="certificate" type="file" accept="application/pdf,image/jpeg,image/png"><span id="selectedFile">PDF, JPG, or PNG · maximum 5 MB</span></label><button class="button button-coral" type="submit">Submit for review <span>→</span></button></form></section><section id="profile" class="profile-panel"><p class="eyebrow">Public profile</p><h3>Tell the story behind your skills.</h3><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_profile"><label>Headline<input name="profile_headline" maxlength="160" value="<?= e($user['profile_headline']) ?>" placeholder="e.g. Self-taught web developer"></label><label>Location<input name="location" maxlength="100" value="<?= e($user['location']) ?>" placeholder="e.g. Kochi, Kerala"></label><label>About you<textarea name="biography" rows="4" placeholder="What are you learning and building?"><?= e($user['biography']) ?></textarea></label><label class="check-label"><input type="checkbox" name="is_public" <?= $user['is_public'] ? 'checked' : '' ?>> Make my verified profile visible</label><button class="button button-outline" type="submit">Save profile</button></form></section></aside></div><?php endif; ?></div></section><?php endif; ?>
</main>
<footer><a class="logo" href="index.php"><span class="logo-symbol">✓</span><span>verify<span>ED</span></span></a><p>Credibility for learning beyond the classroom.</p><p>© <?= date('Y') ?> VerifyED</p></footer>
<script src="assets/app.js"></script>
</body></html>
