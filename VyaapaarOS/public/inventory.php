<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/models/InventoryTransaction.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require('inventory.view', 'view');

$companyId = (int) Session::get('company_id');
$createdBy = (int) Session::get('user_id');

$error = null;
$success = null;
$editTransaction = null;


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
                'inventory.create',
                'create'
            );

            $branchId = (int) (
                $_POST['branch_id'] ?? 0
            );

            $warehouseId = (int) (
                $_POST['warehouse_id'] ?? 0
            );

            $productId = (int) (
                $_POST['product_id'] ?? 0
            );

            $batchId = (int) (
                $_POST['batch_id'] ?? 0
            );

            $transactionType = trim(
                $_POST['transaction_type'] ?? ''
            );

            $qtyIn = (float) (
                $_POST['qty_in'] ?? 0
            );

            $qtyOut = (float) (
                $_POST['qty_out'] ?? 0
            );

            $rate = (float) (
                $_POST['rate'] ?? 0
            );

            $transactionDate = trim(
                $_POST['transaction_date'] ?? ''
            );

            $referenceId = (int) (
                $_POST['reference_id'] ?? 0
            );

            $narration = trim(
                $_POST['narration'] ?? ''
            );


            if ($branchId <= 0) {
                throw new Exception(
                    'Please select a branch.'
                );
            }

            if ($warehouseId <= 0) {
                throw new Exception(
                    'Please select a warehouse.'
                );
            }

            if ($productId <= 0) {
                throw new Exception(
                    'Please select a product.'
                );
            }

            if ($transactionType === '') {
                throw new Exception(
                    'Please select transaction type.'
                );
            }

            if (!in_array(
                $transactionType,
                ['STOCK_IN', 'STOCK_OUT'],
                true
            )) {
                throw new Exception(
                    'Invalid transaction type.'
                );
            }

            if ($transactionType === 'STOCK_IN') {
                $qtyOut = 0;

                if ($qtyIn <= 0) {
                    throw new Exception(
                        'Stock IN quantity must be greater than zero.'
                    );
                }
            }

            if ($transactionType === 'STOCK_OUT') {
                $qtyIn = 0;

                if ($qtyOut <= 0) {
                    throw new Exception(
                        'Stock OUT quantity must be greater than zero.'
                    );
                }
            }

            if ($rate < 0) {
                throw new Exception(
                    'Rate cannot be negative.'
                );
            }

            if ($transactionDate === '') {
                throw new Exception(
                    'Transaction date is required.'
                );
            }


            InventoryTransaction::create(
                $companyId,
                $branchId,
                $warehouseId,
                $productId,
                $batchId > 0 ? $batchId : null,
                null,
                $transactionType,
                $qtyIn,
                $qtyOut,
                $rate,
                $transactionDate,
                $referenceId > 0 ? $referenceId : null,
                $narration,
                $createdBy
            );

            $success =
                'Inventory transaction created successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'inventory.edit',
                'edit'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            $branchId = (int) (
                $_POST['branch_id'] ?? 0
            );

            $warehouseId = (int) (
                $_POST['warehouse_id'] ?? 0
            );

            $productId = (int) (
                $_POST['product_id'] ?? 0
            );

            $batchId = (int) (
                $_POST['batch_id'] ?? 0
            );

            $transactionType = trim(
                $_POST['transaction_type'] ?? ''
            );

            $qtyIn = (float) (
                $_POST['qty_in'] ?? 0
            );

            $qtyOut = (float) (
                $_POST['qty_out'] ?? 0
            );

            $rate = (float) (
                $_POST['rate'] ?? 0
            );

            $transactionDate = trim(
                $_POST['transaction_date'] ?? ''
            );

            $referenceId = (int) (
                $_POST['reference_id'] ?? 0
            );

            $narration = trim(
                $_POST['narration'] ?? ''
            );


            if ($id <= 0) {
                throw new Exception(
                    'Invalid transaction.'
                );
            }

            if ($branchId <= 0) {
                throw new Exception(
                    'Please select a branch.'
                );
            }

            if ($warehouseId <= 0) {
                throw new Exception(
                    'Please select a warehouse.'
                );
            }

            if ($productId <= 0) {
                throw new Exception(
                    'Please select a product.'
                );
            }

            if (!in_array(
                $transactionType,
                ['STOCK_IN', 'STOCK_OUT'],
                true
            )) {
                throw new Exception(
                    'Invalid transaction type.'
                );
            }


            if ($transactionType === 'STOCK_IN') {
                $qtyOut = 0;

                if ($qtyIn <= 0) {
                    throw new Exception(
                        'Stock IN quantity must be greater than zero.'
                    );
                }
            }


            if ($transactionType === 'STOCK_OUT') {
                $qtyIn = 0;

                if ($qtyOut <= 0) {
                    throw new Exception(
                        'Stock OUT quantity must be greater than zero.'
                    );
                }
            }


            if ($rate < 0) {
                throw new Exception(
                    'Rate cannot be negative.'
                );
            }


            InventoryTransaction::update(
                $id,
                $companyId,
                $branchId,
                $warehouseId,
                $productId,
                $batchId > 0 ? $batchId : null,
                $transactionType,
                $qtyIn,
                $qtyOut,
                $rate,
                $transactionDate,
                $referenceId > 0 ? $referenceId : null,
                $narration
            );

            $success =
                'Inventory transaction updated successfully ✅';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'inventory.delete',
                'delete'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new Exception(
                    'Invalid transaction.'
                );
            }


            InventoryTransaction::delete(
                $id,
                $companyId
            );

            $success =
                'Inventory transaction deleted successfully ✅';
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| EDIT DATA
|--------------------------------------------------------------------------
*/

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editTransaction =
            InventoryTransaction::find(
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

$branches =
    InventoryTransaction::getBranches(
        $companyId
    );

$warehouses =
    InventoryTransaction::getWarehouses(
        $companyId
    );

$products =
    InventoryTransaction::getProducts(
        $companyId
    );


/*
|--------------------------------------------------------------------------
| EDIT BATCHES
|--------------------------------------------------------------------------
*/

$editBatches = [];

if ($editTransaction && !empty($editTransaction['product_id'])) {

    $editBatches =
        InventoryTransaction::getBatches(
            $companyId,
            (int) $editTransaction['product_id']
        );
}


/*
|--------------------------------------------------------------------------
| TRANSACTION LIST
|--------------------------------------------------------------------------
*/

$transactions =
    InventoryTransaction::getAll(
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

<title>VyaapaarOS - Inventory</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
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
    min-height: 80px;
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
    padding: 10px;
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

.in {
    color: #15803d;
    font-weight: bold;
}

.out {
    color: #dc2626;
    font-weight: bold;
}

a {
    text-decoration: none;
}

.info {
    background: #eff6ff;
    color: #1e40af;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

</style>

</head>

<body>

<div class="container">


<div class="card">

<h1>
<?= $editTransaction
    ? 'Edit Inventory Transaction'
    : 'Inventory / Stock' ?>
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


<div class="info">

<strong>Stock Rule:</strong>

Stock IN में केवल Qty In भरें।

Stock OUT में केवल Qty Out भरें।

दोनों quantities एक साथ नहीं भर सकते।

</div>


<?php if (!$editTransaction): ?>


<?php if (
    PermissionMiddleware::check(
        'inventory.create',
        'create'
    )
): ?>

<h2>
Add Stock Transaction
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
Branch *
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
>

<?= htmlspecialchars(
    $branch['branch_name']
) ?>

(<?= htmlspecialchars(
    $branch['branch_code']
) ?>)

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Warehouse *
</label>

<select
    name="warehouse_id"
    required
>

<option value="">
-- Select Warehouse --
</option>

<?php foreach ($warehouses as $warehouse): ?>

<option
    value="<?= (int) $warehouse['id'] ?>"
>

<?= htmlspecialchars(
    $warehouse['warehouse_name']
) ?>

(<?= htmlspecialchars(
    $warehouse['warehouse_code']
) ?>)

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Product *
</label>

<select
    name="product_id"
    id="product_id"
    required
>

<option value="">
-- Select Product --
</option>

<?php foreach ($products as $product): ?>

<option
    value="<?= (int) $product['id'] ?>"
>

<?= htmlspecialchars(
    $product['product_name']
) ?>

<?php if (!empty($product['sku'])): ?>

(<?= htmlspecialchars(
    $product['sku']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Batch
</label>

<select
    name="batch_id"
    id="batch_id"
>

<option value="">
-- Select Batch --
</option>

</select>

</div>


<div class="field">

<label>
Transaction Type *
</label>

<select
    name="transaction_type"
    id="transaction_type"
    required
>

<option value="">
-- Select Type --
</option>

<option value="STOCK_IN">
Stock IN
</option>

<option value="STOCK_OUT">
Stock OUT
</option>

</select>

</div>


<div class="field">

<label>
Rate *
</label>

<input
    type="number"
    name="rate"
    step="0.01"
    min="0"
    value="0"
    required
>

</div>


<div class="field">

<label>
Qty In
</label>

<input
    type="number"
    name="qty_in"
    id="qty_in"
    step="0.01"
    min="0"
    value="0"
>

</div>


<div class="field">

<label>
Qty Out
</label>

<input
    type="number"
    name="qty_out"
    id="qty_out"
    step="0.01"
    min="0"
    value="0"
>

</div>


<div class="field">

<label>
Transaction Date *
</label>

<input
    type="datetime-local"
    name="transaction_date"
    value="<?= date('Y-m-d\TH:i') ?>"
    required
>

</div>


<div class="field">

<label>
Reference ID
</label>

<input
    type="number"
    name="reference_id"
    min="1"
>

</div>


<div class="field">

<label>
Narration
</label>

<textarea
    name="narration"
></textarea>

</div>

</div>


<div class="form-actions">

<button
    type="submit"
    class="create"
>
Save Transaction
</button>

</div>

</form>

<?php endif; ?>


<?php else: ?>


<h2>
Edit Inventory Transaction
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
    value="<?= (int) $editTransaction['id'] ?>"
>


<div class="form-grid">


<div class="field">

<label>
Branch *
</label>

<select
    name="branch_id"
    required
>

<?php foreach ($branches as $branch): ?>

<option
    value="<?= (int) $branch['id'] ?>"
    <?= (
        (int) $editTransaction['branch_id']
        === (int) $branch['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $branch['branch_name']
) ?>

(<?= htmlspecialchars(
    $branch['branch_code']
) ?>)

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Warehouse *
</label>

<select
    name="warehouse_id"
    required
>

<?php foreach ($warehouses as $warehouse): ?>

<option
    value="<?= (int) $warehouse['id'] ?>"
    <?= (
        (int) $editTransaction['warehouse_id']
        === (int) $warehouse['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $warehouse['warehouse_name']
) ?>

(<?= htmlspecialchars(
    $warehouse['warehouse_code']
) ?>)

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Product *
</label>

<select
    name="product_id"
    id="edit_product_id"
    required
>

<?php foreach ($products as $product): ?>

<option
    value="<?= (int) $product['id'] ?>"
    <?= (
        (int) $editTransaction['product_id']
        === (int) $product['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $product['product_name']
) ?>

<?php if (!empty($product['sku'])): ?>

(<?= htmlspecialchars(
    $product['sku']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Batch
</label>

<select
    name="batch_id"
    id="edit_batch_id"
>

<option value="">
-- Select Batch --
</option>

<?php foreach ($editBatches as $batch): ?>

<option
    value="<?= (int) $batch['id'] ?>"
    <?= (
        (int) ($editTransaction['batch_id'] ?? 0)
        === (int) $batch['id']
    ) ? 'selected' : '' ?>
>

<?= htmlspecialchars(
    $batch['batch_no']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Transaction Type *
</label>

<select
    name="transaction_type"
    required
>

<option
    value="STOCK_IN"
    <?= $editTransaction['transaction_type']
        === 'STOCK_IN'
        ? 'selected'
        : '' ?>
>
Stock IN
</option>

<option
    value="STOCK_OUT"
    <?= $editTransaction['transaction_type']
        === 'STOCK_OUT'
        ? 'selected'
        : '' ?>
>
Stock OUT
</option>

</select>

</div>


<div class="field">

<label>
Rate *
</label>

<input
    type="number"
    name="rate"
    step="0.01"
    min="0"
    value="<?= htmlspecialchars(
        $editTransaction['rate']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Qty In
</label>

<input
    type="number"
    name="qty_in"
    step="0.01"
    min="0"
    value="<?= htmlspecialchars(
        $editTransaction['qty_in']
    ) ?>"
>

</div>


<div class="field">

<label>
Qty Out
</label>

<input
    type="number"
    name="qty_out"
    step="0.01"
    min="0"
    value="<?= htmlspecialchars(
        $editTransaction['qty_out']
    ) ?>"
>

</div>


<div class="field">

<label>
Transaction Date *
</label>

<input
    type="datetime-local"
    name="transaction_date"
    value="<?= date(
        'Y-m-d\TH:i',
        strtotime(
            $editTransaction['transaction_date']
        )
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Reference ID
</label>

<input
    type="number"
    name="reference_id"
    min="1"
    value="<?= htmlspecialchars(
        $editTransaction['reference_id'] ?? ''
    ) ?>"
>

</div>


<div class="field">

<label>
Narration
</label>

<textarea
    name="narration"
><?= htmlspecialchars(
    $editTransaction['narration'] ?? ''
) ?></textarea>

</div>

</div>


<div class="form-actions">

<button
    type="submit"
    class="edit"
>
Update Transaction
</button>

<a href="inventory.php">
Cancel
</a>

</div>

</form>

<?php endif; ?>

</div>


<div class="card">

<h2>
Inventory Transactions
</h2>


<div class="table-wrap">

<table>

<thead>

<tr>

<th>ID</th>
<th>Date</th>
<th>Type</th>
<th>Branch</th>
<th>Warehouse</th>
<th>Product</th>
<th>Batch</th>
<th>Qty In</th>
<th>Qty Out</th>
<th>Rate</th>
<th>Narration</th>
<th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($transactions as $transaction): ?>

<tr>

<td>
<?= (int) $transaction['id'] ?>
</td>

<td>
<?= htmlspecialchars(
    $transaction['transaction_date']
) ?>
</td>

<td>

<?php if (
    $transaction['transaction_type']
    === 'STOCK_IN'
): ?>

<span class="in">
Stock IN
</span>

<?php else: ?>

<span class="out">
Stock OUT
</span>

<?php endif; ?>

</td>

<td>
<?= htmlspecialchars(
    $transaction['branch_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $transaction['warehouse_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $transaction['product_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
    $transaction['batch_no'] ?? '-'
) ?>
</td>

<td>
<?= number_format(
    (float) $transaction['qty_in'],
    2
) ?>
</td>

<td>
<?= number_format(
    (float) $transaction['qty_out'],
    2
) ?>
</td>

<td>
<?= number_format(
    (float) $transaction['rate'],
    2
) ?>
</td>

<td>
<?= htmlspecialchars(
    $transaction['narration'] ?? '-'
) ?>
</td>

<td>

<div class="actions">


<?php if (
    PermissionMiddleware::check(
        'inventory.edit',
        'edit'
    )
): ?>

<a
    href="inventory.php?edit=<?= (int) $transaction['id'] ?>"
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
        'inventory.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this inventory transaction?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $transaction['id'] ?>"
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


<?php if (empty($transactions)): ?>

<tr>

<td colspan="12">
No inventory transactions found.
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


<script>

/*
|--------------------------------------------------------------------------
| New Transaction - Product → Batch
|--------------------------------------------------------------------------
*/

const productSelect =
    document.getElementById('product_id');

const batchSelect =
    document.getElementById('batch_id');


if (productSelect && batchSelect) {

    productSelect.addEventListener(
        'change',
        function () {

            const productId =
                this.value;

            batchSelect.innerHTML =
                '<option value="">Loading...</option>';

            if (!productId) {

                batchSelect.innerHTML =
                    '<option value="">-- Select Batch --</option>';

                return;
            }


            fetch(
                'inventory_batches.php?product_id='
                + encodeURIComponent(productId)
            )
            .then(response => response.json())
            .then(data => {

                batchSelect.innerHTML =
                    '<option value="">-- Select Batch --</option>';

                data.forEach(batch => {

                    const option =
                        document.createElement('option');

                    option.value =
                        batch.id;

                    option.textContent =
                        batch.batch_no;

                    batchSelect.appendChild(
                        option
                    );
                });

            })
            .catch(() => {

                batchSelect.innerHTML =
                    '<option value="">Unable to load batches</option>';
            });
        }
    );
}


/*
|--------------------------------------------------------------------------
| Qty IN / OUT
|--------------------------------------------------------------------------
*/

const transactionType =
    document.getElementById('transaction_type');

const qtyIn =
    document.getElementById('qty_in');

const qtyOut =
    document.getElementById('qty_out');


if (
    transactionType &&
    qtyIn &&
    qtyOut
) {

    transactionType.addEventListener(
        'change',
        function () {

            if (this.value === 'STOCK_IN') {

                qtyOut.value = '0';
                qtyOut.disabled = true;
                qtyIn.disabled = false;

            } else if (this.value === 'STOCK_OUT') {

                qtyIn.value = '0';
                qtyIn.disabled = true;
                qtyOut.disabled = false;

            } else {

                qtyIn.disabled = false;
                qtyOut.disabled = false;
            }
        }
    );
}

</script>

</body>

</html>