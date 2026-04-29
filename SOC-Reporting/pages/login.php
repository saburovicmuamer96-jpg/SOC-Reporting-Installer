<?php
/**
 * SOC Reporting System - Login Page
 */

// If already logged in, go to dashboard
if (Session::isLoggedIn()) {
    redirect('index.php?page=dashboard');
}

$error = '';

// Handle login form submission
if (isPost()) {
    $username = post('username');
    $password = post('password');

    if (empty($username) || empty($password)) {
        $error = label('Please fill in all fields.', 'Bitte füllen Sie alle Felder aus.');
    } else {
        $result = Auth::attempt($username, $password);
        if (is_array($result)) {
            // Success - redirect to dashboard
            redirect('index.php?page=dashboard');
        } else {
            $error = $result;
        }
    }
}

// Generate CSRF token for the form (after POST handling)
$csrfToken = Auth::generateCsrf();
?>
<!DOCTYPE html>
<html lang="<?= e(currentLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - <?= label('Login', 'Anmeldung') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-card">
                <div class="login-header">
                    <div class="login-logo">&#9737;</div>
                    <h1 class="login-title"><?= e(APP_NAME) ?></h1>
                    <p class="login-subtitle"><?= label('Sign in to continue', 'Melden Sie sich an, um fortzufahren') ?></p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=login" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="form-group">
                        <label class="form-label" for="username"><?= label('Username', 'Benutzername') ?></label>
                        <input type="text" id="username" name="username" class="form-input"
                               placeholder="<?= label('Enter your username', 'Geben Sie Ihren Benutzernamen ein') ?>"
                               value="<?= e(post('username')) ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password"><?= label('Password', 'Passwort') ?></label>
                        <input type="password" id="password" name="password" class="form-input"
                               placeholder="<?= label('Enter your password', 'Geben Sie Ihr Passwort ein') ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <?= label('Sign In', 'Anmelden') ?>
                    </button>
                </form>
            </div>

            <div class="text-center mt-2">
                <span class="text-muted" style="font-size: 0.75rem;">
                    <?= label('Contact your administrator for access', 'Kontaktieren Sie Ihren Administrator für den Zugang') ?>
                </span>
            </div>
        </div>
    </div>
</body>
</html>
