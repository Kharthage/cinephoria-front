<?php
include '../includes/header.php';
include '../../api_helper.php';


$cinemas = appelerApi('/cinemas', 'GET', [], $_SESSION['token'] ?? null);
$liste = $cinemas['data'] ?? [];
?>

<div class="container mt-5">
    <h2 class="mb-4">Liste des cinémas</h2>

    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Nom</th>
                <th>Adresse</th>
                <th>GSM</th>
                <th>Horaires</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($liste as $cinema): ?>
                <tr>
                    <td><?= htmlspecialchars($cinema['nom']) ?></td>
                    <td>
                        <?= htmlspecialchars($cinema['ligne_adresse1']) ?><br>
                        <?= htmlspecialchars($cinema['ligne_adresse2']) ?><br>
                        <?= htmlspecialchars($cinema['code_postal']) ?> <?= htmlspecialchars($cinema['ville']) ?><br>
                        <?= htmlspecialchars($cinema['pays']) ?>
                    </td>
                    <td><?= htmlspecialchars($cinema['numero_gsm']) ?></td>
                    <td><?= nl2br(htmlspecialchars($cinema['horaires'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


faire?eas