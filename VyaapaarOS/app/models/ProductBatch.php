<?php

require_once __DIR__ . '/../core/Database.php';

class ProductBatch
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                pb.id,
                pb.product_id,
                pb.batch_no,
                pb.manufacturing_date,
                pb.expiry_date,
                pb.purchase_rate,
                pb.mrp,
                pb.opening_qty,
                pb.is_active,
                pb.created_at,
                p.product_name,
                p.sku
            FROM product_batches pb
            INNER JOIN products p
                ON p.id = pb.product_id
               AND p.company_id = pb.company_id
            WHERE pb.company_id = :company_id
            ORDER BY pb.id DESC
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
                product_id,
                batch_no,
                manufacturing_date,
                expiry_date,
                purchase_rate,
                mrp,
                opening_qty,
                is_active
            FROM product_batches
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
        int $productId,
        string $batchNo,
        ?string $manufacturingDate,
        ?string $expiryDate,
        float $purchaseRate,
        float $mrp,
        float $openingQty
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO product_batches
            (
                company_id,
                product_id,
                batch_no,
                manufacturing_date,
                expiry_date,
                purchase_rate,
                mrp,
                opening_qty,
                is_active,
                created_at
            )
            VALUES
            (
                :company_id,
                :product_id,
                :batch_no,
                :manufacturing_date,
                :expiry_date,
                :purchase_rate,
                :mrp,
                :opening_qty,
                TRUE,
                CURRENT_TIMESTAMP
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'product_id' => $productId,
            'batch_no' => $batchNo,
            'manufacturing_date' =>
                $manufacturingDate ?: null,
            'expiry_date' =>
                $expiryDate ?: null,
            'purchase_rate' => $purchaseRate,
            'mrp' => $mrp,
            'opening_qty' => $openingQty
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        int $productId,
        string $batchNo,
        ?string $manufacturingDate,
        ?string $expiryDate,
        float $purchaseRate,
        float $mrp,
        float $openingQty,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE product_batches
            SET
                product_id = :product_id,
                batch_no = :batch_no,
                manufacturing_date = :manufacturing_date,
                expiry_date = :expiry_date,
                purchase_rate = :purchase_rate,
                mrp = :mrp,
                opening_qty = :opening_qty,
                is_active = :is_active
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'product_id' => $productId,
            'batch_no' => $batchNo,
            'manufacturing_date' =>
                $manufacturingDate ?: null,
            'expiry_date' =>
                $expiryDate ?: null,
            'purchase_rate' => $purchaseRate,
            'mrp' => $mrp,
            'opening_qty' => $openingQty,
            'is_active' =>
                $isActive ? 'true' : 'false'
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM product_batches
            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }


    public static function getProducts(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                product_name,
                sku
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
}