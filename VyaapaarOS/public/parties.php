<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/models/Party.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'party.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$error = null;
$success = null;
$editParty = null;


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
                'party.create',
                'create'
            );

            $zoneId = ($_POST['zone_id'] ?? '') !== ''
                ? (int) $_POST['zone_id']
                : null;

            $salesmanId = ($_POST['salesman_id'] ?? '') !== ''
                ? (int) $_POST['salesman_id']
                : null;

            $partyName = trim(
                $_POST['party_name'] ?? ''
            );

            $partyType = trim(
                $_POST['party_type'] ?? ''
            );

            $gstType = trim(
                $_POST['gst_type'] ?? ''
            );

            $phone = trim(
                $_POST['phone'] ?? ''
            );

            $email = trim(
                $_POST['email'] ?? ''
            );

            $gstNumber = trim(
                $_POST['gst_number'] ?? ''
            );

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );

            $address = trim(
                $_POST['address'] ?? ''
            );

            $openingBalance = (float) (
                $_POST['opening_balance'] ?? 0
            );

            $openingBalanceType = trim(
                $_POST['opening_balance_type'] ?? 'DEBIT'
            );

            $creditLimit = (float) (
                $_POST['credit_limit'] ?? 0
            );

            $creditDays = (int) (
                $_POST['credit_days'] ?? 0
            );


            if ($partyName === '') {
                throw new Exception(
                    'Party name is required.'
                );
            }

            if (!in_array(
                $partyType,
                ['CUSTOMER', 'VENDOR', 'BOTH'],
                true
            )) {
                throw new Exception(
                    'Invalid party type.'
                );
            }

            if (!in_array(
                $gstType,
                [
                    'REGISTERED',
                    'UNREGISTERED',
                    'COMPOSITION',
                    'CONSUMER'
                ],
                true
            )) {
                throw new Exception(
                    'Invalid GST type.'
                );
            }

            if ($openingBalance < 0) {
                throw new Exception(
                    'Opening balance cannot be negative.'
                );
            }

            if (!in_array(
                $openingBalanceType,
                ['DEBIT', 'CREDIT'],
                true
            )) {
                throw new Exception(
                    'Invalid opening balance type.'
                );
            }

            if ($creditLimit < 0) {
                throw new Exception(
                    'Credit limit cannot be negative.'
                );
            }

            if ($creditDays < 0) {
                throw new Exception(
                    'Credit days cannot be negative.'
                );
            }


            Party::create(
                $companyId,
                $zoneId,
                $salesmanId,
                $partyName,
                $partyType,
                $gstType,
                $phone,
                $email,
                $gstNumber,
                $stateCode,
                $address,
                $openingBalance,
                $openingBalanceType,
                $creditLimit,
                $creditDays
            );

            $success =
                'Party created successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'party.edit',
                'edit'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            $zoneId = ($_POST['zone_id'] ?? '') !== ''
                ? (int) $_POST['zone_id']
                : null;

            $salesmanId = ($_POST['salesman_id'] ?? '') !== ''
                ? (int) $_POST['salesman_id']
                : null;

            $partyName = trim(
                $_POST['party_name'] ?? ''
            );

            $partyType = trim(
                $_POST['party_type'] ?? ''
            );

            $gstType = trim(
                $_POST['gst_type'] ?? ''
            );

            $phone = trim(
                $_POST['phone'] ?? ''
            );

            $email = trim(
                $_POST['email'] ?? ''
            );

            $gstNumber = trim(
                $_POST['gst_number'] ?? ''
            );

            $stateCode = trim(
                $_POST['state_code'] ?? ''
            );

            $address = trim(
                $_POST['address'] ?? ''
            );

            $openingBalance = (float) (
                $_POST['opening_balance'] ?? 0
            );

            $openingBalanceType = trim(
                $_POST['opening_balance_type'] ?? 'DEBIT'
            );

            $creditLimit = (float) (
                $_POST['credit_limit'] ?? 0
            );

            $creditDays = (int) (
                $_POST['credit_days'] ?? 0
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid party.'
                );
            }

            if ($partyName === '') {
                throw new Exception(
                    'Party name is required.'
                );
            }

            if (!in_array(
                $partyType,
                ['CUSTOMER', 'VENDOR', 'BOTH'],
                true
            )) {
                throw new Exception(
                    'Invalid party type.'
                );
            }

            if (!in_array(
                $gstType,
                [
                    'REGISTERED',
                    'UNREGISTERED',
                    'COMPOSITION',
                    'CONSUMER'
                ],
                true
            )) {
                throw new Exception(
                    'Invalid GST type.'
                );
            }

            if ($openingBalance < 0) {
                throw new Exception(
                    'Opening balance cannot be negative.'
                );
            }

            if (!in_array(
                $openingBalanceType,
                ['DEBIT', 'CREDIT'],
                true
            )) {
                throw new Exception(
                    'Invalid opening balance type.'
                );
            }

            if ($creditLimit < 0) {
                throw new Exception(
                    'Credit limit cannot be negative.'
                );
            }

            if ($creditDays < 0) {
                throw new Exception(
                    'Credit days cannot be negative.'
                );
            }


            Party::update(
                $id,
                $companyId,
                $zoneId,
                $salesmanId,
                $partyName,
                $partyType,
                $gstType,
                $phone,
                $email,
                $gstNumber,
                $stateCode,
                $address,
                $openingBalance,
                $openingBalanceType,
                $creditLimit,
                $creditDays,
                $isActive
            );

            $success =
                'Party updated successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'party.delete',
                'delete'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new Exception(
                    'Invalid party.'
                );
            }

            Party::delete(
                $id,
                $companyId
            );

            $success =
                'Party deleted successfully ✅';
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

        $editParty =
            Party::find(
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

$zones =
    Party::getZones(
        $companyId
    );

$salesmen =
    Party::getSalesmen(
        $companyId
    );


/*
|--------------------------------------------------------------------------
| PARTIES
|--------------------------------------------------------------------------
*/

$parties =
    Party::getAll(
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

<title>VyaapaarOS - Parties</title>

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
    width: 1250px;
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
    grid-template-columns: repeat(3, 1fr);
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
    min-width: 1250px;
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

@media (max-width: 900px) {

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
<?= $editParty ? 'Edit Party' : 'Parties' ?>
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


<?php if (!$editParty): ?>


<?php if (
    PermissionMiddleware::check(
        'party.create',
        'create'
    )
): ?>

<h2>
Add Party
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
Party Name *
</label>

<input
    type="text"
    name="party_name"
    required
>

</div>


<div class="field">

<label>
Party Type *
</label>

<select
    name="party_type"
    required
>

<option value="">
-- Select Type --
</option>

<option value="CUSTOMER">
Customer
</option>

<option value="VENDOR">
Vendor
</option>

<option value="BOTH">
Both
</option>

</select>

</div>


<div class="field">

<label>
GST Type *
</label>

<select
    name="gst_type"
    required
>

<option value="">
-- Select GST Type --
</option>

<option value="REGISTERED">
Registered
</option>

<option value="UNREGISTERED">
Unregistered
</option>

<option value="COMPOSITION">
Composition
</option>

<option value="CONSUMER">
Consumer
</option>

</select>

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
GST Number
</label>

<input
    type="text"
    name="gst_number"
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
Salesman
</label>

<select name="salesman_id">

<option value="">
-- Select Salesman --
</option>

<?php foreach ($salesmen as $salesman): ?>

<option
    value="<?= (int) $salesman['id'] ?>"
>

<?= htmlspecialchars(
    $salesman['salesman_name']
) ?>

<?php if (!empty($salesman['phone'])): ?>

(<?= htmlspecialchars(
    $salesman['phone']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Opening Balance
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="opening_balance"
    value="0"
>

</div>


<div class="field">

<label>
Opening Balance Type
</label>

<select
    name="opening_balance_type"
>

<option value="DEBIT">
Debit
</option>

<option value="CREDIT">
Credit
</option>

</select>

</div>


<div class="field">

<label>
Credit Limit
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="credit_limit"
    value="0"
>

</div>


<div class="field">

<label>
Credit Days
</label>

<input
    type="number"
    min="0"
    name="credit_days"
    value="0"
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
Create Party
</button>

</div>

</form>

<?php endif; ?>


<?php else: ?>


<h2>
Edit Party
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
    value="<?= (int) $editParty['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Party Name *
</label>

<input
    type="text"
    name="party_name"
    value="<?= htmlspecialchars(
        $editParty['party_name']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Party Type *
</label>

<select
    name="party_type"
    required
>

<option
    value="CUSTOMER"
    <?= $editParty['party_type'] === 'CUSTOMER'
        ? 'selected'
        : '' ?>
>
Customer
</option>

<option
    value="VENDOR"
    <?= $editParty['party_type'] === 'VENDOR'
        ? 'selected'
        : '' ?>
>
Vendor
</option>

<option
    value="BOTH"
    <?= $editParty['party_type'] === 'BOTH'
        ? 'selected'
        : '' ?>
>
Both
</option>

</select>

</div>


<div class="field">

<label>
GST Type *
</label>

<select
    name="gst_type"
    required
>

<option
    value="REGISTERED"
    <?= $editParty['gst_type'] === 'REGISTERED'
        ? 'selected'
        : '' ?>
>
Registered
</option>

<option
    value="UNREGISTERED"
    <?= $editParty['gst_type'] === 'UNREGISTERED'
        ? 'selected'
        : '' ?>
>
Unregistered
</option>

<option
    value="COMPOSITION"
    <?= $editParty['gst_type'] === 'COMPOSITION'
        ? 'selected'
        : '' ?>
>
Composition
</option>

<option
    value="CONSUMER"
    <?= $editParty['gst_type'] === 'CONSUMER'
        ? 'selected'
        : '' ?>
>
Consumer
</option>

</select>

</div>


<div class="field">

<label>
Phone
</label>

<input
    type="text"
    name="phone"
    value="<?= htmlspecialchars(
        $editParty['phone'] ?? ''
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
        $editParty['email'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
GST Number
</label>

<input
    type="text"
    name="gst_number"
    value="<?= htmlspecialchars(
        $editParty['gst_number'] ?? ''
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
        $editParty['state_code'] ?? ''
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
        (int) ($editParty['zone_id'] ?? 0)
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
Salesman
</label>

<select name="salesman_id">

<option value="">
-- Select Salesman --
</option>

<?php foreach ($salesmen as $salesman): ?>

<option
    value="<?= (int) $salesman['id'] ?>"
    <?= (
        (int) ($editParty['salesman_id'] ?? 0)
        === (int) $salesman['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $salesman['salesman_name']
) ?>

<?php if (!empty($salesman['phone'])): ?>

(<?= htmlspecialchars(
    $salesman['phone']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Opening Balance
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="opening_balance"
    value="<?= htmlspecialchars(
        $editParty['opening_balance']
    ) ?>"
>

</div>


<div class="field">

<label>
Opening Balance Type
</label>

<select
    name="opening_balance_type"
>

<option
    value="DEBIT"
    <?= $editParty['opening_balance_type'] === 'DEBIT'
        ? 'selected'
        : '' ?>
>
Debit
</option>

<option
    value="CREDIT"
    <?= $editParty['opening_balance_type'] === 'CREDIT'
        ? 'selected'
        : '' ?>
>
Credit
</option>

</select>

</div>


<div class="field">

<label>
Credit Limit
</label>

<input
    type="number"
    step="0.01"
    min="0"
    name="credit_limit"
    value="<?= htmlspecialchars(
        $editParty['credit_limit']
    ) ?>"
>

</div>


<div class="field">

<label>
Credit Days
</label>

<input
    type="number"
    min="0"
    name="credit_days"
    value="<?= htmlspecialchars(
        $editParty['credit_days']
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
    $editParty['address'] ?? ''
) ?></textarea>

</div>

</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editParty['is_active']
        ? 'checked'
        : '' ?>
>

Active Party

</label>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Party
</button>

<a
    href="parties.php"
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
Party List
</h2>

<div class="table-wrap">

<table>

<thead>

<tr>
<th>ID</th>
<th>Party Name</th>
<th>Type</th>
<th>GST</th>
<th>Phone</th>
<th>Zone</th>
<th>Salesman</th>
<th>Opening</th>
<th>Credit Limit</th>
<th>Days</th>
<th>Status</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php foreach ($parties as $party): ?>

<tr>

<td>
<?= (int) $party['id'] ?>
</td>

<td>
<?= htmlspecialchars(
    $party['party_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $party['party_type']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $party['gst_type']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $party['phone'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $party['zone_name'] ?? '-'
) ?>
</td>

<td>
<?= htmlspecialchars(
    $party['salesman_name'] ?? '-'
) ?>
</td>

<td>
<?= number_format(
    (float) $party['opening_balance'],
    2
) ?>

<?= htmlspecialchars(
    $party['opening_balance_type']
) ?>

</td>

<td>
<?= number_format(
    (float) $party['credit_limit'],
    2
) ?>
</td>

<td>
<?= (int) $party['credit_days'] ?>
</td>

<td>

<?php if ($party['is_active']): ?>

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
        'party.edit',
        'edit'
    )
): ?>

<a
    href="parties.php?edit=<?= (int) $party['id'] ?>"
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
        'party.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this party?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $party['id'] ?>"
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


<?php if (empty($parties)): ?>

<tr>

<td colspan="12">
No parties found.
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