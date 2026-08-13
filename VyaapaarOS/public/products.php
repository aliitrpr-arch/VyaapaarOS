<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/Product.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'product.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$error = null;
$success = null;
$editProduct = null;


/*
|--------------------------------------------------------------------------
| POST
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
                'product.create',
                'create'
            );

            $categoryId = ($_POST['category_id'] ?? '') !== ''
                ? (int) $_POST['category_id']
                : null;

            $groupId = ($_POST['group_id'] ?? '') !== ''
                ? (int) $_POST['group_id']
                : null;

            $brandId = ($_POST['brand_id'] ?? '') !== ''
                ? (int) $_POST['brand_id']
                : null;

            $productName = trim(
                $_POST['product_name'] ?? ''
            );

            $sku = trim(
                $_POST['sku'] ?? ''
            );

            $barcode = trim(
                $_POST['barcode'] ?? ''
            );

            $hsnCode = trim(
                $_POST['hsn_code'] ?? ''
            );

            $baseUnitId = (int) (
                $_POST['base_unit_id'] ?? 0
            );

            $mrp = (float) (
                $_POST['mrp'] ?? 0
            );

            $purchasePrice = (float) (
                $_POST['purchase_price'] ?? 0
            );

            $salePrice = (float) (
                $_POST['sale_price'] ?? 0
            );

            $wholesalePrice = (float) (
                $_POST['wholesale_price'] ?? 0
            );

            $gstRate = (float) (
                $_POST['gst_rate'] ?? 0
            );

            $cessRate = (float) (
                $_POST['cess_rate'] ?? 0
            );

            $minStockAlert = (float) (
                $_POST['min_stock_alert'] ?? 0
            );

            $trackBatch =
                isset($_POST['track_batch'])
                && $_POST['track_batch'] === '1';

            $trackExpiry =
                isset($_POST['track_expiry'])
                && $_POST['track_expiry'] === '1';


            if ($productName === '') {
                throw new Exception(
                    'Product name is required.'
                );
            }

            if ($baseUnitId <= 0) {
                throw new Exception(
                    'Base unit is required.'
                );
            }


            Product::create(
                $companyId,
                $categoryId,
                $groupId,
                $brandId,
                $productName,
                $sku,
                $barcode,
                $hsnCode,
                $baseUnitId,
                $mrp,
                $purchasePrice,
                $salePrice,
                $wholesalePrice,
                $gstRate,
                $cessRate,
                $minStockAlert,
                $trackBatch,
                $trackExpiry
            );

            header(
                'Location: products.php?success=' .
                urlencode('Product created successfully ✅')
            );

            exit;
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

            $categoryId = ($_POST['category_id'] ?? '') !== ''
                ? (int) $_POST['category_id']
                : null;

            $groupId = ($_POST['group_id'] ?? '') !== ''
                ? (int) $_POST['group_id']
                : null;

            $brandId = ($_POST['brand_id'] ?? '') !== ''
                ? (int) $_POST['brand_id']
                : null;

            $productName = trim(
                $_POST['product_name'] ?? ''
            );

            $sku = trim(
                $_POST['sku'] ?? ''
            );

            $barcode = trim(
                $_POST['barcode'] ?? ''
            );

            $hsnCode = trim(
                $_POST['hsn_code'] ?? ''
            );

            $baseUnitId = (int) (
                $_POST['base_unit_id'] ?? 0
            );

            $mrp = (float) (
                $_POST['mrp'] ?? 0
            );

            $purchasePrice = (float) (
                $_POST['purchase_price'] ?? 0
            );

            $salePrice = (float) (
                $_POST['sale_price'] ?? 0
            );

            $wholesalePrice = (float) (
                $_POST['wholesale_price'] ?? 0
            );

            $gstRate = (float) (
                $_POST['gst_rate'] ?? 0
            );

            $cessRate = (float) (
                $_POST['cess_rate'] ?? 0
            );

            $minStockAlert = (float) (
                $_POST['min_stock_alert'] ?? 0
            );

            $trackBatch =
                isset($_POST['track_batch'])
                && $_POST['track_batch'] === '1';

            $trackExpiry =
                isset($_POST['track_expiry'])
                && $_POST['track_expiry'] === '1';

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid product.'
                );
            }

            if ($productName === '') {
                throw new Exception(
                    'Product name is required.'
                );
            }

            if ($baseUnitId <= 0) {
                throw new Exception(
                    'Base unit is required.'
                );
            }


            Product::update(
                $id,
                $companyId,
                $categoryId,
                $groupId,
                $brandId,
                $productName,
                $sku,
                $barcode,
                $hsnCode,
                $baseUnitId,
                $mrp,
                $purchasePrice,
                $salePrice,
                $wholesalePrice,
                $gstRate,
                $cessRate,
                $minStockAlert,
                $trackBatch,
                $trackExpiry,
                $isActive
            );

            header(
                'Location: products.php?success=' .
                urlencode('Product updated successfully ✅')
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'product.delete',
                'delete'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new Exception(
                    'Invalid product.'
                );
            }

            Product::delete(
                $id,
                $companyId
            );

            header(
                'Location: products.php?success=' .
                urlencode('Product deleted successfully ✅')
            );

            exit;
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    $success = (string) $_GET['success'];
}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editProduct =
            Product::find(
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

$categories =
    Product::getCategories(
        $companyId
    );

$groups =
    Product::getGroups(
        $companyId
    );

$brands =
    Product::getBrands(
        $companyId
    );

$units =
    Product::getUnits(
        $companyId
    );


/*
|--------------------------------------------------------------------------
| PRODUCTS
|--------------------------------------------------------------------------
*/

