<?php
session_start();
require 'testpdo.php'; // Connexion PDO

$redirect = $_GET['redirect'] ?? 'index.php';
if (isset($_SESSION['user_id'])) {
    header("Location: $redirect");
    exit;
}

$message = '';
$nom = '';
$prenom = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $message = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email invalide.";
    } elseif ($password !== $confirm_password) {
        $message = "Les mots de passe ne correspondent pas.";
    } else {
        $check = $pdo->prepare("SELECT id FROM utilisateur WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $message = "Cet email est déjà utilisé.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, 'utilisateur')");
            $stmt->execute([$nom, $prenom, $email, $hash]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['prenom'] = $prenom;

            header("Location: $redirect");
            exit;
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Créer un compte</h1>

    <?php if ($message): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="?redirect=<?= urlencode($redirect) ?>" class="mx-auto" style="max-width: 500px;">
        <div class="mb-3">
            <label for="nom" class="form-label">Nom</label>
            <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($nom) ?>" required>
        </div>

        <div class="mb-3">
            <label for="prenom" class="form-label">Prénom</label>
            <input type="text" class="form-control" id="prenom" name="prenom" value="<?= htmlspecialchars($prenom) ?>" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Adresse email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Créer mon compte</button>
    </form>

    <p class="mt-3 text-center">Déjà un compte ? <a href="login.php?redirect=<?= urlencode($redirect) ?>">Se connecter</a></p>
</div>

<?php include 'includes/footer.php'; ?>