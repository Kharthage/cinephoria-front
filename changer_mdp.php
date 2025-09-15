<?php
session_start();
require_once 'config/api.php';

// Vérifier si le token est présent dans l'URL
if (!isset($_GET['token'])) {
    header('Location: mdp_oublie.php');
    exit;
}

$token = $_GET['token'];
$error = '';
$success = '';

// Vérifier la validité du token
try {
    $stmt = $pdo->prepare("SELECT * FROM reset_tokens WHERE token = ? AND used = 0 AND expiration > NOW()");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        $error = "Ce lien de réinitialisation est invalide ou a expiré.";
    } else {
        // Récupérer les informations de l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id = ?");
        $stmt->execute([$tokenData['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Utilisateur non trouvé.";
        }
    }
} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation des mots de passe
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        try {
            // Hasher le nouveau mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Mettre à jour le mot de passe de l'utilisateur
            $stmt = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $tokenData['user_id']]);

            // Marquer le token comme utilisé
            $stmt = $pdo->prepare("UPDATE reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            $success = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
        } catch (PDOException $e) {
            $error = "Erreur de base de données: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer mon mot de passe - Cinéphoria</title>
    <style>
        :root {
            --primary: #0D0D15;
            --secondary: #1A1A2E;
            --accent: #E50914;
            --text: #FFFFFF;
            --text-secondary: #B8B8B8;
            --success: #4CAF50;
            --error: #F44336;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--primary);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-image:
                radial-gradient(circle at 15% 50%, rgba(29, 29, 49, 0.6) 0%, transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(29, 29, 49, 0.6) 0%, transparent 25%);
        }

        .container {
            width: 100%;
            max-width: 500px;
            background: linear-gradient(135deg, var(--secondary) 0%, #16213E 100%);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .header::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: var(--accent);
            margin: 1rem auto;
            border-radius: 2px;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(to right, #fff, var(--accent));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 500;
            color: var(--text);
        }

        input {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.2);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 42px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
        }

        button {
            background: linear-gradient(135deg, var(--accent) 0%, #B80712 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: block;
            margin: 2rem auto;
            width: 100%;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .status {
            padding: 1.2rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            text-align: center;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .success {
            background: rgba(76, 175, 80, 0.15);
            color: var(--success);
            border-color: rgba(76, 175, 80, 0.3);
        }

        .error {
            background: rgba(244, 67, 54, 0.15);
            color: var(--error);
            border-color: rgba(244, 67, 54, 0.3);
        }

        .links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .links a {
            color: var(--accent);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #fff;
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 0.5rem;
            height: 5px;
            border-radius: 3px;
            background: #333;
            position: relative;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 3px;
            transition: width 0.3s, background 0.3s;
        }

        .password-strength-text {
            font-size: 0.8rem;
            margin-top: 0.3rem;
            text-align: right;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            h1 {
                font-size: 2rem;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Nouveau mot de passe</h1>
            <p>Créez un nouveau mot de passe pour votre compte</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="status error">
                <?php echo $error; ?>
            </div>
            <div class="links">
                <a href="mdp_oublie.php">Demander un nouveau lien</a>
            </div>
        <?php elseif (!empty($success)): ?>
            <div class="status success">
                <?php echo $success; ?>
            </div>
            <div class="links">
                <a href="connexion.php">Se connecter</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">👁️</button>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="password-strength-bar"></div>
                    </div>
                    <div class="password-strength-text" id="password-strength-text"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">👁️</button>
                    <div id="password-match" style="font-size: 0.8rem; margin-top: 0.3rem; text-align: right;"></div>
                </div>

                <button type="submit" name="change_password">Changer mon mot de passe</button>
            </form>

            <div class="links">
                <a href="connexion.php">Retour à la connexion</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Fonction pour afficher/masquer le mot de passe
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggleButton = input.nextElementSibling;

            if (input.type === 'password') {
                input.type = 'text';
                toggleButton.textContent = '🔒';
            } else {
                input.type = 'password';
                toggleButton.textContent = '👁️';
            }
        }

        // Vérification de la force du mot de passe
        const passwordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let message = '';

            // Vérification de la longueur
            if (password.length >= 8) strength += 25;

            // Vérification des minuscules et majuscules
            if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 25;

            // Vérification des chiffres
            if (password.match(/([0-9])/)) strength += 25;

            // Vérification des caractères spéciaux
            if (password.match(/([!,@,#,$,%,^,&,*,?,_,~])/)) strength += 25;

            // Mise à jour de la barre et du texte
            strengthBar.style.width = strength + '%';

            if (strength === 0) {
                strengthBar.style.background = 'transparent';
                message = '';
            } else if (strength < 50) {
                strengthBar.style.background = '#F44336';
                message = 'Faible';
            } else if (strength < 75) {
                strengthBar.style.background = '#FF9800';
                message = 'Moyen';
            } else {
                strengthBar.style.background = '#4CAF50';
                message = 'Fort';
            }

            strengthText.textContent = message;
        });

        // Vérification de la correspondance des mots de passe
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('password-match');

        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;

            if (confirmPassword === '') {
                passwordMatch.textContent = '';
                passwordMatch.style.color = '';
            } else if (password === confirmPassword) {
                passwordMatch.textContent = '✓ Les mots de passe correspondent';
                passwordMatch.style.color = '#4CAF50';
            } else {
                passwordMatch.textContent = '✗ Les mots de passe ne correspondent pas';
                passwordMatch.style.color = '#F44336';
            }
        });
    </script>
</body>

</html>