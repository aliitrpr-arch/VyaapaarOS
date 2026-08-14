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
    return round((float)$value, 2);
}

function qty($value): float
{
    return round((float)$value, 3);
}

/*
|--------------------------------------------------------------------------
| Master Data
|--------------------------------------------------------------------------
*/

$suppliers = [];
$warehouses = [];
$products = [];
$inwards = [];

try {
    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT
            id,
            party_name,
            phone,
            gst_type,
            state_code
        FROM parties
        WHERE company_id = :company_id
          AND is_active = TRUE
          AND party_type IN ('VENDOR', 'BOTH')
        ORDER BY party_name
    ");

    $stmt->execute(['company_id' => $companyId]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Warehouses
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT
            id,
            warehouse_name,
            warehouse_code
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

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
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
        LEFT JOIN product_units u ON u.id = p.base_unit_id
        WHERE p.company_id = :company_id
          AND p.is_active = TRUE
        ORDER BY p.product_name
    ");

    $stmt->execute(['company_id' => $companyId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    $error = 'Master data load error: ' . $ex->getMessage();
}

/*
|--------------------------------------------------------------------------
| Save Inward
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'draft';

    try {
        if (!PermissionMiddleware::check('purchase.create', 'create')) {
            throw new Exception('You do not have permission to create purchase inward.');
        }

        // Get form data
        $inwardNumber = trim($_POST['inward_number'] ?? '');
        $inwardDate = $_POST['inward_date'] ?? date('Y-m-d');
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $warehouseId = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null;
        $supplierInvoiceNumber = trim($_POST['supplier_invoice_number'] ?? '');
        $supplierInvoiceDate = !empty($_POST['supplier_invoice_date']) ? $_POST['supplier_invoice_date'] : null;
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Logistics fields
        $challanLrNo = trim($_POST['challan_lr_no'] ?? '');
        $challanLrDate = !empty($_POST['challan_lr_date']) ? $_POST['challan_lr_date'] : null;
        $vehicleNo = trim($_POST['vehicle_no'] ?? '');
        $transporterName = trim($_POST['transporter_name'] ?? '');
        $gatePassNo = trim($_POST['gate_pass_no'] ?? '');

        // Item arrays
        $productIds = $_POST['product_id'] ?? [];
        $receivedQtys = $_POST['received_qty'] ?? [];
        $freeQtys = $_POST['free_qty'] ?? [];
        $rates = $_POST['rate'] ?? [];
        $mrps = $_POST['mrp'] ?? [];
        $batchNumbers = $_POST['batch_number'] ?? [];
        $manufacturingDates = $_POST['manufacturing_date'] ?? [];
        $expiryDates = $_POST['expiry_date'] ?? [];
        $itemRemarks = $_POST['item_remarks'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        if ($inwardNumber === '') {
            throw new Exception('Inward number is required.');
        }

        // Check if inward number already exists
        $stmt = $db->prepare("SELECT id FROM purchase_inwards WHERE inward_number = :inward_number AND company_id = :company_id");
        $stmt->execute(['inward_number' => $inwardNumber, 'company_id' => $companyId]);
        if ($stmt->fetch()) {
            throw new Exception('Inward number already exists. Please use a unique number.');
        }

        if (!$supplierId) {
            throw new Exception('Please select supplier.');
        }

        if (!$warehouseId) {
            throw new Exception('Please select warehouse.');
        }

        if (count($productIds) === 0) {
            throw new Exception('Please add at least one product.');
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Supplier
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            SELECT id FROM parties
            WHERE id = :id AND company_id = :company_id 
            AND is_active = TRUE AND party_type IN ('VENDOR', 'BOTH')
            LIMIT 1
        ");
        $stmt->execute(['id' => $supplierId, 'company_id' => $companyId]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid supplier selected.');
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Warehouse
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            SELECT id FROM warehouses
            WHERE id = :id AND company_id = :company_id 
            AND branch_id = :branch_id AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $warehouseId,
            'company_id' => $companyId,
            'branch_id' => $branchId
        ]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid warehouse selected.');
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare Items
        |--------------------------------------------------------------------------
        */
        $items = [];

        foreach ($productIds as $index => $productId) {
            $productId = (int)$productId;

            if ($productId <= 0) {
                continue;
            }

            $receivedQty = qty($receivedQtys[$index] ?? 0);
            $freeQty = qty($freeQtys[$index] ?? 0);
            $rate = money($rates[$index] ?? 0);
            $mrp = money($mrps[$index] ?? 0);
            $batchNumber = trim($batchNumbers[$index] ?? '');
            $manufacturingDate = !empty($manufacturingDates[$index]) ? $manufacturingDates[$index] : null;
            $expiryDate = !empty($expiryDates[$index]) ? $expiryDates[$index] : null;
            $itemRemark = trim($itemRemarks[$index] ?? '');

            if ($receivedQty <= 0) {
                throw new Exception('Received quantity must be greater than zero.');
            }

            if ($freeQty < 0) {
                throw new Exception('Free quantity cannot be negative.');
            }

            if ($rate < 0 || $mrp < 0) {
                throw new Exception('Rate/MRP cannot be negative.');
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Product
            |--------------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                SELECT id, base_unit_id, product_name, purchase_price, mrp, gst_rate
                FROM products
                WHERE id = :id AND company_id = :company_id AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute(['id' => $productId, 'company_id' => $companyId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Invalid product selected.');
            }

            /*
            |--------------------------------------------------------------------------
            | Date Validation
            |--------------------------------------------------------------------------
            */
            if ($manufacturingDate !== null && $expiryDate !== null && $expiryDate < $manufacturingDate) {
                throw new Exception('Expiry date cannot be before manufacturing date for ' . $product['product_name']);
            }

            // Auto-fill rate and MRP if not provided
            if ($rate == 0 && $product['purchase_price'] > 0) {
                $rate = $product['purchase_price'];
            }
            if ($mrp == 0 && $product['mrp'] > 0) {
                $mrp = $product['mrp'];
            }

            $items[] = [
                'product_id' => $productId,
                'unit_id' => (int)$product['base_unit_id'],
                'received_qty' => $receivedQty,
                'free_qty' => $freeQty,
                'rate' => $rate,
                'mrp' => $mrp,
                'batch_number' => $batchNumber !== '' ? $batchNumber : null,
                'manufacturing_date' => $manufacturingDate,
                'expiry_date' => $expiryDate,
                'remarks' => $itemRemark !== '' ? $itemRemark : null
            ];
        }

        if (count($items) === 0) {
            throw new Exception('Please add valid products.');
        }

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */
        $status = ($action === 'qc') ? 'QC_PENDING' : 'DRAFT';

        /*
        |--------------------------------------------------------------------------
        | Transaction
        |--------------------------------------------------------------------------
        */
        $db->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Insert Inward
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            INSERT INTO purchase_inwards
            (
                company_id, branch_id, warehouse_id, supplier_id,
                inward_number, inward_date,
                supplier_invoice_number, supplier_invoice_date,
                challan_lr_no, challan_lr_date, vehicle_no,
                transporter_name, gate_pass_no,
                status, remarks, created_by,
                created_at, updated_at
            )
            VALUES
            (
                :company_id, :branch_id, :warehouse_id, :supplier_id,
                :inward_number, :inward_date,
                :supplier_invoice_number, :supplier_invoice_date,
                :challan_lr_no, :challan_lr_date, :vehicle_no,
                :transporter_name, :gate_pass_no,
                :status, :remarks, :created_by,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'supplier_id' => $supplierId,
            'inward_number' => $inwardNumber,
            'inward_date' => $inwardDate,
            'supplier_invoice_number' => $supplierInvoiceNumber !== '' ? $supplierInvoiceNumber : null,
            'supplier_invoice_date' => $supplierInvoiceDate,
            'challan_lr_no' => $challanLrNo !== '' ? $challanLrNo : null,
            'challan_lr_date' => $challanLrDate,
            'vehicle_no' => $vehicleNo !== '' ? $vehicleNo : null,
            'transporter_name' => $transporterName !== '' ? $transporterName : null,
            'gate_pass_no' => $gatePassNo !== '' ? $gatePassNo : null,
            'status' => $status,
            'remarks' => $remarks !== '' ? $remarks : null,
            'created_by' => $userId
        ]);

        $inwardId = (int)$stmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | Insert Items
        |--------------------------------------------------------------------------
        */
        $itemStmt = $db->prepare("
            INSERT INTO purchase_inward_items
            (
                inward_id, product_id, unit_id,
                received_qty, free_qty, rate, mrp,
                batch_number, manufacturing_date, expiry_date,
                remarks, created_at
            )
            VALUES
            (
                :inward_id, :product_id, :unit_id,
                :received_qty, :free_qty, :rate, :mrp,
                :batch_number, :manufacturing_date, :expiry_date,
                :remarks, CURRENT_TIMESTAMP
            )
        ");

        foreach ($items as $item) {
            $itemStmt->execute([
                'inward_id' => $inwardId,
                'product_id' => $item['product_id'],
                'unit_id' => $item['unit_id'],
                'received_qty' => $item['received_qty'],
                'free_qty' => $item['free_qty'],
                'rate' => $item['rate'],
                'mrp' => $item['mrp'],
                'batch_number' => $item['batch_number'],
                'manufacturing_date' => $item['manufacturing_date'],
                'expiry_date' => $item['expiry_date'],
                'remarks' => $item['remarks']
            ]);
        }

        $db->commit();

        if ($status === 'QC_PENDING') {
            $message = 'Purchase Inward saved and sent to Quality Check successfully ✅';
        } else {
            $message = 'Purchase Inward saved as draft successfully ✅';
        }

    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $ex->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Load Inward List
|--------------------------------------------------------------------------
*/

try {
    $stmt = $db->prepare("
        SELECT
            pi.id,
            pi.inward_number,
            pi.inward_date,
            pi.supplier_invoice_number,
            pi.status,
            p.party_name,
            w.warehouse_name,
            COUNT(pii.id) AS item_count,
            COALESCE(SUM(pii.received_qty + pii.free_qty), 0) AS total_qty
        FROM purchase_inwards pi
        LEFT JOIN parties p ON p.id = pi.supplier_id
        LEFT JOIN warehouses w ON w.id = pi.warehouse_id
        LEFT JOIN purchase_inward_items pii ON pii.inward_id = pi.id
        WHERE pi.company_id = :company_id AND pi.branch_id = :branch_id
        GROUP BY pi.id, pi.inward_number, pi.inward_date, 
                 pi.supplier_invoice_number, pi.status,
                 p.party_name, w.warehouse_name
        ORDER BY pi.id DESC
    ");

    $stmt->execute([
        'company_id' => $companyId,
        'branch_id' => $branchId
    ]);
    $inwards = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    if ($error === '') {
        $error = 'Purchase Inward list error: ' . $ex->getMessage();
    }
}

$defaultInwardNumber = 'INW-' . date('Ymd-His');
$today = date('Y-m-d');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Inward - VyaapaarOS</title>
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
            color: white;
            padding: 18px 30px;
        }
        .header h1 { margin: 0; }
        .container {
            width: 1400px;
            max-width: calc(100% - 30px);
            margin: 25px auto;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        h2 { margin-top: 0; }
        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .field label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 14px;
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
            min-width: 1350px;
        }
        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 9px;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 13px;
        }
        .item-input { min-width: 95px; }
        .product-select { min-width: 230px; }
        .date-input { min-width: 145px; }
        .btn {
            border: 0;
            border-radius: 6px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-success { background: #059669; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
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
        .status {
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
        }
        .status-DRAFT { background: #fef3c7; color: #92400e; }
        .status-QC_PENDING { background: #dbeafe; color: #1e40af; }
        .status-QC_APPROVED { background: #d1fae5; color: #065f46; }
        .status-QC_PARTIAL { background: #ede9fe; color: #5b21b6; }
        .status-QC_REJECTED { background: #fee2e2; color: #991b1b; }
        .status-PURCHASE_CREATED { background: #d1fae5; color: #065f46; }
        .status-CANCELLED { background: #f3f4f6; color: #6b7280; }
        .back {
            display: inline-block;
            margin-bottom: 15px;
            text-decoration: none;
            color: #2563eb;
        }
        .info {
            background: #eff6ff;
            color: #1e40af;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 15px;
        }
        .summary {
            display: flex;
            gap: 30px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 18px;
        }
        .summary-box strong {
            display: block;
            font-size: 18px;
            margin-top: 4px;
        }
        .text-muted { color: #6b7280; font-size: 13px; }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        @media (max-width: 900px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
            .grid-3 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .grid { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr; }
            .container { max-width: calc(100% - 15px); }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>📦 Purchase Inward / Receiving</h1>
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
    <div class="info">
        <strong>Important:</strong>
        यह Purchase Entry नहीं है। यह केवल supplier से माल receive होने की entry है।
        QC approve होने के बाद ही इससे Purchase बनेगा और Stock में जाएगा।
    </div>

    <h2>Create Purchase Inward</h2>

    <form method="POST" id="inwardForm">

        <div class="grid">
            <div class="field">
                <label>Inward Number *</label>
                <input type="text" name="inward_number" value="<?= e($defaultInwardNumber) ?>" required>
            </div>

            <div class="field">
                <label>Inward Date *</label>
                <input type="date" name="inward_date" value="<?= e($today) ?>" required>
            </div>

            <div class="field">
                <label>Supplier *</label>
                <select name="supplier_id" required>
                    <option value="">-- Select Supplier --</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int)$supplier['id'] ?>">
                            <?= e($supplier['party_name']) ?>
                            <?php if (!empty($supplier['phone'])): ?>
                                (<?= e($supplier['phone']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Warehouse *</label>
                <select name="warehouse_id" required>
                    <option value="">-- Select Warehouse --</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= (int)$warehouse['id'] ?>">
                            <?= e($warehouse['warehouse_name']) ?>
                            <?php if (!empty($warehouse['warehouse_code'])): ?>
                                (<?= e($warehouse['warehouse_code']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Supplier Invoice No.</label>
                <input type="text" name="supplier_invoice_number" placeholder="Supplier bill number">
            </div>

            <div class="field">
                <label>Supplier Invoice Date</label>
                <input type="date" name="supplier_invoice_date">
            </div>
        </div>

        <!-- Logistics Section -->
        <div class="section-title">🚚 Logistics Details</div>

        <div class="grid-3">
            <div class="field">
                <label>Challan / LR No.</label>
                <input type="text" name="challan_lr_no" placeholder="Challan or LR number">
            </div>

            <div class="field">
                <label>Challan / LR Date</label>
                <input type="date" name="challan_lr_date">
            </div>

            <div class="field">
                <label>Vehicle No.</label>
                <input type="text" name="vehicle_no" placeholder="Vehicle number">
            </div>

            <div class="field">
                <label>Transporter Name</label>
                <input type="text" name="transporter_name" placeholder="Transporter name">
            </div>

            <div class="field">
                <label>Gate Pass No.</label>
                <input type="text" name="gate_pass_no" placeholder="Gate pass number">
            </div>
        </div>

        <br>

        <h2>Received Items</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product *</th>
                        <th>Received Qty *</th>
                        <th>Free Qty</th>
                        <th>Rate</th>
                        <th>MRP</th>
                        <th>Batch No.</th>
                        <th>Manufacturing Date</th>
                        <th>Expiry Date</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>
        </div>

        <br>

        <button type="button" class="btn btn-primary" onclick="addItem()">
            + Add Product
        </button>

        <div class="summary">
            <div class="summary-box">
                Received Qty
                <strong id="totalReceived">0.000</strong>
            </div>
            <div class="summary-box">
                Free Qty
                <strong id="totalFree">0.000</strong>
            </div>
            <div class="summary-box">
                Total Qty
                <strong id="totalQty">0.000</strong>
            </div>
        </div>

        <br>

        <div class="field">
            <label>Inward Remarks</label>
            <textarea name="remarks" placeholder="Receiving remarks..."></textarea>
        </div>

        <div class="actions">
            <button type="submit" name="action" value="draft" class="btn btn-secondary">
                💾 Save Draft
            </button>
            <button type="submit" name="action" value="qc" class="btn btn-success">
                🔎 Save & Send to Quality Check
            </button>
        </div>

    </form>
</div>

<div class="card">
    <h2>Purchase Inward List</h2>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Inward No.</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Warehouse</th>
                    <th>Supplier Invoice</th>
                    <th>Items</th>
                    <th>Total Qty</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($inwards) === 0): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding:30px; color:#6b7280;">
                            No Purchase Inward found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inwards as $inward): ?>
                        <tr>
                            <td><?= (int)$inward['id'] ?></td>
                            <td><strong><?= e($inward['inward_number']) ?></strong></td>
                            <td><?= e($inward['inward_date']) ?></td>
                            <td><?= e($inward['party_name'] ?? '-') ?></td>
                            <td><?= e($inward['warehouse_name'] ?? '-') ?></td>
                            <td><?= e($inward['supplier_invoice_number'] ?? '-') ?></td>
                            <td><?= (int)$inward['item_count'] ?></td>
                            <td><?= number_format((float)$inward['total_qty'], 3) ?></td>
                            <td>
                                <span class="status status-<?= e($inward['status']) ?>">
                                    <?= str_replace('_', ' ', e($inward['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($inward['status'] === 'QC_PENDING'): ?>
                                    <a class="btn btn-warning btn-sm" href="quality_check.php?inward_id=<?= (int)$inward['id'] ?>">
                                        Open QC
                                    </a>
                                <?php elseif ($inward['status'] === 'DRAFT'): ?>
                                    <span class="text-muted">Draft</span>
                                <?php elseif ($inward['status'] === 'QC_APPROVED' || $inward['status'] === 'QC_PARTIAL'): ?>
                                    <span class="text-muted">QC Done</span>
                                <?php elseif ($inward['status'] === 'PURCHASE_CREATED'): ?>
                                    <span class="text-muted">Purchase Created</span>
                                <?php endif; ?>
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
const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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
            <option value="${product.id}" data-rate="${product.purchase_price || 0}" data-mrp="${product.mrp || 0}">
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
        <td class="row-number">${itemIndex + 1}</td>
        <td>
            <select name="product_id[]" class="product-select" onchange="productChanged(this)" required>
                ${productOptions()}
            </select>
        </td>
        <td>
            <input type="number" name="received_qty[]" class="item-input received-qty" min="0.001" step="0.001" value="1" oninput="calculateTotals()" required>
        </td>
        <td>
            <input type="number" name="free_qty[]" class="item-input free-qty" min="0" step="0.001" value="0" oninput="calculateTotals()">
        </td>
        <td>
            <input type="number" name="rate[]" class="item-input rate" min="0" step="0.01" value="0">
        </td>
        <td>
            <input type="number" name="mrp[]" class="item-input mrp" min="0" step="0.01" value="0">
        </td>
        <td>
            <input type="text" name="batch_number[]" class="item-input" placeholder="Batch No.">
        </td>
        <td>
            <input type="date" name="manufacturing_date[]" class="date-input">
        </td>
        <td>
            <input type="date" name="expiry_date[]" class="date-input">
        </td>
        <td>
            <input type="text" name="item_remarks[]" class="item-input" placeholder="Remarks">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">Remove</button>
        </td>
    `;

    tbody.appendChild(tr);
    itemIndex++;
    refreshRowNumbers();
    calculateTotals();
}

function productChanged(select) {
    const row = select.closest('tr');
    const option = select.options[select.selectedIndex];

    if (!option || !option.value) return;

    const rate = option.dataset.rate || 0;
    const mrp = option.dataset.mrp || 0;

    const rateInput = row.querySelector('.rate');
    const mrpInput = row.querySelector('.mrp');

    if (parseFloat(rateInput.value) === 0) {
        rateInput.value = rate;
    }
    if (parseFloat(mrpInput.value) === 0) {
        mrpInput.value = mrp;
    }
}

function removeItem(button) {
    const row = button.closest('tr');
    row.remove();
    refreshRowNumbers();
    calculateTotals();
}

function refreshRowNumbers() {
    document.querySelectorAll('#itemsBody tr').forEach((row, index) => {
        const number = row.querySelector('.row-number');
        if (number) {
            number.textContent = index + 1;
        }
    });
}

function calculateTotals() {
    let received = 0;
    let free = 0;

    document.querySelectorAll('#itemsBody tr').forEach(row => {
        received += parseFloat(row.querySelector('.received-qty')?.value || 0);
        free += parseFloat(row.querySelector('.free-qty')?.value || 0);
    });

    document.getElementById('totalReceived').textContent = received.toFixed(3);
    document.getElementById('totalFree').textContent = free.toFixed(3);
    document.getElementById('totalQty').textContent = (received + free).toFixed(3);
}

document.addEventListener('DOMContentLoaded', function() {
    addItem();
});
</script>

</body>
</html>
