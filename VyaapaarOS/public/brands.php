<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/Brand.php';

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


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $action = $_POST['action'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */

        if ($action === 'create') {

            PermissionMiddleware::require(
                'product.create',
                'create'
            );

            $brandName = trim(
                $_POST['brand_name'] ?? ''
            );

            $description = trim(
                $_POST['description'] ?? ''
            );

            if ($brandName === '') {
                throw new Exception(
                    'Brand name is required.'
                );
            }

            Brand::create(
                $companyId,
                $brandName,
                $description
            );

            $success =
                'Brand created successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'product.edit',
                'edit'
            );

            $id = (int) ($_POST['id'] ?? 0);

            $brandName = trim(
                $_POST['brand_name'] ?? ''
            );

            $description = trim(
                $_POST['description'] ?? ''
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';

            if ($id <= 0) {
                throw new Exception(
                    'Invalid brand.'
                );
            }

            if ($brandName === '') {
                throw new Exception(
                    'Brand name is required.'
                );
            }

            Brand::update(
                $id,
                $companyId,
                $brandName,
                $description,
                $isActive
            );

            $success =
                'Brand updated successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'product.delete',
                'delete'
            );

            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception(
                    'Invalid brand.'
                );
            }

            Brand::delete(
                $id,
                $companyId
            );

            $success =
                'Brand deleted successfully ✅';
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Edit
|--------------------------------------------------------------------------
*/

$editBrand = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editBrand =
            Brand::find(
                $editId,
                $companyId
            );
    }
}


/*
|--------------------------------------------------------------------------
| List
|--------------------------------------------------------------------------
*/

$brands =
    Brand::getAll(
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

<title>VyaapaarOS - Brands</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    width: 1000px;
    max-width: calc(100% - 40px);
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

input,
textarea {
    padding: 10px;
    width: 400px;
    max-width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

textarea {
    height: 80px;
    resize: vertical;
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
}

th,
td {
    padding: 12px;
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

a {
    text-decoration: none;
}

.status-active {
    color: #15803d;
    font-weight: bold;
}

.status-inactive {
    color: #dc2626;
    font-weight: bold;
}

.field {
    margin-bottom: 15px;
}

.field label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
}

</style>

</head>

<body>

<div class="container">


<div class="card">

<h1>
Brands
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


<?php if ($editBrand): ?>

<h2>
Edit Brand
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
    value="<?= (int) $editBrand['id'] ?>"
>


<div class="field">

<label>
Brand Name
</label>

<input
    type="text"
    name="brand_name"
    value="<?= htmlspecialchars($editBrand['brand_name']) ?>"
    required
>

</div>


<div class="field">

<label>
Description
</label>

<textarea
    name="description"
><?= htmlspecialchars($editBrand['description'] ?? '') ?></textarea>

</div>


<div class="field">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editBrand['is_active'] ? 'checked' : '' ?>
>

Active Brand

</label>

</div>


<button
    type="submit"
    class="edit"
>
Update Brand
</button>

<a
    href="brands.php"
    class="cancel"
>
Cancel
</a>

</form>


<?php else: ?>


<?php if (
    PermissionMiddleware::check(
        'product.create',
        'create'
    )
): ?>

<h2>
Add Brand
</h2>

<form method="POST">

<input
    type="hidden"
    name="action"
    value="create"
>


<div class="field">

<label>
Brand Name
</label>

<input
    type="text"
    name="brand_name"
    placeholder="Brand Name"
    required
>

</div>


<div class="field">

<label>
Description
</label>

<textarea
    name="description"
    placeholder="Brand Description"
></textarea>

</div>


<button
    type="submit"
    class="create"
>
Add Brand
</button>

</form>

<?php endif; ?>


<?php endif; ?>

</div>


<div class="card">

<h2>
Brand List
</h2>


<table>

<thead>

<tr>

<th>ID</th>
<th>Brand Name</th>
<th>Description</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($brands as $brand): ?>

<tr>

<td>
<?= (int) $brand['id'] ?>
</td>

<td>
<?= htmlspecialchars($brand['brand_name']) ?>
</td>

<td>
<?= htmlspecialchars($brand['description'] ?? '') ?>
</td>

<td>

<?php if ($brand['is_active']): ?>

<span class="status-active">
Active
</span>

<?php else: ?>

<span class="status-inactive">
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
    href="brands.php?edit=<?= (int) $brand['id'] ?>"
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
    onsubmit="return confirm('Delete this brand?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $brand['id'] ?>"
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


<?php if (empty($brands)): ?>

<tr>

<td colspan="5">
No brands found.
</td>

</tr>

<?php endif; ?>


</tbody>

</table>

</div>


<div class="card">

<a href="dashboard.php">
← Back to Dashboard
</a>

</div>


</div>

</body>

</html>