<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/Purchase.php';

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
    return round((float)$value, 2);
}

/*
|--------------------------------------------------------------------------
| Master data
|--------------------------------------------------------------------------
*/
$parties = [];
$products = [];
$warehouses = [];
$salesmen = [];
$financialYears = [];
$branchState = '';

try {
    $stmt = $db->prepare("
        SELECT id, party_name, party_type, gst_type, state_code, phone
        FROM parties
        WHERE company_id = :company_id
          AND is_active = TRUE
          AND party_type IN ('VENDOR', 'BOTH')
        ORDER BY party_name
    ");
    $stmt->execute(['company_id' => $companyId]);
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT
            p.id,
            p.product_name,
            p.sku,
            p.mrp,
            p.purchase_price,
            p.sale_price,
            p.gst_rate,
            p.cess_rate,
            p.base_unit_id,
            u.unit_name
        FROM products p
        INNER JOIN product_units u ON u.id = p.base_unit_id
        WHERE p.company_id = :company_id
          AND p.is_active = TRUE
        ORDER BY p.product_name
    ");
    $stmt->execute(['company_id' => $companyId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT id, warehouse_name, warehouse_code
        FROM warehouses
        WHERE company_id = :company_id
          AND branch_id = :branch_id
          AND is_active = TRUE
        ORDER BY warehouse_name
    ");
    $stmt->execute([
        'company_id' => $companyId,
        'branch_id' => $branchId
    ]);
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT id, salesman_name, phone, commission_percent
        FROM salesmen
        WHERE company_id = :company_id
          AND is_active = TRUE
        ORDER BY salesman_name
    ");
    $stmt->execute(['company_id' => $companyId]);
    $salesmen = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT id, year_name, start_date, end_date, is_active
        FROM financial_years
        WHERE company_id = :company_id
          AND is_active = TRUE
        ORDER BY start_date DESC
    ");
    $stmt->execute(['company_id' => $companyId]);
    $financialYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT state_code
        FROM branches
        WHERE id = :branch_id
          AND company_id = :company_id
        LIMIT 1
    ");
    $stmt->execute([
        'branch_id' => $branchId,
        'company_id' => $companyId
    ]);
    $branchState = trim((string)$stmt->fetchColumn());

} catch (Throwable $ex) {
    $error = 'Master data load error: ' . $ex->getMessage();
}

/*
|--------------------------------------------------------------------------
| Purchase detail / print preview
|--------------------------------------------------------------------------
*/
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$printMode = isset($_GET['print']) && $_GET['print'] === '1';

$viewPurchase = null;
$viewItems = [];

if ($viewId > 0) {
    try {
        $stmt = $db->prepare("
            SELECT
                v.*,
                p.party_name,
                p.phone AS party_phone,
                p.email AS party_email,
                p.gst_number AS party_gst,
                p.address AS party_address,
                p.state_code AS party_state,
                w.warehouse_name,
                w.warehouse_code,
                b.branch_name,
                b.branch_code,
                b.gstin AS branch_gstin,
                b.phone AS branch_phone,
                b.email AS branch_email,
                b.address AS branch_address,
                b.state_code AS branch_state,
                s.salesman_name,
                fy.year_name
            FROM vouchers v
            LEFT JOIN parties p ON p.id = v.party_id
            LEFT JOIN warehouses w ON w.id = v.warehouse_id
            LEFT JOIN branches b ON b.id = v.branch_id
            LEFT JOIN salesmen s ON s.id = v.salesman_id
            LEFT JOIN financial_years fy ON fy.id = v.financial_year_id
            WHERE v.id = :id
              AND v.company_id = :company_id
              AND v.branch_id = :branch_id
              AND v.voucher_type = 'PURCHASE'
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $viewId,
            'company_id' => $companyId,
            'branch_id' => $branchId
        ]);
        $viewPurchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$viewPurchase) {
            throw new Exception('Purchase not found.');
        }

        $stmt = $db->prepare("
            SELECT
                vi.*,
                p.product_name,
                p.sku,
                u.unit_name
            FROM voucher_items vi
            INNER JOIN products p ON p.id = vi.product_id
            INNER JOIN product_units u ON u.id = vi.unit_id
            WHERE vi.voucher_id = :voucher_id
            ORDER BY vi.id
        ");
        $stmt->execute(['voucher_id' => $viewId]);
        $viewItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        $viewId = 0;
    }
}

/*
|--------------------------------------------------------------------------
| Save purchase
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'draft';

    try {
        if (!PermissionMiddleware::check('purchase.create', 'create')) {
            throw new Exception('You do not have permission to create purchase.');
        }

        $voucherNumber = trim($_POST['voucher_number'] ?? '');
        $voucherDate = $_POST['voucher_date'] ?? date('Y-m-d');

        $partyId = !empty($_POST['party_id']) ? (int)$_POST['party_id'] : null;
        $warehouseId = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null;
        $salesmanId = !empty($_POST['salesman_id']) ? (int)$_POST['salesman_id'] : null;
        $financialYearId = !empty($_POST['financial_year_id']) ? (int)$_POST['financial_year_id'] : null;
        $narration = trim($_POST['narration'] ?? '');

        $cashPaid = money($_POST['cash_paid'] ?? 0);
        $bankPaid = money($_POST['bank_paid'] ?? 0);

        $productIds = $_POST['product_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $freeQtys = $_POST['free_qty'] ?? [];
        $rates = $_POST['rate'] ?? [];
        $mrps = $_POST['mrp'] ?? [];
        $discountPercents = $_POST['discount_percent'] ?? [];

        if ($voucherNumber === '') {
            throw new Exception('Purchase number is required.');
        }

        if (!$partyId) {
            throw new Exception('Please select supplier.');
        }

        if (!$warehouseId) {
            throw new Exception('Please select warehouse.');
        }

        if (!$financialYearId) {
            throw new Exception('Please select financial year.');
        }

        if (count($productIds) === 0) {
            throw new Exception('Please add at least one product.');
        }

        $stmt = $db->prepare("
            SELECT state_code, gst_type
            FROM parties
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $partyId,
            'company_id' => $companyId
        ]);
        $party = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$party) {
            throw new Exception('Invalid supplier.');
        }

        $partyState = trim((string)($party['state_code'] ?? ''));

        $isInterState = (
            $branchState !== '' &&
            $partyState !== '' &&
            $branchState !== $partyState
        );

        $items = [];

        $totalTaxable = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;
        $totalCess = 0;
        $totalDiscount = 0;

        foreach ($productIds as $index => $productId) {

            $productId = (int)$productId;

            if ($productId <= 0) {
                continue;
            }

            $qty = money($qtys[$index] ?? 0);
            $freeQty = money($freeQtys[$index] ?? 0);
            $rate = money($rates[$index] ?? 0);
            $mrp = money($mrps[$index] ?? 0);
            $discountPercent = money($discountPercents[$index] ?? 0);

            if ($qty <= 0) {
                throw new Exception('Product quantity must be greater than zero.');
            }

            if ($rate < 0 || $mrp < 0 || $discountPercent < 0) {
                throw new Exception('Invalid product amount.');
            }

            if ($discountPercent > 100) {
                throw new Exception('Discount cannot be greater than 100%.');
            }

            $stmt = $db->prepare("
                SELECT id, product_name, base_unit_id, gst_rate, cess_rate
                FROM products
                WHERE id = :id
                  AND company_id = :company_id
                  AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $productId,
                'company_id' => $companyId
            ]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Invalid product selected.');
            }

            $gross = money($qty * $rate);
            $discountAmount = money($gross * $discountPercent / 100);
            $taxableAmount = money($gross - $discountAmount);

            $gstRate = money($product['gst_rate'] ?? 0);
            $cessRate = money($product['cess_rate'] ?? 0);

            $cgstRate = 0;
            $sgstRate = 0;
            $igstRate = 0;

            $cgstAmount = 0;
            $sgstAmount = 0;
            $igstAmount = 0;

            if ($isInterState) {
                $igstRate = $gstRate;
                $igstAmount = money($taxableAmount * $igstRate / 100);
            } else {
                $cgstRate = money($gstRate / 2);
                $sgstRate = money($gstRate / 2);
                $cgstAmount = money($taxableAmount * $cgstRate / 100);
                $sgstAmount = money($taxableAmount * $sgstRate / 100);
            }

            $cessAmount = money($taxableAmount * $cessRate / 100);

            $itemTotal = money(
                $taxableAmount +
                $cgstAmount +
                $sgstAmount +
                $igstAmount +
                $cessAmount
            );

            $totalTaxable += $taxableAmount;
            $totalDiscount += $discountAmount;
            $totalCgst += $cgstAmount;
            $totalSgst += $sgstAmount;
            $totalIgst += $igstAmount;
            $totalCess += $cessAmount;

            $items[] = [
                'product_id' => $productId,
                'unit_id' => (int)$product['base_unit_id'],
                'qty' => $qty,
                'free_qty' => $freeQty,
                'rate' => $rate,
                'mrp' => $mrp,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'taxable_amount' => $taxableAmount,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_rate' => $igstRate,
                'igst_amount' => $igstAmount,
                'cess_rate' => $cessRate,
                'cess_amount' => $cessAmount,
                'item_scheme_discount' => 0,
                'item_total' => $itemTotal
            ];
        }

        if (count($items) === 0) {
            throw new Exception('Please add valid products.');
        }

        $netBeforeRound = money(
            $totalTaxable +
            $totalCgst +
            $totalSgst +
            $totalIgst +
            $totalCess
        );

        $netAmount = round($netBeforeRound);
        $roundOff = money($netAmount - $netBeforeRound);

        $creditAmount = money($netAmount - $cashPaid - $bankPaid);

        if ($creditAmount < 0) {
            throw new Exception('Cash + Bank payment cannot be greater than net amount.');
        }

        $status = ($action === 'post') ? 'POSTED' : 'DRAFT';

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO vouchers
            (
                company_id, branch_id, warehouse_id, party_id, salesman_id,
                voucher_number, voucher_type, voucher_date, financial_year_id,
                total_taxable_amount, cgst_amount, sgst_amount, igst_amount,
                cess_amount, scheme_discount_amount, round_off, net_amount,
                cash_paid, bank_paid, credit_amount, is_b2b, place_of_supply,
                narration, status, created_by, created_at, updated_at
            )
            VALUES
            (
                :company_id, :branch_id, :warehouse_id, :party_id, :salesman_id,
                :voucher_number, 'PURCHASE', :voucher_date, :financial_year_id,
                :total_taxable_amount, :cgst_amount, :sgst_amount, :igst_amount,
                :cess_amount, :scheme_discount_amount, :round_off, :net_amount,
                :cash_paid, :bank_paid, :credit_amount, :is_b2b, :place_of_supply,
                :narration, :status, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'party_id' => $partyId,
            'salesman_id' => $salesmanId,
            'voucher_number' => $voucherNumber,
            'voucher_date' => $voucherDate,
            'financial_year_id' => $financialYearId,
            'total_taxable_amount' => money($totalTaxable),
            'cgst_amount' => money($totalCgst),
            'sgst_amount' => money($totalSgst),
            'igst_amount' => money($totalIgst),
            'cess_amount' => money($totalCess),
            'scheme_discount_amount' => money($totalDiscount),
            'round_off' => $roundOff,
            'net_amount' => $netAmount,
            'cash_paid' => $cashPaid,
            'bank_paid' => $bankPaid,
            'credit_amount' => $creditAmount,
            'is_b2b' => strtoupper((string)($party['gst_type'] ?? '')) === 'REGISTERED',
            'place_of_supply' => $partyState !== '' ? $partyState : null,
            'narration' => $narration !== '' ? $narration : null,
            'status' => $status,
            'created_by' => $userId
        ]);

        $voucherId = (int)$stmt->fetchColumn();

        $itemStmt = $db->prepare("
            INSERT INTO voucher_items
            (
                voucher_id, product_id, batch_id, unit_id, qty, free_qty, rate, mrp,
                discount_percent, discount_amount, taxable_amount,
                cgst_rate, cgst_amount, sgst_rate, sgst_amount,
                igst_rate, igst_amount, cess_rate, cess_amount,
                item_scheme_discount, item_total
            )
            VALUES
            (
                :voucher_id, :product_id, NULL, :unit_id, :qty, :free_qty, :rate, :mrp,
                :discount_percent, :discount_amount, :taxable_amount,
                :cgst_rate, :cgst_amount, :sgst_rate, :sgst_amount,
                :igst_rate, :igst_amount, :cess_rate, :cess_amount,
                :item_scheme_discount, :item_total
            )
        ");

        foreach ($items as $item) {
            $item['voucher_id'] = $voucherId;
            $itemStmt->execute($item);

            if ($status === 'POSTED') {
                $inventoryStmt = $db->prepare("
                    INSERT INTO inventory_transactions
                    (
                        company_id, branch_id, warehouse_id, product_id, batch_id,
                        voucher_id, transaction_type, qty_in, qty_out, rate,
                        transaction_date, reference_id, narration, created_by, created_at
                    )
                    VALUES
                    (
                        :company_id, :branch_id, :warehouse_id, :product_id, NULL,
                        :voucher_id, 'STOCK_IN', :qty_in, 0, :rate,
                        :transaction_date, :reference_id, :narration,
                        :created_by, CURRENT_TIMESTAMP
                    )
                ");

                $inventoryStmt->execute([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item['product_id'],
                    'voucher_id' => $voucherId,
                    'qty_in' => money($item['qty'] + $item['free_qty']),
                    'rate' => $item['rate'],
                    'transaction_date' => $voucherDate,
                    'reference_id' => $voucherId,
                    'narration' => 'Purchase ' . $voucherNumber,
                    'created_by' => $userId
                ]);
            }
        }

        $db->commit();

        $message = $status === 'POSTED'
            ? 'Purchase posted successfully and stock updated ✅'
            : 'Purchase saved as draft successfully ✅';

    } catch (Throwable $ex) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error = $ex->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Purchase list
|--------------------------------------------------------------------------
*/
$purchases = [];

try {
    $stmt = $db->prepare("
        SELECT
            v.id,
            v.voucher_number,
            v.voucher_date,
            v.total_taxable_amount,
            v.scheme_discount_amount,
            v.cgst_amount,
            v.sgst_amount,
            v.igst_amount,
            v.cess_amount,
            v.round_off,
            v.net_amount,
            v.cash_paid,
            v.bank_paid,
            v.credit_amount,
            v.status,
            p.party_name,
            w.warehouse_name
        FROM vouchers v
        LEFT JOIN parties p ON p.id = v.party_id
        LEFT JOIN warehouses w ON w.id = v.warehouse_id
        WHERE v.company_id = :company_id
          AND v.branch_id = :branch_id
          AND v.voucher_type = 'PURCHASE'
        ORDER BY v.id DESC
    ");
    $stmt->execute([
        'company_id' => $companyId,
        'branch_id' => $branchId
    ]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    if ($error === '') {
        $error = 'Purchase list error: ' . $ex->getMessage();
    }
}

$defaultVoucherNumber = 'PUR-' . date('Ymd-His');
$today = date('Y-m-d');

if ($viewPurchase && $printMode):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase <?= e($viewPurchase['voucher_number']) ?></title>
<style>
body{font-family:Arial,sans-serif;margin:0;padding:25px;color:#111}
.invoice{max-width:1000px;margin:auto;border:1px solid #222;padding:25px}
h1,h2{margin:0 0 8px}
.header-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;border-bottom:1px solid #222;padding-bottom:15px}
.info{margin:15px 0}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{border:1px solid #999;padding:7px;font-size:13px}
th{background:#eee}
.text-right{text-align:right}
.summary{margin-left:auto;width:350px;margin-top:15px}
.summary div{display:flex;justify-content:space-between;padding:5px}
.total{font-size:18px;font-weight:bold;border-top:2px solid #111}
.footer{margin-top:35px;border-top:1px solid #aaa;padding-top:12px}
.no-print{text-align:center;margin-bottom:15px}
button{padding:10px 18px;cursor:pointer}
@media print{.no-print{display:none}.invoice{border:0}}
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">🖨 Print</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="invoice">
    <div class="header-grid">
        <div>
            <h1><?= e($viewPurchase['branch_name'] ?? 'VyaapaarOS') ?></h1>
            <div><?= e($viewPurchase['branch_address'] ?? '') ?></div>
            <div>GSTIN: <?= e($viewPurchase['branch_gstin'] ?? '-') ?></div>
            <div>Phone: <?= e($viewPurchase['branch_phone'] ?? '-') ?></div>
        </div>
        <div style="text-align:right">
            <h2>PURCHASE INVOICE</h2>
            <div><strong>No:</strong> <?= e($viewPurchase['voucher_number']) ?></div>
            <div><strong>Date:</strong> <?= e($viewPurchase['voucher_date']) ?></div>
            <div><strong>Financial Year:</strong> <?= e($viewPurchase['year_name'] ?? '-') ?></div>
            <div><strong>Status:</strong> <?= e($viewPurchase['status']) ?></div>
        </div>
    </div>

    <div class="info-grid info">
        <div>
            <strong>Supplier</strong><br>
            <?= e($viewPurchase['party_name'] ?? '-') ?><br>
            <?= e($viewPurchase['party_address'] ?? '') ?><br>
            GSTIN: <?= e($viewPurchase['party_gst'] ?? '-') ?><br>
            State: <?= e($viewPurchase['party_state'] ?? '-') ?><br>
            Phone: <?= e($viewPurchase['party_phone'] ?? '-') ?>
        </div>
        <div>
            <strong>Warehouse</strong><br>
            <?= e($viewPurchase['warehouse_name'] ?? '-') ?>
            (<?= e($viewPurchase['warehouse_code'] ?? '-') ?>)<br><br>
            <strong>Salesman:</strong> <?= e($viewPurchase['salesman_name'] ?? '-') ?><br>
            <strong>Place of Supply:</strong> <?= e($viewPurchase['place_of_supply'] ?? '-') ?>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Product</th>
            <th>Qty</th>
            <th>Free</th>
            <th>Rate</th>
            <th>MRP</th>
            <th>Disc %</th>
            <th>Taxable</th>
            <th>GST</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($viewItems as $i => $item): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($item['product_name']) ?><?= $item['sku'] ? ' (' . e($item['sku']) . ')' : '' ?></td>
            <td class="text-right"><?= number_format((float)$item['qty'], 3) ?></td>
            <td class="text-right"><?= number_format((float)$item['free_qty'], 3) ?></td>
            <td class="text-right"><?= number_format((float)$item['rate'], 2) ?></td>
            <td class="text-right"><?= number_format((float)$item['mrp'], 2) ?></td>
            <td class="text-right"><?= number_format((float)$item['discount_percent'], 2) ?></td>
            <td class="text-right"><?= number_format((float)$item['taxable_amount'], 2) ?></td>
            <td class="text-right">
                <?php if ((float)$item['igst_rate'] > 0): ?>
                    IGST <?= number_format((float)$item['igst_rate'], 2) ?>%
                    (<?= number_format((float)$item['igst_amount'], 2) ?>)
                <?php else: ?>
                    CGST <?= number_format((float)$item['cgst_rate'], 2) ?>%
                    (<?= number_format((float)$item['cgst_amount'], 2) ?>)<br>
                    SGST <?= number_format((float)$item['sgst_rate'], 2) ?>%
                    (<?= number_format((float)$item['sgst_amount'], 2) ?>)
                <?php endif; ?>
                <?php if ((float)$item['cess_amount'] > 0): ?>
                    <br>CESS <?= number_format((float)$item['cess_rate'], 2) ?>%
                    (<?= number_format((float)$item['cess_amount'], 2) ?>)
                <?php endif; ?>
            </td>
            <td class="text-right"><?= number_format((float)$item['item_total'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
        <div><span>Taxable Amount</span><strong><?= number_format((float)$viewPurchase['total_taxable_amount'],2) ?></strong></div>
        <div><span>Discount</span><strong><?= number_format((float)$viewPurchase['scheme_discount_amount'],2) ?></strong></div>
        <div><span>CGST</span><strong><?= number_format((float)$viewPurchase['cgst_amount'],2) ?></strong></div>
        <div><span>SGST</span><strong><?= number_format((float)$viewPurchase['sgst_amount'],2) ?></strong></div>
        <div><span>IGST</span><strong><?= number_format((float)$viewPurchase['igst_amount'],2) ?></strong></div>
        <div><span>CESS</span><strong><?= number_format((float)$viewPurchase['cess_amount'],2) ?></strong></div>
        <div><span>Round Off</span><strong><?= number_format((float)$viewPurchase['round_off'],2) ?></strong></div>
        <div class="total"><span>Net Amount</span><strong><?= number_format((float)$viewPurchase['net_amount'],2) ?></strong></div>
        <div><span>Cash Paid</span><strong><?= number_format((float)$viewPurchase['cash_paid'],2) ?></strong></div>
        <div><span>Bank Paid</span><strong><?= number_format((float)$viewPurchase['bank_paid'],2) ?></strong></div>
        <div><span>Credit</span><strong><?= number_format((float)$viewPurchase['credit_amount'],2) ?></strong></div>
    </div>

    <?php if (!empty($viewPurchase['narration'])): ?>
    <div class="footer">
        <strong>Narration:</strong> <?= nl2br(e($viewPurchase['narration'])) ?>
    </div>
    <?php endif; ?>
</div>
<script>
window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 300); });
</script>
</body>
</html>
<?php
exit;
endif;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase - VyaapaarOS</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#111827}
.header{background:#111827;color:#fff;padding:18px 30px}
.header h1{margin:0}
.container{width:1400px;max-width:calc(100% - 30px);margin:25px auto}
.card{background:#fff;border-radius:10px;padding:22px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
h2{margin-top:0}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}
.field label{display:block;font-weight:bold;margin-bottom:6px}
input,select,textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px}
textarea{min-height:80px}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:1150px}
th,td{border-bottom:1px solid #e5e7eb;padding:9px;text-align:left;vertical-align:middle}
th{background:#f8fafc}
.item-input{min-width:85px}
.product-select{min-width:230px}
.btn{border:0;border-radius:6px;padding:10px 16px;cursor:pointer;font-weight:bold}
.btn-primary{background:#2563eb;color:#fff}
.btn-success{background:#059669;color:#fff}
.btn-danger{background:#dc2626;color:#fff}
.btn-secondary{background:#6b7280;color:#fff}
.btn-info{background:#0891b2;color:#fff}
.actions{display:flex;gap:10px;margin-top:20px}
.alert-success{background:#dcfce7;color:#166534;padding:12px;border-radius:7px;margin-bottom:20px}
.alert-error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:7px;margin-bottom:20px}
.summary{margin-left:auto;width:400px}
.summary-row{display:flex;justify-content:space-between;padding:6px 0}
.summary-row.total{border-top:2px solid #111827;margin-top:8px;padding-top:12px;font-size:20px;font-weight:bold}
.status{font-weight:bold}
.status-DRAFT{color:#d97706}
.status-POSTED{color:#059669}
.status-CANCELLED{color:#dc2626}
.remove-btn{white-space:nowrap}
.back{display:inline-block;margin-bottom:15px;text-decoration:none;color:#2563eb}
.list-actions{display:flex;gap:6px;flex-wrap:wrap}
.small-btn{padding:7px 10px;font-size:12px}
.gst-box{font-size:12px;line-height:1.4;color:#374151}
.totals-note{font-size:12px;color:#6b7280;margin-top:8px}
@media(max-width:1100px){.grid{grid-template-columns:repeat(2,1fr)}.summary{width:100%}}
@media(max-width:650px){.grid{grid-template-columns:1fr}}
</style>
</head>

<body>

<div class="header">
    <h1>🛒 Purchase</h1>
</div>

<div class="container">

<a class="back" href="dashboard.php">← Back to Dashboard</a>

<?php if ($message !== ''): ?>
<div class="alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" id="purchaseForm">

<div class="card">
<h2>Purchase Entry</h2>

<div class="grid">

<div class="field">
<label>Purchase No. *</label>
<input type="text" name="voucher_number" value="<?= e($defaultVoucherNumber) ?>" required>
</div>

<div class="field">
<label>Purchase Date *</label>
<input type="date" name="voucher_date" value="<?= e($today) ?>" required>
</div>

<div class="field">
<label>Supplier *</label>
<select name="party_id" id="partyId" required onchange="calculateSummary()">
<option value="">-- Select Supplier --</option>
<?php foreach ($parties as $party): ?>
<option value="<?= (int)$party['id'] ?>">
<?= e($party['party_name']) ?><?= !empty($party['phone']) ? ' (' . e($party['phone']) . ')' : '' ?>
</option>
<?php endforeach; ?>
</select>
<div id="supplierGstInfo" class="gst-box"></div>
</div>

<div class="field">
<label>Warehouse *</label>
<select name="warehouse_id" required>
<option value="">-- Select Warehouse --</option>
<?php foreach ($warehouses as $warehouse): ?>
<option value="<?= (int)$warehouse['id'] ?>">
<?= e($warehouse['warehouse_name']) ?><?= !empty($warehouse['warehouse_code']) ? ' (' . e($warehouse['warehouse_code']) . ')' : '' ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="field">
<label>Salesman</label>
<select name="salesman_id">
<option value="">-- Select Salesman --</option>
<?php foreach ($salesmen as $salesman): ?>
<option value="<?= (int)$salesman['id'] ?>"><?= e($salesman['salesman_name']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="field">
<label>Financial Year *</label>
<select name="financial_year_id" required>
<option value="">-- Select Financial Year --</option>
<?php foreach ($financialYears as $fy): ?>
<option value="<?= (int)$fy['id'] ?>">
<?= e($fy['year_name']) ?> (<?= e($fy['start_date']) ?> to <?= e($fy['end_date']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>

</div>
</div>

<div class="card">
<h2>Purchase Items</h2>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>Product</th>
<th>Qty</th>
<th>Free Qty</th>
<th>Rate</th>
<th>MRP</th>
<th>Discount %</th>
<th>GST %</th>
<th>Taxable</th>
<th>CGST</th>
<th>SGST</th>
<th>IGST</th>
<th>CESS</th>
<th>Total</th>
<th>Action</th>
</tr>
</thead>
<tbody id="itemsBody"></tbody>
</table>
</div>

<br>
<button type="button" class="btn btn-primary" onclick="addItem()">+ Add Product</button>
<div class="totals-note">
GST is automatically split into CGST/SGST for intra-state purchases and IGST for inter-state purchases.
</div>
</div>

<div class="card">
<h2>Payment & Summary</h2>

<div class="summary">

<div class="summary-row"><span>Gross Amount</span><strong id="summaryGross">0.00</strong></div>
<div class="summary-row"><span>Discount</span><strong id="summaryDiscount">0.00</strong></div>
<div class="summary-row"><span>Taxable Amount</span><strong id="summaryTaxable">0.00</strong></div>
<div class="summary-row"><span>CGST</span><strong id="summaryCgst">0.00</strong></div>
<div class="summary-row"><span>SGST</span><strong id="summarySgst">0.00</strong></div>
<div class="summary-row"><span>IGST</span><strong id="summaryIgst">0.00</strong></div>
<div class="summary-row"><span>CESS</span><strong id="summaryCess">0.00</strong></div>
<div class="summary-row"><span>Round Off</span><strong id="summaryRoundOff">0.00</strong></div>
<div class="summary-row total"><span>Net Amount</span><strong id="summaryNet">0.00</strong></div>

<br>

<div class="field">
<label>Cash Paid</label>
<input type="number" step="0.01" min="0" name="cash_paid" id="cashPaid" value="0" oninput="calculateSummary()">
</div>

<br>

<div class="field">
<label>Bank Paid</label>
<input type="number" step="0.01" min="0" name="bank_paid" id="bankPaid" value="0" oninput="calculateSummary()">
</div>

<div class="summary-row">
<span>Credit Amount</span>
<strong id="summaryCredit">0.00</strong>
</div>

</div>

<br>

<div class="field">
<label>Narration</label>
<textarea name="narration" placeholder="Purchase remarks..."></textarea>
</div>

<div class="actions">
<button type="submit" name="action" value="draft" class="btn btn-secondary">Save Draft</button>
<button type="submit" name="action" value="post" class="btn btn-success">Post Purchase</button>
</div>

</div>
</form>

<div class="card">
<h2>Purchase List</h2>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>ID</th>
<th>Purchase No.</th>
<th>Date</th>
<th>Supplier</th>
<th>Warehouse</th>
<th>Taxable</th>
<th>Discount</th>
<th>CGST</th>
<th>SGST</th>
<th>IGST</th>
<th>CESS</th>
<th>Net Amount</th>
<th>Cash</th>
<th>Bank</th>
<th>Credit</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if (count($purchases) === 0): ?>
<tr><td colspan="17">No purchases found.</td></tr>
<?php else: ?>

<?php foreach ($purchases as $purchase): ?>
<tr>
<td><?= (int)$purchase['id'] ?></td>
<td><?= e($purchase['voucher_number']) ?></td>
<td><?= e($purchase['voucher_date']) ?></td>
<td><?= e($purchase['party_name'] ?? '-') ?></td>
<td><?= e($purchase['warehouse_name'] ?? '-') ?></td>
<td><?= number_format((float)$purchase['total_taxable_amount'],2) ?></td>
<td><?= number_format((float)$purchase['scheme_discount_amount'],2) ?></td>
<td><?= number_format((float)$purchase['cgst_amount'],2) ?></td>
<td><?= number_format((float)$purchase['sgst_amount'],2) ?></td>
<td><?= number_format((float)$purchase['igst_amount'],2) ?></td>
<td><?= number_format((float)$purchase['cess_amount'],2) ?></td>
<td><strong><?= number_format((float)$purchase['net_amount'],2) ?></strong></td>
<td><?= number_format((float)$purchase['cash_paid'],2) ?></td>
<td><?= number_format((float)$purchase['bank_paid'],2) ?></td>
<td><?= number_format((float)$purchase['credit_amount'],2) ?></td>
<td class="status status-<?= e($purchase['status']) ?>"><?= e($purchase['status']) ?></td>
<td>
<div class="list-actions">
<a class="btn btn-info small-btn" href="?view=<?= (int)$purchase['id'] ?>">Preview</a>
<a class="btn btn-secondary small-btn" target="_blank" href="?view=<?= (int)$purchase['id'] ?>&print=1">Print</a>
</div>
</td>
</tr>
<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>
</div>
</div>

</div>

<script>

const products = <?= json_encode(
    $products,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

const parties = <?= json_encode(
    $parties,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

const branchState = <?= json_encode($branchState) ?>;

let itemIndex = 0;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function productOptions() {

    let html = '<option value="">-- Select Product --</option>';

    products.forEach(product => {

        html += `
            <option
                value="${product.id}"
                data-rate="${Number(product.purchase_price || 0)}"
                data-mrp="${Number(product.mrp || 0)}"
                data-gst="${Number(product.gst_rate || 0)}"
                data-cess="${Number(product.cess_rate || 0)}"
            >
                ${escapeHtml(product.product_name)}
                ${product.sku ? ' - ' + escapeHtml(product.sku) : ''}
            </option>
        `;
    });

    return html;
}

function addItem() {

    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');

    tr.dataset.index = itemIndex;

    tr.innerHTML = `

        <td>
            <select
                name="product_id[]"
                class="product-select"
                onchange="productChanged(this)"
                required
            >
                ${productOptions()}
            </select>
        </td>

        <td>
            <input
                type="number"
                name="qty[]"
                class="item-input qty"
                min="0.001"
                step="0.001"
                value="1"
                oninput="calculateSummary()"
                required
            >
        </td>

        <td>
            <input
                type="number"
                name="free_qty[]"
                class="item-input free-qty"
                min="0"
                step="0.001"
                value="0"
                oninput="calculateSummary()"
            >
        </td>

        <td>
            <input
                type="number"
                name="rate[]"
                class="item-input rate"
                min="0"
                step="0.01"
                value="0"
                oninput="calculateSummary()"
                required
            >
        </td>

        <td>
            <input
                type="number"
                name="mrp[]"
                class="item-input mrp"
                min="0"
                step="0.01"
                value="0"
                oninput="calculateSummary()"
                required
            >
        </td>

        <td>
            <input
                type="number"
                name="discount_percent[]"
                class="item-input discount"
                min="0"
                max="100"
                step="0.01"
                value="0"
                oninput="calculateSummary()"
            >
        </td>

        <td>
            <input type="text" class="item-input gst" value="0" readonly>
        </td>

        <td>
            <input type="text" class="item-input taxable" value="0.00" readonly>
        </td>

        <td>
            <input type="text" class="item-input cgst" value="0.00" readonly>
        </td>

        <td>
            <input type="text" class="item-input sgst" value="0.00" readonly>
        </td>

        <td>
            <input type="text" class="item-input igst" value="0.00" readonly>
        </td>

        <td>
            <input type="text" class="item-input cess" value="0.00" readonly>
        </td>

        <td>
            <input type="text" class="item-input item-total" value="0.00" readonly>
        </td>

        <td>
            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this)">
                Remove
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    itemIndex++;

    calculateSummary();
}

function productChanged(select) {

    const row = select.closest('tr');
    const option = select.options[select.selectedIndex];

    if (!option || !option.value) {
        row.querySelector('.rate').value = 0;
        row.querySelector('.mrp').value = 0;
        row.querySelector('.gst').value = 0;
        row.dataset.cess = 0;
        calculateSummary();
        return;
    }

    row.querySelector('.rate').value = Number(option.dataset.rate || 0).toFixed(2);
    row.querySelector('.mrp').value = Number(option.dataset.mrp || 0).toFixed(2);
    row.querySelector('.gst').value = Number(option.dataset.gst || 0).toFixed(2);
    row.dataset.cess = Number(option.dataset.cess || 0);

    calculateSummary();
}

function removeItem(button) {

    const row = button.closest('tr');

    if (document.querySelectorAll('#itemsBody tr').length <= 1) {
        row.querySelector('.product-select').value = '';
        row.querySelector('.rate').value = '0';
        row.querySelector('.mrp').value = '0';
        row.querySelector('.discount').value = '0';
        row.querySelector('.gst').value = '0';
        row.dataset.cess = 0;
        calculateSummary();
        return;
    }

    row.remove();
    calculateSummary();
}

function getTaxMode() {

    const partyId = document.getElementById('partyId').value;

    if (!partyId || !branchState) {
        return {
            interState: false,
            partyState: ''
        };
    }

    const party = parties.find(p => String(p.id) === String(partyId));

    const partyState = party
        ? String(party.state_code || '').trim()
        : '';

    return {
        interState:
            branchState !== '' &&
            partyState !== '' &&
            branchState !== partyState,
        partyState
    };
}

function updateSupplierGstInfo() {

    const partyId = document.getElementById('partyId').value;
    const box = document.getElementById('supplierGstInfo');

    if (!partyId) {
        box.textContent = '';
        return;
    }

    const party = parties.find(p => String(p.id) === String(partyId));

    if (!party) {
        box.textContent = '';
        return;
    }

    const mode = getTaxMode();

    box.innerHTML =
        'State: <strong>' + escapeHtml(party.state_code || '-') + '</strong>' +
        ' | GST: <strong>' + escapeHtml(party.gst_type || '-') + '</strong>' +
        ' | Tax: <strong>' + (mode.interState ? 'IGST' : 'CGST + SGST') + '</strong>';
}

function calculateSummary() {

    let grossTotal = 0;
    let taxableTotal = 0;
    let discountTotal = 0;
    let cgstTotal = 0;
    let sgstTotal = 0;
    let igstTotal = 0;
    let cessTotal = 0;

    const taxMode = getTaxMode();

    document.querySelectorAll('#itemsBody tr').forEach(row => {

        const qty = parseFloat(row.querySelector('.qty')?.value || 0);
        const rate = parseFloat(row.querySelector('.rate')?.value || 0);
        const discount = parseFloat(row.querySelector('.discount')?.value || 0);
        const gst = parseFloat(row.querySelector('.gst')?.value || 0);
        const cessRate = parseFloat(row.dataset.cess || 0);

        const gross = qty * rate;
        const discountAmount = gross * discount / 100;
        const taxable = Math.max(0, gross - discountAmount);

        let cgst = 0;
        let sgst = 0;
        let igst = 0;

        if (taxMode.interState) {
            igst = taxable * gst / 100;
        } else {
            cgst = taxable * (gst / 2) / 100;
            sgst = taxable * (gst / 2) / 100;
        }

        const cess = taxable * cessRate / 100;

        const itemTotal = taxable + cgst + sgst + igst + cess;

        row.querySelector('.taxable').value = taxable.toFixed(2);
        row.querySelector('.cgst').value = cgst.toFixed(2);
        row.querySelector('.sgst').value = sgst.toFixed(2);
        row.querySelector('.igst').value = igst.toFixed(2);
        row.querySelector('.cess').value = cess.toFixed(2);
        row.querySelector('.item-total').value = itemTotal.toFixed(2);

        grossTotal += gross;
        discountTotal += discountAmount;
        taxableTotal += taxable;
        cgstTotal += cgst;
        sgstTotal += sgst;
        igstTotal += igst;
        cessTotal += cess;
    });

    const netBeforeRound =
        taxableTotal +
        cgstTotal +
        sgstTotal +
        igstTotal +
        cessTotal;

    const netAmount = Math.round(netBeforeRound);
    const roundOff = netAmount - netBeforeRound;

    const cash = parseFloat(
        document.getElementById('cashPaid')?.value || 0
    );

    const bank = parseFloat(
        document.getElementById('bankPaid')?.value || 0
    );

    const credit = netAmount - cash - bank;

    document.getElementById('summaryGross').textContent = grossTotal.toFixed(2);
    document.getElementById('summaryDiscount').textContent = discountTotal.toFixed(2);
    document.getElementById('summaryTaxable').textContent = taxableTotal.toFixed(2);
    document.getElementById('summaryCgst').textContent = cgstTotal.toFixed(2);
    document.getElementById('summarySgst').textContent = sgstTotal.toFixed(2);
    document.getElementById('summaryIgst').textContent = igstTotal.toFixed(2);
    document.getElementById('summaryCess').textContent = cessTotal.toFixed(2);
    document.getElementById('summaryRoundOff').textContent = roundOff.toFixed(2);
    document.getElementById('summaryNet').textContent = netAmount.toFixed(2);
    document.getElementById('summaryCredit').textContent = credit.toFixed(2);

    updateSupplierGstInfo();
}

document.addEventListener('DOMContentLoaded', function () {
    addItem();
    calculateSummary();
});

</script>

</body>
</html>
