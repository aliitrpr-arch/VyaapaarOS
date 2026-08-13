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

$stmt = $db->prepare("
    SELECT
        p.id AS product_id,
        p.product_name,
        p.sku,

        p.min_stock_alert,

        pu.unit_name,
        pu.short_code,

        b.brand_name,

        pb.batch_no,

        w.warehouse_name,
        w.warehouse_code,

        COALESCE(SUM(it.qty_in), 0) AS total_in,
        COALESCE(SUM(it.qty_out), 0) AS total_out,

        (
            COALESCE(SUM(it.qty_in), 0)
            -
            COALESCE(SUM(it.qty_out), 0)
        ) AS available_stock

    FROM products p

    INNER JOIN product_units pu
        ON pu.id = p.base_unit_id

    LEFT JOIN brands b
        ON b.id = p.brand_id

    LEFT JOIN inventory_transactions it
        ON it.product_id = p.id
        AND it.company_id = p.company_id

    LEFT JOIN product_batches pb
        ON pb.id = it.batch_id

    LEFT JOIN warehouses w
        ON w.id = it.warehouse_id

    WHERE p.company_id = :company_id
      AND p.is_active = TRUE

    GROUP BY
        p.id,
        p.product_name,
        p.sku,
        p.min_stock_alert,
        pu.unit_name,
        pu.short_code,
        b.brand_name,
        pb.batch_no,
        w.warehouse_name,
        w.warehouse_code

    HAVING
        (
            COALESCE(SUM(it.qty_in), 0)
            -
            COALESCE(SUM(it.qty_out), 0)
        ) <= p.min_stock_alert

    ORDER BY
        available_stock ASC,
        p.product_name
");

$stmt->execute([
    'company_id' => $companyId
]);

$alerts = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>VyaapaarOS - Low Stock Alerts</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    width: 1200px;
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
    min-width: 1000px;
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

.alert {
    font-weight: bold;
}

.low {
    color: #dc2626;
}

.ok {
    color: #15803d;
}

.warning {
    background: #fff7ed;
}

a {
    text-decoration: none;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 5px;
    font-weight: bold;
}

</style>

</head>

<body>

<div class="container">


<div class="card">

<h1>
⚠️ Low Stock Alerts
</h1>

<p>
Products whose available stock is at or below the minimum stock alert level.
</p>

</div>


<div class="card">

<h2>
Stock Alerts
</h2>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>Product</th>
<th>SKU</th>
<th>Brand</th>
<th>Batch</th>
<th>Warehouse</th>
<th>Current Stock</th>
<th>Minimum Stock</th>
<th>Unit</th>
<th>Status</th>

</tr>

</thead>


<tbody>

<?php if (empty($alerts)): ?>

<tr>

<td colspan="9">

✅ No low stock products found.

</td>

</tr>

<?php else: ?>


<?php foreach ($alerts as $row): ?>

<?php

$currentStock =
    (float) $row['available_stock'];

$minimumStock =
    (float) $row['min_stock_alert'];

?>

<tr class="warning">


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
    $row['warehouse_name'] ?? '-'
) ?>

<br>

<small>

<?= htmlspecialchars(
    $row['warehouse_code'] ?? ''
) ?>

</small>

</td>


<td class="alert low">

<?= number_format(
    $currentStock,
    2
) ?>

</td>


<td>

<?= number_format(
    $minimumStock,
    2
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['short_code']
) ?>

</td>


<td>

<span class="badge low">

LOW STOCK

</span>

</td>


</tr>

<?php endforeach; ?>


<?php endif; ?>

</tbody>

</table>

</div>

</div>


<div class="card">

<a href="stock_summary.php">
← Current Stock
</a>

&nbsp;&nbsp; | &nbsp;&nbsp;

<a href="inventory.php">
← Inventory
</a>

&nbsp;&nbsp; | &nbsp;&nbsp;

<a href="dashboard.php">
← Back to Dashboard
</a>

</div>


</div>

</body>

</html>