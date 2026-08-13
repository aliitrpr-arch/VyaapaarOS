<?php

require_once __DIR__ . '/../app/core/Database.php';

try {

    $db = Database::connect();

    $db->beginTransaction();

    // 1. Company
    $companyStmt = $db->prepare("
        INSERT INTO companies
        (
            company_name,
            is_active
        )
        VALUES
        (
            :company_name,
            true
        )
        RETURNING id
    ");

    $companyStmt->execute([
        'company_name' => 'VyaapaarOS Demo'
    ]);

    $companyId = $companyStmt->fetch()['id'];


    // 2. Branch
    $branchStmt = $db->prepare("
        INSERT INTO branches
        (
            company_id,
            branch_name,
            branch_code,
            is_active
        )
        VALUES
        (
            :company_id,
            :branch_name,
            :branch_code,
            true
        )
        RETURNING id
    ");

    $branchStmt->execute([
        'company_id' => $companyId,
        'branch_name' => 'Main Branch',
        'branch_code' => 'MAIN'
    ]);

    $branchId = $branchStmt->fetch()['id'];


    // 3. Admin Role
    $roleStmt = $db->prepare("
        INSERT INTO roles
        (
            company_id,
            role_name,
            description,
            is_active
        )
        VALUES
        (
            :company_id,
            :role_name,
            :description,
            true
        )
        RETURNING id
    ");

    $roleStmt->execute([
        'company_id' => $companyId,
        'role_name' => 'ADMIN',
        'description' => 'System Administrator'
    ]);

    $roleId = $roleStmt->fetch()['id'];


    // 4. Admin User
    $passwordHash = password_hash(
        'admin123',
        PASSWORD_DEFAULT
    );

    $userStmt = $db->prepare("
        INSERT INTO users
        (
            company_id,
            branch_id,
            role_id,
            username,
            password_hash,
            full_name,
            is_active
        )
        VALUES
        (
            :company_id,
            :branch_id,
            :role_id,
            :username,
            :password_hash,
            :full_name,
            true
        )
        RETURNING id
    ");

    $userStmt->execute([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'role_id' => $roleId,
        'username' => 'admin',
        'password_hash' => $passwordHash,
        'full_name' => 'System Administrator'
    ]);

    $userId = $userStmt->fetch()['id'];


    $db->commit();


    echo "<h2>VyaapaarOS Admin Setup Successful ✅</h2>";

    echo "<p><strong>Company ID:</strong> {$companyId}</p>";
    echo "<p><strong>Branch ID:</strong> {$branchId}</p>";
    echo "<p><strong>Role ID:</strong> {$roleId}</p>";
    echo "<p><strong>User ID:</strong> {$userId}</p>";

    echo "<hr>";

    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";

    echo "<hr>";

    echo "<a href='login.php'>Go to Login →</a>";


} catch (Throwable $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    echo "<h2>Setup Failed ❌</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}