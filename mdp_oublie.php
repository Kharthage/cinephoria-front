<?php
include 'includes/header.php';

include 'api_helper.php';

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $result = appelerApi('/mdp-oublie', 'POST', ['email' => $email]);
    $message = $result['message'];
}
?>

<div>
<h1 class="mb-4 text-center">Mot de passe oublié</h1>
<?php if ($message): ?>
<div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" action="mdp_oublie.php" class="mx-auto" style="max-width: 500px;">
    <div class="mb-3">
        <label for="email" class="form-label">Adresse email</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Envoyer</button>
</form>
</div>


<?php include 'includes/footer.php'; ?>