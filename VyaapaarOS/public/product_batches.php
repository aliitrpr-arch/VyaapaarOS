<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/ProductBatch.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'product.edit',
    'view'
);

$companyId = (int) Session::get('company_id');

$error = null;
$success = null;


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $action = $_POST['action'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'create') {

            PermissionMiddleware::require(
                'product.edit',
                'create'
            );

            $productId = (int) (
                $_POST['product_id'] ?? 0
            );

            $batchNo = trim(
                $_POST['batch_no'] ?? ''
            );

            $manufacturingDate =
                trim($_POST['manufacturing_date'] ?? '');

            $expiryDate =
                trim($_POST['expiry_date'] ?? '');

            $purchaseRate = (float) (
                $_POST['purchase_rate'] ?? 0
            );

            $mrp = (float) (
                $_POST['mrp'] ?? 0
            );

            $openingQty = (float) (
                $_POST['opening_qty'] ?? 0
            );


            if ($productId <= 0) {
                throw new Exception(
                    'Please select a product.'
                );
            }

            if ($batchNo === '') {
                throw new Exception(
                    'Batch number is required.'
                );
            }


            ProductBatch::create(
                $companyId,
                $productId,
                $batchNo,
                $manufacturingDate,
                $expiryDate,
                $purchaseRate,
                $mrp,
                $openingQty
            );

            $success =
                'Product batch created successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'product.edit',
                'edit'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            $productId = (int) (
                $_POST['product_id'] ?? 0
            );

            $batchNo = trim(
                $_POST['batch_no'] ?? ''
            );

            $manufacturingDate =
                trim($_POST['manufacturing_date'] ?? '');

            $expiryDate =
                trim($_POST['expiry_date'] ?? '');

            $purchaseRate = (float) (
                $_POST['purchase_rate'] ?? 0
            );

            $mrp = (float) (
                $_POST['mrp'] ?? 0
            );

            $openingQty = (float) (
                $_POST['opening_qty'] ?? 0
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid batch.'
                );
            }

            if ($productId <= 0) {
                throw new Exception(
                    'Please select a product.'
                );
            }

            if ($batchNo === '') {
                throw new Exception(
                    'Batch number is required.'
                );
            }


            ProductBatch::update(
                $id,
                $companyId,
                $productId,
                $batchNo,
                $manufacturingDate,
                $expiryDate,
                $purchaseRate,
                $mrp,
                $openingQty,
                $isActive
            );

            $success =
                'Product batch updated successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'product.edit',
                'delete'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new Exception(
                    'Invalid batch.'
                );
            }

            ProductBatch::delete(
                $id,
                $companyId
            );

            $success =
                'Product batch deleted successfully ✅';
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| EDIT DATA
|--------------------------------------------------------------------------
*/

$editBatch = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editBatch =
            ProductBatch::find(
                $editId,
                $companyId
            );
    }
}


/*
|--------------------------------------------------------------------------
| MASTER DATA
|--------------------------------------------------------------------------
*/

$products =
    ProductBatch::getProducts(
        $companyId
    );


/*
|--------------------------------------------------------------------------
| BATCH LIST
|--------------------------------------------------------------------------
*/

