<?php

include '../includes/header.php';
include '../../api_helper.php';
// TODO : récupérer la liste des genres depuis la base données sans passer l'API externe
$genres = appelerApi('/genres', 'GET')['data'] ?? [];

$message = null;
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donnees = [
        'titre' => $_POST['titre'] ?? '',
        'description' => $_POST['description'] ?? '',
        'age_minimum' => (int) ($_POST['age_minimum'] ?? 0),
        'coup_de_coeur' => isset($_POST['coup_de_coeur']),
        'affiche_url' => $_POST['affiche_url'] ?? '',
        'bande_annonce_url' => $_POST['bande_annonce_url'] ?? '',
        'genres' => $_POST['genres'] ?? [],
    ];

    // TODO : Insérer le film dans la base de données sans passer par l'API externe
    $resultat = appelerApi('/films', 'POST', $donnees);

    if ($resultat['success']) {
        $message = "Film ajouté avec succès.";
    } else {
        $erreur = $resultat['message'] ?? "Erreur lors de l'ajout du film.";
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Ajouter un film</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour à la liste des films
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($erreur): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3">

        <div class="col-md-8">
            <label for="titre" class="form-label">Titre du film</label>
            <input type="text" name="titre" id="titre" class="form-control" required>
        </div>

        <div class="col-md-4">
            <label for="age_minimum" class="form-label">Âge minimum</label>
            <input type="number" name="age_minimum" id="age_minimum" class="form-control" min="0" value="0" required>
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea name="description" id="description" rows="4" class="form-control" required></textarea>
        </div>

        <div class="col-md-6">
            <label for="affiche_url" class="form-label">URL de l'affiche</label>
            <input type="url" name="affiche_url" id="affiche_url" class="form-control">
        </div>

        <div class="col-md-6">
            <label for="bande_annonce_url" class="form-label">URL de la bande-annonce</label>
            <input type="url" name="bande_annonce_url" id="bande_annonce_url" class="form-control">
        </div>

        <div class="col-md-12">
            <label class="form-label d-block">Genres</label>
            <div class="row">
                <?php foreach ($genres as $genre): ?>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="genres[]" value="<?= $genre['id'] ?>" id="genre<?= $genre['id'] ?>">
                            <label class="form-check-label" for="genre<?= $genre['id'] ?>">
                                <?= htmlspecialchars($genre['nom']) ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-6 d-flex align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="coup_de_coeur" id="coup_de_coeur">
                <label class="form-check-label" for="coup_de_coeur">Coup de cœur</label>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Ajouter le film
            </button>
        </div>

    </form>
</div>

<?php include '../includes/footer.php'; ?>