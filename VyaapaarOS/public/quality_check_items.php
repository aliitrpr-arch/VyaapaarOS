<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

header('Content-Type: application/json; charset=utf-8');

try {

    if (!Session::has('user_id')) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Session expired.'
        ]);
        exit;
    }

    if (!PermissionMiddleware::check('purchase.view', 'view')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access Denied.'
        ]);
        exit;
    }

    $companyId = (int) Session::get('company_id');
    $branchId  = (int) Session::get('branch_id');

    $purchaseId = (int)($_GET['purchase_id'] ?? 0);

    if ($purchaseId <= 0) {
        throw new Exception('Invalid purchase.');
    }

    $db = Database::connect();

    $stmt = $db->prepare("
        SELECT id
        FROM vouchers
        WHERE id = :id
          AND company_id = :company_id
          AND branch_id = :branch_id
          AND voucher_type = 'PURCHASE'
          AND status = 'POSTED'
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $purchaseId,
        'company_id' => $companyId,
        'branch_id' => $branchId
    ]);

    if (!$stmt->fetchColumn()) {
        throw new Exception('Purchase not found or not posted.');
    }

    $stmt = $db->prepare("
        SELECT
            vi.id AS voucher_item_id,
            vi.product_id,
            p.product_name,
            p.sku,
            (vi.qty + vi.free_qty) AS purchased_qty,
            COALESCE(
                (
                    SELECT SUM(qci.received_qty)
                    FROM quality_check_items qci
                    INNER JOIN quality_checks qc
                        ON qc.id = qci.quality_check_id
                    WHERE qci.voucher_item_id = vi.id
                      AND qc.status <> 'CANCELLED'
                ),
                0
            ) AS already_qc_qty
        FROM voucher_items vi
        INNER JOIN products p
            ON p.id = vi.product_id
        WHERE vi.voucher_id = :voucher_id
        ORDER BY vi.id
    ");

    $stmt->execute([
        'voucher_id' => $purchaseId
    ]);

    $items = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $purchased = (float)$row['purchased_qty'];
        $alreadyQc = (float)$row['already_qc_qty'];
        $remaining = max(0, $purchased - $alreadyQc);

        if ($remaining <= 0) {
            continue;
        }

        $items[] = [
            'voucher_item_id' => (int)$row['voucher_item_id'],
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'sku' => $row['sku'],
            'purchased_qty' => $purchased,
            'already_qc_qty' => $alreadyQc,
            'remaining_qty' => $remaining
        ];
    }

    echo json_encode([
        'success' => true,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $ex) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $ex->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
