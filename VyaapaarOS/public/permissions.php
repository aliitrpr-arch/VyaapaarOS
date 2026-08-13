<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Permission.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'permission.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$db = Database::connect();


/*
|--------------------------------------------------------------------------
| Users
|--------------------------------------------------------------------------
*/

$usersStmt = $db->prepare("
    SELECT
        u.id,
        u.username,
        u.full_name,
        r.role_name
    FROM users u

    INNER JOIN roles r
        ON r.id = u.role_id

    WHERE u.company_id = :company_id

    ORDER BY u.id
");

$usersStmt->execute([
    'company_id' => $companyId
]);

$users = $usersStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Selected User
|--------------------------------------------------------------------------
*/

$selectedUserId = isset($_GET['user_id'])
    ? (int) $_GET['user_id']
    : 0;

if ($selectedUserId <= 0 && !empty($users)) {
    $selectedUserId = (int) $users[0]['id'];
}


/*
|--------------------------------------------------------------------------
| Permissions
|--------------------------------------------------------------------------
*/

$permissions = Permission::getAll();

$currentPermissions = [];

if ($selectedUserId > 0) {

    $currentPermissions =
        Permission::getUserPermissions(
            $selectedUserId
        );
}


$success = null;
$error = null;


/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $selectedUserId =
            (int) ($_POST['user_id'] ?? 0);

        $submitted = $_POST['permissions'] ?? [];

        Permission::saveUserPermissions(
            $selectedUserId,
            $submitted
        );

        $currentPermissions =
            Permission::getUserPermissions(
                $selectedUserId
            );

        $success =
            'Permissions saved successfully ✅';

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Group permissions by module
|--------------------------------------------------------------------------
*/

$groupedPermissions = [];

foreach ($permissions as $permission) {

    $module = $permission['module_name']
        ?: 'Other';

    $groupedPermissions[$module][] =
        $permission;
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

<title>VyaapaarOS - Permissions</title>

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

select {
    padding: 10px;
    width: 350px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
}

.select-all {
    padding: 10px 15px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th,
td {
    border: 1px solid #ddd;
    padding: 10px;
}

th {
    background: #f1f5f9;
}

.module {
    background: #e5e7eb;
    font-weight: bold;
}

.checkbox {
    text-align: center;
}

.save {
    margin-top: 25px;
    padding: 12px 25px;
    border: none;
    background: #16a34a;
    color: white;
    border-radius: 6px;
    cursor: pointer;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 6px;
    margin: 15px 0;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 6px;
    margin: 15px 0;
}

.back {
    display: inline-block;
    margin-top: 20px;
}

</style>

</head>

<body>

<div class="container">

<h1>Permissions Management</h1>


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


<form method="GET">

<label>
<strong>Select User:</strong>
</label>

<select
    name="user_id"
    onchange="this.form.submit()"
>

<?php foreach ($users as $user): ?>

<option
    value="<?= (int) $user['id'] ?>"
    <?= $selectedUserId === (int) $user['id'] ? 'selected' : '' ?>
>

<?= htmlspecialchars($user['full_name']) ?>

(<?= htmlspecialchars($user['username']) ?>)

</option>

<?php endforeach; ?>

</select>

</form>


<form method="POST">

<input
    type="hidden"
    name="user_id"
    value="<?= $selectedUserId ?>"
>


<div class="toolbar">

<h2>
Permissions
</h2>

<button
    type="button"
    class="select-all"
    onclick="selectAllPermissions()"
>
☑ Select All
</button>

</div>


<table>

<thead>

<tr>

<th>Permission</th>
<th>View</th>
<th>Create</th>
<th>Edit</th>
<th>Delete</th>

</tr>

</thead>

<tbody>


<?php foreach ($groupedPermissions as $module => $modulePermissions): ?>

<tr>

<td
    colspan="5"
    class="module"
>

<?= htmlspecialchars($module) ?>

</td>

</tr>


<?php foreach ($modulePermissions as $permission): ?>

<?php

$permissionId =
    (int) $permission['id'];

$current =
    $currentPermissions[$permissionId]
    ?? [
        'can_view' => false,
        'can_create' => false,
        'can_edit' => false,
        'can_delete' => false
    ];

?>


<tr>

<td>

<strong>
<?= htmlspecialchars($permission['permission_name']) ?>
</strong>

<br>

<small>
<?= htmlspecialchars($permission['permission_key']) ?>
</small>

</td>


<td class="checkbox">

<input
    type="checkbox"
    class="permission-checkbox"
    name="permissions[<?= $permissionId ?>][view]"
    value="1"
    <?= $current['can_view'] ? 'checked' : '' ?>
>

</td>


<td class="checkbox">

<input
    type="checkbox"
    class="permission-checkbox"
    name="permissions[<?= $permissionId ?>][create]"
    value="1"
    <?= $current['can_create'] ? 'checked' : '' ?>
>

</td>


<td class="checkbox">

<input
    type="checkbox"
    class="permission-checkbox"
    name="permissions[<?= $permissionId ?>][edit]"
    value="1"
    <?= $current['can_edit'] ? 'checked' : '' ?>
>

</td>


<td class="checkbox">

<input
    type="checkbox"
    class="permission-checkbox"
    name="permissions[<?= $permissionId ?>][delete]"
    value="1"
    <?= $current['can_delete'] ? 'checked' : '' ?>
>

</td>

</tr>

<?php endforeach; ?>

<?php endforeach; ?>


</tbody>

</table>


<button
    type="submit"
    class="save"
>
Save Permissions
</button>

</form>


<a
    href="dashboard.php"
    class="back"
>
← Back to Dashboard
</a>

</div>


<script>

function selectAllPermissions() {

    const checkboxes =
        document.querySelectorAll(
            '.permission-checkbox'
        );

    let allChecked = true;

    checkboxes.forEach(
        checkbox => {
            if (!checkbox.checked) {
                allChecked = false;
            }
        }
    );


    checkboxes.forEach(
        checkbox => {
            checkbox.checked = !allChecked;
        }
    );
}

</script>

</body>

</html>