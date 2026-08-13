<?php

require_once __DIR__ . '/../core/Database.php';

class Purchase
{
    private static function db(): PDO
    {
        return Database::connect();
    }

    /**
     * Purchase list
     */
    public static function all(
        int $companyId,
        int $branchId
    ): array {
        $db = self::db();

        $sql = "
            SELECT
                v.id,
                v.voucher_number,
                v.voucher_date,
                v.party_id,
                p.party_name,
                v.warehouse_id,
                w.warehouse_name,
                v.salesman_id,
                s.salesman_name,
                v.total_taxable_amount,
                v.cgst_amount,
                v.sgst_amount,
                v.igst_amount,
                v.cess_amount,
                v.scheme_discount_amount,
                v.round_off,
                v.net_amount,
                v.cash_paid,
                v.bank_paid,
                v.credit_amount,
                v.status,
                v.narration,
                v.created_at
            FROM vouchers v
            LEFT JOIN parties p
                ON p.id = v.party_id
            LEFT JOIN warehouses w
                ON w.id = v.warehouse_id
            LEFT JOIN salesmen s
                ON s.id = v.salesman_id
            WHERE v.company_id = :company_id
              AND v.branch_id = :branch_id
              AND v.voucher_type = 'PURCHASE'
            ORDER BY v.id DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':company_id' => $companyId,
            ':branch_id'  => $branchId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single purchase
     */
    public static function find(
        int $id,
        int $companyId,
        int $branchId
    ): ?array {
        $db = self::db();

        $sql = "
            SELECT
                v.*
            FROM vouchers v
            WHERE v.id = :id
              AND v.company_id = :company_id
              AND v.branch_id = :branch_id
              AND v.voucher_type = 'PURCHASE'
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id'         => $id,
            ':company_id' => $companyId,
            ':branch_id'  => $branchId
        ]);

        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) {
            return null;
        }

        $itemSql = "
            SELECT
                vi.*,
                pr.product_name,
                pr.sku,
                pu.unit_name
            FROM voucher_items vi
            INNER JOIN products pr
                ON pr.id = vi.product_id
            INNER JOIN product_units pu
                ON pu.id = vi.unit_id
            WHERE vi.voucher_id = :voucher_id
            ORDER BY vi.id ASC
        ";

        $itemStmt = $db->prepare($itemSql);
        $itemStmt->execute([
            ':voucher_id' => $id
        ]);

        $purchase['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        return $purchase;
    }

    /**
     * Create purchase
     *
     * $data:
     * [
     *     'voucher_number' => '',
     *     'voucher_date' => '2026-08-12',
     *     'financial_year_id' => 1,
     *     'warehouse_id' => null,
     *     'party_id' => null,
     *     'salesman_id' => null,
     *     'total_taxable_amount' => 0,
     *     'cgst_amount' => 0,
     *     'sgst_amount' => 0,
     *     'igst_amount' => 0,
     *     'cess_amount' => 0,
     *     'scheme_discount_amount' => 0,
     *     'round_off' => 0,
     *     'net_amount' => 0,
     *     'cash_paid' => 0,
     *     'bank_paid' => 0,
     *     'credit_amount' => 0,
     *     'is_b2b' => false,
     *     'place_of_supply' => null,
     *     'narration' => null,
     *     'status' => 'DRAFT'
     * ]
     *
     * $items:
     * [
     *     [
     *         'product_id' => 1,
     *         'batch_id' => null,
     *         'unit_id' => 1,
     *         'qty' => 10,
     *         'free_qty' => 0,
     *         'rate' => 100,
     *         'mrp' => 120,
     *         'discount_percent' => 0,
     *         'discount_amount' => 0,
     *         'taxable_amount' => 1000,
     *         'cgst_rate' => 0,
     *         'cgst_amount' => 0,
     *         'sgst_rate' => 0,
     *         'sgst_amount' => 0,
     *         'igst_rate' => 0,
     *         'igst_amount' => 0,
     *         'cess_rate' => 0,
     *         'cess_amount' => 0,
     *         'item_scheme_discount' => 0,
     *         'item_total' => 1000
     *     ]
     * ]
     */
    public static function create(
        int $companyId,
        int $branchId,
        int $createdBy,
        array $data,
        array $items
    ): int {
        $db = self::db();

        $db->beginTransaction();

        try {
            $status = $data['status'] ?? 'DRAFT';

            if (!in_array($status, ['DRAFT', 'POSTED'], true)) {
                throw new InvalidArgumentException(
                    'Invalid purchase status.'
                );
            }

            $sql = "
                INSERT INTO vouchers (
                    company_id,
                    branch_id,
                    warehouse_id,
                    party_id,
                    salesman_id,
                    voucher_number,
                    voucher_type,
                    voucher_date,
                    financial_year_id,
                    total_taxable_amount,
                    cgst_amount,
                    sgst_amount,
                    igst_amount,
                    cess_amount,
                    scheme_discount_amount,
                    round_off,
                    net_amount,
                    cash_paid,
                    bank_paid,
                    credit_amount,
                    is_b2b,
                    place_of_supply,
                    narration,
                    status,
                    created_by,
                    created_at,
                    updated_at
                )
                VALUES (
                    :company_id,
                    :branch_id,
                    :warehouse_id,
                    :party_id,
                    :salesman_id,
                    :voucher_number,
                    'PURCHASE',
                    :voucher_date,
                    :financial_year_id,
                    :total_taxable_amount,
                    :cgst_amount,
                    :sgst_amount,
                    :igst_amount,
                    :cess_amount,
                    :scheme_discount_amount,
                    :round_off,
                    :net_amount,
                    :cash_paid,
                    :bank_paid,
                    :credit_amount,
                    :is_b2b,
                    :place_of_supply,
                    :narration,
                    :status,
                    :created_by,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                RETURNING id
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':company_id'            => $companyId,
                ':branch_id'             => $branchId,
                ':warehouse_id'          => $data['warehouse_id'] ?? null,
                ':party_id'              => $data['party_id'] ?? null,
                ':salesman_id'           => $data['salesman_id'] ?? null,
                ':voucher_number'        => $data['voucher_number'],
                ':voucher_date'          => $data['voucher_date'],
                ':financial_year_id'     => $data['financial_year_id'],
                ':total_taxable_amount'  => $data['total_taxable_amount'] ?? 0,
                ':cgst_amount'           => $data['cgst_amount'] ?? 0,
                ':sgst_amount'           => $data['sgst_amount'] ?? 0,
                ':igst_amount'           => $data['igst_amount'] ?? 0,
                ':cess_amount'           => $data['cess_amount'] ?? 0,
                ':scheme_discount_amount'=> $data['scheme_discount_amount'] ?? 0,
                ':round_off'             => $data['round_off'] ?? 0,
                ':net_amount'            => $data['net_amount'] ?? 0,
                ':cash_paid'             => $data['cash_paid'] ?? 0,
                ':bank_paid'             => $data['bank_paid'] ?? 0,
                ':credit_amount'         => $data['credit_amount'] ?? 0,
                ':is_b2b'                 => !empty($data['is_b2b']),
                ':place_of_supply'       => $data['place_of_supply'] ?? null,
                ':narration'             => $data['narration'] ?? null,
                ':status'                => $status,
                ':created_by'            => $createdBy
            ]);

            $voucherId = (int) $stmt->fetchColumn();

            self::insertItems($voucherId, $items);

            if ($status === 'POSTED') {
                self::createStockIn(
                    $companyId,
                    $branchId,
                    $createdBy,
                    $voucherId,
                    $data,
                    $items
                );
            }

            $db->commit();

            return $voucherId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Insert purchase items
     */
    private static function insertItems(
        int $voucherId,
        array $items
    ): void {
        $db = self::db();

        $sql = "
            INSERT INTO voucher_items (
                voucher_id,
                product_id,
                batch_id,
                unit_id,
                qty,
                free_qty,
                rate,
                mrp,
                discount_percent,
                discount_amount,
                taxable_amount,
                cgst_rate,
                cgst_amount,
                sgst_rate,
                sgst_amount,
                igst_rate,
                igst_amount,
                cess_rate,
                cess_amount,
                item_scheme_discount,
                item_total
            )
            VALUES (
                :voucher_id,
                :product_id,
                :batch_id,
                :unit_id,
                :qty,
                :free_qty,
                :rate,
                :mrp,
                :discount_percent,
                :discount_amount,
                :taxable_amount,
                :cgst_rate,
                :cgst_amount,
                :sgst_rate,
                :sgst_amount,
                :igst_rate,
                :igst_amount,
                :cess_rate,
                :cess_amount,
                :item_scheme_discount,
                :item_total
            )
        ";

        $stmt = $db->prepare($sql);

        foreach ($items as $item) {
            $stmt->execute([
                ':voucher_id'           => $voucherId,
                ':product_id'           => $item['product_id'],
                ':batch_id'             => $item['batch_id'] ?? null,
                ':unit_id'              => $item['unit_id'],
                ':qty'                  => $item['qty'],
                ':free_qty'             => $item['free_qty'] ?? 0,
                ':rate'                 => $item['rate'],
                ':mrp'                  => $item['mrp'],
                ':discount_percent'     => $item['discount_percent'] ?? 0,
                ':discount_amount'      => $item['discount_amount'] ?? 0,
                ':taxable_amount'       => $item['taxable_amount'] ?? 0,
                ':cgst_rate'            => $item['cgst_rate'] ?? 0,
                ':cgst_amount'          => $item['cgst_amount'] ?? 0,
                ':sgst_rate'            => $item['sgst_rate'] ?? 0,
                ':sgst_amount'          => $item['sgst_amount'] ?? 0,
                ':igst_rate'            => $item['igst_rate'] ?? 0,
                ':igst_amount'          => $item['igst_amount'] ?? 0,
                ':cess_rate'            => $item['cess_rate'] ?? 0,
                ':cess_amount'          => $item['cess_amount'] ?? 0,
                ':item_scheme_discount' => $item['item_scheme_discount'] ?? 0,
                ':item_total'           => $item['item_total'] ?? 0
            ]);
        }
    }

    /**
     * Create STOCK_IN entries for posted purchase
     */
    private static function createStockIn(
        int $companyId,
        int $branchId,
        int $createdBy,
        int $voucherId,
        array $data,
        array $items
    ): void {
        $warehouseId = $data['warehouse_id'] ?? null;

        if (!$warehouseId) {
            throw new InvalidArgumentException(
                'Warehouse is required when posting a purchase.'
            );
        }

        $db = self::db();

        $sql = "
            INSERT INTO inventory_transactions (
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
            VALUES (
                :company_id,
                :branch_id,
                :warehouse_id,
                :product_id,
                :batch_id,
                :voucher_id,
                'STOCK_IN',
                :qty_in,
                0,
                :rate,
                CURRENT_TIMESTAMP,
                :reference_id,
                :narration,
                :created_by,
                CURRENT_TIMESTAMP
            )
        ";

        $stmt = $db->prepare($sql);

        foreach ($items as $item) {
            $qty = (float) $item['qty'];
            $freeQty = (float) ($item['free_qty'] ?? 0);

            $stmt->execute([
                ':company_id'   => $companyId,
                ':branch_id'    => $branchId,
                ':warehouse_id' => $warehouseId,
                ':product_id'   => $item['product_id'],
                ':batch_id'     => $item['batch_id'] ?? null,
                ':voucher_id'   => $voucherId,
                ':qty_in'       => $qty + $freeQty,
                ':rate'         => $item['rate'],
                ':reference_id' => $voucherId,
                ':narration'    => 'Purchase ' . $data['voucher_number'],
                ':created_by'   => $createdBy
            ]);
        }
    }

    /**
     * Cancel purchase
     *
     * Posted purchase ko direct delete nahi karenge.
     * Usko CANCELLED karenge aur reverse stock transaction create karenge.
     */
    public static function cancel(
        int $id,
        int $companyId,
        int $branchId,
        int $userId
    ): bool {
        $db = self::db();

        $db->beginTransaction();

        try {
            $purchase = self::find(
                $id,
                $companyId,
                $branchId
            );

            if (!$purchase) {
                throw new RuntimeException(
                    'Purchase not found.'
                );
            }

            if ($purchase['status'] === 'CANCELLED') {
                throw new RuntimeException(
                    'Purchase is already cancelled.'
                );
            }

            if ($purchase['status'] === 'POSTED') {
                self::createStockOutForCancellation(
                    $companyId,
                    $branchId,
                    $userId,
                    $purchase
                );
            }

            $stmt = $db->prepare("
                UPDATE vouchers
                SET
                    status = 'CANCELLED',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND company_id = :company_id
                  AND branch_id = :branch_id
                  AND voucher_type = 'PURCHASE'
            ");

            $stmt->execute([
                ':id'         => $id,
                ':company_id' => $companyId,
                ':branch_id'  => $branchId
            ]);

            $db->commit();

            return true;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Reverse STOCK_IN by creating STOCK_OUT
     */
    private static function createStockOutForCancellation(
        int $companyId,
        int $branchId,
        int $userId,
        array $purchase
    ): void {
        $warehouseId = $purchase['warehouse_id'];

        if (!$warehouseId) {
            throw new RuntimeException(
                'Warehouse missing from purchase.'
            );
        }

        $db = self::db();

        $sql = "
            INSERT INTO inventory_transactions (
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
            VALUES (
                :company_id,
                :branch_id,
                :warehouse_id,
                :product_id,
                :batch_id,
                :voucher_id,
                'STOCK_OUT',
                0,
                :qty_out,
                :rate,
                CURRENT_TIMESTAMP,
                :reference_id,
                :narration,
                :created_by,
                CURRENT_TIMESTAMP
            )
        ";

        $stmt = $db->prepare($sql);

        foreach ($purchase['items'] as $item) {
            $qty = (float) $item['qty'];
            $freeQty = (float) ($item['free_qty'] ?? 0);

            $stmt->execute([
                ':company_id'   => $companyId,
                ':branch_id'    => $branchId,
                ':warehouse_id' => $warehouseId,
                ':product_id'   => $item['product_id'],
                ':batch_id'     => $item['batch_id'] ?? null,
                ':voucher_id'   => $purchase['id'],
                ':qty_out'      => $qty + $freeQty,
                ':rate'         => $item['rate'],
                ':reference_id' => $purchase['id'],
                ':narration'    => 'Purchase Cancelled ' . $purchase['voucher_number'],
                ':created_by'   => $userId
            ]);
        }
    }

    /**
     * Generate next purchase number
     */
    public static function nextVoucherNumber(
        int $companyId,
        int $branchId,
        int $financialYearId
    ): string {
        $db = self::db();

        $sql = "
            SELECT voucher_number
            FROM vouchers
            WHERE company_id = :company_id
              AND branch_id = :branch_id
              AND financial_year_id = :financial_year_id
              AND voucher_type = 'PURCHASE'
            ORDER BY id DESC
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':company_id'       => $companyId,
            ':branch_id'        => $branchId,
            ':financial_year_id'=> $financialYearId
        ]);

        $lastNumber = $stmt->fetchColumn();

        if (!$lastNumber) {
            return 'PUR-0001';
        }

        if (preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $next = ((int) $matches[1]) + 1;

            return 'PUR-' . str_pad(
                (string) $next,
                4,
                '0',
                STR_PAD_LEFT
            );
        }

        return 'PUR-0001';
    }
}