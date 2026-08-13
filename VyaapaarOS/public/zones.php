<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/Zone.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'zone.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$error = null;
$success = null;
$editZone = null;


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
                'zone.create',
                'create'
            );

            $zoneName = trim(
                $_POST['zone_name'] ?? ''
            );

            $parentZoneId =
                ($_POST['parent_zone_id'] ?? '') !== ''
                    ? (int) $_POST['parent_zone_id']
                    : null;

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );

            $description = trim(
                $_POST['description'] ?? ''
            );


            if ($zoneName === '') {
                throw new Exception(
                    'Zone name is required.'
                );
            }


            if ($parentZoneId !== null) {

                if ($parentZoneId <= 0) {
                    $parentZoneId = null;
                }
            }


            Zone::create(
                $companyId,
                $zoneName,
                $parentZoneId,
                $stateCode,
                $description
            );

            $success =
                'Zone created successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'zone.edit',
                'edit'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            $zoneName = trim(
                $_POST['zone_name'] ?? ''
            );

            $parentZoneId =
                ($_POST['parent_zone_id'] ?? '') !== ''
                    ? (int) $_POST['parent_zone_id']
                    : null;

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );

            $description = trim(
                $_POST['description'] ?? ''
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid zone.'
                );
            }


            if ($zoneName === '') {
                throw new Exception(
                    'Zone name is required.'
                );
            }


            if ($parentZoneId !== null) {

                if ($parentZoneId <= 0) {
                    $parentZoneId = null;
                }

                /*
                | Prevent zone from being its own parent
                */

                if ($parentZoneId === $id) {

                    throw new Exception(
                        'A zone cannot be its own parent.'
                    );
                }
            }


            Zone::update(
                $id,
                $companyId,
                $zoneName,
                $parentZoneId,
                $stateCode,
                $description,
                $isActive
            );

            $success =
                'Zone updated successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'zone.delete',
                'delete'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );


            if ($id <= 0) {
                throw new Exception(
                    'Invalid zone.'
                );
            }


            Zone::delete(
                $id,
                $companyId
            );

            $success =
                'Zone deleted successfully ✅';
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

        $editZone =
            Zone::find(
                $editId,
                $companyId
            );
    }
}


/*
|--------------------------------------------------------------------------
| MASTER DATA
|--------------------------------------------------------------------------
*/

$excludeId =
    $editZone
        ? (int) $editZone['id']
        : null;

$parentZones =
    Zone::getActive(
        $companyId,
        $excludeId
    );


/*
|--------------------------------------------------------------------------
| ZONE LIST
|--------------------------------------------------------------------------
*/

$zones =
    Zone::getAll(
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

<title>VyaapaarOS - Zones</title>

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
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
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
    text-decoration: none;
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

@media (max-width: 700px) {

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
<?= $editZone
    ? 'Edit Zone'
    : 'Zones' ?>
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


<?php if (!$editZone): ?>


<?php if (
    PermissionMiddleware::check(
        'zone.create',
        'create'
    )
): ?>

<h2>
Add Zone
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
Zone Name *
</label>

<input
    type="text"
    name="zone_name"
    required
>

</div>


<div class="field">

<label>
Parent Zone
</label>

<select
    name="parent_zone_id"
>

<option value="">
-- No Parent Zone --
</option>

<?php foreach ($parentZones as $parent): ?>

<option
    value="<?= (int) $parent['id'] ?>"
>

<?= htmlspecialchars(
    $parent['zone_name']
) ?>

<?php if (
    !empty($parent['state_code'])
): ?>

(<?= htmlspecialchars(
    $parent['state_code']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
State Code
</label>

<input
    type="text"
    name="state_code"
    maxlength="10"
>

</div>


<div class="field">

<label>
Description
</label>

<textarea
    name="description"
></textarea>

</div>


</div>


<div class="form-actions">

<button
    type="submit"
    class="create"
>
Create Zone
</button>

</div>

</form>

<?php endif; ?>


<?php else: ?>


<h2>
Edit Zone
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
    value="<?= (int) $editZone['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Zone Name *
</label>

<input
    type="text"
    name="zone_name"
    value="<?= htmlspecialchars(
        $editZone['zone_name']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Parent Zone
</label>

<select
    name="parent_zone_id"
>

<option value="">
-- No Parent Zone --
</option>

<?php foreach ($parentZones as $parent): ?>

<option
    value="<?= (int) $parent['id'] ?>"
    <?= (
        (int) ($editZone['parent_zone_id'] ?? 0)
        === (int) $parent['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $parent['zone_name']
) ?>

<?php if (
    !empty($parent['state_code'])
): ?>

(<?= htmlspecialchars(
    $parent['state_code']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
State Code
</label>

<input
    type="text"
    name="state_code"
    maxlength="10"
    value="<?= htmlspecialchars(
        $editZone['state_code'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Description
</label>

<textarea
    name="description"
><?= htmlspecialchars(
    $editZone['description'] ?? ''
) ?></textarea>

</div>


</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= !empty($editZone['is_active'])
        ? 'checked'
        : '' ?>
>

Active Zone

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Zone
</button>

<a
    href="zones.php"
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
Zone List
</h2>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>ID</th>
<th>Zone Name</th>
<th>Parent Zone</th>
<th>State Code</th>
<th>Description</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($zones as $zone): ?>

<tr>

<td>
<?= (int) $zone['id'] ?>
</td>

<td>
<?= htmlspecialchars(
    $zone['zone_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $zone['parent_zone_name'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $zone['state_code'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $zone['description'] ?? '-'
) ?>
</td>

<td>

<?php if ($zone['is_active']): ?>

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
        'zone.edit',
        'edit'
    )
): ?>

<a
    href="zones.php?edit=<?= (int) $zone['id'] ?>"
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
        'zone.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this zone?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $zone['id'] ?>"
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


<?php if (empty($zones)): ?>

<tr>

<td colspan="7">
No zones found.
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