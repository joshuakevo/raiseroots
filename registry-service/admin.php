<?php
// Registry admin: add a lender (shows its API key once), enable/disable lenders.
// Protected by the admin_token in config.php - entered on every action, never stored.

require __DIR__ . '/bootstrap.php';

$token   = (string) ($_POST['token'] ?? '');
$authed  = $token !== '' && hash_equals($config['admin_token'], $token);
$message = null;
$newKey  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$authed) {
    $message = 'Wrong admin token.';
} elseif ($authed && ($_POST['action'] ?? '') === 'add') {
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        $message = 'Enter the organisation name.';
    } else {
        $newKey = bin2hex(random_bytes(24));
        db()->prepare('INSERT INTO lenders (name, api_key_hash) VALUES (?, ?)')->execute([$name, hash('sha256', $newKey)]);
        $message = "Added {$name}.";
    }
} elseif ($authed && ($_POST['action'] ?? '') === 'toggle') {
    db()->prepare('UPDATE lenders SET is_active = 1 - is_active WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
    $message = 'Lender updated.';
}

$lenders = $authed ? db()->query(
    'SELECT l.id, l.name, l.is_active, l.created_at,
            (SELECT COUNT(*) FROM credit_records r WHERE r.lender_id = l.id) AS records,
            (SELECT MAX(r.updated_at) FROM credit_records r WHERE r.lender_id = l.id) AS last_push,
            (SELECT COUNT(*) FROM lookup_log g WHERE g.lender_id = l.id) AS lookups
       FROM lenders l ORDER BY l.name'
)->fetchAll() : [];
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ElTech Credit Registry</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:960px">
    <h4 class="fw-bold mb-3">ElTech Credit Registry — Admin</h4>
    <?php if ($message): ?><div class="alert alert-info"><?= $h($message) ?></div><?php endif; ?>
    <?php if ($newKey): ?>
        <div class="alert alert-warning">
            <strong>API key (shown once - copy it now):</strong>
            <div class="font-monospace fs-6 mt-1 user-select-all"><?= $h($newKey) ?></div>
            <div class="small mt-1">Paste it into that system's Settings → Credit Registry → API Key.</div>
        </div>
    <?php endif; ?>

    <?php if (!$authed): ?>
        <form method="POST" class="card card-body">
            <label class="form-label fw-semibold">Admin token</label>
            <div class="d-flex gap-2"><input type="password" name="token" class="form-control" required autofocus><button class="btn btn-primary">Open</button></div>
        </form>
    <?php else: ?>
        <form method="POST" class="card card-body mb-3">
            <input type="hidden" name="token" value="<?= $h($token) ?>"><input type="hidden" name="action" value="add">
            <label class="form-label fw-semibold">Add a lender (one per ElTech system)</label>
            <div class="d-flex gap-2"><input type="text" name="name" class="form-control" placeholder="e.g. Sipmart Uganda Limited" required><button class="btn btn-success">Add &amp; create key</button></div>
        </form>
        <div class="card"><table class="table mb-0 align-middle">
            <thead class="table-light"><tr><th>Lender</th><th class="text-end">Loans held</th><th>Last update</th><th class="text-end">Lookups</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lenders as $l): ?>
                <tr>
                    <td><?= $h($l['name']) ?></td>
                    <td class="text-end"><?= (int) $l['records'] ?></td>
                    <td class="small"><?= $h($l['last_push'] ?? '—') ?></td>
                    <td class="text-end"><?= (int) $l['lookups'] ?></td>
                    <td><?= $l['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>' ?></td>
                    <td class="text-end">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="token" value="<?= $h($token) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"><?= $l['is_active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lenders): ?><tr><td colspan="6" class="text-center text-muted py-3">No lenders yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
</body>
</html>
