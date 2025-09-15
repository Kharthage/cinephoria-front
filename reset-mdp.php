<?php
session_start();
require_once 'testpdo.php';

$message = '';
$messageType = '';
$show_form = true;
$user_info = '';

// Vérifier que le token est présent
if (!isset($_GET['token'])) {
    $message = 'Token manquant. Veuillez utiliser le lien reçu par email.';
    $messageType = 'danger';
    $show_form = false;
} else {
    $token = $_GET['token'];
    
    // Vérifier le token en base
    $stmt = $pdo->prepare("
        SELECT rm.*, u.prenom, u.nom, u.email 
        FROM reset_mdp rm 
        JOIN utilisateur u ON rm.id_utilisateur = u.id 
        WHERE rm.token = ?
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $message = 'Token invalide. Veuillez faire une nouvelle demande de réinitialisation.';
        $messageType = 'danger';
        $show_form = false;
    } elseif (strtotime($reset['date_expiration']) < time()) {
        // Supprimer le token expiré
        $stmt = $pdo->prepare("DELETE FROM reset_mdp WHERE token = ?");
        $stmt->execute([$token]);
        
        $message = 'Ce lien a expiré. Veuillez faire une nouvelle demande de réinitialisation.';
        $messageType = 'warning';
        $show_form = false;
    } else {
        $user_info = 'Réinitialisation du mot de passe pour ' . htmlspecialchars($reset['prenom'] . ' ' . $reset['nom']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $message = 'Veuillez remplir tous les champs.';
        $messageType = 'danger';
    } elseif (strlen($password) < 8) {
        $message = 'Le mot de passe doit contenir au moins 8 caractères.';
        $messageType = 'danger';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        $message = 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.';
        $messageType = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = 'Les mots de passe ne correspondent pas.';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Mettre à jour le mot de passe
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ?, changer_mdp = 0 WHERE id = ?");
            $stmt->execute([$hash, $reset['id_utilisateur']]);
            
            // Supprimer le token pour éviter réutilisation
            $stmt = $pdo->prepare("DELETE FROM reset_mdp WHERE token = ?");
            $stmt->execute([$token]);
            
            $pdo->commit();
            
            $message = 'Votre mot de passe a été réinitialisé avec succès ! Vous pouvez maintenant vous connecter.';
            $messageType = 'success';
            $show_form = false;
            
            // Redirection automatique après 3 secondes
            header("Refresh: 3; URL=login.php");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Une erreur est survenue. Veuillez réessayer.';
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe - Cinéphoria</title>
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
            margin-bottom: 30px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .form-title i {
            color: var(--gold);
            font-size: 1.8rem;
            margin-right: 10px;
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

        .btn-outline-gold {
            color: var(--gold);
            border-color: var(--gold);
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-outline-gold:hover {
            background-color: var(--gold);
            color: white;
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

        .card-footer {
            background: transparent;
            border-top: 1px solid #e9ecef;
            text-align: center;
            padding: 20px 0 0;
            margin-top: 20px;
        }

        .link-gold {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
        }

        .link-gold:hover {
            color: var(--dark-blue);
            text-decoration: underline;
        }

        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 3px;
            transition: all 0.3s ease;
            background-color: #e9ecef;
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

        <h2 class="form-title"><i class="fas fa-key"></i> Nouveau mot de passe</h2>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
            <?php echo $message; ?>
            <?php if ($messageType === 'success'): ?>
                <div class="mt-2">
                    <small><i class="fas fa-clock me-1"></i>Redirection automatique dans 3 secondes...</small>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($user_info): ?>
        <div class="alert alert-info" role="alert">
            <i class="fas fa-user me-2"></i>
            <?php echo $user_info; ?>
        </div>
        <?php endif; ?>

        <?php if ($show_form): ?>
        <form method="post" id="password-reset-form">
            <div class="mb-3">
                <label for="password" class="form-label">Nouveau mot de passe</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <span class="password-toggle" onclick="togglePassword('password', this)">
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
                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <span class="password-toggle" onclick="togglePassword('confirm_password', this)">
                        <i class="far fa-eye"></i>
                    </span>
                </div>
                <small id="confirm-feedback" class="form-text text-danger"></small>
            </div>

            <button type="submit" class="btn btn-cinephoria w-100 mb-3" id="submit-button">
                <i class="fas fa-lock me-2"></i>Réinitialiser le mot de passe
            </button>
        </form>
        <?php endif; ?>

        <div class="card-footer">
            <?php if ($messageType === 'success'): ?>
                <p class="mb-0">Vous allez être redirigé vers la <a href="login.php" class="link-gold">page de connexion</a></p>
            <?php elseif (!$show_form && $messageType !== 'success'): ?>
                <a href="mot_de_passe_oublie.php" class="btn btn-outline-gold">
                    <i class="fas fa-redo me-2"></i>Nouvelle demande
                </a>
            <?php else: ?>
                <p class="mb-0">Revenir à la <a href="login.php" class="link-gold">page de connexion</a></p>
            <?php endif; ?>
        </div>
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
            
            switch(strength) {
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
            
            return strength >= 4; // Au moins moyen
        }

        // Valider que les mots de passe correspondent
        function validatePasswords() {
            const password = document.getElementById('password').value;
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
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (passwordInput) {
                // Écouter les changements sur le mot de passe
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    validatePasswords();
                });
                
                // Écouter les changements sur la confirmation
                confirmPasswordInput.addEventListener('input', validatePasswords);
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>