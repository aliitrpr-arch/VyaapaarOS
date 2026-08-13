<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/models/InventoryTransaction.php';

Session::start();

header('Content-Type: application/json');

if (!Session::has('user_id')) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$companyId = (int) Session::get('company_id');
$productId = (int) ($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode([]);
    exit;
}

$batches = InventoryTransaction::getBatches(
    $companyId,
    $productId
);

echo json_encode($batches);