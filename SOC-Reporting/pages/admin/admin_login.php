<?php
/**
 * SOC Reporting System - Admin Authentication Gate
 * Requires re-authentication every time admin panel is accessed.
 */

Session::requireLogin();

if (!Session::isAdmin()) {
    setFlash('error', label('Access denied. Admin privileges required.', 'Zugriff verweigert. Admin-Berechtigung erforderlich.'));
    redirect('index.php?page=dashboard');
}

// If already admin-authenticated, redirect to admin dashboard
if (Session::isAdminAuthenticated()) {
    redirect('index.php?page=users');
}

$error = '';

if (isPost()) {
    $username = post('username');
    $password = post('password');

    if (empty($username) || empty($password)) {
        $error = label('Please fill in all fields.', 'Bitte füllen Sie alle Felder aus.');
    } else {
        // Verify admin password directly
        $adminUser = Database::fetchOne(Database::system(),
            "SELECT * FROM users WHERE username = ? AND role = 'admin' AND is_active = 1",
            [$username]
        );

        if ($adminUser && password_verify($password, $adminUser['password_hash'])) {
            Session::adminLogin();
            auditLog('admin_auth_success', "Admin authenticated: $username");
            ob_end_clean();
            header('Location: index.php?page=users');
            exit;
        } else {
            $error = label('Invalid admin credentials.', 'Ungültige Administrator-Anmeldedaten.');
        }
    }
}
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Admin Authentication', 'Admin-Authentifizierung') ?>';</script>

<div style="max-width: 440px; margin: 40px auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: var(--accent-warning-dim); border: 2px solid var(--accent-warning); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 12px; color: var(--accent-warning);">
                &#9888;
            </div>
            <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">
                <?= label('Admin Panel Access', 'Admin-Bereich Zugang') ?>
            </h2>
            <p class="text-muted" style="font-size: 0.82rem;">
                <?= label('Please re-enter your admin credentials to continue.', 'Bitte geben Sie Ihre Admin-Anmeldedaten erneut ein.') ?>
            </p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=admin_login" autocomplete="off">

            <div class="form-group">
                <label class="form-label" for="admin_user"><?= label('Admin Username', 'Admin-Benutzername') ?></label>
                <input type="text" id="admin_user" name="username" class="form-input"
                       value="<?= e($currentUser['username']) ?>" required autocomplete="off">
            </div>

            <div class="form-group">
                <label class="form-label" for="admin_pass"><?= label('Admin Password', 'Admin-Passwort') ?></label>
                <input type="password" id="admin_pass" name="password" class="form-input"
                       placeholder="<?= label('Enter your password', 'Geben Sie Ihr Passwort ein') ?>" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-warning btn-block btn-lg">
                <?= label('Authenticate', 'Authentifizieren') ?>
            </button>
        </form>
    </div>

    <div class="text-center mt-2">
        <a href="index.php?page=dashboard" class="text-muted" style="font-size: 0.82rem;">
            &larr; <?= label('Back to Dashboard', 'Zurück zum Dashboard') ?>
        </a>
    </div>
</div>
