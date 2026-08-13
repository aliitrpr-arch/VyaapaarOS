<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/Company.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Permission
|--------------------------------------------------------------------------
*/

PermissionMiddleware::require(
    'company.view',
    'view'
);


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = null;
$success = null;

$editCompany = null;


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
                'company.create',
                'create'
            );


            $companyName = trim(
                $_POST['company_name'] ?? ''
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

            $address = trim(
                $_POST['address'] ?? ''
            );

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );


            if ($companyName === '') {
                throw new Exception(
                    'Company name is required.'
                );
            }


            if (
                $email !== ''
                && !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new Exception(
                    'Please enter a valid email address.'
                );
            }


            Company::create(
                $companyName,
                $gstin,
                $phone,
                $email,
                $address,
                $stateCode
            );


            $success =
                'Company created successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'company.edit',
                'edit'
            );


            $id = (int) (
                $_POST['id'] ?? 0
            );


            $companyName = trim(
                $_POST['company_name'] ?? ''
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

            $address = trim(
                $_POST['address'] ?? ''
            );

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid company.'
                );
            }


            if ($companyName === '') {
                throw new Exception(
                    'Company name is required.'
                );
            }


            if (
                $email !== ''
                && !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new Exception(
                    'Please enter a valid email address.'
                );
            }


            Company::update(
                $id,
                $companyName,
                $gstin,
                $phone,
                $email,
                $address,
                $stateCode,
                $isActive
            );


            $success =
                'Company updated successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'company.delete',
                'delete'
            );


            $id = (int) (
                $_POST['id'] ?? 0
            );


            if ($id <= 0) {
                throw new Exception(
                    'Invalid company.'
                );
            }


            Company::delete($id);


            $success =
                'Company deleted successfully ✅';
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

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];


    if ($editId > 0) {

        $editCompany =
            Company::find($editId);
    }
}


/*
|--------------------------------------------------------------------------
| COMPANY LIST
|--------------------------------------------------------------------------
*/

$companies =
    Company::getAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS - Companies</title>


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
textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-family: Arial, sans-serif;
}


textarea {
    min-height: 90px;
    resize: vertical;
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
<?= $editCompany ? 'Edit Company' : 'Company Master' ?>
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


<?php if (!$editCompany): ?>


<?php if (
    PermissionMiddleware::check(
        'company.create',
        'create'
    )
): ?>


<h2>
Add Company
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
Company Name *
</label>

<input
    type="text"
    name="company_name"
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
>

</div>


<div class="field">

<label>
Phone
</label>

<input
    type="text"
    name="phone"
>

</div>


<div class="field">

<label>
Email
</label>

<input
    type="email"
    name="email"
>

</div>


<div class="field">

<label>
State Code
</label>

<input
    type="text"
    name="state_code"
>

</div>


<div class="field">

<label>
Address
</label>

<textarea
    name="address"
></textarea>

</div>


</div>


<div class="form-actions">

<button
    type="submit"
    class="create"
>
Create Company
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
    value="<?= (int) $editCompany['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Company Name *
</label>

<input
    type="text"
    name="company_name"
    value="<?= htmlspecialchars(
        $editCompany['company_name']
    ) ?>"
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
    value="<?= htmlspecialchars(
        $editCompany['gstin'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Phone
</label>

<input
    type="text"
    name="phone"
    value="<?= htmlspecialchars(
        $editCompany['phone'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Email
</label>

<input
    type="email"
    name="email"
    value="<?= htmlspecialchars(
        $editCompany['email'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
State Code
</label>

<input
    type="text"
    name="state_code"
    value="<?= htmlspecialchars(
        $editCompany['state_code'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Address
</label>

<textarea
    name="address"
><?= htmlspecialchars(
    $editCompany['address'] ?? ''
) ?></textarea>

</div>


</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editCompany['is_active']
        ? 'checked'
        : '' ?>
>

Active Company

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Company
</button>


<a
    href="companies.php"
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
Company List
</h2>


<div class="table-wrap">


<table>

<thead>

<tr>

<th>ID</th>
<th>Company Name</th>
<th>GSTIN</th>
<th>Phone</th>
<th>Email</th>
<th>State Code</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>


<?php foreach ($companies as $company): ?>

<tr>


<td>
<?= (int) $company['id'] ?>
</td>


<td>
<?= htmlspecialchars(
    $company['company_name']
) ?>
</td>


<td>
<?= htmlspecialchars(
    $company['gstin'] ?? '-'
) ?>
</td>


<td>
<?= htmlspecialchars(
    $company['phone'] ?? '-'
) ?>
</td>


<td>
<?= htmlspecialchars(
    $company['email'] ?? '-'
) ?>
</td>


<td>
<?= htmlspecialchars(
    $company['state_code'] ?? '-'
) ?>
</td>


<td>

<?php if ($company['is_active']): ?>

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
        'company.edit',
        'edit'
    )
): ?>

<a
    href="companies.php?edit=<?= (int) $company['id'] ?>"
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
        'company.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this company?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>


<input
    type="hidden"
    name="id"
    value="<?= (int) $company['id'] ?>"
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


<?php if (empty($companies)): ?>

<tr>

<td colspan="8">
No companies found.
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