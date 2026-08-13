<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

$fullName = (string) Session::get('full_name');
$username = (string) Session::get('username');
$roleName = (string) Session::get('role_name');
$companyId = (int) Session::get('company_id');
$branchId = (int) Session::get('branch_id');

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>VyaapaarOS Dashboard</title>

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

/* =========================================================
   LAYOUT
========================================================= */

.layout {
    display: flex;
    min-height: 100vh;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    width: 250px;
    background: #111827;
    color: white;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    overflow-y: auto;
    z-index: 1000;
}

.logo {
    padding: 22px 20px;
    font-size: 22px;
    font-weight: bold;
    border-bottom: 1px solid #374151;
}

.logo span {
    color: #60a5fa;
}

.sidebar-section {
    padding: 18px 15px 5px;
}

.sidebar-title {
    font-size: 11px;
    color: #9ca3af;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .8px;
    padding: 0 10px 8px;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    margin-bottom: 3px;
    border-radius: 7px;
    color: #d1d5db;
    text-decoration: none;
    font-size: 14px;
}

.sidebar a:hover {
    background: #1f2937;
    color: white;
}

.sidebar a.active {
    background: #2563eb;
    color: white;
}

.sidebar-icon {
    width: 22px;
    text-align: center;
}

/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
}

/* =========================================================
   TOP BAR
========================================================= */

.topbar {
    height: 70px;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    position: sticky;
    top: 0;
    z-index: 900;
}

.topbar-title {
    font-size: 20px;
    font-weight: bold;
}

.user-area {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 38px;
    height: 38px;
    background: #2563eb;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-info {
    line-height: 1.3;
}

.user-name {
    font-weight: bold;
    font-size: 14px;
}

.user-role {
    color: #6b7280;
    font-size: 12px;
}

/* =========================================================
   CONTENT
========================================================= */

.content {
    padding: 30px;
    max-width: 1500px;
    margin: auto;
}

/* =========================================================
   WELCOME
========================================================= */

.welcome {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid #e5e7eb;
}

.welcome h1 {
    margin: 0 0 8px;
    font-size: 25px;
}

.welcome p {
    margin: 0;
    color: #6b7280;
}

/* =========================================================
   INFO CARDS
========================================================= */

.info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.info-card {
    background: #f8fafc;
    border-radius: 9px;
    padding: 15px;
    border: 1px solid #e5e7eb;
}

.info-card .label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 5px;
}

.info-card .value {
    font-size: 16px;
    font-weight: bold;
}

/* =========================================================
   SECTION
========================================================= */

.section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid #e5e7eb;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.section-title {
    margin: 0;
    font-size: 19px;
}

.section-subtitle {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 13px;
}

/* =========================================================
   QUICK CARDS
========================================================= */

.quick-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.quick-card {
    display: block;
    padding: 20px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    text-decoration: none;
    color: #111827;
    background: #f8fafc;
    transition: .2s;
}

.quick-card:hover {
    transform: translateY(-2px);
    border-color: #93c5fd;
    background: #eff6ff;
}

.quick-icon {
    font-size: 28px;
    margin-bottom: 10px;
}

.quick-name {
    font-weight: bold;
    font-size: 15px;
}

.quick-desc {
    color: #6b7280;
    font-size: 12px;
    margin-top: 5px;
}

/* =========================================================
   SETUP GRID
========================================================= */

.setup-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.setup-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    text-decoration: none;
    color: #111827;
    background: #fff;
}

.setup-card:hover {
    background: #f8fafc;
    border-color: #93c5fd;
}

.setup-icon {
    font-size: 22px;
}

.setup-name {
    font-weight: bold;
    font-size: 14px;
}

/* =========================================================
   ADMIN
========================================================= */

.admin-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.admin-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    text-decoration: none;
    color: #111827;
}

.admin-card:hover {
    background: #eef2ff;
}

/* =========================================================
   LOGOUT
========================================================= */

.logout-area {
    text-align: right;
}

.logout {
    display: inline-block;
    padding: 10px 18px;
    border-radius: 7px;
    background: #fee2e2;
    color: #991b1b;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

.logout:hover {
    background: #fecaca;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 1100px) {

    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .quick-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .setup-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .admin-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 750px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
        width: calc(100% - 210px);
    }

    .content {
        padding: 20px;
    }

    .topbar {
        padding: 0 20px;
    }

}

