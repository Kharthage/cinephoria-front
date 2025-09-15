<?php

require __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Color\Color;

$data = $_GET['data'] ?? null;
$error = null;
$imageData = null;

if ($data) {
    try {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->backgroundColor(new Color(255, 255, 255))
            ->build();

        $imageData = base64_encode($result->getString());
    } catch (Exception $e) {
        $error = "Erreur lors de la génération du QR code : " . $e->getMessage();
    }
} elseif (isset($_GET['data'])) {
    $error = "Le paramètre 'data' est vide.";
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>QR Code Generator - Cinephoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">
        <h1 class="mb-4 text-center">🎟️ Générateur de QR Code - Cinephoria</h1>

        <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="data" class="form-control" placeholder="Entrez l'URL ou le texte à encoder" required>
                <button type="submit" class="btn btn-primary">Générer</button>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($imageData): ?>
            <div class="text-center">
                <p><strong>QR Code généré pour :</strong> <?= htmlspecialchars($data) ?></p>
                <img src="data:image/png;base64,<?= $imageData ?>" alt="QR Code" class="img-fluid" style="max-width: 300px;">
            </div>
        <?php endif; ?>
    </div>
</body>

</html>