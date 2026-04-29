<?php
/**
 * SOC Reporting System - Asset Management (Admin)
 */

// Handle asset creation
if (isPost() && post('action') === 'create_asset') {
    $token = post('csrf_token');
    if (!Auth::validateCsrf($token)) {
        setFlash('error', label('Invalid session.', 'Ungültige Sitzung.'));
    } else {
        $vendor = sanitize(post('vendor'));
        $model = sanitize(post('model'));
        $productVersion = sanitize(post('product_version'));
        $softwareVersion = sanitize(post('software_version'));
        $hostname = sanitize(post('hostname'));
        $ipAddress = sanitize(post('ip_address'));
        $machineId = post('machine_id') ? (int) post('machine_id') : null;
        $notes = sanitize(post('notes'));

        if (empty($vendor) || empty($model)) {
            setFlash('error', label('Vendor and Model are required.', 'Hersteller und Modell sind erforderlich.'));
        } else {
            Database::insert(Database::system(), 'assets', [
                'vendor' => $vendor,
                'model' => $model,
                'product_version' => $productVersion ?: null,
                'software_version' => $softwareVersion ?: null,
                'hostname' => $hostname ?: null,
                'ip_address' => $ipAddress ?: null,
                'machine_id' => $machineId,
                'notes' => $notes ?: null,
                'created_by' => $_SESSION['user_id']
            ]);
            auditLog('asset_created', "Asset created: $vendor $model");
            setFlash('success', label('Device added successfully.', 'Gerät erfolgreich hinzugefügt.'));
        }
    }
    $csrfToken = Auth::generateCsrf();
    redirect('index.php?page=assets');
}

// Handle asset update
if (isPost() && post('action') === 'update_asset') {
    $token = post('csrf_token');
    if (!Auth::validateCsrf($token)) {
        setFlash('error', label('Invalid session.', 'Ungültige Sitzung.'));
    } else {
        $assetId = (int) post('asset_id');
        $vendor = sanitize(post('vendor'));
        $model = sanitize(post('model'));

        if (empty($vendor) || empty($model)) {
            setFlash('error', label('Vendor and Model are required.', 'Hersteller und Modell sind erforderlich.'));
        } else {
            Database::update(Database::system(), 'assets', [
                'vendor' => $vendor,
                'model' => $model,
                'product_version' => sanitize(post('product_version')) ?: null,
                'software_version' => sanitize(post('software_version')) ?: null,
                'hostname' => sanitize(post('hostname')) ?: null,
                'ip_address' => sanitize(post('ip_address')) ?: null,
                'machine_id' => post('machine_id') ? (int) post('machine_id') : null,
                'notes' => sanitize(post('notes')) ?: null
            ], 'id = ?', [$assetId]);
            auditLog('asset_updated', "Asset $assetId updated: $vendor $model");
            setFlash('success', label('Device updated successfully.', 'Gerät erfolgreich aktualisiert.'));
        }
    }
    $csrfToken = Auth::generateCsrf();
    redirect('index.php?page=assets');
}

// Handle asset deletion
if (isPost() && post('action') === 'delete_asset') {
    $token = post('csrf_token');
    if (!Auth::validateCsrf($token)) {
        setFlash('error', label('Invalid session.', 'Ungültige Sitzung.'));
    } else {
        $assetId = (int) post('asset_id');
        $asset = Database::fetchOne(Database::system(), "SELECT vendor, model FROM assets WHERE id = ?", [$assetId]);
        if ($asset) {
            Database::query(Database::system(), "DELETE FROM assets WHERE id = ?", [$assetId]);
            auditLog('asset_deleted', "Asset $assetId deleted: {$asset['vendor']} {$asset['model']}");
            setFlash('success', label('Device deleted successfully.', 'Gerät erfolgreich gelöscht.'));
        }
    }
    $csrfToken = Auth::generateCsrf();
    redirect('index.php?page=assets');
}

// Fetch data
$filterVendor = get('vendor', '');
$filterMachine = get('machine', '');

