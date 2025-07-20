<?php
include 'includes/header.php';
include 'api_helper.php';

$films = appelerApi('/films/derniers', 'GET')['data'] ?? [];
?>

<h1 class="mb-4">Nouveaux films</h1>
<div class="row">
    <?php foreach ($films as $film): ?>
        <div class="col-md-4 mb-4">
            <div class="card">
                <?php if ($film['affiche_url']): ?>
                    <img src="<?= htmlspecialchars($film['affiche_url']) ?>" class="card-img-top" alt="Affiche">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($film['titre']) ?></h5>
                    <p class="card-text"><?= htmlspecialchars($film['description']) ?></p>
                    <a href="film.php?id=<?= $film['id'] ?>" class="btn btn-primary">Détails</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>