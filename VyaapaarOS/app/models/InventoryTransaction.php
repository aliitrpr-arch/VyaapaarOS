<?php

require_once __DIR__ . '/../core/Database.php';

class InventoryTransaction
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                it.id,
                it.company_id,
                it.branch_id,
                it.warehouse_id,
                it.product_id,
                it.batch_id,
                it.voucher_id,
                it.transaction_type,
                it.qty_in,
                it.qty_out,
                it.rate,
                it.transaction_date,
                it.reference_id,
                it.narration,
                it.created_by,
                it.created_at,

                b.branch_name,
                w.warehouse_name,
                p.product_name,
                pb.batch_no

            FROM inventory_transactions it

            INNER JOIN branches b
                ON b.id = it.branch_id
               AND b.company_id = it.company_id

            INNER JOIN warehouses w
                ON w.id = it.warehouse_id
               AND w.company_id = it.company_id

            INNER JOIN products p
                ON p.id = it.product_id
               AND p.company_id = it.company_id

            LEFT JOIN product_batches pb
                ON pb.id = it.batch_id
               AND pb.company_id = it.company_id

            WHERE it.company_id = :company_id

            ORDER BY
                it.transaction_date DESC,
                it.id DESC
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
                warehouse_id,
                product_id,
                batch_id,
                voucher_id,
                transaction_type,
                qty_in,
                qty_out,
                rate,
                transaction_date,
                reference_id,
                narration
            FROM inventory_transactions
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
        int $warehouseId,
        int $productId,
        ?int $batchId,
        ?int $voucherId,
        string $transactionType,
        float $qtyIn,
        float $qtyOut,
        float $rate,
        string $transactionDate,
        ?int $referenceId,
        ?string $narration,
        int $createdBy
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO inventory_transactions
            (
                company_id,
                branch_id,
                warehouse_id,
                product_id,
                batch_id,
                voucher_id,
                transaction_type,
                qty_in,
                qty_out,
                rate,
                transaction_date,
                reference_id,
                narration,
                created_by,
                created_at
            )
            VALUES
            (
                :company_id,
                :branch_id,
                :warehouse_id,
                :product_id,
                :batch_id,
                :voucher_id,
                :transaction_type,
                :qty_in,
                :qty_out,
                :rate,
                :transaction_date,
                :reference_id,
                :narration,
                :created_by,
                CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'batch_id' => $batchId ?: null,
            'voucher_id' => $voucherId ?: null,
            'transaction_type' => $transactionType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'rate' => $rate,
            'transaction_date' => $transactionDate,
            'reference_id' => $referenceId ?: null,
            'narration' => $narration ?: null,
            'created_by' => $createdBy
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        int $branchId,
        int $warehouseId,
        int $productId,
        ?int $batchId,
        string $transactionType,
        float $qtyIn,
        float $qtyOut,
        float $rate,
        string $transactionDate,
        ?int $referenceId,
        ?string $narration
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE inventory_transactions
            SET
                branch_id = :branch_id,
                warehouse_id = :warehouse_id,
                product_id = :product_id,
                batch_id = :batch_id,
                transaction_type = :transaction_type,
                qty_in = :qty_in,
                qty_out = :qty_out,
                rate = :rate,
                transaction_date = :transaction_date,
                reference_id = :reference_id,
                narration = :narration
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'batch_id' => $batchId ?: null,
            'transaction_type' => $transactionType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'rate' => $rate,
            'transaction_date' => $transactionDate,
            'reference_id' => $referenceId ?: null,
            'narration' => $narration ?: null
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM inventory_transactions
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


    public static function getWarehouses(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                branch_id,
                warehouse_name,
                warehouse_code
            FROM warehouses
            WHERE company_id = :company_id
              AND is_active = TRUE
            ORDER BY warehouse_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function getProducts(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                product_name,
                sku,
                base_unit_id
            FROM products
            WHERE company_id = :company_id
              AND is_active = TRUE
            ORDER BY product_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function getBatches(
        int $companyId,
        int $productId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                product_id,
                batch_no,
                expiry_date,
                purchase_rate,
                mrp
            FROM product_batches
            WHERE company_id = :company_id
              AND product_id = :product_id
              AND is_active = TRUE
            ORDER BY batch_no
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'product_id' => $productId
        ]);

        return $stmt->fetchAll();
    }
}