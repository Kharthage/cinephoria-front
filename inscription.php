<?php
session_start();
require 'testpdo.php';

// Rediriger vers l'accueil si déjà connecté
if (isset($_SESSION['utilisateur'])) {
    header('Location: index.php');
    exit;
}

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
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/', $nom)) {
        $error = 'Le nom ne doit contenir que des lettres, espaces, tirets et apostrophes.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/', $prenom)) {
        $error = 'Le prénom ne doit contenir que des lettres, espaces, tirets et apostrophes.';
    } else {
        try {
            // Vérifier si l'email existe déjà
            $check_email = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = ?");
            $check_email->execute([$email]);

            if ($check_email->fetchColumn() > 0) {
                $error = 'Cette adresse email est déjà utilisée.';
            } else {
                // Générer un token de confirmation
                $confirmation_token = bin2hex(random_bytes(32));

                // Créer le nouvel utilisateur
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, confirmation_token, email_confirme, role, date_creation, changer_mdp) 
                        VALUES (?, ?, ?, ?, ?, 0, 'utilisateur', NOW(), 0)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prenom, $email, $hashed_password, $confirmation_token]);

                // Envoi de l'email de bienvenue
                require_once 'lib/EmailSender.php';
                try {
                    $emailSender = new EmailSender();
                    $emailSender->sendWelcomeEmail($email, $prenom);

                    // Message de succès
                    $success = "Inscription réussie ! Un email de bienvenue a été envoyé à votre adresse.<br>";
                } catch (Exception $e) {
                    // Ne pas interrompre le processus d'inscription même si l'email échoue
                    error_log("Erreur d'envoi d'email: " . $e->getMessage());
                    $success = "Inscription réussie ! (Erreur d'envoi d'email, veuillez contacter le support)";
                }

                // Vider les champs après succès
                $nom = $prenom = $email = '';
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'inscription. Veuillez réessayer plus tard.';
            error_log("Erreur inscription: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Cinéphoria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background: linear-gradient(to right, #1a2a3a, #2c3e50);
            color: #d4af37;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo h1 {
            margin: 0;
            font-size: 28px;
            color: #d4af37;
            font-weight: bold;
        }

        nav a {
            color: #e0e0e0;
            margin-left: 20px;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        nav a:hover {
            background-color: #d4af37;
            color: #1a2a3a;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .register-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #d4af37;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .btn-register {
            width: 100%;
            padding: 12px;
            background-color: #d4af37;
            color: #1a2a3a;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-register:hover {
            background-color: #c19b2e;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .login-link a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            text-align: left;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        footer {
            background: #2c3e50;
            color: #e0e0e0;
            padding: 25px 0;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            justify-content: space-around;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
        }

        .footer-section {
            flex: 1;
            padding: 0 15px;
        }

        .footer-section h3 {
            color: #d4af37;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .footer-section p {
            font-size: 14px;
            line-height: 1.5;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #3a506b;
            margin-top: 20px;
            color: #b0b0b0;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .register-container {
                margin: 20px 15px;
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .footer-content {
                flex-direction: column;
            }

            .footer-section {
                margin-bottom: 20px;
            }

            header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }

            nav {
                margin-top: 15px;
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }

            nav a {
                margin: 5px;
            }

            .login-link {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <header>
        <a href="index.php" class="logo">
            <h1>Cinéphoria</h1>
        </a>
        <nav>
            <a href="index.php">🏠 Accueil</a>
            <a href="films.php">🎭 Films</a>
            <a href="reserver.php">🎫 Ma Réservation</a>
        </nav>
    </header>

    <div class="main-content">
        <div class="register-container">
            <h2 class="register-title">📝 Créer un compte</h2>

            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message success"><?= $success ?></div>
                <div class="login-link">
                    <p>Vous pouvez maintenant <a href="login.php">vous connecter</a>.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="inscription.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prenom">Prénom :</label>
                            <input type="text" id="prenom" name="prenom" required
                                placeholder="Votre prénom"
                                autocomplete="given-name"
                                value="<?= htmlspecialchars($prenom ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="nom">Nom :</label>
                            <input type="text" id="nom" name="nom" required
                                placeholder="Votre nom"
                                autocomplete="family-name"
                                value="<?= htmlspecialchars($nom ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse email :</label>
                        <input type="email" id="email" name="email" required
                            placeholder="votre.email@exemple.com"
                            autocomplete="email"
                            value="<?= htmlspecialchars($email ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe :</label>
                        <input type="password" id="password" name="password" required
                            placeholder="Choisissez un mot de passe sécurisé"
                            autocomplete="new-password">
                        <div class="password-requirements">
                            • Minimum 8 caractères recommandés
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe :</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                            placeholder="Répétez votre mot de passe"
                            autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-register">
                        Créer mon compte
                    </button>
                </form>

                <div class="login-link">
                    <p>Déjà inscrit ? <a href="login.php">Se connecter</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>À propos de Cinéphoria</h3>
                <p>Cinéphoria est votre cinéma de référence pour découvrir les dernières sorties et les classiques du cinéma dans un cadre exceptionnel.</p>
            </div>
            <div class="footer-section">
                <h3>Nos horaires</h3>
                <p>Lundi au vendredi: 14h - 23h<br>
                    Samedi et dimanche: 12h - 00h</p>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>123 Avenue du Cinéma<br>
                    75000 Paris<br>
                    contact@cinephoria.fr<br>
                    01 23 45 67 89</p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date("Y") ?> Cinéphoria. Tous droits réservés.
        </div>
    </footer>
</body>

</html>