$where = [];
$params = [];
if ($filterVendor) {
    $where[] = "a.vendor = ?";
    $params[] = $filterVendor;
}
if ($filterMachine) {
    $where[] = "a.machine_id = ?";
    $params[] = (int) $filterMachine;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$assets = Database::fetchAll(Database::system(),
    "SELECT a.*, m.name AS machine_name, m.slot_number, u.display_name AS created_by_name
     FROM assets a
     LEFT JOIN machines m ON a.machine_id = m.id
     LEFT JOIN users u ON a.created_by = u.id
     $whereClause
     ORDER BY a.vendor, a.model",
    $params
);

$allMachines = Database::fetchAll(Database::system(), "SELECT * FROM machines ORDER BY slot_number");

// Get distinct vendors for filter
$vendors = Database::fetchAll(Database::system(), "SELECT DISTINCT vendor FROM assets ORDER BY vendor");
?>

<script>document.getElementById('pageTitle').textContent = '<?= label('Asset Management', 'Geräteverwaltung') ?>';</script>

<!-- Splunk-style Filter Bar -->
<div class="splunk-toolbar">
    <div class="splunk-toolbar-left">
        <div class="splunk-toolbar-title"><?= label('Asset Management', 'Geräteverwaltung') ?></div>
        <div class="splunk-toolbar-subtitle"><?= count($assets) ?> <?= label('devices total', 'Geräte insgesamt') ?></div>
    </div>
    <div class="splunk-toolbar-right">
        <div class="splunk-filter-group">
            <span class="splunk-filter-label"><?= label('Machine:', 'Maschine:') ?></span>
            <a href="index.php?page=assets"
               class="splunk-pill <?= !$filterMachine ? 'active' : '' ?>">
                <?= label('All Machines', 'Alle Maschinen') ?>
            </a>
            <?php foreach ($allMachines as $m): ?>
            <a href="index.php?page=assets&machine=<?= $m['id'] ?><?= $filterVendor ? '&vendor=' . urlencode($filterVendor) : '' ?>"
               class="splunk-pill <?= $filterMachine == $m['id'] ? 'active' : '' ?>">
                <?= e($m['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($vendors)): ?>
        <div class="splunk-filter-group">
            <span class="splunk-filter-label"><?= label('Vendor:', 'Hersteller:') ?></span>
            <form method="GET" style="display: inline-block;">
                <input type="hidden" name="page" value="assets">
                <?php if ($filterMachine): ?>
                <input type="hidden" name="machine" value="<?= $filterMachine ?>">
                <?php endif; ?>
                <select name="vendor" class="form-select" onchange="this.form.submit()"
                        style="padding: 6px 12px; font-size: 0.85rem; min-width: 150px; background: rgba(26, 34, 52, 0.5); border: 1px solid rgba(160, 174, 192, 0.2);">
                    <option value=""><?= label('All Vendors', 'Alle Hersteller') ?></option>
                    <?php foreach ($vendors as $v): ?>
                    <option value="<?= e($v['vendor']) ?>" <?= $filterVendor === $v['vendor'] ? 'selected' : '' ?>>
                        <?= e($v['vendor']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
        <button class="splunk-pill" onclick="document.getElementById('createModal').classList.add('active')"
                style="cursor: pointer; background: rgba(0, 212, 255, 0.12); color: #00d4ff; border-color: rgba(0, 212, 255, 0.3);">
            + <?= label('Add Device', 'Gerät hinzufügen') ?>
        </button>
    </div>
</div>

<!-- Quick Links -->
<div style="margin-bottom: 16px; display: flex; gap: 8px;">
    <a href="index.php?page=users" class="btn btn-secondary btn-sm"><?= label('Users', 'Benutzer') ?></a>
    <a href="index.php?page=machines" class="btn btn-secondary btn-sm"><?= label('Machines', 'Maschinen') ?></a>
</div>

<!-- Assets Table -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= label('Vendor', 'Hersteller') ?></th>
                <th><?= label('Model', 'Modell') ?></th>
                <th><?= label('Product Version', 'Produktversion') ?></th>
                <th><?= label('Software Version', 'Software-Version') ?></th>
                <th><?= label('Hostname', 'Hostname') ?></th>
                <th><?= label('IP Address', 'IP-Adresse') ?></th>
                <th><?= label('Machine', 'Maschine') ?></th>
                <th><?= label('Created', 'Erstellt') ?></th>
                <th class="text-right"><?= label('Actions', 'Aktionen') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($assets)): ?>
            <tr>
                <td colspan="10" style="text-align: center; padding: 2rem; opacity: 0.5;">
                    <?= label('No devices found. Add your first device above.', 'Keine Geräte gefunden. Fügen Sie oben Ihr erstes Gerät hinzu.') ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($assets as $asset): ?>
            <tr>
                <td class="text-mono"><?= $asset['id'] ?></td>
                <td><strong><?= e($asset['vendor']) ?></strong></td>
                <td><?= e($asset['model']) ?></td>
                <td><span class="text-mono"><?= e($asset['product_version'] ?? '—') ?></span></td>
                <td><span class="text-mono"><?= e($asset['software_version'] ?? '—') ?></span></td>
                <td><span class="text-mono"><?= e($asset['hostname'] ?? '—') ?></span></td>
                <td><span class="text-mono"><?= e($asset['ip_address'] ?? '—') ?></span></td>
                <td>
                    <?php if ($asset['machine_name']): ?>
                    <span class="badge badge-submitted"><?= e($asset['machine_name']) ?></span>
                    <?php else: ?>
                    <span style="opacity: 0.4;">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="timestamp"><?= formatDate($asset['created_at']) ?></span></td>
                <td class="text-right">
                    <div class="flex gap-1" style="justify-content: flex-end;">
                        <button class="btn btn-secondary btn-sm"
                                onclick="editAsset(<?= htmlspecialchars(json_encode($asset), ENT_QUOTES) ?>)">
                            <?= label('Edit', 'Bearbeiten') ?>
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('<?= label('Delete this device?', 'Dieses Gerät löschen?') ?>');">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete_asset">
                            <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <?= label('Delete', 'Löschen') ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Create Asset Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= label('Add New Device', 'Neues Gerät hinzufügen') ?></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create_asset">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Vendor', 'Hersteller') ?> <span class="required-mark">*</span></label>
                        <input type="text" name="vendor" class="form-input" required
                               placeholder="<?= label('e.g. Cisco, Fortinet, Palo Alto', 'z.B. Cisco, Fortinet, Palo Alto') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Model', 'Modell') ?> <span class="required-mark">*</span></label>
                        <input type="text" name="model" class="form-input" required
                               placeholder="<?= label('e.g. Meraki MX84', 'z.B. Meraki MX84') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Product Version', 'Produktversion') ?></label>
                        <input type="text" name="product_version" class="form-input"
                               placeholder="<?= label('e.g. v2, Rev B', 'z.B. v2, Rev B') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Software Version', 'Software-Version') ?></label>
                        <input type="text" name="software_version" class="form-input"
                               placeholder="<?= label('e.g. 17.6.3', 'z.B. 17.6.3') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Assigned Machine', 'Zugewiesene Maschine') ?></label>
                        <select name="machine_id" class="form-select">
                            <option value=""><?= label('— None —', '— Keine —') ?></option>
                            <?php foreach ($allMachines as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Hostname', 'Hostname') ?></label>
                        <input type="text" name="hostname" class="form-input"
                               placeholder="<?= label('e.g. fw-core-01', 'z.B. fw-core-01') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('IP Address', 'IP-Adresse') ?></label>
                        <input type="text" name="ip_address" class="form-input"
                               placeholder="<?= label('e.g. 192.168.1.1', 'z.B. 192.168.1.1') ?>">
                    </div>
                    <div class="form-group">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Notes', 'Notizen') ?></label>
                    <textarea name="notes" class="form-textarea" rows="3"
                              placeholder="<?= label('Optional notes about this device', 'Optionale Notizen zu diesem Gerät') ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">
                    <?= label('Cancel', 'Abbrechen') ?>
                </button>
                <button type="submit" class="btn btn-primary"><?= label('Add Device', 'Gerät hinzufügen') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Asset Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= label('Edit Device', 'Gerät bearbeiten') ?></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="update_asset">
            <input type="hidden" name="asset_id" id="edit_asset_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Vendor', 'Hersteller') ?> <span class="required-mark">*</span></label>
                        <input type="text" name="vendor" id="edit_vendor" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Model', 'Modell') ?> <span class="required-mark">*</span></label>
                        <input type="text" name="model" id="edit_model" class="form-input" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Product Version', 'Produktversion') ?></label>
                        <input type="text" name="product_version" id="edit_product_version" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Software Version', 'Software-Version') ?></label>
                        <input type="text" name="software_version" id="edit_software_version" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('Assigned Machine', 'Zugewiesene Maschine') ?></label>
                        <select name="machine_id" id="edit_machine_id" class="form-select">
                            <option value=""><?= label('— None —', '— Keine —') ?></option>
                            <?php foreach ($allMachines as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= label('Hostname', 'Hostname') ?></label>
                        <input type="text" name="hostname" id="edit_hostname" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= label('IP Address', 'IP-Adresse') ?></label>
                        <input type="text" name="ip_address" id="edit_ip_address" class="form-input">
                    </div>
                    <div class="form-group">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= label('Notes', 'Notizen') ?></label>
                    <textarea name="notes" id="edit_notes" class="form-textarea" rows="3"></textarea>
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
function editAsset(asset) {
    document.getElementById('edit_asset_id').value = asset.id;
    document.getElementById('edit_vendor').value = asset.vendor;
    document.getElementById('edit_model').value = asset.model;
    document.getElementById('edit_product_version').value = asset.product_version || '';
    document.getElementById('edit_software_version').value = asset.software_version || '';
    document.getElementById('edit_hostname').value = asset.hostname || '';
    document.getElementById('edit_ip_address').value = asset.ip_address || '';
    document.getElementById('edit_machine_id').value = asset.machine_id || '';
    document.getElementById('edit_notes').value = asset.notes || '';
    document.getElementById('editModal').classList.add('active');
}
</script>
