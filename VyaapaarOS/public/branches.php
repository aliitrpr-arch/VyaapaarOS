<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/Branch.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'branch.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$error = null;
$success = null;

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

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
                'branch.create',
                'create'
            );

            $branchName = trim(
                $_POST['branch_name'] ?? ''
            );

            $branchCode = trim(
                $_POST['branch_code'] ?? ''
            );

            $gstin = trim(
                $_POST['gstin'] ?? ''
            );

            $phone = trim(
                $_POST['phone'] ?? ''
            );

            $email = trim(
                $_POST['email'] ?? ''
            );

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );

            $address = trim(
                $_POST['address'] ?? ''
            );


            if ($branchName === '') {
                throw new Exception(
                    'Branch name is required.'
                );
            }

            if ($branchCode === '') {
                throw new Exception(
                    'Branch code is required.'
                );
            }


            Branch::create(
                $companyId,
                $branchName,
                $branchCode,
                $gstin ?: null,
                $phone ?: null,
                $email ?: null,
                $stateCode ?: null,
                $address ?: null
            );

            $success =
                'Branch created successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'branch.edit',
                'edit'
            );

            $id = (int) ($_POST['id'] ?? 0);

            $branchName = trim(
                $_POST['branch_name'] ?? ''
            );

            $branchCode = trim(
                $_POST['branch_code'] ?? ''
            );

            $gstin = trim(
                $_POST['gstin'] ?? ''
            );

            $phone = trim(
                $_POST['phone'] ?? ''
            );

            $email = trim(
                $_POST['email'] ?? ''
            );

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );

            $address = trim(
                $_POST['address'] ?? ''
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid branch.'
                );
            }

            if ($branchName === '') {
                throw new Exception(
                    'Branch name is required.'
                );
            }

            if ($branchCode === '') {
                throw new Exception(
                    'Branch code is required.'
                );
            }


            $updated = Branch::update(
    $id,
    $companyId,
    $branchName,
    $branchCode,
    $gstin ?: null,
    $phone ?: null,
    $email ?: null,
    $stateCode ?: null,
    $address ?: null,
    $isActive
);

if (!$updated) {
    throw new Exception('Branch update failed.');
}

header('Location: branches.php?success=' . urlencode('Branch updated successfully'));
exit;
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'branch.delete',
                'delete'
            );

            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception(
                    'Invalid branch.'
                );
            }

            Branch::delete(
                $id,
                $companyId
            );

            $success =
                'Branch deleted successfully ✅';
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

$editBranch = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editBranch =
            Branch::find(
                $editId,
                $companyId
            );
    }
}


/*
|--------------------------------------------------------------------------
| LIST
|--------------------------------------------------------------------------
*/

$branches =
    Branch::getAll(
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

<title>VyaapaarOS - Branches</title>


<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #111827;
}

.container {
    width: 1100px;
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
Branch Master
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


<?php if ($editBranch): ?>


<h2>
Edit Branch
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
    value="<?= (int) $editBranch['id'] ?>"
>


<div class="field">

<label>
Branch Name
</label>

<input
    type="text"
    name="branch_name"
    value="<?= htmlspecialchars($editBranch['branch_name']) ?>"
    required
>

</div>


<div class="field">

<label>
Branch Code
</label>

<input
    type="text"
    name="branch_code"
    value="<?= htmlspecialchars($editBranch['branch_code']) ?>"
    required
>

</div>


<div class="field">

<label>
GSTIN
</label>

<input
    type="text"
    name="gstin"
    value="<?= htmlspecialchars($editBranch['gstin'] ?? '') ?>"
>

</div>


<div class="field">

<label>
Phone
</label>

<input
    type="text"
    name="phone"
    value="<?= htmlspecialchars($editBranch['phone'] ?? '') ?>"
>

</div>


<div class="field">

<label>
Email
</label>

<input
    type="email"
    name="email"
    value="<?= htmlspecialchars($editBranch['email'] ?? '') ?>"
>

</div>


<div class="field">

<label>
State Code
</label>

<input
    type="text"
    name="state_code"
    value="<?= htmlspecialchars($editBranch['state_code'] ?? '') ?>"
>

</div>


<div class="field">

<label>
Address
</label>

<textarea
    name="address"
><?= htmlspecialchars($editBranch['address'] ?? '') ?></textarea>

</div>


<div class="field">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editBranch['is_active'] ? 'checked' : '' ?>
>

Active Branch

</label>

</div>


<button
    type="submit"
    class="edit"
>
Update Branch
</button>


<a
    href="branches.php"
    class="cancel"
>
Cancel
</a>

</form>


<?php else: ?>


<?php if (
    PermissionMiddleware::check(
        'branch.create',
        'create'
    )
): ?>


<h2>
Add Branch
</h2>


<form method="POST">

<input
    type="hidden"
    name="action"
    value="create"
>


<div class="field">

<label>
Branch Name
</label>

<input
    type="text"
    name="branch_name"
    placeholder="Branch Name"
    required
>

</div>


<div class="field">

<label>
Branch Code
</label>

<input
    type="text"
    name="branch_code"
    placeholder="Branch Code"
    required
>

</div>


<div class="field">

<label>
GSTIN
</label>

<input
    type="text"
    name="gstin"
    placeholder="GSTIN"
>

</div>


<div class="field">

<label>
Phone
</label>

<input
    type="text"
    name="phone"
    placeholder="Phone"
>

</div>


<div class="field">

<label>
Email
</label>

<input
    type="email"
    name="email"
    placeholder="Email"
>

</div>


<div class="field">

<label>
State Code
</label>

<input
    type="text"
    name="state_code"
    placeholder="State Code"
>

</div>


<div class="field">

<label>
Address
</label>

<textarea
    name="address"
    placeholder="Branch Address"
></textarea>

</div>


<button
    type="submit"
    class="create"
>
Create Branch
</button>

</form>


<?php endif; ?>


<?php endif; ?>

</div>



<div class="card">

<h2>
Branch List
</h2>


<table>

<thead>

<tr>

<th>ID</th>
<th>Branch Name</th>
<th>Branch Code</th>
<th>GSTIN</th>
<th>Phone</th>
<th>Email</th>
<th>State Code</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>


<?php foreach ($branches as $branch): ?>


<tr>

<td>
<?= (int) $branch['id'] ?>
</td>

<td>
<?= htmlspecialchars($branch['branch_name']) ?>
</td>

<td>
<?= htmlspecialchars($branch['branch_code']) ?>
</td>

<td>
<?= htmlspecialchars($branch['gstin'] ?? '-') ?>
</td>

<td>
<?= htmlspecialchars($branch['phone'] ?? '-') ?>
</td>

<td>
<?= htmlspecialchars($branch['email'] ?? '-') ?>
</td>

<td>
<?= htmlspecialchars($branch['state_code'] ?? '-') ?>
</td>

<td>


<?php if ($branch['is_active']): ?>

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
        'branch.edit',
        'edit'
    )
): ?>

<a
    href="branches.php?edit=<?= (int) $branch['id'] ?>"
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
        'branch.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this branch?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $branch['id'] ?>"
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


<?php if (empty($branches)): ?>

<tr>

<td colspan="9">
No branches found.
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