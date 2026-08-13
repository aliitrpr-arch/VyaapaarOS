<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/controllers/UserController.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'user.create',
    'create'
);

$companyId = (int) Session::get('company_id');

$db = Database::connect();

$rolesStmt = $db->prepare("
    SELECT
        id,
        role_name
    FROM roles
    WHERE company_id = :company_id
      AND is_active = true
    ORDER BY role_name
");

$rolesStmt->execute([
    'company_id' => $companyId
]);

$roles = $rolesStmt->fetchAll();


$branchesStmt = $db->prepare("
    SELECT
        id,
        branch_name,
        branch_code
    FROM branches
    WHERE company_id = :company_id
      AND is_active = true
    ORDER BY branch_name
");

$branchesStmt->execute([
    'company_id' => $companyId
]);

$branches = $branchesStmt->fetchAll();


$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $userId = UserController::create([
            'company_id' => $companyId,
            'branch_id' => $_POST['branch_id'] ?? null,
            'role_id' => $_POST['role_id'] ?? 0,
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'full_name' => $_POST['full_name'] ?? ''
        ]);

        header(
            'Location: users.php?created=1'
        );

        exit;

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS - Add User</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    width: 600px;
    max-width: calc(100% - 40px);
    margin: 40px auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
}

h1 {
    margin-top: 0;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
select {
    width: 100%;
    box-sizing: border-box;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

button {
    padding: 12px 25px;
    border: none;
    background: #2563eb;
    color: white;
    border-radius: 6px;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.back {
    display: inline-block;
    margin-top: 20px;
}

</style>

</head>

<body>

<div class="container">

<h1>Add New User</h1>

<?php if ($error): ?>

<div class="error">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<form method="POST">


<div class="form-group">

<label>
Full Name
</label>

<input
    type="text"
    name="full_name"
    required
    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
>

</div>


<div class="form-group">

<label>
Username
</label>

<input
    type="text"
    name="username"
    required
    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
>

</div>


<div class="form-group">

<label>
Password
</label>

<input
    type="password"
    name="password"
    required
    minlength="6"
>

</div>


<div class="form-group">

<label>
Role
</label>

<select
    name="role_id"
    required
>

<option value="">
-- Select Role --
</option>

<?php foreach ($roles as $role): ?>

<option
    value="<?= $role['id'] ?>"
    <?= (($_POST['role_id'] ?? '') == $role['id']) ? 'selected' : '' ?>
>

<?= htmlspecialchars($role['role_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>
Branch
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
    value="<?= $branch['id'] ?>"
    <?= (($_POST['branch_id'] ?? '') == $branch['id']) ? 'selected' : '' ?>
>

<?= htmlspecialchars($branch['branch_name']) ?>

<?php if (!empty($branch['branch_code'])): ?>

(<?= htmlspecialchars($branch['branch_code']) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<button type="submit">
Create User
</button>


</form>


<a
    href="users.php"
    class="back"
>
    ← Back to Users
</a>

</div>

</body>

</html>