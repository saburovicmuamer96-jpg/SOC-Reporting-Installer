<?php
/**
 * SOC Reporting System - User Management (Admin)
 */

// Handle user creation
if (isPost() && post('action') === 'create_user') {
    $token = post('csrf_token');
    if (!Auth::validateCsrf($token)) {
        setFlash('error', label('Invalid session.', 'Ungültige Sitzung.'));
    } else {
        $username = sanitize(post('username'));
        $password = post('password');
        $displayName = sanitize(post('display_name'));
        $role = post('role');
        $language = post('language', 'en');

        if (empty($username) || empty($password) || empty($displayName)) {
            setFlash('error', label('All fields are required.', 'Alle Felder sind erforderlich.'));
        } elseif (strlen($password) < 8) {
            setFlash('error', label('Password must be at least 8 characters.', 'Passwort muss mindestens 8 Zeichen lang sein.'));
        } else {
            $result = Auth::createUser($username, $password, $displayName, $role, $language);
            if (is_int($result)) {
                setFlash('success', label('User created successfully.', 'Benutzer erfolgreich erstellt.'));
            } else {
                setFlash('error', $result);
            }
        }
    }
    $csrfToken = Auth::generateCsrf();
    redirect('index.php?page=users');
}

// Handle user update
if (isPost() && post('action') === 'update_user') {
    $token = post('csrf_token');
    if (!Auth::validateCsrf($token)) {
        setFlash('error', label('Invalid session.', 'Ungültige Sitzung.'));
    } else {
        $userId = (int) post('user_id');
        $data = [
            'display_name' => sanitize(post('display_name')),
            'role' => post('role'),
            'language' => post('language', 'en'),
            'is_active' => (int) post('is_active', 1)
        ];
        if (post('password') !== '') {
            if (strlen(post('password')) < 8) {
                setFlash('error', label('Password must be at least 8 characters.', 'Passwort muss mindestens 8 Zeichen lang sein.'));
                redirect('index.php?page=users');
            }
            $data['password'] = post('password');
        }

        Auth::updateUser($userId, $data);
        setFlash('success', label('User updated successfully.', 'Benutzer erfolgreich aktualisiert.'));
    }
    $csrfToken = Auth::generateCsrf();
    redirect('index.php?page=users');
}

// Fetch all users
$users = Database::fetchAll(Database::system(), "SELECT * FROM users ORDER BY created_at DESC");
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('User Management', 'Benutzerverwaltung') ?>';</script>

<div class="page-header flex-between">
    <div>
        <h2 class="page-title"><?= label('User Management', 'Benutzerverwaltung') ?></h2>
        <p class="page-subtitle"><?= count($users) ?> <?= label('users total', 'Benutzer insgesamt') ?></p>
    </div>
    <div class="flex gap-1">
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')">
            + <?= label('New User', 'Neuer Benutzer') ?>
        </button>
        <a href="index.php?page=machines" class="btn btn-secondary"><?= label('Machines', 'Maschinen') ?></a>
        <a href="index.php?page=form_builder" class="btn btn-secondary"><?= label('Form Builder', 'Formular-Builder') ?></a>
    </div>
</div>

<!-- Users Table -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= label('Username', 'Benutzername') ?></th>
                <th><?= label('Display Name', 'Anzeigename') ?></th>
                <th><?= label('Role', 'Rolle') ?></th>
                <th><?= label('Language', 'Sprache') ?></th>
                <th><?= label('Status', 'Status') ?></th>
                <th><?= label('Created', 'Erstellt') ?></th>
                <th class="text-right"><?= label('Actions', 'Aktionen') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td class="text-mono"><?= $user['id'] ?></td>
                <td><strong><?= e($user['username']) ?></strong></td>
                <td><?= e($user['display_name']) ?></td>
                <td>
                    <span class="badge <?= $user['role'] === 'admin' ? 'badge-draft' : 'badge-submitted' ?>">
                        <?= e(ucfirst($user['role'])) ?>
                    </span>
                </td>
                <td><?= strtoupper(e($user['language'])) ?></td>
                <td>
                    <span class="badge <?= $user['is_active'] ? 'badge-reviewed' : 'badge-closed' ?>">
                        <?= $user['is_active'] ? label('Active', 'Aktiv') : label('Inactive', 'Inaktiv') ?>
                    </span>
                </td>
                <td><span class="timestamp"><?= formatDate($user['created_at']) ?></span></td>
                <td class="text-right">
                    <button class="btn btn-secondary btn-sm"
                            onclick="editUser(<?= htmlspecialchars(json_encode($user), ENT_QUOTES) ?>)">
                        <?= label('Edit', 'Bearbeiten') ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= label('Create New User', 'Neuen Benutzer erstellen') ?></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_user">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= label('Username', 'Benutzername') ?> <span class="required-mark">*</span></label>
                    <input type="text" name="username" class="form-input" required
                           pattern="[a-zA-Z0-9_.]+" title="<?= label('Alphanumeric, dots and underscores only', 'Nur alphanumerisch, Punkte und Unterstriche') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Password', 'Passwort') ?> <span class="required-mark">*</span></label>
                    <input type="password" name="password" class="form-input" required minlength="8">
                    <span class="form-hint"><?= label('Minimum 8 characters', 'Mindestens 8 Zeichen') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Display Name', 'Anzeigename') ?> <span class="required-mark">*</span></label>
                    <input type="text" name="display_name" class="form-input" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Role', 'Rolle') ?></label>
                        <select name="role" class="form-select">
                            <option value="user"><?= label('Standard User', 'Standardbenutzer') ?></option>
                            <option value="admin"><?= label('Administrator', 'Administrator') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Language', 'Sprache') ?></label>
                        <select name="language" class="form-select">
                            <option value="en">English</option>
                            <option value="de">Deutsch</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">
                    <?= label('Cancel', 'Abbrechen') ?>
                </button>
                <button type="submit" class="btn btn-primary"><?= label('Create User', 'Benutzer erstellen') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= label('Edit User', 'Benutzer bearbeiten') ?></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= label('Username', 'Benutzername') ?></label>
                    <input type="text" id="edit_username" class="form-input" disabled style="opacity: 0.6;">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('New Password', 'Neues Passwort') ?></label>
                    <input type="password" name="password" class="form-input" minlength="8">
                    <span class="form-hint"><?= label('Leave blank to keep current password', 'Leer lassen, um das aktuelle Passwort beizubehalten') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Display Name', 'Anzeigename') ?> <span class="required-mark">*</span></label>
                    <input type="text" name="display_name" id="edit_display_name" class="form-input" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Role', 'Rolle') ?></label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="user"><?= label('Standard User', 'Standardbenutzer') ?></option>
                            <option value="admin"><?= label('Administrator', 'Administrator') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Language', 'Sprache') ?></label>
                        <select name="language" id="edit_language" class="form-select">
                            <option value="en">English</option>
                            <option value="de">Deutsch</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Status', 'Status') ?></label>
                    <select name="is_active" id="edit_active" class="form-select">
                        <option value="1"><?= label('Active', 'Aktiv') ?></option>
                        <option value="0"><?= label('Inactive', 'Inaktiv') ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">
                    <?= label('Cancel', 'Abbrechen') ?>
                </button>
                <button type="submit" class="btn btn-primary"><?= label('Save Changes', 'Änderungen speichern') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_display_name').value = user.display_name;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_language').value = user.language;
    document.getElementById('edit_active').value = user.is_active;
    document.getElementById('editModal').classList.add('active');
}
</script>
