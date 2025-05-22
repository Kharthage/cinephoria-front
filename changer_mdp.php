<?php
include 'includes/header.php';

include 'api_helper.php';

$message = null;
$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ancien = $_POST['ancien_mdp'] ?? '';
    $nouveau = $_POST['nouveau_mdp'] ?? '';
    $confirmation = $_POST['confirm_mdp'] ?? '';

    if ($nouveau !== $confirmation) {
        $message = "Les mots de passe ne correspondent pas.";
        $alert = "danger";
    } else {
        $response = appelerApi('/utilisateurs/' . $_SESSION['utilisateur']['id'] . '/changer-mdp', 'PUT', [
            'ancien' => $ancien,
            'nouveau' => $nouveau
        ], $_SESSION['token'] ?? null);

        if ($response['code'] === 'UTILISATEUR.MDP_MODIFIE') {
            $message = $response['message'] ?? "Mot de passe changer avec succès.";
            $alert = "success";
        } else {
            $message = $response['message'] ?? "Erreur lors du changement de mot de passe.";
            $alert = "danger";
        }
    }
}
?>

<h1 class="mb-4 text-center">Changement de mot de passe</h1>

<?php if ($message): ?>
<div class="alert alert-<?php echo $alert ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (isset($_GET['auto'])): ?>
<div style="max-width: 500px;" class="mx-auto alert alert-warning">
    Votre mot de passe a été généré automatiquement. Merci de le modifier pour continuer à utiliser votre compte.
</div>
<?php endif; ?>

<form method="POST" class="mx-auto" style="max-width: 500px;">
    <div class="mb-3">
        <label for="ancien_mdp" class="form-label">Mot de passe actuel</label>
        <input type="password" class="form-control" id="ancien_mdp" name="ancien_mdp" required>
    </div>
    <div class="mb-3">
        <label for="nouveau_mdp" class="form-label">Nouveau mot de passe</label>
        <input type="password" class="form-control" id="nouveau_mdp" name="nouveau_mdp" required>
    </div>
    <div class="mb-3">
        <label for="confirm_mdp" class="form-label">Confirmer le mot de passe</label>
        <input type="password" class="form-control" id="confirm_mdp" name="confirm_mdp" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Changer</button>
</form>

<?php include 'includes/footer.php'; ?>