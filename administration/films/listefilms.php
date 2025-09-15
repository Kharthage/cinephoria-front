<?php
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

    $films = $pdo->query("SELECT id, titre, affiche, annee FROM film ORDER BY titre ASC")->fetchAll();
} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Liste des films — Cinephoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <h1>🎬 Liste des films</h1>
        <div class="row">
            <?php foreach ($films as $film): ?>
                <div class="col-md-3 mb-4">
                    <div class="card shadow-sm">
                        <?php if (!empty($film['affiche'])): ?>
                            <img src="<?= htmlspecialchars($film['affiche']) ?>" class="card-img-top" alt="Affiche">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($film['titre']) ?></h5>
                            <p class="text-muted"><?= htmlspecialchars($film['annee']) ?></p>
                            <a href="films/details.php?id=<?= urlencode($film['id']) ?>" class="btn btn-primary">Voir détails</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>