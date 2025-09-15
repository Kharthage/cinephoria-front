<?php
session_start();
require 'testpdo.php';

$message = '';
$success = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = 'Token de confirmation manquant.';
} else {
    try {
        // Vérifier si le token existe et récupérer les informations utilisateur
        $stmt = $pdo->prepare("SELECT id, email, prenom, nom, email_confirme FROM utilisateur WHERE confirmation_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['email_confirme'] == 1) {
                $message = 'Votre adresse email a déjà été confirmée.';
                $success = true;
            } else {
                // Confirmer l'email
                $stmt = $pdo->prepare("UPDATE utilisateur SET email_confirme = 1, confirmation_token = '' WHERE confirmation_token = ?");
                $stmt->execute([$token]);

                $message = 'Félicitations ! Votre adresse email a été confirmée avec succès. Vous pouvez maintenant vous connecter.';
                $success = true;
            }
        } else {
            $message = 'Token de confirmation invalide ou expiré.';
        }
    } catch (PDOException $e) {
        $message = 'Erreur lors de la confirmation de l\'email. Veuillez réessayer plus tard.';
        error_log("Erreur confirmation email: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d'email - Cinephoria</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        :root {
            --primary-color: #1b1f3b;
            --accent-color: #f4c842;
            --bg-color: #ffffff;
            --text-color: #333333;
            --header-bg: #1b1f3b;
            --success-color: #28a745;
            --error-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: var(--header-bg);
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        header h1 {
            color: var(--accent-color);
            font-size: 2.5rem;
            margin-bottom: 5px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .tagline {
            color: #ffffff;
            font-style: italic;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        main {
            flex: 1;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.6s ease-out;
            text-align: center;
        }

        .card h2 {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-size: 1.8rem;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .success-icon {
            color: var(--success-color);
        }

        .error-icon {
            color: var(--error-color);
        }

        .message {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            color: var(--text-color);
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-width: 200px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color) 0%, #e0b935 100%);
            color: var(--primary-color);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(244, 200, 66, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        footer {
            text-align: center;
            padding: 25px;
            background-color: var(--header-bg);
            color: white;
            font-size: 0.95rem;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .card {
                padding: 30px;
                margin: 20px;
            }

            header h1 {
                font-size: 2rem;
            }

            .status-icon {
                font-size: 3rem;
            }
        }

        @media (max-width: 480px) {
            .card {
                padding: 25px 20px;
            }

            .btn {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <header>
        <h1>🎬 Cinephoria</h1>
        <p class="tagline">Confirmation de votre adresse email</p>
    </header>

    <main>
        <div class="card">
            <h2>Confirmation d'email</h2>

            <div class="status-icon <?= $success ? 'success-icon' : 'error-icon' ?>">
                <?= $success ? '✅' : '❌' ?>
            </div>

            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>

            <div class="actions">
                <?php if ($success): ?>
                    <a href="login.php" class="btn btn-primary">
                        Se connecter maintenant
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        Retour à l'accueil
                    </a>
                <?php else: ?>
                    <a href="inscription.php" class="btn btn-primary">
                        Nouvelle inscription
                    </a>
                    <a href="login.php" class="btn btn-secondary">
                        Retour à la connexion
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>© 2025 Cinephoria. Tous droits réservés. 🎬</p>
    </footer>
</body>

</html>