<?php
session_start();
require_once 'testpdo.php';

// Vérifier que l'utilisateur est connecté et doit changer son mot de passe
if (!isset($_SESSION['user_id']) || !isset($_SESSION['changer_mdp'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Veuillez remplir tous les champs.';
        $messageType = 'danger';
    } elseif (strlen($new_password) < 8) {
        $message = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        $messageType = 'danger';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
        $message = 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.';
        $messageType = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Les nouveaux mots de passe ne correspondent pas.';
        $messageType = 'danger';
    } else {
        // Vérifier l'ancien mot de passe
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateur WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['mot_de_passe'])) {
            $message = 'Mot de passe actuel incorrect.';
            $messageType = 'danger';
        } else {
            try {
                // Mettre à jour le mot de passe et réinitialiser les flags
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ?, changer_mdp = 0, mdp_temp_expire = NULL WHERE id = ?");
                $stmt->execute([$new_hash, $_SESSION['user_id']]);

                $message = 'Votre mot de passe a été changé avec succès !';
                $messageType = 'success';

                // Supprimer le flag de changement forcé
                unset($_SESSION['changer_mdp']);

                // Redirection automatique après 2 secondes
                header("Refresh: 2; URL=index.php");
            } catch (Exception $e) {
                $message = 'Une erreur est survenue. Veuillez réessayer.';
                $messageType = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changement de mot de passe obligatoire - Cinéphoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1a4f8c;
            --light-blue: #3498db;
            --gold: #d4af37;
            --light-gold: #f1c40f;
            --dark-blue: #0a2c4e;
        }

        body {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .cinephoria-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            padding: 35px;
            width: 100%;
            max-width: 500px;
            border-top: 4px solid var(--gold);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo h1 {
            color: var(--primary-blue);
            font-weight: 700;
            font-size: 2.5rem;
            margin: 0;
            letter-spacing: 1px;
        }

        .logo span {
            color: var(--gold);
        }

        .form-title {
            text-align: center;
            margin-bottom: 20px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .form-title i {
            color: var(--gold);
            font-size: 1.8rem;
            margin-right: 10px;
        }

        .warning-notice {
            background: linear-gradient(135deg, #fff3cd 0%, #fef7d0 100%);
            border: 2px solid #f0c674;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            color: #856404;
        }

        .warning-notice i {
            color: #f0ad4e;
            margin-right: 8px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .btn-cinephoria {
            background: linear-gradient(to right, var(--primary-blue) 0%, var(--light-blue) 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-cinephoria:hover {
            background: linear-gradient(to right, var(--dark-blue) 0%, var(--primary-blue) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26, 79, 140, 0.4);
            color: white;
        }

        .btn-secondary {
            color: #6c757d;
            border-color: #6c757d;
            border-radius: 8px;
        }

        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .password-rules {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 3px;
            transition: all 0.3s ease;
            background-color: #e9ecef;
        }

        .user-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            color: #1565c0;
        }

        @media (max-width: 576px) {
            .cinephoria-card {
                padding: 25px;
            }

            .logo h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="cinephoria-card">
        <div class="logo">
            <h1>CINÉ<span>PHORIA</span></h1>
        </div>

        <h2 class="form-title"><i class="fas fa-shield-alt"></i> Changement obligatoire</h2>

        <div class="user-info">
            <i class="fas fa-user me-2"></i>
            Connecté en tant que : <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
        </div>

        <div class="warning-notice">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Action requise :</strong> Vous devez changer votre mot de passe pour continuer à utiliser votre compte.
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                <?php echo $message; ?>
                <?php if ($messageType === 'success'): ?>
                    <div class="mt-2">
                        <small><i class="fas fa-clock me-1"></i>Redirection automatique dans 2 secondes...</small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($messageType !== 'success'): ?>
            <form method="post" id="password-change-form">
                <div class="mb-3">
                    <label for="current_password" class="form-label">Mot de passe temporaire</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <span class="password-toggle" onclick="togglePassword('current_password', this)">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <span class="password-toggle" onclick="togglePassword('new_password', this)">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-rules">
                        Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.
                    </div>
                    <div class="password-strength" id="password-strength"></div>
                    <small id="password-feedback" class="form-text"></small>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <span class="password-toggle" onclick="togglePassword('confirm_password', this)">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    <small id="confirm-feedback" class="form-text text-danger"></small>
                </div>

                <div class="row">
                    <div class="col">
                        <button type="submit" class="btn btn-cinephoria w-100">
                            <i class="fas fa-lock me-2"></i>Changer le mot de passe
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-3 text-center">
                <a href="logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt me-2"></i>Se déconnecter
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Fonctionnalité pour afficher/masquer les mots de passe
        function togglePassword(inputId, element) {
            const passwordInput = document.getElementById(inputId);
            const icon = element.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Vérifier la force du mot de passe
        function checkPasswordStrength(password) {
            let strength = 0;
            const feedback = document.getElementById('password-feedback');
            const strengthBar = document.getElementById('password-strength');

            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            let strengthText = '';
            let strengthColor = '';

            switch (strength) {
                case 0:
                case 1:
                case 2:
                    strengthText = 'Faible';
                    strengthColor = '#dc3545';
                    break;
                case 3:
                case 4:
                    strengthText = 'Moyen';
                    strengthColor = '#ffc107';
                    break;
                case 5:
                    strengthText = 'Fort';
                    strengthColor = '#28a745';
                    break;
            }

            feedback.textContent = `Force du mot de passe: ${strengthText}`;
            feedback.style.color = strengthColor;
            strengthBar.style.width = `${strength * 20}%`;
            strengthBar.style.backgroundColor = strengthColor;

            return strength >= 4;
        }

        // Valider que les mots de passe correspondent
        function validatePasswords() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmFeedback = document.getElementById('confirm-feedback');

            if (password !== confirmPassword && confirmPassword.length > 0) {
                confirmFeedback.textContent = 'Les mots de passe ne correspondent pas.';
                return false;
            } else {
                confirmFeedback.textContent = '';
                return true;
            }
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    validatePasswords();
                });

                confirmPasswordInput.addEventListener('input', validatePasswords);
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>