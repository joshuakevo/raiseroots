<?php
// GET /api/lookup.php?national_id=XXX - a lender asks about one national ID.
// Returns that person's loans at OTHER lenders only (never the caller's own),
// as a per-lender summary with the client's name as that lender recorded it.

require __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['error' => 'Use GET.']);
}
$lender = authenticate();

$nid = normalise_nid((string) ($_GET['national_id'] ?? ''));
if ($nid === null) {
    respond(422, ['error' => 'national_id is required.']);
}

$stmt = db()->prepare(
    'SELECT l.name AS lender, r.client_name, r.national_id, r.status, r.principal, r.outstanding, r.disbursed_on, r.days_in_arrears, r.updated_at
       FROM credit_records r JOIN lenders l ON l.id = r.lender_id
      WHERE r.national_id = ? AND r.lender_id <> ?
      ORDER BY FIELD(r.status, "defaulted", "active", "closed"), r.disbursed_on DESC'
);
$stmt->execute([$nid, $lender['id']]);
$records = $stmt->fetchAll();

db()->prepare('INSERT INTO lookup_log (lender_id, national_id, matches) VALUES (?, ?, ?)')
    ->execute([$lender['id'], $nid, count($records)]);

foreach ($records as &$r) {
    $r['principal']       = (float) $r['principal'];
    $r['outstanding']     = (float) $r['outstanding'];
    $r['days_in_arrears'] = (int) $r['days_in_arrears'];
}

respond(200, ['records' => $records]);
