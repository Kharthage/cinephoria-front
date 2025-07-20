<?php
include '../includes/header.php';
include '../../api_helper.php';

$response = appelerApi('/salles', 'GET');
$salles = $response['data'] ?? [];
?>

<div class="container mt-5">
    <h2 class="mb-4">Gestion des Salles</h2>

    <div class="mb-3 text-end">
        <a href="ajouter.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Ajouter une salle
        </a>
    </div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Cinéma</th>
                <th>Référence</th>
                <th>Places</th>
                <th>Places PMR</th>
                <th>Qualité</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($salles as $salle): ?>
                <tr>
                    <td><?= htmlspecialchars($salle['cinema_nom'] ?? 'Non défini') ?></td>
                    <td><?= htmlspecialchars($salle['reference']) ?></td>
                    <td><?= htmlspecialchars($salle['nb_places']) ?></td>
                    <td><?= htmlspecialchars($salle['nb_places_pmr']) ?></td>
                    <td><?= htmlspecialchars($salle['qualite_nom']) ?></td>
                    <td class="text-center">
                        <a href="details.php?id=<?= $salle['id'] ?>" class="btn btn-sm btn-outline-secondary d-none">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="modifier.php?id=<?= $salle['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="supprimer.php?id=<?= $salle['id'] ?>" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


<?php include '../includes/footer.php'; ?>