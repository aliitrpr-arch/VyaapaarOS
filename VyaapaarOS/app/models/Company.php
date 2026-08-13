<?php

require_once __DIR__ . '/../core/Database.php';

class Company
{
    public static function getAll(): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_name,
                gstin,
                phone,
                email,
                address,
                state_code,
                is_active,
                created_at,
                updated_at
            FROM companies
            ORDER BY id DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll();
    }


    public static function find(int $id): ?array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_name,
                gstin,
                phone,
                email,
                address,
                state_code,
                is_active,
                created_at,
                updated_at
            FROM companies
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }


    public static function create(
        string $companyName,
        ?string $gstin,
        ?string $phone,
        ?string $email,
        ?string $address,
        ?string $stateCode
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO companies
            (
                company_name,
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
                :company_name,
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
            'company_name' => $companyName,
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
        string $companyName,
        ?string $gstin,
        ?string $phone,
        ?string $email,
        ?string $address,
        ?string $stateCode,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE companies
            SET
                company_name = :company_name,
                gstin = :gstin,
                phone = :phone,
                email = :email,
                address = :address,
                state_code = :state_code,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_name' => $companyName,
            'gstin' => $gstin ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'address' => $address ?: null,
            'state_code' => $stateCode ?: null,
            'is_active' => $isActive ? 'true' : 'false'
        ]);
    }


    public static function delete(int $id): bool
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM companies
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id
        ]);
    }
}