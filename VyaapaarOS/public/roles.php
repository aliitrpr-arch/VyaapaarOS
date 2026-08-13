<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/Role.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'role.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$roles = Role::getAllByCompany($companyId);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS - Roles</title>

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
    text-align: left;
}

th {
    background: #f1f5f9;
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

<h1>Roles</h1>

<?php if (PermissionMiddleware::check('role.create', 'create')): ?>

<a
    href="role_create.php"
    class="add-button"
>
    + Add Role
</a>

<?php endif; ?>

</div>

<table>

<thead>

<tr>

<th>ID</th>
<th>Role Name</th>
<th>Description</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php foreach ($roles as $role): ?>

<tr>

<td>
<?= htmlspecialchars((string) $role['id']) ?>
</td>

<td>
<?= htmlspecialchars($role['role_name']) ?>
</td>

<td>
<?= htmlspecialchars($role['description'] ?? '-') ?>
</td>

<td>

<?php if ($role['is_active']): ?>

<span class="active">
Active
</span>

<?php else: ?>

<span class="inactive">
Inactive
</span>

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