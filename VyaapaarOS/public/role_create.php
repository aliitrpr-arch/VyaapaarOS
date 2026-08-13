<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/controllers/RoleController.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'role.create',
    'create'
);

$companyId = (int) Session::get('company_id');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        RoleController::create([
            'company_id' => $companyId,
            'role_name' => $_POST['role_name'] ?? '',
            'description' => $_POST['description'] ?? ''
        ]);

        header(
            'Location: roles.php?created=1'
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

<title>VyaapaarOS - Add Role</title>

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
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

button {
    padding: 12px 25px;
    border: none;
    background: #2563eb;
    color: white;
    border-radius: 6px;
    cursor: pointer;
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

<h1>Add New Role</h1>

<?php if ($error): ?>

<div class="error">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<form method="POST">


<div class="form-group">

<label>
Role Name
</label>

<input
    type="text"
    name="role_name"
    required
    value="<?= htmlspecialchars($_POST['role_name'] ?? '') ?>"
>

</div>


<div class="form-group">

<label>
Description
</label>

<textarea
    name="description"
><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

</div>


<button type="submit">
Create Role
</button>


</form>


<a
    href="roles.php"
    class="back"
>
    ← Back to Roles
</a>

</div>

</body>

</html>