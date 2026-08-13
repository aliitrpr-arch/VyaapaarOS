<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/models/Warehouse.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| VIEW PERMISSION
|--------------------------------------------------------------------------
*/

PermissionMiddleware::require(
    'warehouse.view',
    'view'
);


$companyId = (int) Session::get('company_id');

$error = null;
$success = null;
$editWarehouse = null;


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
                'warehouse.create',
                'create'
            );


            $branchId = (int) (
                $_POST['branch_id'] ?? 0
            );

            $warehouseName = trim(
                $_POST['warehouse_name'] ?? ''
            );

            $warehouseCode = trim(
                $_POST['warehouse_code'] ?? ''
            );

            $address = trim(
                $_POST['address'] ?? ''
            );


            if ($branchId <= 0) {

                throw new Exception(
                    'Please select a branch.'
                );
            }


            if ($warehouseName === '') {

                throw new Exception(
                    'Warehouse name is required.'
                );
            }


            if ($warehouseCode === '') {

                throw new Exception(
                    'Warehouse code is required.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Verify branch belongs to current company
            |--------------------------------------------------------------------------
            */

            $branches = Warehouse::getBranches(
                $companyId
            );

            $validBranch = false;

            foreach ($branches as $branch) {

                if (
                    (int) $branch['id']
                    === $branchId
                ) {

                    $validBranch = true;
                    break;
                }
            }


            if (!$validBranch) {

                throw new Exception(
                    'Invalid branch selected.'
                );
            }


            Warehouse::create(
                $companyId,
                $branchId,
                $warehouseName,
                $warehouseCode,
                $address ?: null
            );


            /*
            |--------------------------------------------------------------------------
            | Redirect after create
            |--------------------------------------------------------------------------
            */

            header(
                'Location: warehouses.php?success='
                . urlencode(
                    'Warehouse created successfully.'
                )
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
                'warehouse.edit',
                'edit'
            );


            $id = (int) (
                $_POST['id'] ?? 0
            );

            $branchId = (int) (
                $_POST['branch_id'] ?? 0
            );

            $warehouseName = trim(
                $_POST['warehouse_name'] ?? ''
            );

            $warehouseCode = trim(
                $_POST['warehouse_code'] ?? ''
            );

            $address = trim(
                $_POST['address'] ?? ''
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {

                throw new Exception(
                    'Invalid warehouse.'
                );
            }


            if ($branchId <= 0) {

                throw new Exception(
                    'Please select a branch.'
                );
            }


            if ($warehouseName === '') {

                throw new Exception(
                    'Warehouse name is required.'
                );
            }


            if ($warehouseCode === '') {

                throw new Exception(
                    'Warehouse code is required.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Verify warehouse belongs to current company
            |--------------------------------------------------------------------------
            */

            $existingWarehouse =
                Warehouse::find(
                    $id,
                    $companyId
                );


            if (!$existingWarehouse) {

                throw new Exception(
                    'Warehouse not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Verify branch belongs to current company
            |--------------------------------------------------------------------------
            */

            $branches = Warehouse::getBranches(
                $companyId
            );

            $validBranch = false;

            foreach ($branches as $branch) {

                if (
                    (int) $branch['id']
                    === $branchId
                ) {

                    $validBranch = true;
                    break;
                }
            }


            if (!$validBranch) {

                throw new Exception(
                    'Invalid branch selected.'
                );
            }


            Warehouse::update(
                $id,
                $companyId,
                $branchId,
                $warehouseName,
                $warehouseCode,
                $address ?: null,
                $isActive
            );


            /*
            |--------------------------------------------------------------------------
            | Redirect after update
            |--------------------------------------------------------------------------
            */

            header(
                'Location: warehouses.php?success='
                . urlencode(
                    'Warehouse updated successfully.'
                )
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
                'warehouse.delete',
                'delete'
            );


            $id = (int) (
                $_POST['id'] ?? 0
            );


            if ($id <= 0) {

                throw new Exception(
                    'Invalid warehouse.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Verify warehouse belongs to current company
            |--------------------------------------------------------------------------
            */

            $existingWarehouse =
                Warehouse::find(
                    $id,
                    $companyId
                );


            if (!$existingWarehouse) {

                throw new Exception(
                    'Warehouse not found.'
                );
            }


            Warehouse::delete(
                $id,
                $companyId
            );


            /*
            |--------------------------------------------------------------------------
            | Redirect after delete
            |--------------------------------------------------------------------------
            */

            header(
                'Location: warehouses.php?success='
                . urlencode(
                    'Warehouse deleted successfully.'
                )
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

        PermissionMiddleware::require(
            'warehouse.edit',
            'edit'
        );


        $editWarehouse =
            Warehouse::find(
                $editId,
                $companyId
            );


        if (!$editWarehouse) {

            $error =
                'Warehouse not found.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| MASTER DATA
|--------------------------------------------------------------------------
*/

$branches =
    Warehouse::getBranches(
        $companyId
    );


/*
|--------------------------------------------------------------------------
| WAREHOUSE LIST
|--------------------------------------------------------------------------
*/

$warehouses =
    Warehouse::getAll(
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

<title>
VyaapaarOS - Warehouses
</title>


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
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}


.field label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
}


input,
select,
textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}


textarea {
    min-height: 90px;
    resize: vertical;
}


button {
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
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


.checkbox-row {
    margin-top: 18px;
}


.checkbox-row input {
    width: auto;
}


.form-actions {
    margin-top: 20px;
}


.cancel {
    margin-left: 10px;
}


.table-wrap {
    overflow-x: auto;
}


table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
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


a {
    text-decoration: none;
}


@media (max-width: 800px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<div class="container">


<div class="card">


<h1>

<?= $editWarehouse
    ? 'Edit Warehouse'
    : 'Warehouse Master'
?>

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


<?php if (!$editWarehouse): ?>


<?php if (
    PermissionMiddleware::check(
        'warehouse.create',
        'create'
    )
): ?>


<h2>
Add Warehouse
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
Branch *
</label>


<select
    name="branch_id"
    required
>

<option value="">
-- Select Branch --
</option>


<?php foreach ($branches as $branch): ?>

<option
    value="<?= (int) $branch['id'] ?>"
>

<?= htmlspecialchars(
    $branch['branch_name']
) ?>

(
<?= htmlspecialchars(
    $branch['branch_code']
) ?>
)

</option>

<?php endforeach; ?>


</select>

</div>


<div class="field">

<label>
Warehouse Name *
</label>


<input
    type="text"
    name="warehouse_name"
    placeholder="Warehouse Name"
    required
>

</div>


<div class="field">

<label>
Warehouse Code *
</label>


<input
    type="text"
    name="warehouse_code"
    placeholder="Warehouse Code"
    required
>

</div>


<div class="field">

<label>
Address
</label>


<textarea
    name="address"
    placeholder="Warehouse Address"
></textarea>

</div>


</div>


<div class="form-actions">

<button
    type="submit"
    class="create"
>
Create Warehouse
</button>

</div>


</form>


<?php endif; ?>


<?php else: ?>


<h2>
Edit Warehouse
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
    value="<?= (int) $editWarehouse['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Branch *
</label>


<select
    name="branch_id"
    required
>


<?php foreach ($branches as $branch): ?>

<option
    value="<?= (int) $branch['id'] ?>"
    <?= (
        (int) $editWarehouse['branch_id']
        === (int) $branch['id']
    )
        ? 'selected'
        : ''
    ?>
>

<?= htmlspecialchars(
    $branch['branch_name']
) ?>

(
<?= htmlspecialchars(
    $branch['branch_code']
) ?>
)

</option>

<?php endforeach; ?>


</select>

</div>


<div class="field">

<label>
Warehouse Name *
</label>


<input
    type="text"
    name="warehouse_name"
    value="<?= htmlspecialchars(
        $editWarehouse['warehouse_name']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Warehouse Code *
</label>


<input
    type="text"
    name="warehouse_code"
    value="<?= htmlspecialchars(
        $editWarehouse['warehouse_code']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Address
</label>


<textarea
    name="address"
><?= htmlspecialchars(
    $editWarehouse['address'] ?? ''
) ?></textarea>

</div>


</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editWarehouse['is_active']
        ? 'checked'
        : ''
    ?>
>

Active Warehouse

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Warehouse
</button>


<a
    href="warehouses.php"
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
Warehouse List
</h2>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>ID</th>

<th>Warehouse Name</th>

<th>Code</th>

<th>Branch</th>

<th>Address</th>

<th>Status</th>

<th>Action</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $warehouses
    as $warehouse
): ?>


<tr>


<td>

<?= (int) $warehouse['id'] ?>

</td>


<td>

<?= htmlspecialchars(
    $warehouse['warehouse_name']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $warehouse['warehouse_code']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $warehouse['branch_name']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $warehouse['address'] ?? '-'
) ?>

</td>


<td>


<?php if (
    $warehouse['is_active']
): ?>

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
        'warehouse.edit',
        'edit'
    )
): ?>


<a
    href="warehouses.php?edit=<?= (int) $warehouse['id'] ?>"
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
        'warehouse.delete',
        'delete'
    )
): ?>


<form
    method="POST"
    onsubmit="return confirm(
        'Delete this warehouse?'
    );"
>


<input
    type="hidden"
    name="action"
    value="delete"
>


<input
    type="hidden"
    name="id"
    value="<?= (int) $warehouse['id'] ?>"
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


<?php if (empty($warehouses)): ?>


<tr>

<td colspan="7">

No warehouses found.

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