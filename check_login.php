<?php
session_start();
require_once 'testpdo.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $message = 'Veuillez remplir tous les champs.';
        $messageType = 'danger';
    } else {
        // Vérifier les identifiants
        $stmt = $pdo->prepare("SELECT id, nom, prenom, email, mot_de_passe, role, changer_mdp, mot_de_passe_temporaire FROM utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Vérifier si l'utilisateur doit changer son mot de passe
            if ($user['changer_mdp'] == 1 || $user['mot_de_passe_temporaire'] == 1) {
                // Rediriger vers une page de changement de mot de passe obligatoire
                $_SESSION['force_password_change'] = true;
                header('Location: changer_mot_de_passe_obligatoire.php');
                exit();
            }
            
            // Connexion normale - rediriger selon le rôle
            switch($user['role']) {
                case 'admin':
                    header('Location: admin_dashboard.php');
                    break;
                case 'employe':
                    header('Location: employe_dashboard.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit();
            
        } else {
            $message = 'Email ou mot de passe incorrect.';
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
    <title>Connexion - Cinéphoria</title>
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
            max-width: 400px;
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

        .forgot-password {
            text-align: center;
            margin: 15px 0;
        }

        .forgot-password a {
            color: var(--gold);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password a:hover {
            color: var(--dark-blue);
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }

        .divider span {
            padding: 0 10px;
            color: #6c757d;
            font-size: 0.9rem;
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

        <h2 class="form-title">Connexion</h2>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="votre.email@exemple.com" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Votre mot de passe" required>
            </div>

            <button type="submit" class="btn btn-cinephoria w-100">
                <i class="fas fa-sign-in-alt me-2"></i>Se connecter
            </button>
        </form>

        <div class="forgot-password">
            <a href="mot_de_passe_oublie.php">
                <i class="fas fa-key me-1"></i>Mot de passe oublié ?
            </a>
        </div>

        <div class="divider">
            <span>ou</span>
        </div>

        <div class="card-footer">
            <p class="mb-2">Vous n'avez pas de compte ?</p>
            <a href="register.php" class="btn btn-outline-gold">
                <i class="fas fa-user-plus me-2"></i>Créer un compte
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>