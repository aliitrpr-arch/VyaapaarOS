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
$productUnits = [];
$inwards = [];

try {
    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT id, party_name, phone, gst_type, state_code
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

    /*
    |--------------------------------------------------------------------------
    | Products with Unit Information
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
            u.id AS unit_id,
            u.unit_name,
            u.unit_code,
            u.is_base_unit,
            -- Get all available units for this product
            (
                SELECT json_agg(
                    json_build_object(
                        'unit_id', pu.id,
                        'unit_code', pu.unit_code,
                        'unit_name', pu.unit_name,
                        'is_base', pu.is_base_unit,
                        'conversion_factor', (
                            SELECT conversion_factor 
                            FROM product_unit_conversions 
                            WHERE product_id = p.id 
                              AND from_unit_id = pu.id 
                              AND to_unit_id = p.base_unit_id
                              AND is_active = TRUE
                            LIMIT 1
                        )
                    )
                )
                FROM product_units pu
                WHERE pu.id IN (
                    SELECT to_unit_id 
                    FROM product_unit_conversions 
                    WHERE product_id = p.id 
                      AND is_active = TRUE
                    UNION
                    SELECT from_unit_id 
                    FROM product_unit_conversions 
                    WHERE product_id = p.id 
                      AND is_active = TRUE
                )
                OR pu.id = p.base_unit_id
            ) AS available_units
        FROM products p
        LEFT JOIN product_units u ON u.id = p.base_unit_id
        WHERE p.company_id = :company_id
          AND p.is_active = TRUE
        ORDER BY p.product_name
    ");
    $stmt->execute(['company_id' => $companyId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | All Units (for dropdown)
    |--------------------------------------------------------------------------
    */
    $stmt = $db->prepare("
        SELECT id, unit_name, unit_code, is_base_unit
        FROM product_units
        WHERE company_id = :company_id AND is_active = TRUE
        ORDER BY unit_name
    ");
    $stmt->execute(['company_id' => $companyId]);
    $productUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $unitIds = $_POST['unit_id'] ?? [];  // Selected unit for receiving
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
        $stmt = $db->prepare("
            SELECT id FROM purchase_inwards 
            WHERE inward_number = :inward_number AND company_id = :company_id
        ");
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

            $unitId = isset($unitIds[$index]) ? (int)$unitIds[$index] : 0;
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
                SELECT 
                    p.id, 
                    p.base_unit_id, 
                    p.product_name, 
                    p.purchase_price, 
                    p.mrp, 
                    p.gst_rate,
                    u.unit_code,
                    u.unit_name
                FROM products p
                LEFT JOIN product_units u ON u.id = p.base_unit_id
                WHERE p.id = :id AND p.company_id = :company_id AND p.is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute(['id' => $productId, 'company_id' => $companyId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Invalid product selected.');
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Unit (if provided, check if valid for this product)
            |--------------------------------------------------------------------------
            */
            if ($unitId > 0 && $unitId != $product['base_unit_id']) {
                // Check if this unit is valid for the product
                $stmt = $db->prepare("
                    SELECT conversion_factor 
                    FROM product_unit_conversions 
                    WHERE product_id = :product_id 
                      AND from_unit_id = :unit_id 
                      AND to_unit_id = :base_unit_id
                      AND is_active = TRUE
                    LIMIT 1
                ");
                $stmt->execute([
                    'product_id' => $productId,
                    'unit_id' => $unitId,
                    'base_unit_id' => $product['base_unit_id']
                ]);
                $conversion = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$conversion) {
                    // Try reverse conversion
                    $stmt = $db->prepare("
                        SELECT 1 / conversion_factor AS conversion_factor 
                        FROM product_unit_conversions 
                        WHERE product_id = :product_id 
                          AND from_unit_id = :base_unit_id 
                          AND to_unit_id = :unit_id
                          AND is_active = TRUE
                        LIMIT 1
                    ");
                    $stmt->execute([
                        'product_id' => $productId,
                        'base_unit_id' => $product['base_unit_id'],
                        'unit_id' => $unitId
                    ]);
                    $conversion = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                if (!$conversion) {
                    throw new Exception('Invalid unit selected for product: ' . $product['product_name']);
                }
            }

            // Auto-fill rate and MRP if not provided
            if ($rate == 0 && $product['purchase_price'] > 0) {
                $rate = $product['purchase_price'];
            }
            if ($mrp == 0 && $product['mrp'] > 0) {
                $mrp = $product['mrp'];
            }

            // Use selected unit or base unit
            $finalUnitId = $unitId > 0 ? $unitId : $product['base_unit_id'];

            // Get unit code for display
            $stmt = $db->prepare("
                SELECT unit_code, unit_name 
                FROM product_units 
                WHERE id = :unit_id
            ");
            $stmt->execute(['unit_id' => $finalUnitId]);
            $unitInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            $items[] = [
                'product_id' => $productId,
                'unit_id' => $finalUnitId,
                'received_qty' => $receivedQty,
                'free_qty' => $freeQty,
                'rate' => $rate,
                'mrp' => $mrp,
                'batch_number' => $batchNumber !== '' ? $batchNumber : null,
                'manufacturing_date' => $manufacturingDate,
                'expiry_date' => $expiryDate,
                'remarks' => $itemRemark !== '' ? $itemRemark : null,
                'unit_code' => $unitInfo['unit_code'] ?? $product['unit_code'] ?? '',
                'unit_name' => $unitInfo['unit_name'] ?? $product['unit_name'] ?? ''
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
        /* ... (same as before) ... */
        .unit-select { min-width: 80px; padding: 6px 8px; }
        .product-cell { display: flex; gap: 5px; align-items: center; }
        .unit-badge { 
            display: inline-block; 
            background: #dbeafe; 
            color: #1e40af; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 11px; 
            font-weight: bold;
        }
    </style>
</head>
<body>
<!-- ... (same as before, update items table) ... -->

<script>
const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allUnits = <?= json_encode($productUnits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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
        let unitDisplay = product.unit_code ? ' (' + escapeHtml(product.unit_code) + ')' : '';
        html += `
            <option value="${product.id}" 
                    data-rate="${product.purchase_price || 0}" 
                    data-mrp="${product.mrp || 0}" 
                    data-base-unit="${product.base_unit_id || 0}"
                    data-unit="${escapeHtml(product.unit_code || '')}">
                ${escapeHtml(product.product_name)}
                ${product.sku ? ' - ' + escapeHtml(product.sku) : ''}
                ${unitDisplay}
            </option>
        `;
    });
    return html;
}

function getUnitOptions(productId, selectedUnitId) {
    let html = '';
    // Find product's available units
    const product = products.find(p => p.id == productId);
    if (product && product.available_units) {
        const units = JSON.parse(product.available_units);
        units.forEach(unit => {
            let selected = (unit.unit_id == selectedUnitId) ? 'selected' : '';
            let label = unit.unit_code + (unit.is_base ? ' (Base)' : '');
            html += `<option value="${unit.unit_id}" ${selected}>${escapeHtml(label)}</option>`;
        });
    }
    return html;
}

function addItem() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.dataset.index = itemIndex;

    tr.innerHTML = `
        <td class="row-number" style="text-align:center;">${itemIndex + 1}</td>
        <td>
            <div class="product-cell">
                <select name="product_id[]" class="product-select" onchange="productChanged(this, ${itemIndex})" required>
                    ${productOptions()}
                </select>
                <span class="unit-badge" id="unitDisplay_${itemIndex}" style="display:none;">Unit</span>
            </div>
        </td>
        <td>
            <select name="unit_id[]" class="unit-select" id="unitSelect_${itemIndex}" onchange="unitChanged(${itemIndex})">
                <option value="">Unit</option>
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
            <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">✕</button>
        </td>
    `;

    tbody.appendChild(tr);
    itemIndex++;
    refreshRowNumbers();
    calculateTotals();
}

function productChanged(select, index) {
    const row = select.closest('tr');
    const option = select.options[select.selectedIndex];
    const unitSelect = document.getElementById('unitSelect_' + index);

    if (!option || !option.value) {
        unitSelect.innerHTML = '<option value="">Unit</option>';
        document.getElementById('unitDisplay_' + index).style.display = 'none';
        return;
    }

    const rate = option.dataset.rate || 0;
    const mrp = option.dataset.mrp || 0;
    const productId = option.value;
    const baseUnitId = option.dataset.baseUnit || 0;

    const rateInput = row.querySelector('.rate');
    const mrpInput = row.querySelector('.mrp');

    if (parseFloat(rateInput.value) === 0) {
        rateInput.value = rate;
    }
    if (parseFloat(mrpInput.value) === 0) {
        mrpInput.value = mrp;
    }

    // Update unit dropdown
    const product = products.find(p => p.id == productId);
    if (product && product.available_units) {
        let units = JSON.parse(product.available_units);
        let html = '';
        // Add base unit first
        const baseUnit = units.find(u => u.is_base);
        if (baseUnit) {
            html += `<option value="${baseUnit.unit_id}" selected>${escapeHtml(baseUnit.unit_code)} (Base)</option>`;
        }
        // Add other units
        units.forEach(unit => {
            if (!unit.is_base) {
                html += `<option value="${unit.unit_id}">${escapeHtml(unit.unit_code)}</option>`;
            }
        });
        unitSelect.innerHTML = html;
    } else {
        unitSelect.innerHTML = '<option value="">No units available</option>';
    }

    // Show unit badge
    const unitBadge = document.getElementById('unitDisplay_' + index);
    const selectedUnit = unitSelect.options[unitSelect.selectedIndex];
    if (selectedUnit && selectedUnit.value) {
        unitBadge.textContent = selectedUnit.text;
        unitBadge.style.display = 'inline-block';
    } else {
        unitBadge.style.display = 'none';
    }
}

function unitChanged(index) {
    const unitSelect = document.getElementById('unitSelect_' + index);
    const unitBadge = document.getElementById('unitDisplay_' + index);
    const selectedUnit = unitSelect.options[unitSelect.selectedIndex];
    
    if (selectedUnit && selectedUnit.value) {
        unitBadge.textContent = selectedUnit.text;
        unitBadge.style.display = 'inline-block';
    } else {
        unitBadge.style.display = 'none';
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
