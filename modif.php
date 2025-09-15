<?php
session_start();
require 'testpdo.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        try {
            // Vérifier si l'email existe déjà
            $check_email = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = ?");
            $check_email->execute([$email]);

            if ($check_email->fetchColumn() > 0) {
                $error = 'Cet email est déjà utilisé.';
            } else {
                // Créer le nouvel utilisateur
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prenom, $email, $hashed_password]);

                // Récupérer l'ID du nouvel utilisateur
                $user_id = $pdo->lastInsertId();

                // Connexion automatique après inscription
                $_SESSION['id_utilisateur'] = $user_id;
                $_SESSION['email'] = $email;

                header('Location: index.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'inscription : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Cinephoria</title>
    <style>
        :root {
            --primary-color: #1b1f3b;
            --accent-color: #f4c842;
            --bg-color: #121228;
            --text-color: #ffffff;
            --input-bg: #1f213a;
            --input-border: #333459;
        }

        body {
            margin: 0;
            font-family: 'Arial', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        header {
            background-color: var(--primary-color);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
            color: var(--accent-color);
        }

        nav a {
            color: var(--text-color);
            text-decoration: none;
            margin-left: 20px;
            font-weight: bold;
            transition: color 0.2s;
        }

        nav a:hover {
            color: var(--accent-color);
        }

        main {
            display: flex;
            justify-content: center;
            align-items: center;
            height: calc(100vh - 120px);
        }

        .register-container {
            background-color: #1f213a;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 400px;
        }

        .register-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--accent-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid var(--input-border);
            background-color: var(--input-bg);
            color: var(--text-color);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 5px var(--accent-color);
        }

        .btn-register {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background-color: var(--accent-color);
            color: var(--primary-color);
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-register:hover {
            background-color: #e0b935;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }

        .alert.danger {
            background-color: #d32f2f33;
            color: #d32f2f;
        }

        .text-center {
            text-align: center;
            margin-top: 20px;
        }

        .text-center a {
            color: var(--accent-color);
            font-weight: bold;
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: var(--primary-color);
            color: var(--text-color);
        }
    </style>
</head>

<body>
    <header>
        <h1>Cinephoria</h1>
        <nav>
            <a href="index.php">Accueil</a>
            <a href="films.php">Films</a>
            <a href="contact.php">Contact</a>
        </nav>
    </header>

    <main>
        <div class="register-container">
            <h2>Modification des informations</h2>

            <?php if ($error): ?>
                <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="nom">Nom :</label>
                    <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="prenom">Prénom :</label>
                    <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe :</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe :</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn-register">confirmation</button>
            </form>

            <div class="text-center">
                <p>Déjà inscrit ? <a href="login.php">Se connecter</a></p>
            </div>
        </div>
    </main>

    <footer>
        © 2025 Cinephoria. Tous droits réservés. 🎬
    </footer>
</body>

</html>