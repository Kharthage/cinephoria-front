<?php
include '../includes/header.php';
include '../../api_helper.php';

$response = appelerApi('/films', 'GET');
$films = $response['data'] ?? [];
?>

<div class="container mt-5">
    <h2 class="mb-4">Liste des Films</h2>

    <div class="mb-3 text-end">
        <a href="ajouter.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Ajouter un film
        </a>
    </div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Affiche</th>
                <th>Titre</th>
                <th>Note Moyenne</th>
                <th>Âge Minimum</th>
                <th>Coup de Cœur</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($films)): ?>
                <tr>
                    <td colspan="6" class="text-center">Aucun film trouvé.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($films as $film): ?>
                    <tr>
                        <td>
                            <?php if (!empty($film['affiche_url'])): ?>
                                <img src="<?= htmlspecialchars($film['affiche_url']) ?>" alt="Affiche" width="60">
                            <?php else: ?>
                                <span class="text-muted">Pas d'affiche</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($film['titre']) ?></td>
                        <td><?= is_numeric($film['note_moyenne']) ? round($film['note_moyenne'], 1) : '—' ?></td>
                        <td><?= (int)$film['age_minimum'] ?>+</td>
                        <td>
                            <?php if ($film['coup_de_coeur']): ?>
                                <i class="bi bi-heart-fill text-danger"></i>
                            <?php else: ?>
                                <i class="bi bi-heart text-muted"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="details.php?id=<?= $film['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="modifier.php?id=<?= $film['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="supprimer.php?id=<?= $film['id'] ?>" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>