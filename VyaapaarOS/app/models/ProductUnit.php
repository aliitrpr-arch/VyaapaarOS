<?php

require_once __DIR__ . '/../core/Database.php';

class ProductUnit
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                unit_name,
                short_code,
                is_active
            FROM product_units
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
                unit_name,
                short_code,
                is_active
            FROM product_units
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
        string $unitName,
        string $shortCode
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO product_units
            (
                company_id,
                unit_name,
                short_code,
                is_active
            )
            VALUES
            (
                :company_id,
                :unit_name,
                :short_code,
                TRUE
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'unit_name' => $unitName,
            'short_code' => $shortCode
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        string $unitName,
        string $shortCode,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE product_units
            SET
                unit_name = :unit_name,
                short_code = :short_code,
                is_active = :is_active
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'unit_name' => $unitName,
            'short_code' => $shortCode,
            'is_active' => $isActive
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM product_units
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }
}