<?php
include '../includes/header.php';
include '../../api_helper.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Récupération du film (avec genres et séances)
$film = appelerApi("/films/$id", 'GET')['data'] ?? null;
if (!$film) {
    echo "<div class='alert alert-danger'>Film introuvable.</div>";
    include '../includes/footer.php';
    exit;
}

$message = null;
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultat = appelerApi("/films/$id", 'DELETE');

    if ($resultat['success']) {
        header('Location: index.php?supprime=1');
        exit;
    } else {
        $erreur = $resultat['message'] ?? "Erreur lors de la suppression du film.";
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4">Supprimer le film</h2>

    <?php if ($erreur): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="alert alert-warning">
        Êtes-vous sûr de vouloir supprimer le film <strong><?= htmlspecialchars($film['titre']) ?></strong> ?
    </div>

    <?php if (!empty($film['seances'])): ?>
        <div class="alert alert-info">
            <strong>Attention :</strong> Ce film possède encore des séances associées. Leur suppression sera définitive.
        </div>

        <h5>Séances associées :</h5>
        <ul class="list-group mb-3">
            <?php foreach ($film['seances'] as $seance): ?>
                <li class="list-group-item">
                    Salle #<?= htmlspecialchars($seance['id_salle']) ?> –
                    Début : <?= htmlspecialchars($seance['debut']) ?> –
                    Fin : <?= htmlspecialchars($seance['fin']) ?> –
                    Prix : <?= htmlspecialchars($seance['prix']) ?> €
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST">
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Annuler
        </a>
        <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash"></i> Confirmer la suppression
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>