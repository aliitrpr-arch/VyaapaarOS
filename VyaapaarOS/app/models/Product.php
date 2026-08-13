<?php

require_once __DIR__ . '/../core/Database.php';

class Product
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                p.id,
                p.company_id,
                p.category_id,
                p.group_id,
                p.brand_id,
                p.product_name,
                p.sku,
                p.barcode,
                p.hsn_code,
                p.mrp,
                p.purchase_price,
                p.sale_price,
                p.wholesale_price,
                p.gst_rate,
                p.cess_rate,
                p.min_stock_alert,
                p.track_batch,
                p.track_expiry,
                p.is_active,
                p.created_at,
                p.updated_at,

                c.category_name,
                pg.group_name,
                b.brand_name,
                pu.unit_name,
                pu.short_code

            FROM products p

            LEFT JOIN categories c
                ON c.id = p.category_id
               AND c.company_id = p.company_id

            LEFT JOIN product_groups pg
                ON pg.id = p.group_id
               AND pg.company_id = p.company_id

            LEFT JOIN brands b
                ON b.id = p.brand_id
               AND b.company_id = p.company_id

            INNER JOIN product_units pu
                ON pu.id = p.base_unit_id
               AND pu.company_id = p.company_id

            WHERE p.company_id = :company_id

            ORDER BY p.id DESC
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
                category_id,
                group_id,
                brand_id,
                product_name,
                sku,
                barcode,
                hsn_code,
                base_unit_id,
                mrp,
                purchase_price,
                sale_price,
                wholesale_price,
                gst_rate,
                cess_rate,
                min_stock_alert,
                track_batch,
                track_expiry,
                is_active

            FROM products

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
        ?int $categoryId,
        ?int $groupId,
        ?int $brandId,
        string $productName,
        ?string $sku,
        ?string $barcode,
        ?string $hsnCode,
        int $baseUnitId,
        float $mrp,
        float $purchasePrice,
        float $salePrice,
        float $wholesalePrice,
        float $gstRate,
        float $cessRate,
        float $minStockAlert,
        bool $trackBatch,
        bool $trackExpiry
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO products
            (
                company_id,
                category_id,
                group_id,
                brand_id,
                product_name,
                sku,
                barcode,
                hsn_code,
                base_unit_id,
                mrp,
                purchase_price,
                sale_price,
                wholesale_price,
                gst_rate,
                cess_rate,
                min_stock_alert,
                track_batch,
                track_expiry,
                is_active,
                created_at,
                updated_at
            )

            VALUES
            (
                :company_id,
                :category_id,
                :group_id,
                :brand_id,
                :product_name,
                :sku,
                :barcode,
                :hsn_code,
                :base_unit_id,
                :mrp,
                :purchase_price,
                :sale_price,
                :wholesale_price,
                :gst_rate,
                :cess_rate,
                :min_stock_alert,
                :track_batch,
                :track_expiry,
                TRUE,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )

            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'group_id' => $groupId,
            'brand_id' => $brandId,
            'product_name' => $productName,
            'sku' => $sku ?: null,
            'barcode' => $barcode ?: null,
            'hsn_code' => $hsnCode ?: null,
            'base_unit_id' => $baseUnitId,
            'mrp' => $mrp,
            'purchase_price' => $purchasePrice,
            'sale_price' => $salePrice,
            'wholesale_price' => $wholesalePrice,
            'gst_rate' => $gstRate,
            'cess_rate' => $cessRate,
            'min_stock_alert' => $minStockAlert,
            'track_batch' => $trackBatch ? 'true' : 'false',
            'track_expiry' => $trackExpiry ? 'true' : 'false'
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        ?int $categoryId,
        ?int $groupId,
        ?int $brandId,
        string $productName,
        ?string $sku,
        ?string $barcode,
        ?string $hsnCode,
        int $baseUnitId,
        float $mrp,
        float $purchasePrice,
        float $salePrice,
        float $wholesalePrice,
        float $gstRate,
        float $cessRate,
        float $minStockAlert,
        bool $trackBatch,
        bool $trackExpiry,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE products

            SET
                category_id = :category_id,
                group_id = :group_id,
                brand_id = :brand_id,
                product_name = :product_name,
                sku = :sku,
                barcode = :barcode,
                hsn_code = :hsn_code,
                base_unit_id = :base_unit_id,
                mrp = :mrp,
                purchase_price = :purchase_price,
                sale_price = :sale_price,
                wholesale_price = :wholesale_price,
                gst_rate = :gst_rate,
                cess_rate = :cess_rate,
                min_stock_alert = :min_stock_alert,
                track_batch = :track_batch,
                track_expiry = :track_expiry,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'group_id' => $groupId,
            'brand_id' => $brandId,
            'product_name' => $productName,
            'sku' => $sku ?: null,
            'barcode' => $barcode ?: null,
            'hsn_code' => $hsnCode ?: null,
            'base_unit_id' => $baseUnitId,
            'mrp' => $mrp,
            'purchase_price' => $purchasePrice,
            'sale_price' => $salePrice,
            'wholesale_price' => $wholesalePrice,
            'gst_rate' => $gstRate,
            'cess_rate' => $cessRate,
            'min_stock_alert' => $minStockAlert,
            'track_batch' => $trackBatch ? 'true' : 'false',
            'track_expiry' => $trackExpiry ? 'true' : 'false',
            'is_active' => $isActive ? 'true' : 'false'
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM products

            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }


    public static function getCategories(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                category_name

            FROM categories

            WHERE company_id = :company_id
              AND is_active = TRUE

            ORDER BY category_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function getGroups(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                group_name

            FROM product_groups

            WHERE company_id = :company_id
              AND is_active = TRUE

            ORDER BY group_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function getBrands(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                brand_name

            FROM brands

            WHERE company_id = :company_id
              AND is_active = TRUE

            ORDER BY brand_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function getUnits(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                unit_name,
                short_code

            FROM product_units

            WHERE company_id = :company_id
              AND is_active = TRUE

            ORDER BY unit_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }
}