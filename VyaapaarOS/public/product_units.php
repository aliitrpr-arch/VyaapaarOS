<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/ProductUnit.php';

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
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $action = $_POST['action'] ?? '';


        if ($action === 'create') {

            PermissionMiddleware::require(
                'product.create',
                'create'
            );

            $unitName = trim(
                $_POST['unit_name'] ?? ''
            );

            $shortCode = trim(
                $_POST['short_code'] ?? ''
            );

            if ($unitName === '') {
                throw new Exception(
                    'Unit name is required.'
                );
            }

            if ($shortCode === '') {
                throw new Exception(
                    'Short code is required.'
                );
            }

            ProductUnit::create(
                $companyId,
                $unitName,
                $shortCode
            );

            $success =
                'Product Unit created successfully ✅';
        }


        if ($action === 'update') {

            PermissionMiddleware::require(
                'product.edit',
                'edit'
            );

            $id = (int) ($_POST['id'] ?? 0);

            $unitName = trim(
                $_POST['unit_name'] ?? ''
            );

            $shortCode = trim(
                $_POST['short_code'] ?? ''
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';

            if ($id <= 0) {
                throw new Exception(
                    'Invalid unit.'
                );
            }

            if ($unitName === '') {
                throw new Exception(
                    'Unit name is required.'
                );
            }

            if ($shortCode === '') {
                throw new Exception(
                    'Short code is required.'
                );
            }

            ProductUnit::update(
                $id,
                $companyId,
                $unitName,
                $shortCode,
                $isActive
            );

            $success =
                'Product Unit updated successfully ✅';
        }


        if ($action === 'delete') {

            PermissionMiddleware::require(
                'product.delete',
                'delete'
            );

            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception(
                    'Invalid unit.'
                );
            }

            ProductUnit::delete(
                $id,
                $companyId
            );

            $success =
                'Product Unit deleted successfully ✅';
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

$editUnit = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editUnit =
            ProductUnit::find(
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

$units =
    ProductUnit::getAll(
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

<title>VyaapaarOS - Product Units</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    width: 900px;
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

input {
    padding: 10px;
    width: 300px;
    max-width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 6px;
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

.active {
    color: #15803d;
    font-weight: bold;
}

.inactive {
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
Product Units
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


<?php if ($editUnit): ?>

<h2>
Edit Unit
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
    value="<?= (int) $editUnit['id'] ?>"
>


<div class="field">

<label>
Unit Name
</label>

<input
    type="text"
    name="unit_name"
    value="<?= htmlspecialchars($editUnit['unit_name']) ?>"
    required
>

</div>


<div class="field">

<label>
Short Code
</label>

<input
    type="text"
    name="short_code"
    value="<?= htmlspecialchars($editUnit['short_code']) ?>"
    required
>

</div>


<div class="field">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editUnit['is_active'] ? 'checked' : '' ?>
>

Active Unit

</label>

</div>


<button
    type="submit"
    class="edit"
>
Update Unit
</button>

<a
    href="product_units.php"
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
Add Product Unit
</h2>

<form method="POST">

<input
    type="hidden"
    name="action"
    value="create"
>


<div class="field">

<label>
Unit Name
</label>

<input
    type="text"
    name="unit_name"
    placeholder="Example: Piece"
    required
>

</div>


<div class="field">

<label>
Short Code
</label>

<input
    type="text"
    name="short_code"
    placeholder="Example: PCS"
    required
>

</div>


<button
    type="submit"
    class="create"
>
Add Unit
</button>

</form>

<?php endif; ?>


<?php endif; ?>

</div>


<div class="card">

<h2>
Unit List
</h2>


<table>

<thead>

<tr>

<th>ID</th>
<th>Unit Name</th>
<th>Short Code</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($units as $unit): ?>

<tr>

<td>
<?= (int) $unit['id'] ?>
</td>

<td>
<?= htmlspecialchars($unit['unit_name']) ?>
</td>

<td>
<?= htmlspecialchars($unit['short_code']) ?>
</td>

<td>

<?php if ($unit['is_active']): ?>

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
    href="product_units.php?edit=<?= (int) $unit['id'] ?>"
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
    onsubmit="return confirm('Delete this unit?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $unit['id'] ?>"
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


<?php if (empty($units)): ?>

<tr>

<td colspan="5">
No product units found.
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