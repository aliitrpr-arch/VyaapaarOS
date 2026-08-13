<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'salesman.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$error = null;


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
                'salesman.create',
                'create'
            );

            $salesmanName = trim(
                $_POST['salesman_name'] ?? ''
            );

            $phone = trim(
                $_POST['phone'] ?? ''
            );

            $commissionPercent = (float) (
                $_POST['commission_percent'] ?? 0
            );

            $zoneId = (
                isset($_POST['zone_id'])
                && $_POST['zone_id'] !== ''
            )
                ? (int) $_POST['zone_id']
                : null;


            if ($salesmanName === '') {
                throw new Exception(
                    'Salesman name is required.'
                );
            }

            if ($commissionPercent < 0) {
                throw new Exception(
                    'Commission cannot be negative.'
                );
            }


            $db = Database::connect();

            /*
            |--------------------------------------------------------------
            | Verify Zone belongs to current company
            |--------------------------------------------------------------
            */

            if ($zoneId !== null) {

                $zoneStmt = $db->prepare("
                    SELECT id
                    FROM zones
                    WHERE id = :id
                      AND company_id = :company_id
                      AND is_active = TRUE
                    LIMIT 1
                ");

                $zoneStmt->execute([
                    'id' => $zoneId,
                    'company_id' => $companyId
                ]);

                if (!$zoneStmt->fetch()) {
                    throw new Exception(
                        'Invalid zone selected.'
                    );
                }
            }


            $stmt = $db->prepare("
                INSERT INTO salesmen
                (
                    company_id,
                    salesman_name,
                    phone,
                    commission_percent,
                    is_active,
                    zone_id,
                    created_at,
                    updated_at
                )

                VALUES
                (
                    :company_id,
                    :salesman_name,
                    :phone,
                    :commission_percent,
                    TRUE,
                    :zone_id,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $stmt->execute([
                'company_id' => $companyId,
                'salesman_name' => $salesmanName,
                'phone' => $phone ?: null,
                'commission_percent' => $commissionPercent,
                'zone_id' => $zoneId
            ]);


            header(
                'Location: salesmen.php?success=created'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'salesman.edit',
                'edit'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            $salesmanName = trim(
                $_POST['salesman_name'] ?? ''
            );

            $phone = trim(
                $_POST['phone'] ?? ''
            );

            $commissionPercent = (float) (
                $_POST['commission_percent'] ?? 0
            );

            $zoneId = (
                isset($_POST['zone_id'])
                && $_POST['zone_id'] !== ''
            )
                ? (int) $_POST['zone_id']
                : null;

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid salesman.'
                );
            }

            if ($salesmanName === '') {
                throw new Exception(
                    'Salesman name is required.'
                );
            }

            if ($commissionPercent < 0) {
                throw new Exception(
                    'Commission cannot be negative.'
                );
            }


            $db = Database::connect();


            /*
            |--------------------------------------------------------------
            | Verify Zone
            |--------------------------------------------------------------
            */

            if ($zoneId !== null) {

                $zoneStmt = $db->prepare("
                    SELECT id
                    FROM zones
                    WHERE id = :id
                      AND company_id = :company_id
                      AND is_active = TRUE
                    LIMIT 1
                ");

                $zoneStmt->execute([
                    'id' => $zoneId,
                    'company_id' => $companyId
                ]);

                if (!$zoneStmt->fetch()) {
                    throw new Exception(
                        'Invalid zone selected.'
                    );
                }
            }


            $stmt = $db->prepare("
                UPDATE salesmen

                SET
                    salesman_name = :salesman_name,
                    phone = :phone,
                    commission_percent = :commission_percent,
                    zone_id = :zone_id,
                    is_active = :is_active,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = :id
                  AND company_id = :company_id
            ");

            $stmt->execute([
                'id' => $id,
                'company_id' => $companyId,
                'salesman_name' => $salesmanName,
                'phone' => $phone ?: null,
                'commission_percent' => $commissionPercent,
                'zone_id' => $zoneId,
                'is_active' => $isActive
            ]);


            /*
            |--------------------------------------------------------------
            | Redirect after update
            |--------------------------------------------------------------
            */

            header(
                'Location: salesmen.php?success=updated'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'salesman.delete',
                'delete'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new Exception(
                    'Invalid salesman.'
                );
            }


            $db = Database::connect();

            $stmt = $db->prepare("
                DELETE FROM salesmen

                WHERE id = :id
                  AND company_id = :company_id
            ");

            $stmt->execute([
                'id' => $id,
                'company_id' => $companyId
            ]);


            header(
                'Location: salesmen.php?success=deleted'
            );

            exit;
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Success Message
|--------------------------------------------------------------------------
*/

$success = null;

if (isset($_GET['success'])) {

    switch ($_GET['success']) {

        case 'created':
            $success =
                'Salesman created successfully ✅';
            break;

        case 'updated':
            $success =
                'Salesman updated successfully ✅';
            break;

        case 'deleted':
            $success =
                'Salesman deleted successfully ✅';
            break;
    }
}


/*
|--------------------------------------------------------------------------
| Edit Salesman
|--------------------------------------------------------------------------
*/

$editSalesman = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_id,
                user_id,
                salesman_name,
                phone,
                commission_percent,
                is_active,
                zone_id

            FROM salesmen

            WHERE id = :id
              AND company_id = :company_id

            LIMIT 1
        ");

        $stmt->execute([
            'id' => $editId,
            'company_id' => $companyId
        ]);

        $editSalesman = $stmt->fetch();

        if (!$editSalesman) {

            $error =
                'Salesman not found.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Zones
|--------------------------------------------------------------------------
*/

$db = Database::connect();

$zoneStmt = $db->prepare("
    SELECT
        id,
        zone_name,
        state_code

    FROM zones

    WHERE company_id = :company_id
      AND is_active = TRUE

    ORDER BY zone_name
");

$zoneStmt->execute([
    'company_id' => $companyId
]);

$zones = $zoneStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Salesmen List
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        s.id,
        s.salesman_name,
        s.phone,
        s.commission_percent,
        s.is_active,
        s.zone_id,

        z.zone_name,
        z.state_code

    FROM salesmen s

    LEFT JOIN zones z
        ON z.id = s.zone_id
       AND z.company_id = s.company_id

    WHERE s.company_id = :company_id

    ORDER BY s.id DESC
");

$stmt->execute([
    'company_id' => $companyId
]);

$salesmen = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS - Salesmen</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #111827;
}

.container {
    width: 1100px;
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
select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

.checkbox-row {
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

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.table-wrap {
    overflow-x: auto;
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
<?= $editSalesman ? 'Edit Salesman' : 'Salesmen' ?>
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


<?php if (!$editSalesman): ?>


<?php if (
    PermissionMiddleware::check(
        'salesman.create',
        'create'
    )
): ?>

<h2>
Add Salesman
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
Salesman Name *
</label>

<input
    type="text"
    name="salesman_name"
    required
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
Zone
</label>

<select name="zone_id">

<option value="">
-- Select Zone --
</option>

<?php foreach ($zones as $zone): ?>

<option
    value="<?= (int) $zone['id'] ?>"
>

<?= htmlspecialchars(
    $zone['zone_name']
) ?>

<?php if (!empty($zone['state_code'])): ?>

(<?= htmlspecialchars(
    $zone['state_code']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Commission %
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="commission_percent"
    value="0"
>

</div>


</div>


<div class="form-actions">

<button
    type="submit"
    class="create"
>
Create Salesman
</button>

</div>

</form>

<?php endif; ?>


<?php else: ?>


<h2>
Edit Salesman
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
    value="<?= (int) $editSalesman['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Salesman Name *
</label>

<input
    type="text"
    name="salesman_name"
    value="<?= htmlspecialchars(
        $editSalesman['salesman_name']
    ) ?>"
    required
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
        $editSalesman['phone'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Zone
</label>

<select name="zone_id">

<option value="">
-- Select Zone --
</option>

<?php foreach ($zones as $zone): ?>

<option
    value="<?= (int) $zone['id'] ?>"
    <?= (
        (int) ($editSalesman['zone_id'] ?? 0)
        === (int) $zone['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $zone['zone_name']
) ?>

<?php if (!empty($zone['state_code'])): ?>

(<?= htmlspecialchars(
    $zone['state_code']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Commission %
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="commission_percent"
    value="<?= htmlspecialchars(
        $editSalesman['commission_percent']
    ) ?>"
>

</div>


</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editSalesman['is_active']
        ? 'checked'
        : '' ?>
>

Active Salesman

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Salesman
</button>

<a
    href="salesmen.php"
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
Salesman List
</h2>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>ID</th>
<th>Salesman Name</th>
<th>Phone</th>
<th>Zone</th>
<th>Commission %</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($salesmen as $salesman): ?>

<tr>

<td>
<?= (int) $salesman['id'] ?>
</td>

<td>
<?= htmlspecialchars(
    $salesman['salesman_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $salesman['phone'] ?? '-'
) ?>
</td>

<td>

<?php if (!empty($salesman['zone_name'])): ?>

<?= htmlspecialchars(
    $salesman['zone_name']
) ?>

<?php if (!empty($salesman['state_code'])): ?>

(<?= htmlspecialchars(
    $salesman['state_code']
) ?>)

<?php endif; ?>

<?php else: ?>

-

<?php endif; ?>

</td>

<td>
<?= number_format(
    (float) $salesman['commission_percent'],
    2
) ?>%
</td>

<td>

<?php if ($salesman['is_active']): ?>

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
        'salesman.edit',
        'edit'
    )
): ?>

<a
    href="salesmen.php?edit=<?= (int) $salesman['id'] ?>"
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
        'salesman.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this salesman?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $salesman['id'] ?>"
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


<?php if (empty($salesmen)): ?>

<tr>

<td colspan="7">
No salesmen found.
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