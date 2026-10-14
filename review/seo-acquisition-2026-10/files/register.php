<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
$redirect = clientReturnPath($_POST['redirect'] ?? $_GET['redirect'] ?? '');

if (isLoggedIn()) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    ehdr_rate_limit('register');

    if (\EstateHDR\Security\SpamDetector::checkHoneypots($_POST)) {
        $error = 'Registration could not be completed. Please try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $company  = trim($_POST['company'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (\EstateHDR\Security\SpamDetector::isDisposableEmail($email)) {
            $error = 'Temporary or disposable email domains are not allowed. Please use your business or personal email.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (findUserByEmail($email)) {
            $error = 'An account with that email address already exists. Please sign in instead.';
        } else {
            $user = createUser($name, $email, $password, $company, '', '', '', 'client');
            loginUser($user);
            setFlash('success', 'Account created! Welcome to Estate HDR Client Portal.');
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Official Gold & White Favicons -->
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
<link rel="shortcut icon" href="/favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — Estate HDR</title>
<meta name="robots" content="noindex, nofollow">
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&display=swap">
</noscript>
<link rel="stylesheet" href="/assets/css/bundle.css?v=<?= file_exists(ROOT_PATH . '/assets/css/bundle.css') ? filemtime(ROOT_PATH . '/assets/css/bundle.css') : time() ?>">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= file_exists(ROOT_PATH . '/assets/css/app.css') ? filemtime(ROOT_PATH . '/assets/css/app.css') : time() ?>">
</head>
<body class="auth-page relative bg-dark min-h-screen flex items-center justify-center p-6">



<div class="auth-card glass-card rounded-2xl p-8 max-w-sm w-full border border-dark-border shadow-2xl">
  <a href="/" class="flex items-center gap-3 mb-8 justify-center">
    <img src="/assets/images/logo.png" alt="Estate HDR" class="h-20 sm:h-24 w-auto object-contain max-h-[84px]">
  </a>

  <h1 class="text-2xl font-bold text-white mb-2 text-center">Create Account</h1>
  <p class="text-gray-400 text-xs mb-8 text-center">Start placing orders with 24-hour turnaround.</p>

  <?php if ($error): ?><div class="flash-msg flash-error mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?= renderFlash() ?>

  <form method="POST" class="space-y-4">
    <?= csrfField() ?>
    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
    <div>
      <label for="register-name" class="form-label text-xs">Full Name *</label>
      <input id="register-name" type="text" name="name" class="form-input text-sm" placeholder="Craig Westerman"
             value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autocomplete="name">
    </div>
    <div>
      <label for="register-email" class="form-label text-xs">Email Address *</label>
      <input id="register-email" type="email" name="email" class="form-input text-sm" placeholder="craig@agency.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
    </div>
    <div>
      <label for="register-company" class="form-label text-xs">Company / Agency (optional)</label>
      <input id="register-company" type="text" name="company" class="form-input text-sm" placeholder="Prime Realty Media"
             value="<?= htmlspecialchars($_POST['company'] ?? '') ?>" autocomplete="organization">
    </div>
    <div>
      <label for="register-password" class="form-label text-xs">Password * (min 8 characters)</label>
      <input id="register-password" type="password" name="password" class="form-input text-sm" placeholder="••••••••" required autocomplete="new-password" minlength="8">
    </div>
    <?= ehdr_render_honeypot() ?>
    <?= ehdr_render_turnstile('register') ?>

    <button type="submit" class="btn-gold w-full py-3 font-bold text-xs shadow-glow mt-2">Create Account &rarr;</button>
  </form>

  <p class="text-center text-gray-400 text-xs mt-8">
    Already have an account? <a href="/login?redirect=<?= htmlspecialchars(urlencode($redirect), ENT_QUOTES, 'UTF-8') ?>" class="text-gold hover:underline font-bold">Sign In &rarr;</a>
  </p>
</div>
<?php require_once __DIR__ . '/includes/chat-widgets.php'; ?>
</body>
</html>
