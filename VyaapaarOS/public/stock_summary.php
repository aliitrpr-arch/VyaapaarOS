<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require('inventory.view', 'view');

$companyId = (int) Session::get('company_id');

$db = Database::connect();

/*
|--------------------------------------------------------------------------
| CURRENT STOCK
|--------------------------------------------------------------------------
|
| Available Stock = Total Qty In - Total Qty Out
|
*/

$stmt = $db->prepare("
    SELECT
        p.id AS product_id,
        p.product_name,
        p.sku,

        pu.unit_name,
        pu.short_code,

        pb.id AS batch_id,
        pb.batch_no,

        b.brand_name,

        w.id AS warehouse_id,
        w.warehouse_name,
        w.warehouse_code,

        br.branch_name,
        br.branch_code,

        COALESCE(SUM(it.qty_in), 0) AS total_in,
        COALESCE(SUM(it.qty_out), 0) AS total_out,

        COALESCE(SUM(it.qty_in), 0)
        -
        COALESCE(SUM(it.qty_out), 0)
        AS available_stock

    FROM inventory_transactions it

    INNER JOIN products p
        ON p.id = it.product_id

    INNER JOIN product_units pu
        ON pu.id = p.base_unit_id

    LEFT JOIN product_batches pb
        ON pb.id = it.batch_id

    LEFT JOIN brands b
        ON b.id = p.brand_id

    INNER JOIN warehouses w
        ON w.id = it.warehouse_id

    INNER JOIN branches br
        ON br.id = it.branch_id

    WHERE it.company_id = :company_id

    GROUP BY
        p.id,
        p.product_name,
        p.sku,
        pu.unit_name,
        pu.short_code,
        pb.id,
        pb.batch_no,
        b.brand_name,
        w.id,
        w.warehouse_name,
        w.warehouse_code,
        br.branch_name,
        br.branch_code

    HAVING
        COALESCE(SUM(it.qty_in), 0)
        -
        COALESCE(SUM(it.qty_out), 0) <> 0

    ORDER BY
        p.product_name,
        pb.batch_no,
        w.warehouse_name
");

$stmt->execute([
    'company_id' => $companyId
]);

$stockRows = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS - Current Stock</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    width: 1250px;
    max-width: calc(100% - 30px);
    margin: 30px auto;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

h1 {
    margin-top: 0;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th,
td {
    padding: 11px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

th {
    background: #f8fafc;
}

.stock-positive {
    color: #15803d;
    font-weight: bold;
}

.stock-zero {
    color: #6b7280;
    font-weight: bold;
}

.stock-negative {
    color: #dc2626;
    font-weight: bold;
}

.summary {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.summary-box {
    background: #f8fafc;
    padding: 15px 20px;
    border-radius: 8px;
}

.summary-box strong {
    display: block;
    font-size: 22px;
    margin-top: 5px;
}

a {
    text-decoration: none;
}

</style>

</head>

<body>

<div class="container">


<div class="card">

<h1>
📊 Current Stock
</h1>

<p>
Product-wise available stock based on inventory transactions.
</p>

</div>


<div class="summary">

<div class="summary-box">

Total Stock Items

<strong>
<?= count($stockRows) ?>
</strong>

</div>

</div>


<div class="card">

<h2>
Stock Summary
</h2>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>Product</th>
<th>SKU</th>
<th>Brand</th>
<th>Batch</th>
<th>Branch</th>
<th>Warehouse</th>
<th>Stock In</th>
<th>Stock Out</th>
<th>Available</th>
<th>Unit</th>

</tr>

</thead>


<tbody>

<?php if (empty($stockRows)): ?>

<tr>

<td colspan="10">
No stock available.
</td>

</tr>

<?php else: ?>


<?php foreach ($stockRows as $row): ?>

<tr>


<td>

<?= htmlspecialchars(
    $row['product_name']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['sku'] ?? '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['brand_name'] ?? '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['batch_no'] ?? '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['branch_name']
) ?>

<br>

<small>
<?= htmlspecialchars(
    $row['branch_code']
) ?>
</small>

</td>


<td>

<?= htmlspecialchars(
    $row['warehouse_name']
) ?>

<br>

<small>
<?= htmlspecialchars(
    $row['warehouse_code']
) ?>
</small>

</td>


<td>

<?= number_format(
    (float) $row['total_in'],
    2
) ?>

</td>


<td>

<?= number_format(
    (float) $row['total_out'],
    2
) ?>

</td>


<td>

<?php

$available =
    (float) $row['available_stock'];

?>

<?php if ($available > 0): ?>

<span class="stock-positive">

<?= number_format(
    $available,
    2
) ?>

</span>

<?php elseif ($available < 0): ?>

<span class="stock-negative">

<?= number_format(
    $available,
    2
) ?>

</span>

<?php else: ?>

<span class="stock-zero">

0.00

</span>

<?php endif; ?>

</td>


<td>

<?= htmlspecialchars(
    $row['short_code']
) ?>

</td>


</tr>

<?php endforeach; ?>


<?php endif; ?>

</tbody>

</table>

</div>

</div>


<div class="card">

<a href="inventory.php">
← Inventory Transactions
</a>

&nbsp;&nbsp; | &nbsp;&nbsp;

<a href="dashboard.php">
← Back to Dashboard
</a>

</div>


</div>

</body>

</html>