<?php
include 'includes/header.php';

include 'api_helper.php';

if (!isset($_SESSION['utilisateur'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['utilisateur']['id'];
$reponse = appelerApi('/utilisateurs/' . $userId);
$infos = $reponse['success'] ? $reponse['data'] : null;
?>

<h1 class="mb-4">Mon profil</h1>

<?php if ($infos): ?>
<div class="card" style="max-width: 500px;">
    <div class="card-body">
        <h5 class="card-title">Informations utilisateur</h5>
        <p><strong>Nom :</strong> <?= htmlspecialchars($infos['nom']) ?></p>
        <p><strong>Prénom :</strong> <?= htmlspecialchars($infos['prenom']) ?></p>
        <p><strong>Email :</strong> <?= htmlspecialchars($infos['email']) ?></p>
        <p><strong>Rôle :</strong> <?= htmlspecialchars($infos['role']) ?></p>
        <p><strong>Email confirmé :</strong> <?= $infos['email_confirme'] ? 'Oui' : 'Non' ?></p>
    </div>
</div>
<a href="changer_mdp.php">Changer mot de passe</a>
<?php else: ?>
<div class="alert alert-danger"><?php echo($reponse['message'] ?? 'Impossible de charger les informations utilisateur.');?></div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>