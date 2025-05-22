<?php
include '../includes/header.php';
include '../../api_helper.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Récupération du film via l’API
$film = appelerApi("/films/$id", 'GET')['data'] ?? null;

if (!$film) {
    echo "<div class='alert alert-danger'>Film introuvable.</div>";
    include '../includes/footer.php';
    exit;
}
?>

<div class="container mt-5">
    <h2>Détails du film</h2>
    <div class="card mb-4">
        <div class="row g-0">
            <?php if (!empty($film['affiche_url'])): ?>
                <div class="col-md-4">
                    <img src="<?= htmlspecialchars($film['affiche_url']) ?>" alt="Affiche du film" class="img-fluid rounded-start">
                </div>
            <?php endif; ?>

            <div class="col-md-8">
                <div class="card-body">
                    <h3 class="card-title"><?= htmlspecialchars($film['titre']) ?></h3>
                    <p class="card-text"><strong>Description :</strong> <?= nl2br(htmlspecialchars($film['description'])) ?></p>
                    <p class="card-text"><strong>Âge minimum :</strong> <?= (int)$film['age_minimum'] ?> ans</p>
                    <p class="card-text"><strong>Note moyenne :</strong> <?= $film['note_moyenne'] ?? 'Non noté' ?></p>
                    <p class="card-text"><strong>Coup de cœur :</strong> <?= $film['coup_de_coeur'] ? '❤️ Oui' : 'Non' ?></p>
                    <p class="card-text"><strong>Date de création :</strong> <?= date('d/m/Y H:i', strtotime($film['date_creation'])) ?></p>

                    <?php if (!empty($film['bande_annonce_url'])): ?>
                        <p class="card-text">
                            <a href="<?= htmlspecialchars($film['bande_annonce_url']) ?>" class="btn btn-outline-primary" target="_blank">
                                Voir la bande-annonce
                            </a>
                        </p>
                    <?php endif; ?>

                    <p class="card-text">
                        <strong>Genres :</strong>
                        <?php if (!empty($film['genres'])): ?>
                            <?= implode(', ', array_map(fn($g) => htmlspecialchars($g['nom']), $film['genres'])) ?>
                        <?php else: ?>
                            Aucun
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <h4>Séances à venir</h4>
    <?php if (!empty($film['seances'])): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Salle</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Prix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($film['seances'] as $seance): ?>
                        <tr>
                            <td><?= htmlspecialchars($seance['id_salle']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($seance['debut'])) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($seance['fin'])) ?></td>
                            <td><?= htmlspecialchars($seance['prix']) ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Aucune séance programmée pour ce film.</div>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Retour à la liste des films
    </a>
</div>

<?php include '../includes/footer.php'; ?>