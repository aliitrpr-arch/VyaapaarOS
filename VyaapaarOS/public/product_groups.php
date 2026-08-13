<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/ProductGroup.php';

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
| Create
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

            $groupName = trim(
                $_POST['group_name'] ?? ''
            );

            if ($groupName === '') {
                throw new Exception(
                    'Group name is required.'
                );
            }

            ProductGroup::create(
                $companyId,
                $groupName
            );

            $success =
                'Product Group created successfully ✅';
        }


        if ($action === 'update') {

            PermissionMiddleware::require(
                'product.edit',
                'edit'
            );

            $id = (int) ($_POST['id'] ?? 0);

            $groupName = trim(
                $_POST['group_name'] ?? ''
            );

            if ($id <= 0) {
                throw new Exception(
                    'Invalid group.'
                );
            }

            if ($groupName === '') {
                throw new Exception(
                    'Group name is required.'
                );
            }

            ProductGroup::update(
                $id,
                $companyId,
                $groupName
            );

            $success =
                'Product Group updated successfully ✅';
        }


        if ($action === 'delete') {

            PermissionMiddleware::require(
                'product.delete',
                'delete'
            );

            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception(
                    'Invalid group.'
                );
            }

            ProductGroup::delete(
                $id,
                $companyId
            );

            $success =
                'Product Group deleted successfully ✅';
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

$editGroup = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editGroup =
            ProductGroup::find(
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

$groups =
    ProductGroup::getAll(
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

<title>VyaapaarOS - Product Groups</title>

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
    width: 350px;
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

.actions {
    display: flex;
    gap: 8px;
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
Product Groups
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


<?php if ($editGroup): ?>

<h2>
Edit Product Group
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
    value="<?= (int) $editGroup['id'] ?>"
>

<input
    type="text"
    name="group_name"
    value="<?= htmlspecialchars($editGroup['group_name']) ?>"
    required
>

<button
    type="submit"
    class="edit"
>
Update
</button>

<a
    href="product_groups.php"
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
Add Product Group
</h2>

<form method="POST">

<input
    type="hidden"
    name="action"
    value="create"
>

<input
    type="text"
    name="group_name"
    placeholder="Group Name"
    required
>

<button
    type="submit"
    class="create"
>
Add Group
</button>

</form>

<?php endif; ?>


<?php endif; ?>

</div>


<div class="card">

<h2>
Group List
</h2>


<table>

<thead>

<tr>

<th>ID</th>

<th>Group Name</th>

<th>Created At</th>

<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($groups as $group): ?>

<tr>

<td>
<?= (int) $group['id'] ?>
</td>

<td>
<?= htmlspecialchars($group['group_name']) ?>
</td>

<td>
<?= htmlspecialchars($group['created_at']) ?>
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
    href="product_groups.php?edit=<?= (int) $group['id'] ?>"
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
    onsubmit="return confirm('Delete this product group?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $group['id'] ?>"
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


<?php if (empty($groups)): ?>

<tr>

<td colspan="4">

No product groups found.

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