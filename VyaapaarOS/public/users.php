<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/User.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'user.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$users = User::getAllByCompany($companyId);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS - Users</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    width: 1100px;
    max-width: calc(100% - 40px);
    margin: 30px auto;
    background: white;
    padding: 25px;
    border-radius: 10px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.add-button {
    background: #2563eb;
    color: white;
    padding: 10px 18px;
    text-decoration: none;
    border-radius: 6px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 25px;
}

th,
td {
    border: 1px solid #ddd;
    padding: 10px;
}

th {
    background: #f1f5f9;
    text-align: left;
}

.active {
    color: #15803d;
    font-weight: bold;
}

.inactive {
    color: #dc2626;
    font-weight: bold;
}

.back {
    display: inline-block;
    margin-top: 20px;
}

</style>

</head>

<body>

<div class="container">

<div class="header">

<h1>Users</h1>

<?php if (PermissionMiddleware::check('user.create', 'create')): ?>

<a
    href="user_create.php"
    class="add-button"
>
    + Add User
</a>

<?php endif; ?>

</div>

<table>

<thead>

<tr>

<th>ID</th>
<th>Username</th>
<th>Full Name</th>
<th>Role</th>
<th>Branch</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php foreach ($users as $user): ?>

<tr>

<td>
<?= htmlspecialchars((string) $user['id']) ?>
</td>

<td>
<?= htmlspecialchars($user['username']) ?>
</td>

<td>
<?= htmlspecialchars($user['full_name']) ?>
</td>

<td>
<?= htmlspecialchars($user['role_name']) ?>
</td>

<td>
<?= htmlspecialchars($user['branch_name'] ?? '-') ?>
</td>

<td>

<?php if ($user['is_active']): ?>

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

<?php if (PermissionMiddleware::check('user.edit', 'edit')): ?>

<a href="user_edit.php?id=<?= (int) $user['id'] ?>">
    Edit
</a>

<?php endif; ?>

</td>




</tr>

<?php endforeach; ?>

</tbody>

</table>

<a
    href="dashboard.php"
    class="back"
>
    ← Back to Dashboard
</a>

</div>

</body>

</html>