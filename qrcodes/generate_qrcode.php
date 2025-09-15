<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_GET['billet_id'])) {
    http_response_code(400);
    echo "ID billet manquant.";
    exit;
}

$billetId = intval($_GET['billet_id']);

$result = Builder::create()
    ->writer(new PngWriter())
    ->data((string)$billetId)
    ->size(300)
    ->margin(10)
    ->build();

header('Content-Type: image/png');
echo $result->getString();
exit;
