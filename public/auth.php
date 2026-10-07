<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php?dashboard=1#dashboard');
}

$mode = ($_GET['mode'] ?? 'login') === 'signup' ? 'signup' : 'login';
$isSignup = $mode === 'signup';
$success = flash('success');
$error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Sign in or create a VerifyED account.">
  <title><?= $isSignup ? 'Create account' : 'Sign in' ?> | VerifyED</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Newsreader:opsz,wght@6..72,500;6..72,600;6..72,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-page auth-mode-<?= e($mode) ?>">
  <header class="auth-header">
    <a class="logo" href="index.php"><span class="logo-symbol">✓</span><span>verify<span>ED</span></span></a>
    <a class="back-home" href="index.php">← Back to home</a>
  </header>

  <?php if ($success || $error): ?><div class="flash <?= $error ? 'flash-error' : '' ?>"><?= e($error ?: $success) ?></div><?php endif; ?>

  <main class="auth-layout">
    <section class="auth-story">
      <div class="auth-orb auth-orb-large"></div><div class="auth-orb auth-orb-small"></div>
      <div class="auth-story-copy">
        <p class="eyebrow">Learning deserves recognition</p>
        <h1>Turn what you’ve learned into <em>trusted proof.</em></h1>
        <p>Build a profile that makes your independently earned skills clearer to people who matter.</p>
        <div class="auth-proof-list">
          <div><span>1</span><p><b>Submit evidence</b><br>Share your certificate securely.</p></div>
          <div><span>2</span><p><b>Get it reviewed</b><br>Real people assess your proof.</p></div>
          <div><span>3</span><p><b>Share with confidence</b><br>Show verified learning anywhere.</p></div>
        </div>
      </div>
      <div class="auth-mini-card"><span class="tiny-label">VERIFIED LEARNING</span><strong>Skills worth<br>believing in.</strong><span class="mini-check">✓</span></div>
    </section>

    <section class="auth-panel">
      <div class="auth-panel-inner">
        <div class="auth-switch" aria-label="Authentication options">
          <a class="<?= !$isSignup ? 'active' : '' ?>" data-auth-switch href="auth.php?mode=login">Sign in</a>
          <a class="<?= $isSignup ? 'active' : '' ?>" data-auth-switch href="auth.php?mode=signup">Create account</a>
        </div>

        <?php if ($isSignup): ?>
          <div class="auth-intro"><p class="eyebrow">Start your profile</p><h2>Create your account</h2><p>It takes less than a minute. You can add certificates after signing in.</p></div>
          <form class="auth-form" method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="register"><input type="hidden" name="return_to" value="auth.php?mode=signup">
            <label>Full name<input required name="full_name" minlength="2" maxlength="100" autocomplete="name" placeholder="Your full name"></label>
            <label>Email address<input required name="email" type="email" autocomplete="email" placeholder="you@example.com"></label>
            <label>Password<span class="field-caption">At least 8 characters</span><div class="password-field"><input id="registerPassword" required name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Create a password"><button type="button" data-reveal-password="#registerPassword">Show</button></div></label>
            <button class="button button-coral auth-submit" type="submit">Create my profile <span>→</span></button>
          </form>
          <p class="auth-footer-note">Already have an account? <a href="auth.php?mode=login">Sign in</a></p>
        <?php else: ?>
          <div class="auth-intro"><p class="eyebrow">Welcome back</p><h2>Sign in to VerifyED</h2><p>Continue building and sharing your trusted learning record.</p></div>
          <form class="auth-form" method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="login"><input type="hidden" name="return_to" value="auth.php?mode=login">
            <label>Email address<input required name="email" type="email" autocomplete="email" placeholder="you@example.com"></label>
            <label>Password<div class="password-field"><input id="loginPassword" required name="password" type="password" autocomplete="current-password" placeholder="Enter your password"><button type="button" data-reveal-password="#loginPassword">Show</button></div></label>
            <button class="button button-dark auth-submit" type="submit">Sign in <span>→</span></button>
          </form>
          <p class="auth-role-note"><span>✓</span> Learners and authorized verifiers use the same secure sign-in.</p>
          <p class="auth-footer-note">New to VerifyED? <a href="auth.php?mode=signup">Create an account</a></p>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script src="assets/app.js"></script>
</body>
</html>
