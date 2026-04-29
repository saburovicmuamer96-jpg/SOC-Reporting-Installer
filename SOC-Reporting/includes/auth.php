<?php
/**
 * SOC Reporting System - Authentication
 * NIST-compliant: bcrypt hashing, rate limiting, audit logging.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

class Auth {
    /**
     * Attempt login with username and password.
     * Returns user array on success, error string on failure.
     */
    public static function attempt(string $username, string $password): array|string {
        $db = Database::system();
        $user = Database::fetchOne($db,
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );

        // User not found
        if (!$user) {
            auditLog('login_failed', "Unknown username: $username");
            return label('Invalid username or password.', 'Ungültiger Benutzername oder Passwort.');
        }

        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            auditLog('login_locked', "Account locked: $username");
            return label(
                "Account is locked. Try again in $remaining minute(s).",
                "Konto ist gesperrt. Versuchen Sie es in $remaining Minute(n) erneut."
            );
        }

        // Check if account is active
        if (!$user['is_active']) {
            auditLog('login_inactive', "Inactive account: $username");
            return label('This account has been deactivated.', 'Dieses Konto wurde deaktiviert.');
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = $user['failed_attempts'] + 1;
            $lockUntil = null;

            if ($attempts >= AUTH_MAX_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + AUTH_LOCKOUT_TIME);
                $attempts = 0;
            }

            Database::update($db, 'users', [
                'failed_attempts' => $attempts,
                'locked_until' => $lockUntil
            ], 'id = ?', [$user['id']]);

            auditLog('login_failed', "Wrong password for: $username (attempt $attempts)");
            return label('Invalid username or password.', 'Ungültiger Benutzername oder Passwort.');
        }

        // Successful login - reset failed attempts
        Database::update($db, 'users', [
            'failed_attempts' => 0,
            'locked_until' => null
        ], 'id = ?', [$user['id']]);

        // Check if password needs rehash
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => AUTH_BCRYPT_COST])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => AUTH_BCRYPT_COST]);
            Database::update($db, 'users', ['password_hash' => $newHash], 'id = ?', [$user['id']]);
        }

        Session::login($user);
        auditLog('login_success', "User logged in: $username");

        return $user;
    }

    /**
     * Attempt admin authentication (separate gate).
     */
    public static function adminAttempt(string $username, string $password): bool|string {
        if (!Session::isLoggedIn() || !Session::isAdmin()) {
            return label('Access denied.', 'Zugriff verweigert.');
        }

        $user = Database::fetchOne(Database::system(),
            "SELECT * FROM users WHERE username = ? AND role = 'admin' AND is_active = 1",
            [$username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            auditLog('admin_auth_failed', "Admin auth failed for: $username");
            return label('Invalid admin credentials.', 'Ungültige Administrator-Anmeldedaten.');
        }

        if ((int)$user['id'] !== (int)$_SESSION['user_id']) {
            auditLog('admin_auth_failed', "Admin auth user mismatch: $username");
            return label('Admin credentials do not match current session.', 'Administrator-Anmeldedaten stimmen nicht mit der aktuellen Sitzung überein.');
        }

        Session::adminLogin();
        auditLog('admin_auth_success', "Admin authenticated: $username");
        return true;
    }

    /**
     * Hash a password using bcrypt.
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => AUTH_BCRYPT_COST]);
    }

    /**
     * Create a new user (admin only).
     */
    public static function createUser(string $username, string $password, string $displayName, string $role = 'user', string $language = 'en'): int|string {
        $db = Database::system();

        // Check if username exists
        $existing = Database::fetchOne($db, "SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            return label('Username already exists.', 'Benutzername existiert bereits.');
        }

        // Validate role
        if (!in_array($role, ['admin', 'user'])) {
            return label('Invalid role.', 'Ungültige Rolle.');
        }

        $id = Database::insert($db, 'users', [
            'username' => $username,
            'password_hash' => self::hashPassword($password),
            'display_name' => $displayName,
            'role' => $role,
            'language' => $language
        ]);

        auditLog('user_created', "Created user: $username (role: $role)");
        return (int) $id;
    }

    /**
     * Update user details.
     */
    public static function updateUser(int $userId, array $data): bool {
        $allowed = ['display_name', 'role', 'language', 'is_active'];
        $update = array_intersect_key($data, array_flip($allowed));

        if (isset($data['password']) && $data['password'] !== '') {
            $update['password_hash'] = self::hashPassword($data['password']);
        }

        if (empty($update)) {
            return false;
        }

        Database::update(Database::system(), 'users', $update, 'id = ?', [$userId]);
        auditLog('user_updated', "Updated user ID: $userId");
        return true;
    }

    /**
     * Generate a CSRF token (reuses existing valid token).
     */
    public static function generateCsrf(): string {
        if (isset($_SESSION['csrf_token']) && isset($_SESSION['csrf_time'])
            && time() - $_SESSION['csrf_time'] < CSRF_TOKEN_LIFETIME) {
            return $_SESSION['csrf_token'];
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_time'] = time();
        return $token;
    }

    /**
     * Validate a CSRF token.
     */
    public static function validateCsrf(string $token): bool {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_time'])) {
            return false;
        }
        if (time() - $_SESSION['csrf_time'] > CSRF_TOKEN_LIFETIME) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Logout the current user.
     */
    public static function logout(): void {
        $username = $_SESSION['username'] ?? 'unknown';
        auditLog('logout', "User logged out: $username");
        Session::logout();
    }
}