$batches =
    ProductBatch::getAll(
        $companyId
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS - Product Batches</title>

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

h1,
h2 {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.field label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
}

input,
select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

.checkbox-row {
    margin-top: 18px;
}

.checkbox-row label {
    font-weight: normal;
}

button {
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.create {
    background: #2563eb;
    color: white;
}

.edit {
    background: #f59e0b;
    color: white;
}

.delete {
    background: #dc2626;
    color: white;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
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

.actions {
    display: flex;
    gap: 8px;
}

.actions form {
    margin: 0;
}

.active {
    color: #15803d;
    font-weight: bold;
}

.inactive {
    color: #dc2626;
    font-weight: bold;
}

.form-actions {
    margin-top: 20px;
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

<?= $editBatch
    ? 'Edit Product Batch'
    : 'Product Batches' ?>

</h1>


<?php if ($success): ?>

<div class="success">
<?= htmlspecialchars($success) ?>
</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="error">
<?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>


<?php if (!$editBatch): ?>


<?php if (
    PermissionMiddleware::check(
        'product.edit',
        'create'
    )
): ?>

<h2>
Add Product Batch
</h2>


<form method="POST">

<input
    type="hidden"
    name="action"
    value="create"
>


<div class="form-grid">


<div class="field">

<label>
Product *
</label>

<select
    name="product_id"
    required
>

<option value="">
-- Select Product --
</option>

<?php foreach ($products as $product): ?>

<option
    value="<?= (int) $product['id'] ?>"
>

<?= htmlspecialchars(
    $product['product_name']
) ?>

<?php if (!empty($product['sku'])): ?>

(<?= htmlspecialchars(
    $product['sku']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Batch No. *
</label>

<input
    type="text"
    name="batch_no"
    required
>

</div>


<div class="field">

<label>
Manufacturing Date
</label>

<input
    type="date"
    name="manufacturing_date"
>

</div>


<div class="field">

<label>
Expiry Date
</label>

<input
    type="date"
    name="expiry_date"
>

</div>


<div class="field">

<label>
Purchase Rate *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="purchase_rate"
    value="0"
    required
>

</div>


<div class="field">

<label>
MRP *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="mrp"
    value="0"
    required
>

</div>


<div class="field">

<label>
Opening Quantity *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="opening_qty"
    value="0"
    required
>

</div>

</div>


<div class="form-actions">

<button
    type="submit"
    class="create"
>
Create Batch
</button>

</div>

</form>

<?php endif; ?>


<?php else: ?>


<form method="POST">

<input
    type="hidden"
    name="action"
    value="update"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $editBatch['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Product *
</label>

<select
    name="product_id"
    required
>

<option value="">
-- Select Product --
</option>

<?php foreach ($products as $product): ?>

<option
    value="<?= (int) $product['id'] ?>"
    <?= (
        (int) $editBatch['product_id']
        === (int) $product['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $product['product_name']
) ?>

<?php if (!empty($product['sku'])): ?>

(<?= htmlspecialchars(
    $product['sku']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Batch No. *
</label>

<input
    type="text"
    name="batch_no"
    value="<?= htmlspecialchars(
        $editBatch['batch_no']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Manufacturing Date
</label>

<input
    type="date"
    name="manufacturing_date"
    value="<?= htmlspecialchars(
        $editBatch['manufacturing_date'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Expiry Date
</label>

<input
    type="date"
    name="expiry_date"
    value="<?= htmlspecialchars(
        $editBatch['expiry_date'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Purchase Rate *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="purchase_rate"
    value="<?= htmlspecialchars(
        $editBatch['purchase_rate']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
MRP *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="mrp"
    value="<?= htmlspecialchars(
        $editBatch['mrp']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Opening Quantity *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="opening_qty"
    value="<?= htmlspecialchars(
        $editBatch['opening_qty']
    ) ?>"
    required
>

</div>

</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editBatch['is_active']
        ? 'checked'
        : '' ?>
>

Active Batch

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Batch
</button>

<a
    href="product_batches.php"
>
Cancel
</a>

</div>

</form>

<?php endif; ?>

</div>


<div class="card">

<h2>
Batch List
</h2>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>ID</th>
<th>Product</th>
<th>Batch No.</th>
<th>Mfg. Date</th>
<th>Expiry Date</th>
<th>Purchase Rate</th>
<th>MRP</th>
<th>Opening Qty</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($batches as $batch): ?>

<tr>

<td>
<?= (int) $batch['id'] ?>
</td>

<td>
<?= htmlspecialchars(
    $batch['product_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $batch['batch_no']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $batch['manufacturing_date'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $batch['expiry_date'] ?? '-'
) ?>
</td>

<td>
<?= number_format(
    (float) $batch['purchase_rate'],
    2
) ?>
</td>

<td>
<?= number_format(
    (float) $batch['mrp'],
    2
) ?>
</td>

<td>
<?= number_format(
    (float) $batch['opening_qty'],
    2
) ?>
</td>

<td>

<?php if ($batch['is_active']): ?>

<span class="active">
Active
</span>

<?php else: ?>

<span class="inactive">
Inactive
</span>

<?php endif; ?>

</td>

<td>

<div class="actions">


<?php if (
    PermissionMiddleware::check(
        'product.edit',
        'edit'
    )
): ?>

<a
    href="product_batches.php?edit=<?= (int) $batch['id'] ?>"
>

<button
    type="button"
    class="edit"
>
Edit
</button>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'product.edit',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this batch?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $batch['id'] ?>"
>

<button
    type="submit"
    class="delete"
>
Delete
</button>

</form>

<?php endif; ?>


</div>

</td>

</tr>

<?php endforeach; ?>


<?php if (empty($batches)): ?>

<tr>

<td colspan="10">
No product batches found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>


<div class="card">

<a href="dashboard.php">
← Back to Dashboard
</a>

</div>


</div>

</body>

</html>