<?php
session_start();
require 'testpdo.php';
require_once 'lib/EmailSender.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Veuillez entrer une adresse email valide.';
        $messageType = 'danger';
    } else {
        try {
            // Vérifier si l'email existe
            $stmt = $pdo->prepare("SELECT id, nom, prenom FROM utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Générer un mot de passe temporaire
                $tempPassword = generateSecureTempPassword();
                $hashedTempPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                // Mettre à jour le mot de passe et marquer comme temporaire
                $stmt = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ?, changer_mdp = 1, mdp_temp_expire = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?");
                $stmt->execute([$hashedTempPassword, $user['id']]);

                // Essayer d'envoyer l'email
                if (sendTempPasswordEmail($email, $user['prenom'], $user['nom'], $tempPassword)) {
                    $message = "Un mot de passe temporaire a été envoyé à votre adresse email. Il expire dans 24 heures.";
                    $messageType = 'success';
                } else {
                    $message = "Erreur lors de l'envoi de l'email. Vérifiez la configuration du serveur mail.";
                    $messageType = 'danger';
                }
            } else {
                // Message générique pour la sécurité
                $message = "Si cette adresse email est associée à un compte, vous recevrez un mot de passe temporaire par email.";
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            error_log("Erreur récupération mot de passe: " . $e->getMessage());
            $message = "Une erreur technique est survenue. Veuillez réessayer plus tard.";
            $messageType = 'danger';
        }
    }
}

function generateSecureTempPassword($length = 12)
{
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';

    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];

    $allChars = $uppercase . $lowercase . $numbers . $symbols;

    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }

    return str_shuffle($password);
}

function sendTempPasswordEmail($email, $prenom, $nom, $tempPassword)
{
    $subject = 'Cinephoria - Mot de passe temporaire';

    $message = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #ddd; }
            .password-box { 
                background: #f8f9fa; 
                border: 2px solid #2c3e50; 
                padding: 20px; 
                margin: 20px 0; 
                text-align: center; 
                border-radius: 5px;
            }
            .password { 
                font-size: 24px; 
                font-weight: bold; 
                color: #2c3e50; 
                letter-spacing: 3px; 
                font-family: monospace; 
            }
            .warning { 
                background: #fff3cd; 
                padding: 15px; 
                margin: 20px 0; 
                border-left: 4px solid #ffc107; 
                border-radius: 0 5px 5px 0;
            }
            .button {
                display: inline-block;
                background: #2c3e50;
                color: white !important;
                padding: 12px 25px;
                text-decoration: none;
                border-radius: 5px;
                margin: 15px 0;
            }
            .footer { 
                text-align: center; 
                color: #666; 
                font-size: 12px; 
                margin-top: 30px; 
                padding: 20px;
                background: #f8f9fa;
                border-radius: 0 0 10px 10px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Cinéphoria</h1>
                <p>Récupération de mot de passe</p>
            </div>
            
            <div class='content'>
                <h2>Bonjour " . htmlspecialchars($prenom . ' ' . $nom) . ",</h2>
                
                <p>Vous avez demandé la réinitialisation de votre mot de passe sur Cinéphoria.</p>
                
                <div class='password-box'>
                    <p><strong>Votre mot de passe temporaire :</strong></p>
                    <div class='password'>" . htmlspecialchars($tempPassword) . "</div>
                </div>
                
                <div class='warning'>
                    <h3>⚠️ Important :</h3>
                    <ul>
                        <li><strong>Ce mot de passe expire dans 24 heures</strong></li>
                        <li><strong>Vous DEVEZ le changer à votre prochaine connexion</strong></li>
                        <li>Ne partagez jamais ce mot de passe avec personne</li>
                        <li>Ce mot de passe ne peut être utilisé qu'une seule fois</li>
                    </ul>
                </div>
                
                <h3>Comment vous connecter :</h3>
                <ol>
                    <li>Cliquez sur le bouton ci-dessous</li>
                    <li>Connectez-vous avec votre email : <strong>" . htmlspecialchars($email) . "</strong></li>
                    <li>Utilisez le mot de passe temporaire ci-dessus</li>
                    <li>Choisissez immédiatement un nouveau mot de passe sécurisé</li>
                </ol>
                
                <p style='text-align: center;'>
                    <a href='http://localhost/cinephoria-front/login.php' class='button'>
                        Se connecter maintenant
                    </a>
                </p>
                
                <p><small><em>Si vous n'avez pas demandé cette réinitialisation, ignorez ce message. Votre compte reste sécurisé.</em></small></p>
            </div>
            
            <div class='footer'>
                <p>Cet email a été envoyé automatiquement depuis Cinéphoria.</p>
                <p>&copy; " . date('Y') . " Cinéphoria - Système de réservation de cinéma</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Headers pour email HTML
    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Cinéphoria <noreply@cinephoria.com>',
        'Reply-To: noreply@cinephoria.com',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1'
    );

    // Utiliser la fonction mail() de PHP
    $emailSender = new EmailSender();
    $result = $emailSender->sendEmail($email, $prenom, $subject, $message);
    if ($result) {
        echo "mail envoyé avec succés";
    } else {
        echo ("Échec de l'envoi de l'email à $email");
    }
    return $result;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Cinéphoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a2a3a;
            --primary: #2c3e50;
            --accent: #d4af37;
            --light-bg: #f8f9fa;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }

        .reset-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .reset-header {
            background: linear-gradient(45deg, var(--primary-dark), var(--primary));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .reset-header h1 {
            font-weight: 600;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .reset-header .lead {
            opacity: 0.9;
            margin-bottom: 0;
        }

        .reset-body {
            padding: 30px;
        }

        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .alert-danger {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .alert-icon {
            margin-right: 15px;
            font-size: 18px;
        }

        .alert-content {
            flex: 1;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }

        .input-group .form-control {
            padding-left: 45px;
        }

        .input-group-text {
            background: transparent;
            border: 1px solid #ddd;
            border-right: none;
            color: var(--primary);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-dark), var(--primary));
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .security-info {
            margin-top: 25px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            font-size: 14px;
        }

        .security-info h5 {
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 600;
        }

        footer {
            background-color: var(--primary-dark);
            color: white;
            text-align: center;
            padding: 20px 0;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-dark);">
        <div class="container">
            <a class="navbar-brand" href="index.php">🎬 Cinéphoria</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="films.php">Films</a>
                <a class="nav-link" href="login.php">Connexion</a>
                <a class="nav-link" href="inscription.php">Inscription</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <div class="reset-container">
                <div class="reset-card">
                    <div class="reset-header">
                        <h1><i class="fas fa-key"></i> Mot de passe oublié</h1>
                        <p class="lead">Récupération par email</p>
                    </div>

                    <div class="reset-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
                                <div class="alert-icon">
                                    <?php if ($messageType === 'success'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="alert-content">
                                    <?= htmlspecialchars($message) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($messageType !== 'success'): ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i> Adresse email
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email"
                                            placeholder="votre@email.com" required
                                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Envoyer le mot de passe par email
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <a href="login.php" class="back-link">
                            <i class="fas fa-arrow-left"></i> Retour à la connexion
                        </a>

                        <div class="security-info">
                            <h5><i class="fas fa-shield-alt"></i> Informations importantes</h5>
                            <ul>
                                <li><strong>Vérifiez votre boîte email</strong> (et les spams)</li>
                                <li><strong>Le mot de passe expire dans 24h</strong></li>
                                <li><strong>Changement obligatoire</strong> à la première connexion</li>
                                <li><strong>Un seul usage</strong> du mot de passe temporaire</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2025 Cinéphoria. Tous droits réservés.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>