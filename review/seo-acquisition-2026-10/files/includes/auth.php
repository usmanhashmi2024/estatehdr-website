<?php
/**
 * Estate HDR — Authentication, CSRF, Flash Messages & Multi-Role RBAC Engine
 * Supports Owner/Super Admin, Manager, Supervisor, SEO Manager, and Client Roles
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/client-return.php';

// ── CSRF Protection ──────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ── IP Brute-Force & Lockout Engine ───────────────────────────
if (!defined('DATA_PATH')) {
    define('DATA_PATH', dirname(__DIR__) . '/data/');
}
define('LOGIN_ATTEMPTS_FILE', DATA_PATH . 'login_attempts.json');
define('MAX_FAILED_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION_SECS', 900); // 15 Minutes

function isIpLockedOut(string $ip): bool {
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) return false;
    $attempts = function_exists('readJson') ? readJson(LOGIN_ATTEMPTS_FILE) : (json_decode(@file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: []);
    if (!isset($attempts[$ip])) return false;
    
    $rec = $attempts[$ip];
    if (($rec['count'] ?? 0) >= MAX_FAILED_LOGIN_ATTEMPTS) {
        $elapsed = time() - ($rec['last_attempt'] ?? 0);
        if ($elapsed < LOCKOUT_DURATION_SECS) {
            return true; // Locked out
        }
        // Lockout expired, clean up
        unset($attempts[$ip]);
        if (function_exists('atomicWriteJson')) {
            atomicWriteJson(LOGIN_ATTEMPTS_FILE, $attempts);
        } else {
            @file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }
    return false;
}

function recordFailedLoginAttempt(string $ip): void {
    $attempts = function_exists('readJson') ? readJson(LOGIN_ATTEMPTS_FILE) : (file_exists(LOGIN_ATTEMPTS_FILE) ? (json_decode(@file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: []) : []);
    $count = ($attempts[$ip]['count'] ?? 0) + 1;
    $attempts[$ip] = [
        'count'        => $count,
        'last_attempt' => time()
    ];
    if (function_exists('atomicWriteJson')) {
        atomicWriteJson(LOGIN_ATTEMPTS_FILE, $attempts);
    } else {
        @file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    usleep(random_int(200000, 400000)); // 200ms anti-timing pause
}

function clearFailedLoginAttempts(string $ip): void {
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) return;
    $attempts = function_exists('readJson') ? readJson(LOGIN_ATTEMPTS_FILE) : (json_decode(@file_get_contents(LOGIN_ATTEMPTS_FILE), true) ?: []);
    if (isset($attempts[$ip])) {
        unset($attempts[$ip]);
        if (function_exists('atomicWriteJson')) {
            atomicWriteJson(LOGIN_ATTEMPTS_FILE, $attempts);
        } else {
            @file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attempts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }
}

// ── Flash Messages ───────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $type = $flash['type'] === 'error' ? 'flash-error' : 'flash-success';
    return '<div class="flash-msg ' . $type . '">' . htmlspecialchars($flash['message']) . '</div>';
}

// ── Multi-Role Admin User Helpers ────────────────────────────
function currentAdminUser(): ?array {
    return $_SESSION['admin_user'] ?? null;
}

function isAdminLoggedIn(): bool {
    if (!empty($_SESSION['admin_user']) || !empty($_SESSION['is_admin'])) {
        $expectedFingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        if (isset($_SESSION['_ehdr_admin_fingerprint']) && !hash_equals($_SESSION['_ehdr_admin_fingerprint'], $expectedFingerprint)) {
            \EstateHDR\Security\SecurityLogger::log('admin_session_hijack_detected', 'CRITICAL', 'Admin session fingerprint mismatch.', [
                'client_ip' => \EstateHDR\Security\SecurityLogger::getClientIp()
            ]);
            logoutAdmin();
            return false;
        }
        return true;
    }
    // Auto-restore from secure persistent cookie
    if (!empty($_COOKIE['ehdr_admin_logged_in']) && !empty($_COOKIE['ehdr_admin_email'])) {
        $email = strtolower(trim($_COOKIE['ehdr_admin_email']));
        if (function_exists('getAdminUsers')) {
            $adminUsers = getAdminUsers();
            foreach ($adminUsers as $u) {
                if (!empty($u['is_active']) && strtolower($u['email']) === $email) {
                    $_SESSION['admin_user'] = $u;
                    $_SESSION['is_admin']   = true;
                    return true;
                }
            }
        }
        if ($email === 'admin@estatehdr.com') {
            $_SESSION['admin_user'] = [
                'id'    => 'adm-1',
                'email' => 'admin@estatehdr.com',
                'name'  => 'Master Admin',
                'roles' => ['owner', 'super_admin']
            ];
            $_SESSION['is_admin'] = true;
            return true;
        }
    }
    return false;
}

function getAdminRoles(): array {
    $user = currentAdminUser();
    if (!$user) return !empty($_SESSION['is_admin']) ? ['owner', 'super_admin'] : [];
    if (isset($user['roles']) && is_array($user['roles'])) return $user['roles'];
    if (!empty($user['role'])) return [$user['role']];
    return ['super_admin'];
}

function isOwner(): bool {
    $roles = getAdminRoles();
    return in_array('owner', $roles) || in_array('super_admin', $roles);
}

function isSuperAdmin(): bool {
    return isOwner();
}

function isManager(): bool {
    $roles = getAdminRoles();
    return in_array('manager', $roles) || isOwner();
}

function isSupervisor(): bool {
    $roles = getAdminRoles();
    return in_array('supervisor', $roles) || isManager() || isOwner();
}

// ── Production & Organization RBAC Role Helpers ───────────────
function isPhotoEditor(): bool {
    $roles = getAdminRoles();
    return in_array('photo_editor', $roles);
}

function isTeamLead(): bool {
    $roles = getAdminRoles();
    return in_array('team_lead', $roles) || isSupervisor() || isManager() || isOwner();
}

function isSupervisorRole(): bool {
    $roles = getAdminRoles();
    return in_array('supervisor', $roles) || isManager() || isOwner();
}

function isProductionManager(): bool {
    $roles = getAdminRoles();
    return in_array('manager', $roles) || in_array('production_manager', $roles) || isOwner();
}

function isHR(): bool {
    $roles = getAdminRoles();
    return in_array('hr', $roles) || isOwner();
}

function isSeoManager(): bool {
    $roles = getAdminRoles();
    return in_array('seo_manager', $roles) && !isOwner() && !isManager();
}

function enforceRoleAccess(): void {
    if (!isAdminLoggedIn()) return;
    $curr = currentAdminUser();
    if (!empty($curr['id']) && function_exists('recordStaffActivity')) {
        recordStaffActivity($curr['id']);
    }
    
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    if (in_array($currentPage, ['index', 'logout'])) {
        return; // Login and logout endpoints are open to session
    }
    
    $roles         = getAdminRoles();
    $isOwner       = in_array('owner', $roles) || in_array('super_admin', $roles);
    $isManagerOnly = (in_array('manager', $roles) || in_array('production_manager', $roles)) && !$isOwner;
    
    // 1. Owner / Super Admin -> Full unrestricted access
    if ($isOwner) {
        return;
    }
    
    // 2. Production Manager -> Restricted from HR, Ledger, and Dev/SEO
    if ($isManagerOnly) {
        $restrictedForManager = ['hr-dashboard', 'ledger', 'pages', 'page-edit', 'posts', 'post-edit', 'categories', 'seo-sitemap'];
        if (in_array($currentPage, $restrictedForManager)) {
            setFlash('error', 'Access Denied: Production Manager does not have access to HR, Financial Ledger, or Developer/SEO configurations.');
            header("Location: /admin/");
            exit;
        }
        return;
    }
    
    // 2. Photo Editor -> Strictly restricted to Editor Workspace only
    if (in_array('photo_editor', $roles)) {
        $allowed = ['editor-dashboard', 'editor-order', 'roles-sop', 'profile', 'order-view'];
        if (!in_array($currentPage, $allowed)) {
            setFlash('error', 'Access Denied: Restricted to your Photo Editor Workspace.');
            header('Location: /admin/editor-dashboard.php');
            exit;
        }
        return;
    }
    
    // 3. Team Lead -> Strictly restricted to Team Lead QA Hub only
    if (in_array('team_lead', $roles)) {
        $allowed = ['teamlead-dashboard', 'editor-order', 'roles-sop', 'profile', 'order-view', 'orders'];
        if (!in_array($currentPage, $allowed)) {
            setFlash('error', 'Access Denied: Restricted to Team Lead QA Hub.');
            header('Location: /admin/teamlead-dashboard.php');
            exit;
        }
        return;
    }
    
    // 4. Supervisor -> Strictly restricted to Supervisor Operations only
    if (in_array('supervisor', $roles)) {
        $allowed = ['supervisor-dashboard', 'supervisor-teams', 'supervisor-performance', 'roles-sop', 'profile', 'order-view', 'orders'];
        if (!in_array($currentPage, $allowed)) {
            setFlash('error', 'Access Denied: Restricted to Supervisor Operations.');
            header('Location: /admin/supervisor-dashboard.php');
            exit;
        }
        return;
    }
    
    // 5. HR Officer -> Strictly restricted to HR & Staff Management only
    if (in_array('hr', $roles)) {
        $allowed = ['hr-dashboard', 'hr-documents', 'hr-document', 'users', 'user-new', 'user-edit', 'roles-sop', 'profile'];
        if (!in_array($currentPage, $allowed)) {
            setFlash('error', 'Access Denied: Restricted to HR & People Operations.');
            header('Location: /admin/hr-dashboard.php');
            exit;
        }
        return;
    }
    
    // 6. SEO Manager -> Strictly restricted to SEO & Content CMS only
    if (in_array('seo_manager', $roles)) {
        $allowed = ['pages', 'page-edit', 'posts', 'post-edit', 'categories', 'seo-sitemap', 'preview', 'roles-sop', 'profile'];
        if (!in_array($currentPage, $allowed)) {
            setFlash('error', 'Access Denied: Restricted to SEO & Content CMS.');
            header('Location: /admin/pages.php');
            exit;
        }
        return;
    }
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/?redirect=' . urlencode($uri));
        exit;
    }
    enforceRoleAccess();
}

function requireOwner(): void {
    requireAdmin();
    if (!isOwner()) {
        setFlash('error', 'Access Denied: This setting requires Owner permissions.');
        header('Location: /admin/');
        exit;
    }
}

function requireSuperAdmin(): void {
    requireOwner();
}

function requireManagerOrAdmin(): void {
    requireAdmin();
    $roles = getAdminRoles();
    $isHR = in_array('hr', $roles);
    if (!isManager() && !isOwner() && !$isHR) {
        setFlash('error', 'Access Denied: Restricted to Management and HR staff.');
        header('Location: /admin/');
        exit;
    }
}

function requireSupervisorOrAdmin(): void {
    requireAdmin();
    if (!isSupervisor() && !isManager() && !isOwner()) {
        setFlash('error', 'Access Denied: Restricted to Supervisors and Managers.');
        header('Location: /admin/pages.php');
        exit;
    }
}

function requireSeoManagerOrAdmin(): void {
    requireAdmin();
}

function loginAdmin(string $email = 'admin@estatehdr.com', string $name = 'Master Admin', string $role = 'super_admin', array $roles = ['super_admin']): void {
    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'email' => $email,
        'name'  => $name,
        'role'  => $role,
        'roles' => $roles
    ];
    $_SESSION['is_admin'] = true;
    $_SESSION['_ehdr_admin_fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
}

function logoutAdmin(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
        setcookie('ehdr_admin_logged_in', '', time() - 42000, '/');
    }
    @session_destroy();
}

// ── Client Portal Auth ──────────────────────────────────────
function currentUser(): ?array {
    return $_SESSION['client_user'] ?? null;
}

function getCurrentUser(): ?array {
    return currentUser();
}

function isLoggedIn(): bool {
    if (empty($_SESSION['client_user'])) {
        return false;
    }
    
    // Anti-Session Hijacking Fingerprint Check
    $expectedFingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
    if (isset($_SESSION['_ehdr_fingerprint']) && !hash_equals($_SESSION['_ehdr_fingerprint'], $expectedFingerprint)) {
        \EstateHDR\Security\SecurityLogger::log('session_hijack_detected', 'CRITICAL', 'Session fingerprint mismatch. Possible session hijacking attempt.', [
            'client_ip' => \EstateHDR\Security\SecurityLogger::getClientIp()
        ]);
        logoutUser();
        return false;
    }

    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $cleanUri = clientReturnPath($_SERVER['REQUEST_URI'] ?? '');
        header('Location: /login?redirect=' . urlencode($cleanUri));
        exit;
    }
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['client_user'] = [
        'id'      => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'company' => $user['company'] ?? '',
        'phone'   => $user['phone'] ?? ''
    ];
    $_SESSION['_ehdr_fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
    $_SESSION['_ehdr_login_time']  = time();
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
}
