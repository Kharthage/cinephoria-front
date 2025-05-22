<?php
include 'includes/header.php';
include 'api_helper.php';


$message = null;
$alert = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom' => $_POST['nom'],
        'prenom' => $_POST['prenom'],
        'email' => $_POST['email'],
        'mot_de_passe' => $_POST['mot_de_passe']
    ];

    $result = appelerApi('/utilisateurs', 'POST', $data);
    if ($result['success']) {
        $message = 'Votre compte est crée avec succès';
        $alert = "success";
    } else {
        $message = $result['message'];
        $alert = "danger";
    }
}
?>



<?php if ($message): ?>
<div class="alert alert-<?php echo $alert ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" action="register.php" class="mx-auto" style="max-width: 500px;">
    <h1 class="mb-4">Créer un compte</h1>
    <div class="mb-3">
        <label for="nom" class="form-label">Nom</label>
        <input type="text" class="form-control" id="nom" name="nom" required>
    </div>
    <div class="mb-3">
        <label for="prenom" class="form-label">Prénom</label>
        <input type="text" class="form-control" id="prenom" name="prenom" required>
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">Adresse email</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>
    <div class="mb-3">
        <label for="mot_de_passe" class="form-label">Mot de passe</label>
        <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
        <div class="form-text">
            8 caractères minimum, avec au moins une majuscule, une minuscule, un chiffre et un caractère spécial.
        </div>
    </div>
    <button type="submit" class="btn btn-success w-100">Créer le compte</button>
</form>

<?php include 'includes/footer.php'; ?>