<?php
$id = $_GET['id'] ?? null;

if (!$id || !ctype_digit($id)) {
    header("Location: ../films/index.php");
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=cinephoria;charset=utf8',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $stmt = $pdo->prepare("SELECT id, titre, description AS synopsis, affiche_url FROM film WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    $stmt->execute();
    $film = $stmt->fetch();

    if (!$film) {
        header("Location: ../films/index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($film['titre']) ?> — Cinephoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <a href="../listefilms.php" class="btn btn-secondary mb-3">⬅ Retour à la liste</a>
        <div class="card shadow">
            <div class="row g-0">
                <?php if (!empty($film['affiche_url'])): ?>
                    <div class="col-md-4">
                        <img src="<?= htmlspecialchars($film['affiche_url']) ?>" class="img-fluid rounded-start" alt="Affiche">
                    </div>
                <?php endif; ?>
                <div class="col-md-8">
                    <div class="card-body">
                        <h2 class="card-title"><?= htmlspecialchars($film['titre']) ?></h2>
                        <p><?= nl2br(htmlspecialchars($film['synopsis'] ?? "Pas de synopsis.")) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>