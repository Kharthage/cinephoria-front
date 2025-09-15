<?php
require __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;

try {
    $result = Builder::create()
        ->data('https://cinephoria.fr/scan?ticket=123456')
        ->size(300)
        ->margin(10)
        ->build();

    header('Content-Type: ' . $result->getMimeType());
    echo $result->getString();
} catch (Throwable $e) {
    echo "Erreur : " . $e->getMessage();
}