@media (max-width: 600px) {

    .sidebar {
        position: relative;
        width: 100%;
        min-height: auto;
    }

    .main {
        margin-left: 0;
        width: 100%;
    }

    .layout {
        display: block;
    }

    .info-grid,
    .quick-grid,
    .setup-grid,
    .admin-grid {
        grid-template-columns: 1fr;
    }

    .topbar {
        position: relative;
    }

}

</style>

</head>

<body>

<div class="layout">

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

<div class="logo">
    Vyaapaar<span>OS</span>
</div>


<a
    href="dashboard.php"
    class="active"
>
    <span class="sidebar-icon">🏠</span>
    Dashboard
</a>


<!-- =====================================================
     BUSINESS
===================================================== -->

<div class="sidebar-section">

<div class="sidebar-title">
    Business
</div>


<?php if (
    PermissionMiddleware::check(
        'product.view',
        'view'
    )
): ?>

<a href="products.php">
    <span class="sidebar-icon">📦</span>
    Products
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'party.view',
        'view'
    )
): ?>

<a href="parties.php">
    <span class="sidebar-icon">👤</span>
    Parties
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'purchase.view',
        'view'
    )
): ?>

<a href="purchases.php">
    <span class="sidebar-icon">🛒</span>
    Purchase
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'purchase.view',
        'view'
    )
): ?>

<a href="purchase_inward.php">
    <span class="sidebar-icon">📥</span>
    Purchase Inward
</a>

<a href="quality_check.php">
    <span class="sidebar-icon">🔎</span>
    Quality Check
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'sale.view',
        'view'
    )
): ?>

<a href="sales.php">
    <span class="sidebar-icon">💰</span>
    Sales
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'purchase.view',
        'view'
    )
): ?>

<a href="purchase_inward.php" class="quick-card">
    <div class="quick-icon">📥</div>
    <div class="quick-name">Purchase Inward</div>
    <div class="quick-desc">Receive purchased goods</div>
</a>

<a href="quality_check.php" class="quick-card">
    <div class="quick-icon">🔎</div>
    <div class="quick-name">Quality Check</div>
    <div class="quick-desc">Check and approve received goods</div>
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'inventory.view',
        'view'
    )
): ?>

<a href="inventory.php">
    <span class="sidebar-icon">📊</span>
    Inventory
</a>

<a href="stock_summary.php">
    <span class="sidebar-icon">📦</span>
    Current Stock
</a>

<a href="stock_alerts.php">
    <span class="sidebar-icon">⚠️</span>
    Stock Alerts
</a>

<a href="inventory_batches.php">
    <span class="sidebar-icon">🧾</span>
    Inventory Batches
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'ledger.view',
        'view'
    )
): ?>

<a href="ledger.php">
    <span class="sidebar-icon">📒</span>
    Ledger
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'payment.view',
        'view'
    )
): ?>

<a href="payments.php">
    <span class="sidebar-icon">💳</span>
    Payments
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'report.view',
        'view'
    )
): ?>

<a href="reports.php">
    <span class="sidebar-icon">📈</span>
    Reports
</a>

<?php endif; ?>

</div>


<!-- =====================================================
     SETUP
===================================================== -->

<div class="sidebar-section">

<div class="sidebar-title">
    Setup
</div>


<?php if (
    PermissionMiddleware::check(
        'company.view',
        'view'
    )
): ?>

<a href="companies.php">
    <span class="sidebar-icon">🏢</span>
    Company
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'branch.view',
        'view'
    )
): ?>

<a href="branches.php">
    <span class="sidebar-icon">🏢</span>
    Branches
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'warehouse.view',
        'view'
    )
): ?>

<a href="warehouses.php">
    <span class="sidebar-icon">🏬</span>
    Warehouses
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'category.view',
        'view'
    )
): ?>

<a href="categories.php">
    <span class="sidebar-icon">🗂️</span>
    Categories
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'product.view',
        'view'
    )
): ?>

<a href="brands.php" class="setup-card">
    <span class="setup-icon">🏷️</span>
    <span class="setup-name">Brands</span>
</a>

<a href="product_groups.php" class="setup-card">
    <span class="setup-icon">📚</span>
    <span class="setup-name">Product Groups</span>
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'zone.view',
        'view'
    )
): ?>

