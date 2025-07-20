<?php
include '../includes/header.php';
include '../../api_helper.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Charger la salle
$salle = appelerApi("/salles/$id", 'GET')['data'] ?? null;
if (!$salle) {
    echo "<div class='alert alert-danger'>Salle introuvable.</div>";
    include '../../includes/admin_footer.php';
    exit;
}

// Charger les cinémas et qualités
$cinemas = appelerApi('/cinemas', 'GET')['data'] ?? [];
$qualites = appelerApi('/qualites-projection', 'GET')['data'] ?? [];

$message = null;
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donnees = [
        'reference' => $_POST['reference'],
        'nb_places' => $_POST['nb_places'],
        'nb_places_pmr' => $_POST['nb_places_pmr'],
        'id_qualite_projection' => $_POST['id_qualite_projection']
    ];

    $resultat = appelerApi("/salles/$id", 'PUT', $donnees);

    if ($resultat['success']) {
        $message = "Salle modifiée avec succès.";
    } else {
        $erreur = $resultat['message'] ?? "Erreur inconnue.";
    }

    $salle = appelerApi("/salles/$id", 'GET')['data'] ?? null;
}
?>

<a href="index.php">Liste des salles</a>
<div class="container mt-5">
    <h2 class="mb-4">Modifier la salle <?= htmlspecialchars($salle['reference']) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($erreur): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label for="id_cinema" class="form-label">Cinéma</label>
            <select class="form-select" disabled>
                <?php foreach ($cinemas as $cinema): ?>
                    <option <?= $cinema['id'] == $salle['id_cinema'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cinema['nom']) ?> - <?= htmlspecialchars($cinema['ville']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Le cinéma ne peut pas être modifié.</div>
        </div>

        <div class="col-md-6">
            <label for="id_qualite_projection" class="form-label">Qualité de projection</label>
            <select name="id_qualite_projection" class="form-select" required>
                <?php foreach ($qualites as $q): ?>
                    <option value="<?= $q['id'] ?>" <?= $q['id'] == $salle['id_qualite_projection'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($q['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="reference" class="form-label">Référence</label>
            <input type="text" name="reference" class="form-control" value="<?= htmlspecialchars($salle['reference']) ?>" required>
        </div>

        <div class="col-md-3">
            <label for="nb_places" class="form-label">Nombre de places</label>
            <input type="number" name="nb_places" class="form-control" min="1" value="<?= $salle['nb_places'] ?>" required>
        </div>

        <div class="col-md-3">
            <label for="nb_places_pmr" class="form-label">Places PMR</label>
            <input type="number" name="nb_places_pmr" class="form-control" min="0" value="<?= $salle['nb_places_pmr'] ?>">
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>