$products =
    Product::getAll(
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

<title>VyaapaarOS - Products</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #111827;
}

.container {
    width: 1300px;
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
    display: flex;
    gap: 25px;
    margin-top: 15px;
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

.cancel {
    margin-left: 10px;
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

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1250px;
}

.table-wrap {
    overflow-x: auto;
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

a {
    text-decoration: none;
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

.back {
    display: inline-block;
    margin-top: 5px;
}

@media (max-width: 800px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .checkbox-row {
        flex-direction: column;
        gap: 10px;
    }

}

</style>

</head>

<body>

<div class="container">


<div class="card">

<h1>
<?= $editProduct ? 'Edit Product' : 'Products' ?>
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


<?php if (!$editProduct): ?>


<?php if (
    PermissionMiddleware::check(
        'product.create',
        'create'
    )
): ?>

<h2>
Add Product
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
Product Name *
</label>

<input
    type="text"
    name="product_name"
    required
>

</div>


<div class="field">

<label>
Category
</label>

<select name="category_id">

<option value="">
-- Select Category --
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?= (int) $category['id'] ?>"
>

<?= htmlspecialchars(
    $category['category_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Product Group
</label>

<select name="group_id">

<option value="">
-- Select Group --
</option>

<?php foreach ($groups as $group): ?>

<option
    value="<?= (int) $group['id'] ?>"
>

<?= htmlspecialchars(
    $group['group_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Brand
</label>

<select name="brand_id">

<option value="">
-- Select Brand --
</option>

<?php foreach ($brands as $brand): ?>

<option
    value="<?= (int) $brand['id'] ?>"
>

<?= htmlspecialchars(
    $brand['brand_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Base Unit *
</label>

<select
    name="base_unit_id"
    required
>

<option value="">
-- Select Unit --
</option>

<?php foreach ($units as $unit): ?>

<option
    value="<?= (int) $unit['id'] ?>"
>

<?= htmlspecialchars(
    $unit['unit_name']
) ?>

(<?= htmlspecialchars(
    $unit['short_code']
) ?>)

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
SKU
</label>

<input
    type="text"
    name="sku"
>

</div>


<div class="field">

<label>
Barcode
</label>

<input
    type="text"
    name="barcode"
>

</div>


<div class="field">

<label>
HSN Code
</label>

<input
    type="text"
    name="hsn_code"
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
Purchase Price *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="purchase_price"
    value="0"
    required
>

</div>


<div class="field">

<label>
Sale Price *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="sale_price"
    value="0"
    required
>

</div>


<div class="field">

<label>
Wholesale Price *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="wholesale_price"
    value="0"
    required
>

</div>


<div class="field">

<label>
GST Rate %
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="gst_rate"
    value="0"
>

</div>


<div class="field">

<label>
CESS Rate %
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="cess_rate"
    value="0"
>

</div>


<div class="field">

<label>
Minimum Stock Alert
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="min_stock_alert"
    value="0"
>

</div>

</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="track_batch"
    value="1"
>

Track Batch

</label>


<label>

<input
    type="checkbox"
    name="track_expiry"
    value="1"
>

Track Expiry

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="create"
>
Create Product
</button>

</div>

</form>

<?php endif; ?>


<?php else: ?>


<h2>
Edit Product
</h2>

<form method="POST">

<input
    type="hidden"
    name="action"
    value="update"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $editProduct['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Product Name *
</label>

<input
    type="text"
    name="product_name"
    value="<?= htmlspecialchars(
        $editProduct['product_name']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Category
</label>

<select name="category_id">

<option value="">
-- Select Category --
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?= (int) $category['id'] ?>"
    <?= (
        (int) ($editProduct['category_id'] ?? 0)
        === (int) $category['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $category['category_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Product Group
</label>

<select name="group_id">

<option value="">
-- Select Group --
</option>

<?php foreach ($groups as $group): ?>

<option
    value="<?= (int) $group['id'] ?>"
    <?= (
        (int) ($editProduct['group_id'] ?? 0)
        === (int) $group['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $group['group_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Brand
</label>

<select name="brand_id">

<option value="">
-- Select Brand --
</option>

<?php foreach ($brands as $brand): ?>

<option
    value="<?= (int) $brand['id'] ?>"
    <?= (
        (int) ($editProduct['brand_id'] ?? 0)
        === (int) $brand['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $brand['brand_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Base Unit *
</label>

<select
    name="base_unit_id"
    required
>

<option value="">
-- Select Unit --
</option>

<?php foreach ($units as $unit): ?>

<option
    value="<?= (int) $unit['id'] ?>"
    <?= (
        (int) $editProduct['base_unit_id']
        === (int) $unit['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $unit['unit_name']
) ?>

(<?= htmlspecialchars(
    $unit['short_code']
) ?>)

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
SKU
</label>

<input
    type="text"
    name="sku"
    value="<?= htmlspecialchars(
        $editProduct['sku'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Barcode
</label>

<input
    type="text"
    name="barcode"
    value="<?= htmlspecialchars(
        $editProduct['barcode'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
HSN Code
</label>

<input
    type="text"
    name="hsn_code"
    value="<?= htmlspecialchars(
        $editProduct['hsn_code'] ?? ''
    ) ?>"
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
        $editProduct['mrp']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Purchase Price *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="purchase_price"
    value="<?= htmlspecialchars(
        $editProduct['purchase_price']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Sale Price *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="sale_price"
    value="<?= htmlspecialchars(
        $editProduct['sale_price']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Wholesale Price *
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="wholesale_price"
    value="<?= htmlspecialchars(
        $editProduct['wholesale_price']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
GST Rate %
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="gst_rate"
    value="<?= htmlspecialchars(
        $editProduct['gst_rate']
    ) ?>"
>

</div>


<div class="field">

<label>
CESS Rate %
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="cess_rate"
    value="<?= htmlspecialchars(
        $editProduct['cess_rate']
    ) ?>"
>

</div>


<div class="field">

<label>
Minimum Stock Alert
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="min_stock_alert"
    value="<?= htmlspecialchars(
        $editProduct['min_stock_alert']
    ) ?>"
>

</div>

</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="track_batch"
    value="1"
    <?= $editProduct['track_batch']
        ? 'checked'
        : '' ?>
>

Track Batch

</label>


<label>

<input
    type="checkbox"
    name="track_expiry"
    value="1"
    <?= $editProduct['track_expiry']
        ? 'checked'
        : '' ?>
>

Track Expiry

</label>


<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editProduct['is_active']
        ? 'checked'
        : '' ?>
>

Active Product

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Product
</button>

<a
    href="products.php"
    class="cancel"
>
Cancel
</a>

</div>

</form>

<?php endif; ?>

</div>


<div class="card">

<h2>
Product List
</h2>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>ID</th>
<th>Product</th>
<th>Category</th>
<th>Group</th>
<th>Brand</th>
<th>Unit</th>
<th>SKU</th>
<th>MRP</th>
<th>Sale Price</th>
<th>GST %</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($products as $product): ?>

<tr>

<td>
<?= (int) $product['id'] ?>
</td>

<td>
<?= htmlspecialchars(
    $product['product_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $product['category_name'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $product['group_name'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $product['brand_name'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $product['short_code']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $product['sku'] ?? '-'
) ?>
</td>

<td>
<?= number_format(
    (float) $product['mrp'],
    2
) ?>
</td>

<td>
<?= number_format(
    (float) $product['sale_price'],
    2
) ?>
</td>

<td>
<?= number_format(
    (float) $product['gst_rate'],
    2
) ?>%
</td>

<td>

<?php if ($product['is_active']): ?>

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
    href="products.php?edit=<?= (int) $product['id'] ?>"
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
        'product.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this product?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $product['id'] ?>"
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


<?php if (empty($products)): ?>

<tr>

<td colspan="12">
No products found.
</td>

</tr>

<?php endif; ?>


</tbody>

</table>

</div>

</div>


<div class="card">

<a
    href="dashboard.php"
    class="back"
>
← Back to Dashboard
</a>

</div>


</div>

</body>

</html>