<a href="zones.php">
    <span class="sidebar-icon">📍</span>
    Zones
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'salesman.view',
        'view'
    )
): ?>

<a href="salesmen.php">
    <span class="sidebar-icon">👨‍💼</span>
    Salesmen
</a>

<?php endif; ?>

</div>


<!-- =====================================================
     ADMINISTRATION
===================================================== -->

<div class="sidebar-section">

<div class="sidebar-title">
    Administration
</div>


<?php if (
    PermissionMiddleware::check(
        'user.view',
        'view'
    )
): ?>

<a href="users.php">
    <span class="sidebar-icon">👥</span>
    Users
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'role.view',
        'view'
    )
): ?>

<a href="roles.php">
    <span class="sidebar-icon">🔐</span>
    Roles
</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'permission.view',
        'view'
    )
): ?>

<a href="permissions.php">
    <span class="sidebar-icon">🔑</span>
    Permissions
</a>

<?php endif; ?>

</div>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<!-- TOP BAR -->

<header class="topbar">

<div class="topbar-title">
    Dashboard
</div>


<div class="user-area">

<div class="user-avatar">
    <?= strtoupper(
        substr(
            $fullName ?: $username,
            0,
            1
        )
    ) ?>
</div>


<div class="user-info">

<div class="user-name">
    <?= htmlspecialchars($fullName) ?>
</div>

<div class="user-role">
    <?= htmlspecialchars($roleName) ?>
</div>

</div>

</div>

</header>


<!-- CONTENT -->

<div class="content">


<!-- WELCOME -->

<div class="welcome">

<h1>
    Welcome, <?= htmlspecialchars($fullName) ?> 👋
</h1>

<p>
    Manage your business operations from one place.
</p>


<div class="info-grid">


<div class="info-card">

<div class="label">
    Username
</div>

<div class="value">
    <?= htmlspecialchars($username) ?>
</div>

</div>


<div class="info-card">

<div class="label">
    Role
</div>

<div class="value">
    <?= htmlspecialchars($roleName) ?>
</div>

</div>


<div class="info-card">

<div class="label">
    Company ID
</div>

<div class="value">
    <?= htmlspecialchars(
        (string) $companyId
    ) ?>
</div>

</div>


<div class="info-card">

<div class="label">
    Branch ID
</div>

<div class="value">
    <?= htmlspecialchars(
        (string) $branchId
    ) ?>
</div>

</div>


</div>

</div>


<!-- =====================================================
     BUSINESS QUICK ACCESS
===================================================== -->

<div class="section">

<div class="section-header">

<div>

<h2 class="section-title">
    Business
</h2>

<p class="section-subtitle">
    Manage your day-to-day business operations.
</p>

</div>

</div>


<div class="quick-grid">


<?php if (
    PermissionMiddleware::check(
        'sale.view',
        'view'
    )
): ?>

<a
    href="sales.php"
    class="quick-card"
>

<div class="quick-icon">
    💰
</div>

<div class="quick-name">
    Sales
</div>

<div class="quick-desc">
    Create and manage sales
</div>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'purchase.view',
        'view'
    )
): ?>

<a
    href="purchases.php"
    class="quick-card"
>

<div class="quick-icon">
    🛒
</div>

<div class="quick-name">
    Purchase
</div>

<div class="quick-desc">
    Manage purchases
</div>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'inventory.view',
        'view'
    )
): ?>

<a
    href="inventory.php"
    class="quick-card"
>

<div class="quick-icon">
    📊
</div>

<div class="quick-name">
    Inventory
</div>

<div class="quick-desc">
    Manage stock and inventory
</div>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'ledger.view',
        'view'
    )
): ?>

<a
    href="ledger.php"
    class="quick-card"
>

<div class="quick-icon">
    📒
</div>

<div class="quick-name">
    Ledger
</div>

<div class="quick-desc">
    View account ledger
</div>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'product.view',
        'view'
    )
): ?>

<a
    href="products.php"
    class="quick-card"
>

<div class="quick-icon">
    📦
</div>

<div class="quick-name">
    Products
</div>

<div class="quick-desc">
    Manage products
</div>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'party.view',
        'view'
    )
): ?>

