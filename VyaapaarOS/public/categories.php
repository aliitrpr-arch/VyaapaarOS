<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../app/models/Category.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

PermissionMiddleware::require(
    'category.view',
    'view'
);

$companyId = (int) Session::get('company_id');

$error = null;
$success = null;


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
                'category.create',
                'create'
            );

            $categoryName = trim(
                $_POST['category_name'] ?? ''
            );

            $description = trim(
                $_POST['description'] ?? ''
            );

            if ($categoryName === '') {
                throw new Exception(
                    'Category name is required.'
                );
            }

            Category::create(
                $companyId,
                $categoryName,
                $description
            );

            $success =
                'Category created successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'update') {

            PermissionMiddleware::require(
                'category.edit',
                'edit'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            $categoryName = trim(
                $_POST['category_name'] ?? ''
            );

            $description = trim(
                $_POST['description'] ?? ''
            );

            $isActive =
                isset($_POST['is_active'])
                && $_POST['is_active'] === '1';


            if ($id <= 0) {
                throw new Exception(
                    'Invalid category.'
                );
            }

            if ($categoryName === '') {
                throw new Exception(
                    'Category name is required.'
                );
            }

            Category::update(
                $id,
                $companyId,
                $categoryName,
                $description,
                $isActive
            );

            $success =
                'Category updated successfully.';
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            PermissionMiddleware::require(
                'category.delete',
                'delete'
            );

            $id = (int) (
                $_POST['id'] ?? 0
            );

            if ($id <= 0) {
                throw new Exception(
                    'Invalid category.'
                );
            }

            Category::delete(
                $id,
                $companyId
            );

            $success =
                'Category deleted successfully.';
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

$editCategory = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $editCategory =
            Category::find(
                $editId,
                $companyId
            );
    }
}


/*
|--------------------------------------------------------------------------
| CATEGORY LIST
|--------------------------------------------------------------------------
*/

$categories =
    Category::getAll(
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

<title>VyaapaarOS - Categories</title>


<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #111827;
}

.container {
    width: 1000px;
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

.field {
    margin-bottom: 15px;
}

.field label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
}

input,
textarea {
    width: 400px;
    max-width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

textarea {
    height: 90px;
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
    margin-bottom: 15px;
}

.checkbox-row input {
    width: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px;
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

.status-active {
    color: #15803d;
    font-weight: bold;
}

.status-inactive {
    color: #dc2626;
    font-weight: bold;
}

</style>

</head>


<body>


<div class="container">


<div class="card">

<h1>
<?= $editCategory
    ? 'Edit Category'
    : 'Category Master' ?>
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


<?php if ($editCategory): ?>


<h2>
Edit Category
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
    value="<?= (int) $editCategory['id'] ?>"
>


<div class="field">

<label>
Category Name *
</label>

<input
    type="text"
    name="category_name"
    value="<?= htmlspecialchars(
        $editCategory['category_name']
    ) ?>"
    required
>

</div>


<div class="field">

<label>
Description
</label>

<textarea
    name="description"
><?= htmlspecialchars(
    $editCategory['description'] ?? ''
) ?></textarea>

</div>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="is_active"
    value="1"
    <?= $editCategory['is_active']
        ? 'checked'
        : '' ?>
>

Active Category

</label>

</div>


<button
    type="submit"
    class="edit"
>
Update Category
</button>


<a
    href="categories.php"
    class="cancel"
>
Cancel
</a>

</form>


<?php else: ?>


<?php if (
    PermissionMiddleware::check(
        'category.create',
        'create'
    )
): ?>


<h2>
Add Category
</h2>


<form method="POST">

<input
    type="hidden"
    name="action"
    value="create"
>


<div class="field">

<label>
Category Name *
</label>

<input
    type="text"
    name="category_name"
    placeholder="Category Name"
    required
>

</div>


<div class="field">

<label>
Description
</label>

<textarea
    name="description"
    placeholder="Category Description"
></textarea>

</div>


<button
    type="submit"
    class="create"
>
Create Category
</button>

</form>


<?php endif; ?>


<?php endif; ?>

</div>


<div class="card">

<h2>
Category List
</h2>


<table>

<thead>

<tr>

<th>ID</th>
<th>Category Name</th>
<th>Description</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody>


<?php foreach ($categories as $category): ?>

<tr>

<td>
<?= (int) $category['id'] ?>
</td>


<td>
<?= htmlspecialchars(
    $category['category_name']
) ?>
</td>


<td>
<?= htmlspecialchars(
    $category['description'] ?? '-'
) ?>
</td>


<td>

<?php if ($category['is_active']): ?>

<span class="status-active">
Active
</span>

<?php else: ?>

<span class="status-inactive">
Inactive
</span>

<?php endif; ?>

</td>


<td>

<div class="actions">


<?php if (
    PermissionMiddleware::check(
        'category.edit',
        'edit'
    )
): ?>

<a
    href="categories.php?edit=<?= (int) $category['id'] ?>"
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
        'category.delete',
        'delete'
    )
): ?>

<form
    method="POST"
    onsubmit="return confirm('Delete this category?');"
>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="id"
    value="<?= (int) $category['id'] ?>"
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


<?php if (empty($categories)): ?>

<tr>

<td colspan="5">
No categories found.
</td>

</tr>

<?php endif; ?>


</tbody>

</table>

</div>


<div class="card">

<a href="dashboard.php">
← Back to Dashboard
</a>

</div>


</div>

</body>

</html>