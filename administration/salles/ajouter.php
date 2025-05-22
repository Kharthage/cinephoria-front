<?php
include '../includes/header.php';
include '../../api_helper.php';

$cinemas = appelerApi('/cinemas', 'GET')['data'] ?? [];
$qualites = appelerApi('/qualites-projection', 'GET')['data'] ?? [];
$message = null;
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donnees = [
        'id_cinema' => $_POST['id_cinema'],
        'reference' => $_POST['reference'],
        'nb_places' => $_POST['nb_places'],
        'nb_places_pmr' => $_POST['nb_places_pmr'],
        'id_qualite_projection' => $_POST['id_qualite_projection'],
    ];

    $resultat = appelerApi('/salles', 'POST', $donnees);
    if ($resultat['success']) {
        $message = "Salle créée avec succès.";
    } else {
        $erreur = $resultat['message'] ?? "Erreur inconnue.";
    }
}
?>

<a href="index.php">Liste des salles</a>
<div class="container mt-5">
    <h2 class="mb-4">Ajouter une salle</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($erreur): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3">

        <div class="col-md-6">
            <label for="id_cinema" class="form-label">Cinéma</label>
            <select name="id_cinema" id="id_cinema" class="form-select" required>
                <option value="">-- Choisir un cinéma --</option>
                <?php foreach ($cinemas as $cinema): ?>
                    <option value="<?= $cinema['id'] ?>"><?= htmlspecialchars($cinema['nom']) ?> - <?= htmlspecialchars($cinema['ville']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="id_qualite_projection" class="form-label">Qualité de projection</label>
            <select name="id_qualite_projection" id="id_qualite_projection" class="form-select" required>
                <option value="">-- Choisir une qualité --</option>
                <?php foreach ($qualites as $q): ?>
                    <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="reference" class="form-label">Référence</label>
            <input type="text" name="reference" id="reference" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label for="nb_places" class="form-label">Nombre de places</label>
            <input type="number" name="nb_places" id="nb_places" class="form-control" min="1" required>
        </div>

        <div class="col-md-3">
            <label for="nb_places_pmr" class="form-label">Places PMR</label>
            <input type="number" name="nb_places_pmr" id="nb_places_pmr" class="form-control" min="0" value="0">
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Enregistrer
            </button>
        </div>

    </form>
</div>


<?php include '../includes/footer.php'; ?>