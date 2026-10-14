<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$redirect = clientReturnPath($_POST['redirect'] ?? $_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $clientIp = \EstateHDR\Security\SecurityLogger::getClientIp();
    if (isIpLockedOut($clientIp)) {
        $error = 'Security Alert: Too many failed attempts. Account login locked for 15 minutes.';
    } else {
        ehdr_rate_limit('login_attempt');
        
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $user = findUserByEmail($email);
            $userHash = $user['password'] ?? $user['password_hash'] ?? '';
            if ($user && !empty($userHash) && password_verify($password, $userHash)) {
                if (isset($user['is_active']) && $user['is_active'] === false) {
                    $error = 'Your account has been deactivated. Please contact support.';
                } else {
                    clearFailedLoginAttempts($clientIp);
                    loginUser($user);
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                recordFailedLoginAttempt($clientIp);
                \EstateHDR\Security\SecurityLogger::log('auth_failed', 'WARNING', 'Failed client login attempt', [
                    'email'     => $email,
                    'client_ip' => $clientIp
                ]);
                $error = 'Invalid email address or password. Please try again.';
            }
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
<title>Sign In — Estate HDR</title>
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

  <h1 class="text-2xl font-bold text-white mb-2 text-center">Welcome back</h1>
  <p class="text-gray-400 text-xs mb-8 text-center">Sign in to your Estate HDR Client Portal.</p>

  <?php if ($error): ?><div class="flash-msg flash-error mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?= renderFlash() ?>

  <form method="POST" class="space-y-4">
    <?= csrfField() ?>
    <?= ehdr_render_honeypot() ?>
    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
    <div>
      <label for="login-email" class="form-label text-xs">Email Address</label>
      <input id="login-email" type="email" name="email" class="form-input text-sm" placeholder="name@company.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
    </div>
    <div>
      <label for="login-password" class="form-label text-xs">Password</label>
      <input id="login-password" type="password" name="password" class="form-input text-sm" placeholder="Your password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn-gold w-full py-3 font-bold text-xs shadow-glow mt-2">Sign In to Portal</button>
  </form>

  <p class="text-center text-gray-400 text-xs mt-8">
    No account yet? <a href="/register?redirect=<?= htmlspecialchars(urlencode($redirect), ENT_QUOTES, 'UTF-8') ?>" class="text-gold hover:underline font-bold">Create one free &rarr;</a>
  </p>
</div>
<?php require_once __DIR__ . '/includes/chat-widgets.php'; ?>
</body>
</html>
