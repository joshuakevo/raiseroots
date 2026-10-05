<?php
// POST /api/loans.php - a lender upserts summaries of its own loans.
// Body: {"loans": [{"loan_ref","national_id","client_name","status","principal","outstanding","disbursed_on","days_in_arrears"}, ...]}
// status "removed" deletes that loan's record (e.g. disbursement reversed, loan deleted).

require __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Use POST.']);
}
$lender = authenticate();

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$loans   = $payload['loans'] ?? null;
if (!is_array($loans) || count($loans) > 500) {
    respond(422, ['error' => 'Send {"loans": [...]} with at most 500 loans per request.']);
}

$allowed = ['active', 'defaulted', 'closed', 'removed'];
$upsert  = db()->prepare(
    'INSERT INTO credit_records (lender_id, loan_ref, national_id, client_name, status, principal, outstanding, disbursed_on, days_in_arrears, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE national_id = VALUES(national_id), client_name = VALUES(client_name), status = VALUES(status), principal = VALUES(principal),
         outstanding = VALUES(outstanding), disbursed_on = VALUES(disbursed_on), days_in_arrears = VALUES(days_in_arrears), updated_at = NOW()'
);
$delete = db()->prepare('DELETE FROM credit_records WHERE lender_id = ? AND loan_ref = ?');

$saved = $removed = 0;
$skipped = [];
db()->beginTransaction();
foreach ($loans as $i => $loan) {
    $ref    = substr(trim((string) ($loan['loan_ref'] ?? '')), 0, 64);
    $status = (string) ($loan['status'] ?? '');
    if ($ref === '' || !in_array($status, $allowed, true)) {
        $skipped[] = $i;
        continue;
    }
    if ($status === 'removed') {
        $delete->execute([$lender['id'], $ref]);
        $removed++;
        continue;
    }
    $nid  = normalise_nid((string) ($loan['national_id'] ?? ''));
    $date = $loan['disbursed_on'] ?? null;
    if ($nid === null || ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date))) {
        $skipped[] = $i;
        continue;
    }
    $upsert->execute([
        $lender['id'], $ref, $nid, substr(trim((string) ($loan['client_name'] ?? '')), 0, 150), $status,
        round((float) ($loan['principal'] ?? 0), 2),
        round((float) ($loan['outstanding'] ?? 0), 2),
        $date,
        max(0, (int) ($loan['days_in_arrears'] ?? 0)),
    ]);
    $saved++;
}
db()->commit();

respond(200, ['saved' => $saved, 'removed' => $removed, 'skipped' => $skipped]);