<a
    href="parties.php"
    class="quick-card"
>

<div class="quick-icon">
    👤
</div>

<div class="quick-name">
    Parties
</div>

<div class="quick-desc">
    Customers and vendors
</div>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'payment.view',
        'view'
    )
): ?>

<a
    href="payments.php"
    class="quick-card"
>

<div class="quick-icon">
    💳
</div>

<div class="quick-name">
    Payments
</div>

<div class="quick-desc">
    Manage payments
</div>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'report.view',
        'view'
    )
): ?>

<a
    href="reports.php"
    class="quick-card"
>

<div class="quick-icon">
    📈
</div>

<div class="quick-name">
    Reports
</div>

<div class="quick-desc">
    Business reports
</div>

</a>

<?php endif; ?>


</div>

</div>


<!-- =====================================================
     SETUP
===================================================== -->

<div class="section">

<div class="section-header">

<div>

<h2 class="section-title">
    ⚙️ Setup
</h2>

<p class="section-subtitle">
    Configure company, branches and master data.
</p>

</div>

</div>


<div class="setup-grid">


<?php if (
    PermissionMiddleware::check(
        'company.view',
        'view'
    )
): ?>

<a
    href="companies.php"
    class="setup-card"
>

<span class="setup-icon">🏢</span>

<span class="setup-name">
    Company
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'branch.view',
        'view'
    )
): ?>

<a
    href="branches.php"
    class="setup-card"
>

<span class="setup-icon">🏢</span>

<span class="setup-name">
    Branches
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'warehouse.view',
        'view'
    )
): ?>

<a
    href="warehouses.php"
    class="setup-card"
>

<span class="setup-icon">🏬</span>

<span class="setup-name">
    Warehouses
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'category.view',
        'view'
    )
): ?>

<a
    href="categories.php"
    class="setup-card"
>

<span class="setup-icon">🗂️</span>

<span class="setup-name">
    Categories
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'zone.view',
        'view'
    )
): ?>

<a
    href="zones.php"
    class="setup-card"
>

<span class="setup-icon">📍</span>

<span class="setup-name">
    Zones
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'salesman.view',
        'view'
    )
): ?>

<a
    href="salesmen.php"
    class="setup-card"
>

<span class="setup-icon">👨‍💼</span>

<span class="setup-name">
    Salesmen
</span>

</a>

<?php endif; ?>



<?php if (
    PermissionMiddleware::check(
        'product_unit.view',
        'view'
    )
): ?>

<a
    href="product_units.php"
    class="setup-card"
>

<span class="setup-icon">📦</span>

<span class="setup-name">
    Product Units / UOM
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'scheme.view',
        'view'
    )
): ?>

<a
    href="schemes.php"
    class="setup-card"
>
    <span class="setup-icon">🎁</span>
    <span class="setup-name">
        Schemes
    </span>
</a>

<?php endif; ?>
    


</div>

</div>


<!-- =====================================================
     ADMINISTRATION
===================================================== -->

<div class="section">

<div class="section-header">

<div>

<h2 class="section-title">
    🔐 Administration
</h2>

<p class="section-subtitle">
    Manage users, roles and permissions.
</p>

</div>

</div>


<div class="admin-grid">


<?php if (
    PermissionMiddleware::check(
        'user.view',
        'view'
    )
): ?>

<a
    href="users.php"
    class="admin-card"
>

<span class="setup-icon">
    👥
</span>

<span>
    <strong>Users</strong>
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'role.view',
        'view'
    )
): ?>

<a
    href="roles.php"
    class="admin-card"
>

<span class="setup-icon">
    🔐
</span>

<span>
    <strong>Roles</strong>
</span>

</a>

<?php endif; ?>


<?php if (
    PermissionMiddleware::check(
        'permission.view',
        'view'
    )
): ?>

<a
    href="permissions.php"
    class="admin-card"
>

<span class="setup-icon">
    🔑
</span>

<span>
    <strong>Permissions</strong>
</span>

</a>

<?php endif; ?>


</div>

</div>


<!-- =====================================================
     LOGOUT
===================================================== -->

<div class="section">

<div class="logout-area">

<a
    href="logout.php"
    class="logout"
>
    🚪 Logout
</a>

</div>

</div>


</div>

</main>

</div>

</body>

</html>
