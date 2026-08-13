<?php

require_once __DIR__ . '/../core/Database.php';

class Brand
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                brand_name,
                description,
                is_active,
                created_at,
                updated_at
            FROM brands
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
                brand_name,
                description,
                is_active,
                created_at,
                updated_at
            FROM brands
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
        string $brandName,
        string $description
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO brands
            (
                company_id,
                brand_name,
                description,
                is_active,
                created_at,
                updated_at
            )
            VALUES
            (
                :company_id,
                :brand_name,
                :description,
                TRUE,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'brand_name' => $brandName,
            'description' => $description
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        string $brandName,
        string $description,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE brands
            SET
                brand_name = :brand_name,
                description = :description,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'brand_name' => $brandName,
            'description' => $description,
            'is_active' => $isActive
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM brands
            WHERE id = :id
              AND company_id = :companyId
        ");

        return $stmt->execute([
            'id' => $id,
            'companyId' => $companyId
        ]);
    }
}