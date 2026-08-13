<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

if (!PermissionMiddleware::check('purchase.view', 'view')) {
    http_response_code(403);
    exit('Access Denied');
}

$companyId = (int) Session::get('company_id');
$branchId  = (int) Session::get('branch_id');
$userId    = (int) Session::get('user_id');

$db = Database::connect();

$message = '';
$error = '';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): float
{
    return round((float)$value, 3);
}

/*
|--------------------------------------------------------------------------
| Save Quality Check
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (!PermissionMiddleware::check('purchase.create', 'create')) {
            throw new Exception('You do not have permission to create Quality Check.');
        }

        $purchaseVoucherId = (int)($_POST['purchase_voucher_id'] ?? 0);
        $qcNumber = trim($_POST['qc_number'] ?? '');
        $qcDate = $_POST['qc_date'] ?? date('Y-m-d');
        $status = strtoupper(trim($_POST['status'] ?? 'PENDING'));
        $remarks = trim($_POST['remarks'] ?? '');

        $productIds = $_POST['product_id'] ?? [];
        $voucherItemIds = $_POST['voucher_item_id'] ?? [];
        $receivedQtys = $_POST['received_qty'] ?? [];
        $acceptedQtys = $_POST['accepted_qty'] ?? [];
        $rejectedQtys = $_POST['rejected_qty'] ?? [];
        $batchIds = $_POST['batch_id'] ?? [];
        $rejectionReasons = $_POST['rejection_reason'] ?? [];
        $itemRemarks = $_POST['item_remarks'] ?? [];

        if ($purchaseVoucherId <= 0) {
            throw new Exception('Please select a purchase.');
        }

        if ($qcNumber === '') {
            throw new Exception('QC number is required.');
        }

        if (!in_array($status, ['PENDING', 'PASSED', 'PARTIAL', 'FAILED'], true)) {
            throw new Exception('Invalid Quality Check status.');
        }

        /*
        |--------------------------------------------------------------------------
        | Purchase validation
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            SELECT
                v.id,
                v.voucher_number,
                v.voucher_date,
                v.party_id,
                v.warehouse_id,
                p.party_name,
                w.warehouse_name
            FROM vouchers v
            LEFT JOIN parties p ON p.id = v.party_id
            LEFT JOIN warehouses w ON w.id = v.warehouse_id
            WHERE v.id = :id
              AND v.company_id = :company_id
              AND v.branch_id = :branch_id
              AND v.voucher_type = 'PURCHASE'
              AND v.status = 'POSTED'
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $purchaseVoucherId,
            'company_id' => $companyId,
            'branch_id' => $branchId
        ]);

        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) {
            throw new Exception('Invalid or non-posted purchase selected.');
        }

        if (count($voucherItemIds) === 0) {
            throw new Exception('No purchase items found.');
        }

        /*
        |--------------------------------------------------------------------------
        | Validate duplicate QC number
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            SELECT id
            FROM quality_checks
            WHERE company_id = :company_id
              AND branch_id = :branch_id
              AND qc_number = :qc_number
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'qc_number' => $qcNumber
        ]);

        if ($stmt->fetch()) {
            throw new Exception('QC number already exists.');
        }

        /*
        |--------------------------------------------------------------------------
        | Validate purchase items against DB
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            SELECT
                vi.id AS voucher_item_id,
                vi.product_id,
                vi.qty,
                vi.free_qty,
                p.product_name,
                p.sku
            FROM voucher_items vi
            INNER JOIN products p ON p.id = vi.product_id
            WHERE vi.voucher_id = :voucher_id
            ORDER BY vi.id
        ");
        $stmt->execute(['voucher_id' => $purchaseVoucherId]);
        $dbItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dbItemMap = [];
        foreach ($dbItems as $dbItem) {
            $dbItemMap[(int)$dbItem['voucher_item_id']] = $dbItem;
        }

        $items = [];
        $totalReceived = 0;
        $totalAccepted = 0;
        $totalRejected = 0;

        foreach ($voucherItemIds as $index => $voucherItemId) {

            $voucherItemId = (int)$voucherItemId;

            if ($voucherItemId <= 0) {
                continue;
            }

            if (!isset($dbItemMap[$voucherItemId])) {
                throw new Exception('Invalid purchase item selected.');
            }

            $dbItem = $dbItemMap[$voucherItemId];

            $productId = (int)($productIds[$index] ?? 0);

            if ($productId !== (int)$dbItem['product_id']) {
                throw new Exception('Product mismatch detected.');
            }

            $receivedQty = money($receivedQtys[$index] ?? 0);
            $acceptedQty = money($acceptedQtys[$index] ?? 0);
            $rejectedQty = money($rejectedQtys[$index] ?? 0);

            if ($receivedQty <= 0) {
                throw new Exception(
                    'Received quantity must be greater than zero for ' .
                    $dbItem['product_name']
                );
            }

            if ($acceptedQty < 0 || $rejectedQty < 0) {
                throw new Exception('Accepted/rejected quantity cannot be negative.');
            }

            if (abs(($acceptedQty + $rejectedQty) - $receivedQty) > 0.0001) {
                throw new Exception(
                    'Accepted + Rejected must equal Received for ' .
                    $dbItem['product_name']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent QC quantity from exceeding purchase quantity
            |--------------------------------------------------------------------------
            */
            $purchaseQty = money($dbItem['qty']) + money($dbItem['free_qty']);

            if ($receivedQty > $purchaseQty + 0.0001) {
                throw new Exception(
                    'QC received quantity cannot exceed purchased quantity for ' .
                    $dbItem['product_name']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate QC quantity
            |--------------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(qci.received_qty), 0)
                FROM quality_check_items qci
                INNER JOIN quality_checks qc
                    ON qc.id = qci.quality_check_id
                WHERE qci.voucher_item_id = :voucher_item_id
                  AND qc.status <> 'CANCELLED'
            ");
            $stmt->execute([
                'voucher_item_id' => $voucherItemId
            ]);

            $alreadyQcQty = money($stmt->fetchColumn());

            if (($alreadyQcQty + $receivedQty) > $purchaseQty + 0.0001) {
                throw new Exception(
                    'QC quantity exceeds remaining quantity for ' .
                    $dbItem['product_name'] .
                    '. Already checked: ' .
                    number_format($alreadyQcQty, 3)
                );
            }

            $batchId = !empty($batchIds[$index])
                ? (int)$batchIds[$index]
                : null;

            $reason = trim($rejectionReasons[$index] ?? '');
            $itemRemark = trim($itemRemarks[$index] ?? '');

            if ($rejectedQty > 0 && $reason === '') {
                throw new Exception(
                    'Rejection reason is required for ' .
                    $dbItem['product_name']
                );
            }

            $items[] = [
                'voucher_item_id' => $voucherItemId,
                'product_id' => $productId,
                'batch_id' => $batchId,
                'received_qty' => $receivedQty,
                'accepted_qty' => $acceptedQty,
                'rejected_qty' => $rejectedQty,
                'rejection_reason' => $reason !== '' ? $reason : null,
                'remarks' => $itemRemark !== '' ? $itemRemark : null
            ];

            $totalReceived += $receivedQty;
            $totalAccepted += $acceptedQty;
            $totalRejected += $rejectedQty;
        }

        if (count($items) === 0) {
            throw new Exception('Please add at least one QC item.');
        }

        /*
        |--------------------------------------------------------------------------
        | Auto status if not explicitly appropriate
        |--------------------------------------------------------------------------
        */
        if ($totalRejected <= 0 && $totalAccepted > 0) {
            $status = 'PASSED';
        } elseif ($totalAccepted > 0 && $totalRejected > 0) {
            $status = 'PARTIAL';
        } elseif ($totalAccepted <= 0 && $totalRejected > 0) {
            $status = 'FAILED';
        } else {
            $status = 'PENDING';
        }

        $db->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Insert QC header
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            INSERT INTO quality_checks
            (
                company_id,
                branch_id,
                warehouse_id,
                purchase_voucher_id,
                supplier_id,
                qc_number,
                qc_date,
                status,
                remarks,
                checked_by,
                created_at,
                updated_at
            )
            VALUES
            (
                :company_id,
                :branch_id,
                :warehouse_id,
                :purchase_voucher_id,
                :supplier_id,
                :qc_number,
                :qc_date,
                :status,
                :remarks,
                :checked_by,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $purchase['warehouse_id'],
            'purchase_voucher_id' => $purchaseVoucherId,
            'supplier_id' => $purchase['party_id'],
            'qc_number' => $qcNumber,
            'qc_date' => $qcDate,
            'status' => $status,
            'remarks' => $remarks !== '' ? $remarks : null,
            'checked_by' => $userId
        ]);

        $qcId = (int)$stmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | Insert QC items
        |--------------------------------------------------------------------------
        */
        $itemStmt = $db->prepare("
            INSERT INTO quality_check_items
            (
                quality_check_id,
                voucher_item_id,
                product_id,
                batch_id,
                received_qty,
                accepted_qty,
                rejected_qty,
                rejection_reason,
                remarks,
                created_at,
                updated_at
            )
            VALUES
            (
                :quality_check_id,
                :voucher_item_id,
                :product_id,
                :batch_id,
                :received_qty,
                :accepted_qty,
                :rejected_qty,
                :rejection_reason,
                :remarks,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        foreach ($items as $item) {
            $itemStmt->execute([
                'quality_check_id' => $qcId,
                'voucher_item_id' => $item['voucher_item_id'],
                'product_id' => $item['product_id'],
                'batch_id' => $item['batch_id'],
                'received_qty' => $item['received_qty'],
                'accepted_qty' => $item['accepted_qty'],
                'rejected_qty' => $item['rejected_qty'],
                'rejection_reason' => $item['rejection_reason'],
                'remarks' => $item['remarks']
            ]);
        }

        $db->commit();

        $message = 'Quality Check saved successfully. QC No: ' . $qcNumber .
            ' | Accepted: ' . number_format($totalAccepted, 3) .
            ' | Rejected: ' . number_format($totalRejected, 3);

    } catch (Throwable $ex) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error = $ex->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Load posted purchases having remaining QC quantity
|--------------------------------------------------------------------------
*/
$purchases = [];

try {

    $stmt = $db->prepare("
        SELECT
            v.id,
            v.voucher_number,
            v.voucher_date,
            v.party_id,
            v.warehouse_id,
            p.party_name,
            w.warehouse_name
        FROM vouchers v
        LEFT JOIN parties p ON p.id = v.party_id
        LEFT JOIN warehouses w ON w.id = v.warehouse_id
        WHERE v.company_id = :company_id
          AND v.branch_id = :branch_id
          AND v.voucher_type = 'PURCHASE'
          AND v.status = 'POSTED'
        ORDER BY v.voucher_date DESC, v.id DESC
    ");

    $stmt->execute([
        'company_id' => $companyId,
        'branch_id' => $branchId
    ]);

    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    if ($error === '') {
        $error = 'Purchase load error: ' . $ex->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Existing QC list
|--------------------------------------------------------------------------
*/
$qualityChecks = [];

try {

    $stmt = $db->prepare("
        SELECT
            qc.id,
            qc.qc_number,
            qc.qc_date,
            qc.status,
            qc.purchase_voucher_id,
            v.voucher_number,
            p.party_name,
            qc.created_at
        FROM quality_checks qc
        INNER JOIN vouchers v
            ON v.id = qc.purchase_voucher_id
        LEFT JOIN parties p
            ON p.id = qc.supplier_id
        WHERE qc.company_id = :company_id
          AND qc.branch_id = :branch_id
        ORDER BY qc.id DESC
    ");

    $stmt->execute([
        'company_id' => $companyId,
        'branch_id' => $branchId
    ]);

    $qualityChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    if ($error === '') {
        $error = 'QC list error: ' . $ex->getMessage();
    }
}

$defaultQcNumber = 'QC-' . date('Ymd-His');
$today = date('Y-m-d');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quality Check - VyaapaarOS</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #111827;
}

.header {
    background: #111827;
    color: #fff;
    padding: 18px 30px;
}

.header h1 { margin: 0; }

.container {
    width: 1250px;
    max-width: calc(100% - 30px);
    margin: 25px auto;
}

.card {
    background: #fff;
    border-radius: 10px;
    padding: 22px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.field label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
}

input, select, textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

textarea { min-height: 80px; }

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th, td {
    border-bottom: 1px solid #e5e7eb;
    padding: 9px;
    text-align: left;
    vertical-align: middle;
}

th { background: #f8fafc; }

.btn {
    border: 0;
    border-radius: 6px;
    padding: 10px 16px;
    cursor: pointer;
    font-weight: bold;
}

.btn-primary { background: #2563eb; color: #fff; }
.btn-success { background: #059669; color: #fff; }
.btn-secondary { background: #6b7280; color: #fff; }
.btn-danger { background: #dc2626; color: #fff; }

.actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.back {
    display: inline-block;
    margin-bottom: 15px;
    text-decoration: none;
    color: #2563eb;
}

.status {
    font-weight: bold;
}

.status-PASSED { color: #059669; }
.status-PARTIAL { color: #d97706; }
.status-FAILED { color: #dc2626; }
.status-PENDING { color: #6b7280; }

.summary {
    display: flex;
    gap: 25px;
    margin-top: 15px;
    font-weight: bold;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 5px;
    background: #f3f4f6;
}

@media (max-width: 900px) {
    .grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .grid { grid-template-columns: 1fr; }
}
</style>
</head>

<body>

<div class="header">
    <h1>🔎 Quality Check</h1>
</div>

<div class="container">

<a class="back" href="dashboard.php">← Back to Dashboard</a>

<?php if ($message !== ''): ?>
<div class="alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
<h2>Create Quality Check</h2>

<form method="POST" id="qcForm">

<div class="grid">

<div class="field">
<label>QC Number *</label>
<input type="text" name="qc_number" value="<?= e($defaultQcNumber) ?>" required>
</div>

<div class="field">
<label>QC Date *</label>
<input type="date" name="qc_date" value="<?= e($today) ?>" required>
</div>

<div class="field">
<label>Purchase *</label>
<select name="purchase_voucher_id" id="purchaseSelect" required onchange="loadPurchaseItems()">
<option value="">-- Select Posted Purchase --</option>
<?php foreach ($purchases as $purchase): ?>
<option
    value="<?= (int)$purchase['id'] ?>"
    data-supplier="<?= e($purchase['party_name'] ?? '-') ?>"
    data-warehouse="<?= e($purchase['warehouse_name'] ?? '-') ?>"
>
    <?= e($purchase['voucher_number']) ?>
    - <?= e($purchase['voucher_date']) ?>
    - <?= e($purchase['party_name'] ?? '-') ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="field">
<label>Supplier</label>
<input type="text" id="supplierDisplay" readonly>
</div>

<div class="field">
<label>Warehouse</label>
<input type="text" id="warehouseDisplay" readonly>
</div>

</div>

<br>

<div class="card" style="background:#f8fafc; box-shadow:none;">
<h3>Purchase Items / QC Result</h3>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>Product</th>
<th>Purchased Qty</th>
<th>Already QC</th>
<th>Remaining</th>
<th>Receive for QC</th>
<th>Accepted</th>
<th>Rejected</th>
<th>Batch ID</th>
<th>Rejection Reason</th>
<th>Remarks</th>
</tr>
</thead>
<tbody id="itemsBody">
<tr>
<td colspan="10">Select a posted purchase to load items.</td>
</tr>
</tbody>
</table>
</div>

<div class="summary">
<span>Received: <strong id="totalReceived">0.000</strong></span>
<span>Accepted: <strong id="totalAccepted">0.000</strong></span>
<span>Rejected: <strong id="totalRejected">0.000</strong></span>
</div>
</div>

<div class="field">
<label>QC Remarks</label>
<textarea name="remarks" placeholder="Quality check remarks..."></textarea>
</div>

<div class="actions">
<button type="submit" class="btn btn-success">Save Quality Check</button>
</div>

</form>
</div>

<div class="card">
<h2>Quality Check List</h2>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>ID</th>
<th>QC Number</th>
<th>Date</th>
<th>Purchase</th>
<th>Supplier</th>
<th>Status</th>
<th>Created</th>
</tr>
</thead>

<tbody>
<?php if (count($qualityChecks) === 0): ?>
<tr>
<td colspan="7">No Quality Checks found.</td>
</tr>
<?php else: ?>
<?php foreach ($qualityChecks as $qc): ?>
<tr>
<td><?= (int)$qc['id'] ?></td>
<td><?= e($qc['qc_number']) ?></td>
<td><?= e($qc['qc_date']) ?></td>
<td><?= e($qc['voucher_number']) ?></td>
<td><?= e($qc['party_name'] ?? '-') ?></td>
<td class="status status-<?= e($qc['status']) ?>">
<?= e($qc['status']) ?>
</td>
<td><?= e($qc['created_at']) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>

<script>
const purchases = <?= json_encode(
    $purchases,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function loadPurchaseItems() {

    const select = document.getElementById('purchaseSelect');
    const purchaseId = select.value;
    const itemsBody = document.getElementById('itemsBody');

    const option = select.options[select.selectedIndex];

    document.getElementById('supplierDisplay').value =
        option?.dataset?.supplier || '';

    document.getElementById('warehouseDisplay').value =
        option?.dataset?.warehouse || '';

    if (!purchaseId) {
        itemsBody.innerHTML =
            '<tr><td colspan="10">Select a posted purchase to load items.</td></tr>';
        calculateTotals();
        return;
    }

    itemsBody.innerHTML =
        '<tr><td colspan="10">Loading purchase items...</td></tr>';

    try {

        const response = await fetch(
            'quality_check_items.php?purchase_id=' +
            encodeURIComponent(purchaseId),
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        );

        if (!response.ok) {
            throw new Error('Unable to load purchase items.');
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Unable to load purchase items.');
        }

        if (!Array.isArray(data.items) || data.items.length === 0) {
            itemsBody.innerHTML =
                '<tr><td colspan="10">No items found.</td></tr>';
            calculateTotals();
            return;
        }

        itemsBody.innerHTML = '';

        data.items.forEach(item => {

            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>
                    <strong>${escapeHtml(item.product_name)}</strong>
                    ${item.sku ? '<br><small>' + escapeHtml(item.sku) + '</small>' : ''}
                    <input type="hidden"
                        name="voucher_item_id[]"
                        value="${item.voucher_item_id}">
                    <input type="hidden"
                        name="product_id[]"
                        value="${item.product_id}">
                </td>

                <td>${Number(item.purchased_qty).toFixed(3)}</td>

                <td>${Number(item.already_qc_qty).toFixed(3)}</td>

                <td class="remaining">
                    ${Number(item.remaining_qty).toFixed(3)}
                </td>

                <td>
                    <input
                        type="number"
                        name="received_qty[]"
                        class="qc-input received"
                        min="0"
                        max="${Number(item.remaining_qty)}"
                        step="0.001"
                        value="${Number(item.remaining_qty)}"
                        data-max="${Number(item.remaining_qty)}"
                        oninput="calculateRow(this)"
                    >
                </td>

                <td>
                    <input
                        type="number"
                        name="accepted_qty[]"
                        class="qc-input accepted"
                        min="0"
                        step="0.001"
                        value="${Number(item.remaining_qty)}"
                        oninput="calculateRow(this)"
                    >
                </td>

                <td>
                    <input
                        type="number"
                        name="rejected_qty[]"
                        class="qc-input rejected"
                        min="0"
                        step="0.001"
                        value="0"
                        oninput="calculateRow(this)"
                    >
                </td>

                <td>
                    <input
                        type="number"
                        name="batch_id[]"
                        min="0"
                        step="1"
                        placeholder="Optional"
                    >
                </td>

                <td>
                    <input
                        type="text"
                        name="rejection_reason[]"
                        class="reason"
                        placeholder="Required if rejected"
                    >
                </td>

                <td>
                    <input
                        type="text"
                        name="item_remarks[]"
                        placeholder="Remarks"
                    >
                </td>
            `;

            itemsBody.appendChild(tr);
        });

        calculateTotals();

    } catch (error) {

        itemsBody.innerHTML =
            '<tr><td colspan="10">' +
            escapeHtml(error.message) +
            '</td></tr>';

        calculateTotals();
    }
}

function calculateRow(input) {

    const row = input.closest('tr');

    if (!row) return;

    let received =
        parseFloat(row.querySelector('.received')?.value || 0);

    const max =
        parseFloat(row.querySelector('.received')?.dataset.max || 0);

    if (received < 0) received = 0;

    if (received > max) {
        received = max;
        row.querySelector('.received').value = max;
    }

    const acceptedInput = row.querySelector('.accepted');
    const rejectedInput = row.querySelector('.rejected');

    let accepted =
        parseFloat(acceptedInput?.value || 0);

    let rejected =
        parseFloat(rejectedInput?.value || 0);

    if (input.classList.contains('received')) {

        accepted = received;
        rejected = 0;

        acceptedInput.value = accepted.toFixed(3);
        rejectedInput.value = rejected.toFixed(3);

    } else if (input.classList.contains('accepted')) {

        if (accepted < 0) accepted = 0;

        if (accepted > received) {
            accepted = received;
            acceptedInput.value = accepted.toFixed(3);
        }

        rejected = received - accepted;
        rejectedInput.value = rejected.toFixed(3);

    } else if (input.classList.contains('rejected')) {

        if (rejected < 0) rejected = 0;

        if (rejected > received) {
            rejected = received;
            rejectedInput.value = rejected.toFixed(3);
        }

        accepted = received - rejected;
        acceptedInput.value = accepted.toFixed(3);
    }

    const reason = row.querySelector('.reason');

    if (parseFloat(rejectedInput.value || 0) > 0) {
        reason.required = true;
    } else {
        reason.required = false;
    }

    calculateTotals();
}

function calculateTotals() {

    let received = 0;
    let accepted = 0;
    let rejected = 0;

    document.querySelectorAll('#itemsBody tr').forEach(row => {

        received += parseFloat(
            row.querySelector('.received')?.value || 0
        );

        accepted += parseFloat(
            row.querySelector('.accepted')?.value || 0
        );

        rejected += parseFloat(
            row.querySelector('.rejected')?.value || 0
        );
    });

    document.getElementById('totalReceived').textContent =
        received.toFixed(3);

    document.getElementById('totalAccepted').textContent =
        accepted.toFixed(3);

    document.getElementById('totalRejected').textContent =
        rejected.toFixed(3);
}

document.getElementById('qcForm').addEventListener('submit', function(event) {

    let valid = true;

    document.querySelectorAll('#itemsBody tr').forEach(row => {

        const received =
            parseFloat(row.querySelector('.received')?.value || 0);

        const accepted =
            parseFloat(row.querySelector('.accepted')?.value || 0);

        const rejected =
            parseFloat(row.querySelector('.rejected')?.value || 0);

        const reason =
            row.querySelector('.reason');

        if (received <= 0) return;

        if (Math.abs((accepted + rejected) - received) > 0.001) {
            valid = false;
        }

        if (rejected > 0 && !reason.value.trim()) {
            valid = false;
        }
    });

    if (!valid) {
        event.preventDefault();
        alert(
            'Please ensure Accepted + Rejected = Received and provide rejection reason where required.'
        );
    }
});
</script>

</body>
</html>
