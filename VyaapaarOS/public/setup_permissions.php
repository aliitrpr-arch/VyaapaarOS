<?php

require_once __DIR__ . '/../app/core/Database.php';

try {

    $db = Database::connect();

    $permissions = [

        ['dashboard.view', 'View Dashboard', 'Dashboard', 'Access dashboard'],

        ['company.view', 'View Company', 'Company', 'View company information'],
        ['company.create', 'Create Company', 'Company', 'Create company'],
        ['company.edit', 'Edit Company', 'Company', 'Edit company'],
        ['company.delete', 'Delete Company', 'Company', 'Delete company'],

        ['user.view', 'View Users', 'Users', 'View users'],
        ['user.create', 'Create User', 'Users', 'Create users'],
        ['user.edit', 'Edit User', 'Users', 'Edit users'],
        ['user.delete', 'Delete User', 'Users', 'Delete users'],

        ['role.view', 'View Roles', 'Roles', 'View roles'],
        ['role.create', 'Create Role', 'Roles', 'Create roles'],
        ['role.edit', 'Edit Role', 'Roles', 'Edit roles'],
        ['role.delete', 'Delete Role', 'Roles', 'Delete roles'],

        ['product.view', 'View Products', 'Products', 'View products'],
        ['product.create', 'Create Product', 'Products', 'Create products'],
        ['product.edit', 'Edit Product', 'Products', 'Edit products'],
        ['product.delete', 'Delete Product', 'Products', 'Delete products'],

        ['party.view', 'View Parties', 'Parties', 'View parties'],
        ['party.create', 'Create Party', 'Parties', 'Create parties'],
        ['party.edit', 'Edit Party', 'Parties', 'Edit parties'],
        ['party.delete', 'Delete Party', 'Parties', 'Delete parties'],

        ['purchase.view', 'View Purchase', 'Purchase', 'View purchase'],
        ['purchase.create', 'Create Purchase', 'Purchase', 'Create purchase'],
        ['purchase.edit', 'Edit Purchase', 'Purchase', 'Edit purchase'],
        ['purchase.delete', 'Delete Purchase', 'Purchase', 'Delete purchase'],

        ['sale.view', 'View Sales', 'Sales', 'View sales'],
        ['sale.create', 'Create Sale', 'Sales', 'Create sales'],
        ['sale.edit', 'Edit Sale', 'Sales', 'Edit sales'],
        ['sale.delete', 'Delete Sale', 'Sales', 'Delete sales'],

        ['inventory.view', 'View Inventory', 'Inventory', 'View inventory'],
        ['inventory.create', 'Create Inventory Transaction', 'Inventory', 'Create inventory transaction'],
        ['inventory.edit', 'Edit Inventory', 'Inventory', 'Edit inventory'],
        ['inventory.delete', 'Delete Inventory', 'Inventory', 'Delete inventory'],

        ['ledger.view', 'View Ledger', 'Ledger', 'View ledger'],
        ['ledger.create', 'Create Ledger Entry', 'Ledger', 'Create ledger entry'],
        ['ledger.edit', 'Edit Ledger Entry', 'Ledger', 'Edit ledger entry'],
        ['ledger.delete', 'Delete Ledger Entry', 'Ledger', 'Delete ledger entry'],

        ['payment.view', 'View Payments', 'Payments', 'View payments'],
        ['payment.create', 'Create Payment', 'Payments', 'Create payments'],
        ['payment.edit', 'Edit Payment', 'Payments', 'Edit payments'],
        ['payment.delete', 'Delete Payment', 'Payments', 'Delete payments'],

        ['report.view', 'View Reports', 'Reports', 'View reports']

    ];

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO permissions
        (
            permission_key,
            permission_name,
            module_name,
            description
        )
        VALUES
        (
            :permission_key,
            :permission_name,
            :module_name,
            :description
        )
        ON CONFLICT (permission_key) DO NOTHING
    ");

    foreach ($permissions as $permission) {

        $stmt->execute([
            'permission_key'  => $permission[0],
            'permission_name' => $permission[1],
            'module_name'     => $permission[2],
            'description'     => $permission[3]
        ]);
    }

    $db->commit();

    echo "<h2>Permission Master Created Successfully ✅</h2>";
    echo "<p>Total permissions processed: " . count($permissions) . "</p>";

    echo "<hr>";
    echo "<a href='dashboard.php'>Go to Dashboard →</a>";

} catch (Throwable $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    echo "<h2>Permission Setup Failed ❌</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}