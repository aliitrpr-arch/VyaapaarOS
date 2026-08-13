<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/User.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'user.edit',
    'edit'
);

$companyId = (int) Session::get('company_id');

$userId = isset($_GET['id'])
    ? (int) $_GET['id']
    : (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: users.php');
    exit;
}

$user = User::find(
    $userId,
    $companyId
);

if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$db = Database::connect();


/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Branches
|--------------------------------------------------------------------------
*/

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
$success = null;


/*
|--------------------------------------------------------------------------
| Update User
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $branchId = !empty($_POST['branch_id'])
            ? (int) $_POST['branch_id']
            : null;

        $roleId = (int) ($_POST['role_id'] ?? 0);

        $fullName = trim(
            $_POST['full_name'] ?? ''
        );

        $isActive = isset($_POST['is_active']);


        if ($fullName === '') {
            throw new Exception(
                'Full name is required.'
            );
        }


        if ($roleId <= 0) {
            throw new Exception(
                'Please select a role.'
            );
        }


        User::update(
            $userId,
            $companyId,
            $branchId,
            $roleId,
            $fullName,
            $isActive
        );


        /*
        |--------------------------------------------------------------------------
        | Optional Password Change
        |--------------------------------------------------------------------------
        */

        $newPassword = $_POST['new_password'] ?? '';

        if ($newPassword !== '') {

            if (strlen($newPassword) < 6) {

                throw new Exception(
                    'New password must be at least 6 characters.'
                );
            }

            User::updatePassword(
                $userId,
                $companyId,
                $newPassword
            );
        }


        $success = 'User updated successfully ✅';


        // Reload updated user
        $user = User::find(
            $userId,
            $companyId
        );

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

<title>VyaapaarOS - Edit User</title>

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

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-row input {
    width: auto;
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

.success {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.readonly {
    background: #f3f4f6;
}

.back {
    display: inline-block;
    margin-top: 20px;
}

</style>

</head>

<body>

<div class="container">

<h1>Edit User</h1>


<?php if ($error): ?>

<div class="error">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<?php if ($success): ?>

<div class="success">

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>


<form method="POST">

<input
    type="hidden"
    name="user_id"
    value="<?= (int) $user['id'] ?>"
>


<div class="form-group">

<label>
Username
</label>

<input
    type="text"
    class="readonly"
    value="<?= htmlspecialchars($user['username']) ?>"
    readonly
>

</div>


<div class="form-group">

<label>
Full Name
</label>

<input
    type="text"
    name="full_name"
    required
    value="<?= htmlspecialchars($user['full_name']) ?>"
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
    value="<?= (int) $role['id'] ?>"
    <?= (int) $user['role_id'] === (int) $role['id'] ? 'selected' : '' ?>
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
    value="<?= (int) $branch['id'] ?>"
    <?= (int) $user['branch_id'] === (int) $branch['id'] ? 'selected' : '' ?>
>

<?= htmlspecialchars($branch['branch_name']) ?>

<?php if (!empty($branch['branch_code'])): ?>

(<?= htmlspecialchars($branch['branch_code']) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>
New Password
</label>

<input
    type="password"
    name="new_password"
    minlength="6"
    placeholder="Leave blank to keep current password"
>

</div>


<div class="form-group checkbox-row">

<input
    type="checkbox"
    name="is_active"
    id="is_active"
    <?= $user['is_active'] ? 'checked' : '' ?>
>

<label for="is_active">
    Active User
</label>

</div>


<button type="submit">
Save Changes
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