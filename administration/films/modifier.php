<?php
include '../includes/header.php';
include '../../api_helper.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Récupération du film
$film = appelerApi("/films/$id", 'GET')['data'] ?? null;
if (!$film) {
    echo "<div class='alert alert-danger'>Film introuvable.</div>";
    include '../includes/footer.php';
    exit;
}

// Récupération des genres
$genres = appelerApi('/genres', 'GET')['data'] ?? [];

// Récupération des genres associés au film
$genres_film = array_column($film['genres'] ?? [], 'id'); // Liste des id_genre liés

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

    $resultat = appelerApi("/films/$id", 'PUT', $donnees);

    if ($resultat['success']) {
        $message = "Film modifié avec succès.";
        // Recharger les données mises à jour
        $film = appelerApi("/films/$id", 'GET')['data'] ?? null;
        $genres_film = array_column($film['genres'] ?? [], 'id');
    } else {
        $erreur = $resultat['message'] ?? "Erreur lors de la modification.";
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Modifier le film : <?= htmlspecialchars($film['titre']) ?></h2>
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
            <input type="text" name="titre" id="titre" class="form-control" value="<?= htmlspecialchars($film['titre']) ?>" required>
        </div>

        <div class="col-md-4">
            <label for="age_minimum" class="form-label">Âge minimum</label>
            <input type="number" name="age_minimum" id="age_minimum" class="form-control" min="0" value="<?= (int) $film['age_minimum'] ?>" required>
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea name="description" id="description" rows="4" class="form-control" required><?= htmlspecialchars($film['description']) ?></textarea>
        </div>

        <div class="col-md-6">
            <label for="affiche_url" class="form-label">URL de l'affiche</label>
            <input type="url" name="affiche_url" id="affiche_url" class="form-control" value="<?= htmlspecialchars($film['affiche_url']) ?>">
        </div>

        <div class="col-md-6">
            <label for="bande_annonce_url" class="form-label">URL de la bande-annonce</label>
            <input type="url" name="bande_annonce_url" id="bande_annonce_url" class="form-control" value="<?= htmlspecialchars($film['bande_annonce_url']) ?>">
        </div>

        <div class="col-md-12">
            <label class="form-label d-block">Genres</label>
            <div class="row">
                <?php foreach ($genres as $genre): ?>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="genres[]" value="<?= $genre['id'] ?>" id="genre<?= $genre['id'] ?>"
                                <?= in_array($genre['id'], $genres_film) ? 'checked' : '' ?>>
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
                <input class="form-check-input" type="checkbox" name="coup_de_coeur" id="coup_de_coeur" <?= $film['coup_de_coeur'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="coup_de_coeur">Coup de cœur</label>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>