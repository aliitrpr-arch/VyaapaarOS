<?php

require_once __DIR__ . '/../core/Database.php';

class User
{
    public static function findByUsername(string $username): ?array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                u.id,
                u.company_id,
                u.branch_id,
                u.role_id,
                u.username,
                u.password_hash,
                u.full_name,
                u.is_active,
                r.role_name
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE u.username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }


    public static function getAllByCompany(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                u.id,
                u.username,
                u.full_name,
                u.is_active,
                u.company_id,
                u.branch_id,
                u.role_id,
                r.role_name,
                b.branch_name
            FROM users u

            INNER JOIN roles r
                ON r.id = u.role_id

            LEFT JOIN branches b
                ON b.id = u.branch_id

            WHERE u.company_id = :company_id

            ORDER BY u.id DESC
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function create(
        int $companyId,
        ?int $branchId,
        int $roleId,
        string $username,
        string $password,
        string $fullName,
        bool $isActive = true
    ): int {

        $db = Database::connect();

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $db->prepare("
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
                :is_active
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'username' => trim($username),
            'password_hash' => $passwordHash,
            'full_name' => trim($fullName),
            'is_active' => $isActive
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function find(
        int $userId,
        int $companyId
    ): ?array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                u.id,
                u.company_id,
                u.branch_id,
                u.role_id,
                u.username,
                u.full_name,
                u.is_active,
                r.role_name,
                b.branch_name
            FROM users u

            INNER JOIN roles r
                ON r.id = u.role_id

            LEFT JOIN branches b
                ON b.id = u.branch_id

            WHERE u.id = :user_id
              AND u.company_id = :company_id

            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId,
            'company_id' => $companyId
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }


    public static function update(
        int $userId,
        int $companyId,
        ?int $branchId,
        int $roleId,
        string $fullName,
        bool $isActive
    ): void {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE users

            SET
                branch_id = :branch_id,
                role_id = :role_id,
                full_name = :full_name,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = :user_id
              AND company_id = :company_id
        ");

        $stmt->execute([
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'full_name' => trim($fullName),
            'is_active' => $isActive,
            'user_id' => $userId,
            'company_id' => $companyId
        ]);
    }


    public static function updatePassword(
        int $userId,
        int $companyId,
        string $password
    ): void {

        $db = Database::connect();

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $db->prepare("
            UPDATE users

            SET
                password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = :user_id
              AND company_id = :company_id
        ");

        $stmt->execute([
            'password_hash' => $passwordHash,
            'user_id' => $userId,
            'company_id' => $companyId
        ]);
    }
}