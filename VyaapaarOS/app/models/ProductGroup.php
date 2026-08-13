<?php

require_once __DIR__ . '/../core/Database.php';

class ProductGroup
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                group_name,
                created_at
            FROM product_groups
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
                group_name,
                created_at
            FROM product_groups
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
        string $groupName
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO product_groups
            (
                company_id,
                group_name,
                created_at
            )
            VALUES
            (
                :company_id,
                :group_name,
                CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'group_name' => $groupName
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        string $groupName
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE product_groups
            SET group_name = :group_name
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'group_name' => $groupName
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM product_groups
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }
}