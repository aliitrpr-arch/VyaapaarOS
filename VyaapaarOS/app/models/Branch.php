<?php

require_once __DIR__ . '/../core/Database.php';

class Branch
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_id,
                branch_name,
                branch_code,
                gstin,
                phone,
                email,
                address,
                state_code,
                is_active,
                created_at,
                updated_at
            FROM branches
            WHERE company_id = :company_id
            ORDER BY id DESC
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function find(
        int $id,
        int $companyId
    ): ?array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_id,
                branch_name,
                branch_code,
                gstin,
                phone,
                email,
                address,
                state_code,
                is_active,
                created_at,
                updated_at
            FROM branches
            WHERE id = :id
              AND company_id = :company_id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }


    public static function create(
        int $companyId,
        string $branchName,
        string $branchCode,
        ?string $gstin,
        ?string $phone,
        ?string $email,
        ?string $stateCode,
        ?string $address
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO branches
            (
                company_id,
                branch_name,
                branch_code,
                gstin,
                phone,
                email,
                address,
                state_code,
                is_active,
                created_at,
                updated_at
            )
            VALUES
            (
                :company_id,
                :branch_name,
                :branch_code,
                :gstin,
                :phone,
                :email,
                :address,
                :state_code,
                TRUE,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'branch_name' => $branchName,
            'branch_code' => $branchCode,
            'gstin' => $gstin ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'address' => $address ?: null,
            'state_code' => $stateCode ?: null
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        string $branchName,
        string $branchCode,
        ?string $gstin,
        ?string $phone,
        ?string $email,
        ?string $stateCode,
        ?string $address,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE branches
            SET
                branch_name = :branch_name,
                branch_code = :branch_code,
                gstin = :gstin,
                phone = :phone,
                email = :email,
                address = :address,
                state_code = :state_code,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'branch_name' => $branchName,
            'branch_code' => $branchCode,
            'gstin' => $gstin ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'address' => $address ?: null,
            'state_code' => $stateCode ?: null,
            'is_active' => $isActive
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM branches
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }
}