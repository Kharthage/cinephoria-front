<?php
include 'includes/header.php';

include 'api_helper.php';

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'email' => $_POST['email'],
        'mot_de_passe' => $_POST['mot_de_passe']
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json",
            'content' => json_encode($data)
        ]
    ];
    $result = appelerApi('/login', 'POST', $data);
    if ($result['success']) {
        $data = $result['data'];
        if (isset($data['token'])) {
            $_SESSION['token'] = $data['token'];
            $payload = explode('.', $data['token'])[1];
            $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
            $_SESSION['utilisateur'] = [
                'id' => $decoded['user_id'],
                'role' => $decoded['role'],
                'changer_mdp' => $decoded['changer_mdp'],
            ];
            header('Location: index.php');
            exit;
        } else {
            $message = $data['message'] ?? "Erreur inconnue.";
            $alert = "danger";
        }
    } else {
        $message = $result['message'] ?? "Erreur inconnue.";;
        $alert = "danger";
    }
}
?>



<?php if ($message): ?>
<div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" action="login.php" class="mx-auto" style="max-width: 400px;">
    <h1 class="mb-4">Connexion</h1>
    <div class="mb-3">
        <label for="email" class="form-label">Adresse email</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>
    <div class="mb-3">
        <label for="mot_de_passe" class="form-label">Mot de passe</label>
        <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Se connecter</button>

    <div class="text-end">
        <a href="mdp_oublie.php">Mot de passe oublié</a>
    </div>
    <div class="mt-4 text-center">
        Pas encore inscrit <a href="register.php">Créer compte</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>