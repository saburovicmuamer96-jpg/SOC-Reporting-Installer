<?php
/**
 * SOC Reporting System - Session Management
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

class Session {
    /**
     * Start a secure session.
     */
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.cookie_httponly', SESSION_HTTPONLY ? '1' : '0');
        ini_set('session.cookie_secure', SESSION_SECURE ? '1' : '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

        session_name(SESSION_NAME);
        session_start();

        // Regenerate session ID periodically
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    /**
     * Check if user is logged in.
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
    }

    /**
     * Check if current user is admin.
     */
    public static function isAdmin(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Check if admin session is active (separate gate).
     */
    public static function isAdminAuthenticated(): bool {
        if (!self::isAdmin()) {
            return false;
        }
        if (!isset($_SESSION['admin_token'])) {
            return false;
        }
        // Verify admin session in database
        $adminSession = Database::fetchOne(Database::system(),
            "SELECT * FROM admin_sessions WHERE token = ? AND user_id = ? AND expires_at > NOW()",
            [$_SESSION['admin_token'], $_SESSION['user_id']]
        );
        return $adminSession !== null;
    }

    /**
     * Create a login session for a user.
     */
    public static function login(array $user): void {
        session_regenerate_id(true);
        $token = bin2hex(random_bytes(64));

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['language'] = $user['language'];
        $_SESSION['session_token'] = $token;
        $_SESSION['_created'] = time();

        // Store session in database
        Database::insert(Database::system(), 'sessions', [
            'user_id' => $user['id'],
            'token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'expires_at' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
        ]);
    }

    /**
     * Create an admin authentication session.
     */
    public static function adminLogin(): string {
        $token = bin2hex(random_bytes(64));
        $_SESSION['admin_token'] = $token;

        Database::insert(Database::system(), 'admin_sessions', [
            'user_id' => $_SESSION['user_id'],
            'token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'expires_at' => date('Y-m-d H:i:s', time() + ADMIN_SESSION_LIFETIME)
        ]);

        return $token;
    }

    /**
     * Destroy the current session.
     */
    public static function logout(): void {
        if (isset($_SESSION['session_token'])) {
            Database::query(Database::system(),
                "DELETE FROM sessions WHERE token = ?",
                [$_SESSION['session_token']]
            );
        }
        if (isset($_SESSION['admin_token'])) {
            Database::query(Database::system(),
                "DELETE FROM admin_sessions WHERE token = ?",
                [$_SESSION['admin_token']]
            );
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Require login - redirect if not logged in.
     */
    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            redirect('index.php?page=login');
        }
    }

    /**
     * Require admin access.
     */
    public static function requireAdmin(): void {
        if (!self::isAdmin()) {
            redirect('index.php?page=dashboard');
        }
    }

    /**
     * Require admin authentication gate.
     */
    public static function requireAdminAuth(): void {
        self::requireLogin();
        self::requireAdmin();
        if (!self::isAdminAuthenticated()) {
            redirect('index.php?page=admin_login');
        }
    }

    /**
     * Get current user info.
     */
    public static function user(): array {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'display_name' => $_SESSION['display_name'] ?? '',
            'role' => $_SESSION['role'] ?? 'user',
            'language' => $_SESSION['language'] ?? DEFAULT_LANGUAGE,
        ];
    }
}
