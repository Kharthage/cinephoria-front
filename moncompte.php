<?php
session_start();
require 'testpdo.php';

// Rediriger vers la connexion si non connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$error = '';
$success = '';

// Traitement de la modification des informations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['modifier_infos'])) {
        // Modification des informations personnelles
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($nom) || empty($prenom) || empty($email)) {
            $error = 'Tous les champs sont obligatoires.';
        } else {
            try {
                // Vérifier si l'email est déjà utilisé par un autre utilisateur
                $check_email = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = ? AND id != ?");
                $check_email->execute([$email, $user['id']]);

                if ($check_email->fetchColumn() > 0) {
                    $error = 'Cet email est déjà utilisé par un autre compte.';
                } else {
                    // Mettre à jour les informations
                    $sql = "UPDATE utilisateur SET nom = ?, prenom = ?, email = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nom, $prenom, $email, $user['id']]);

                    // Mettre à jour la session
                    $_SESSION['user']['nom'] = $nom;
                    $_SESSION['user']['prenom'] = $prenom;
                    $_SESSION['user']['email'] = $email;

                    $success = 'Vos informations ont été mises à jour avec succès.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['changer_mdp'])) {
        // Changement de mot de passe
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Tous les champs sont obligatoires.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
        } else {
            try {
                // Vérifier le mot de passe actuel
                $check_password = $pdo->prepare("SELECT mot_de_passe FROM utilisateur WHERE id = ?");
                $check_password->execute([$user['id']]);
                $user_data = $check_password->fetch();

                // Adaptation pour votre système (mots de passe en clair)
                if ($user_data && password_verify($current_password, $user_data['mot_de_passe'])) {
                    // Mettre à jour le mot de passe (vous pourriez vouloir hasher ici)
                    $sql = "UPDATE utilisateur SET mot_de_passe = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt->execute([$hashed_password, $user['id']]);

                    $success = 'Votre mot de passe a été changé avec succès.';
                } else {
                    $error = 'Le mot de passe actuel est incorrect.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors du changement de mot de passe : ' . $e->getMessage();
            }
        }
    }
}

// Récupérer les réservations de l'utilisateur
$reservations = [];

try {
    // Requête simplifiée pour récupérer les commandes
    $sql = "
        SELECT 
            c.id, 
            c.id_utilisateur, 
            c.id_seance, 
            c.date_commande, 
            c.prix_total, 
            c.code_reservation, 
            c.numero_reservation,
            s.debut as seance_debut,
            s.fin as seance_fin,
            s.prix as prix_seance,
            f.titre as film_titre,
            sa.reference as salle_nom
        FROM commande c
        LEFT JOIN seance s ON c.id_seance = s.id
        LEFT JOIN film f ON s.id_film = f.id
        LEFT JOIN salle sa ON s.id_salle = sa.id
        WHERE c.id_utilisateur = ?
        ORDER BY c.date_commande DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
    $reservations = $stmt->fetchAll();

    // Pour chaque réservation, récupérer les détails des places
    foreach ($reservations as &$reservation) {
        $sql_places = "
            SELECT p.reference, p.est_pmr
            FROM billet b
            JOIN place p ON b.id_place = p.id
            WHERE b.id_commande = ?
            ORDER BY p.reference
        ";
        $stmt_places = $pdo->prepare($sql_places);
        $stmt_places->execute([$reservation['id']]);
        $reservation['places'] = $stmt_places->fetchAll();

        // Compter le nombre de places
        $reservation['nombre_places'] = count($reservation['places']);
    }
} catch (PDOException $e) {
    error_log("Erreur récupération réservations: " . $e->getMessage());
    $reservations = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte - Cinéphoria</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
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

        nav a:hover,
        nav a.active {
            background-color: #d4af37;
            color: #1a2a3a;
        }

        .account-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .account-header {
            background: linear-gradient(45deg, #1a2a3a, #2c3e50);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .account-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs {
            display: flex;
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .nav-tabs li {
            margin-right: 10px;
        }

        .nav-tabs button {
            background: none;
            border: none;
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 600;
            color: #2c3e50;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
        }

        .nav-tabs button.active {
            background: #2c3e50;
            color: white;
        }

        .nav-tabs button:hover {
            background: #d4af37;
            color: #1a2a3a;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

        .row {
            display: flex;
            gap: 20px;
        }

        .col-md-6 {
            flex: 1;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #d4af37;
            color: #1a2a3a;
        }

        .btn-primary:hover {
            background-color: #c19b2e;
        }

        .btn-outline-primary {
            background-color: transparent;
            color: #d4af37;
            border: 2px solid #d4af37;
        }

        .btn-outline-primary:hover {
            background-color: #d4af37;
            color: #1a2a3a;
        }

        .reservation-item {
            border-left: 4px solid #d4af37;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .reservation-details {
            flex: 2;
        }

        .reservation-qr {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }

        .qr-container {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 180px;
            min-width: 180px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d1edff;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            background: #28a745;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-secondary {
            background: #6c757d;
        }

        .badge-warning {
            background: #ffc107;
            color: #212529;
        }

        .places-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .place-tag {
            background: #d4af37;
            color: #1a2a3a;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .place-tag.pmr {
            background: #ffc107;
        }

        .reservation-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .info-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #d4af37;
        }

        .info-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .info-value {
            color: #666;
        }

        footer {
            background: #2c3e50;
            color: #e0e0e0;
            padding: 25px 0;
            margin-top: 50px;
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

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #3a506b;
            margin-top: 20px;
            color: #b0b0b0;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .row {
                flex-direction: column;
            }

            .nav-tabs {
                flex-direction: column;
            }

            .nav-tabs li {
                margin-right: 0;
                margin-bottom: 5px;
            }

            .reservation-info-grid {
                grid-template-columns: 1fr;
            }
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .reservation-title {
            margin: 0;
            color: #2c3e50;
        }

        .reservation-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .no-reservations {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-reservations i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
            color: #d4af37;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
            color: #666;
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
            <a href="moncompte.php" class="active">👤 Mon Compte</a>
            <a href="logout.php">🚪 Déconnexion</a>
        </nav>
    </header>

    <div class="account-container">
        <div class="account-header">
            <h1>👤 Mon Compte</h1>
            <p>Bienvenue, <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <ul class="nav-tabs" id="accountTabs">
            <li>
                <button class="nav-link active" onclick="showTab('infos')">
                    👤 Informations personnelles
                </button>
            </li>
            <li>
                <button class="nav-link" onclick="showTab('password')">
                    🔒 Mot de passe
                </button>
            </li>
            <li>
                <button class="nav-link" onclick="showTab('reservations')">
                    🎫 Mes réservations (<?= count($reservations) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content active" id="infos">
            <div class="account-card">
                <h4>✏️ Modifier mes informations</h4>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nom</label>
                                <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Prénom</label>
                                <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <button type="submit" name="modifier_infos" class="btn btn-primary">
                        💾 Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>

        <div class="tab-content" id="password">
            <div class="account-card">
                <h4>🔑 Changer mon mot de passe</h4>
                <form method="POST">
                    <div class="form-group">
                        <label>Mot de passe actuel</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Nouveau mot de passe</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="changer_mdp" class="btn btn-primary">
                        🔑 Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>

        <div class="tab-content" id="reservations">
            <div class="account-card">
                <h4>🎫 Mes réservations</h4>

                <?php if (empty($reservations)): ?>
                    <div class="no-reservations">
                        <i class="fas fa-ticket-alt"></i>
                        <h3>Aucune réservation trouvée</h3>
                        <p>Vous n'avez pas encore effectué de réservation.</p>
                        <a href="films.php" class="btn btn-primary">🎭 Voir les films</a>
                    </div>
                <?php else: ?>
                    <p>Vous avez <?= count($reservations) ?> réservation(s) au total.</p>

                    <?php foreach ($reservations as $reservation): ?>
                        <div class="reservation-item">
                            <div class="reservation-details">
                                <div class="reservation-header">
                                    <h3 class="reservation-title"><?= htmlspecialchars($reservation['film_titre'] ?? 'Film inconnu') ?></h3>
                                    <div class="reservation-status">
                                        <span class="badge">Réservation #<?= $reservation['id'] ?></span>
                                        <span class="badge badge-secondary"><?= number_format($reservation['prix_total'], 2) ?> €</span>
                                    </div>
                                </div>

                                <div class="reservation-info-grid">
                                    <div class="info-item">
                                        <div class="info-label">📅 Date de la séance</div>
                                        <div class="info-value"><?= date('d/m/Y', strtotime($reservation['seance_debut'])) ?></div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">⏰ Horaires</div>
                                        <div class="info-value"><?= date('H:i', strtotime($reservation['seance_debut'])) ?> - <?= date('H:i', strtotime($reservation['seance_fin'])) ?></div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">🎭 Salle</div>
                                        <div class="info-value"><?= htmlspecialchars($reservation['salle_nom'] ?? 'Non spécifiée') ?></div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">📋 Code réservation</div>
                                        <div class="info-value"><?= htmlspecialchars($reservation['code_reservation'] ?? 'N/A') ?></div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">📦 Nombre de places</div>
                                        <div class="info-value"><?= $reservation['nombre_places'] ?> place(s)</div>
                                    </div>

                                    <div class="info-item">
                                        <div class="info-label">📆 Date de réservation</div>
                                        <div class="info-value"><?= date('d/m/Y H:i', strtotime($reservation['date_commande'])) ?></div>
                                    </div>
                                </div>

                                <?php if (!empty($reservation['places'])): ?>
                                    <div class="info-item">
                                        <div class="info-label">💺 Places réservées</div>
                                        <div class="places-list">
                                            <?php foreach ($reservation['places'] as $place): ?>
                                                <span class="place-tag <?= $place['est_pmr'] ? 'pmr' : '' ?>">
                                                    <?= htmlspecialchars($place['reference']) ?>
                                                    <?= $place['est_pmr'] ? ' ♿' : '' ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="reservation-qr">
                                <div class="qr-container" id="qr-<?= $reservation['id'] ?>"></div>
                                <button onclick="window.print()" class="btn btn-outline-primary">
                                    🖨️ Imprimer le billet
                                </button>
                            </div>
                        </div>

                        <script>
                            // Générer le QR code pour cette réservation
                            var qr = qrcode(0, 'M');
                            qr.addData('CINEPHORIA\nRéservation: <?= $reservation['id'] ?>\nFilm: <?= htmlspecialchars($reservation['film_titre'] ?? 'Film inconnu') ?>\nSéance: <?= date('d/m/Y H:i', strtotime($reservation['seance_debut'])) ?>\nSalle: <?= htmlspecialchars($reservation['salle_nom'] ?? 'Non spécifiée') ?>\nPlaces: <?= $reservation['nombre_places'] ?>\nPrix: <?= number_format($reservation['prix_total'], 2) ?>€');
                            qr.make();
                            document.getElementById('qr-<?= $reservation['id'] ?>').innerHTML = qr.createSvgTag({
                                scalable: true
                            });
                        </script>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>À propos de Cinéphoria</h3>
                <p>Votre cinéma de référence pour les dernières sorties et les classiques du grand écran.</p>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>Email: contact@cinephoria.com</p>
                <p>Téléphone: 01 23 45 67 89</p>
            </div>
            <div class="footer-section">
                <h3>Suivez-nous</h3>
                <p>Facebook | Twitter | Instagram</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2023 Cinéphoria - Tous droits réservés</p>
        </div>
    </footer>

    <script>
        function showTab(tabId) {
            // Masquer tous les onglets
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Afficher l'onglet sélectionné
            document.getElementById(tabId).classList.add('active');

            // Mettre à jour les liens actifs
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            event.currentTarget.classList.add('active');
        }
    </script>
</body>

</html>