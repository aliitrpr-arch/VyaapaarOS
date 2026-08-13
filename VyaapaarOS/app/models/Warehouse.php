<?php

require_once __DIR__ . '/../core/Database.php';

class Warehouse
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                w.id,
                w.company_id,
                w.branch_id,
                w.warehouse_name,
                w.warehouse_code,
                w.address,
                w.is_active,
                w.created_at,
                w.updated_at,
                b.branch_name
            FROM warehouses w
            INNER JOIN branches b
                ON b.id = w.branch_id
               AND b.company_id = w.company_id
            WHERE w.company_id = :company_id
            ORDER BY w.id DESC
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
                branch_id,
                warehouse_name,
                warehouse_code,
                address,
                is_active
            FROM warehouses
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
        int $branchId,
        string $warehouseName,
        string $warehouseCode,
        ?string $address
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO warehouses
            (
                company_id,
                branch_id,
                warehouse_name,
                warehouse_code,
                address,
                is_active,
                created_at,
                updated_at
            )
            VALUES
            (
                :company_id,
                :branch_id,
                :warehouse_name,
                :warehouse_code,
                :address,
                TRUE,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_name' => $warehouseName,
            'warehouse_code' => $warehouseCode,
            'address' => $address ?: null
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        int $branchId,
        string $warehouseName,
        string $warehouseCode,
        ?string $address,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE warehouses
            SET
                branch_id = :branch_id,
                warehouse_name = :warehouse_name,
                warehouse_code = :warehouse_code,
                address = :address,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_name' => $warehouseName,
            'warehouse_code' => $warehouseCode,
            'address' => $address ?: null,
            'is_active' => $isActive ? 'true' : 'false'
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM warehouses
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }


    public static function getBranches(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                branch_name,
                branch_code
            FROM branches
            WHERE company_id = :company_id
              AND is_active = TRUE
            ORDER BY branch_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }
}