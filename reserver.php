<?php
session_start();
require 'testpdo.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['film_id'], $_POST['seance_id'], $_POST['sieges'], $_POST['place_ids'], $_POST['code_reservation'])) {
    die("Erreur: Données de réservation manquantes.");
}

// Récupérer les données du formulaire
$film_id = (int)$_POST['film_id'];
$seance_id = (int)$_POST['seance_id'];
$user_id = (int)$_POST['user_id'];
$sieges = explode(',', $_POST['sieges']);
$place_ids = explode(',', $_POST['place_ids']);
$code_reservation = $_POST['code_reservation'];

// Vérifier la cohérence des données
if (count($sieges) !== count($place_ids)) {
    die("Erreur: Incohérence dans les données de places.");
}

// Récupérer les informations sur le film et la séance
$stmt = $pdo->prepare("
    SELECT s.*, f.titre as film_titre, f.age_minimum, sa.reference as salle_nom, s.prix
    FROM seance s
    JOIN film f ON s.id_film = f.id
    JOIN salle sa ON s.id_salle = sa.id
    WHERE s.id = ? AND s.id_film = ?
");
$stmt->execute([$seance_id, $film_id]);
$seance_info = $stmt->fetch();

if (!$seance_info) {
    die("Erreur: Informations sur la séance introuvables.");
}

// Récupérer les informations sur l'utilisateur
$stmt = $pdo->prepare("SELECT nom, prenom, email FROM utilisateur WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

if (!$user_info) {
    die("Erreur: Utilisateur introuvable.");
}

// Calculer le prix total
$prix_total = count($sieges) * $seance_info['prix'];

// Vérifier que les places sont toujours disponibles
foreach ($place_ids as $place_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM billet b
        JOIN commande c ON b.id_commande = c.id
        WHERE b.id_place = ? AND c.id_seance = ?
    ");
    $stmt->execute([$place_id, $seance_id]);
    $est_occupe = $stmt->fetchColumn();

    if ($est_occupe > 0) {
        die("Erreur: Une ou plusieurs places ne sont plus disponibles.");
    }
}

// Générer un numéro de réservation unique
$numero_reservation = 'RES-' . date('Ymd') . '-' . strtoupper(uniqid());

// Commencer une transaction pour assurer l'intégrité des données
$pdo->beginTransaction();

try {
    // Créer la commande
    $stmt = $pdo->prepare("
        INSERT INTO commande (id_utilisateur, id_seance, date_commande, prix_total, code_reservation, numero_reservation) 
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    $stmt->execute([$user_id, $seance_id, $prix_total, $code_reservation, $numero_reservation]);
    $commande_id = $pdo->lastInsertId();

    // Créer les billets pour chaque place
    foreach ($place_ids as $index => $place_id) {
        // Récupérer les infos sur la place (pour savoir si c'est PMR)
        $stmt_place = $pdo->prepare("SELECT est_pmr FROM place WHERE id = ?");
        $stmt_place->execute([$place_id]);
        $place_info = $stmt_place->fetch();
        $est_pmr = $place_info['est_pmr'] ? 'pmr' : 'normal';

        // Créer le billet
        $stmt = $pdo->prepare("
            INSERT INTO billet (id_commande, id_place, prix, qr_code) 
            VALUES (?, ?, ?, ?)
        ");
        $qr_code_data = "CINEPHORIA|$commande_id|{$sieges[$index]}|$code_reservation";
        $stmt->execute([$commande_id, $place_id, $seance_info['prix'], $qr_code_data]);
    }

    // Valider la transaction
    $pdo->commit();

    $reservation_success = true;
} catch (Exception $e) {
    // En cas d'erreur, annuler la transaction
    $pdo->rollBack();
    $reservation_success = false;
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Réservation - Cinéphoria</title>
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
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .confirmation-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
        }

        .confirmation-error {
            border-left: 5px solid #dc3545;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 20px 0;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-item h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1rem;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 5px;
        }

        .places-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .place-tag {
            background: #d4af37;
            color: #1a2a3a;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .place-tag.pmr {
            background: #ffc107;
        }

        .total-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s ease;
        }

        .btn-primary {
            background: #d4af37;
            color: #1a2a3a;
        }

        .btn-secondary {
            background: #2c3e50;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .ticket-download {
            text-align: center;
            margin: 20px 0;
        }

        .ticket-download a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .ticket-download a:hover {
            text-decoration: underline;
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
            .info-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-ticket-alt"></i> Confirmation de Réservation</h1>
            </div>

            <?php if ($reservation_success): ?>
                <div class="confirmation-card">
                    <h2><i class="fas fa-check-circle"></i> Réservation Confirmée!</h2>
                    <p>Votre réservation a été enregistrée avec succès. Vous trouverez ci-dessous le détail de votre commande.</p>
                    <p>Un email de confirmation a été envoyé à <?= htmlspecialchars($user_info['email']) ?>.</p>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <h3><i class="fas fa-film"></i> Film</h3>
                        <p><?= htmlspecialchars($seance_info['film_titre']) ?></p>
                    </div>

                    <div class="info-item">
                        <h3><i class="fas fa-calendar-alt"></i> Séance</h3>
                        <p><?= date('d/m/Y à H:i', strtotime($seance_info['debut'])) ?></p>
                    </div>

                    <div class="info-item">
                        <h3><i class="fas fa-door-open"></i> Salle</h3>
                        <p><?= htmlspecialchars($seance_info['salle_nom']) ?></p>
                    </div>

                    <div class="info-item">
                        <h3><i class="fas fa-user"></i> Client</h3>
                        <p><?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></p>
                        <p><?= htmlspecialchars($user_info['email']) ?></p>
                    </div>
                </div>

                <div class="info-item">
                    <h3><i class="fas fa-chair"></i> Places Réservées</h3>
                    <div class="places-list">
                        <?php
                        // Récupérer les infos PMR pour chaque place
                        foreach ($place_ids as $index => $place_id):
                            $stmt_place = $pdo->prepare("SELECT est_pmr FROM place WHERE id = ?");
                            $stmt_place->execute([$place_id]);
                            $place_info = $stmt_place->fetch();
                            $is_pmr = $place_info['est_pmr'];
                        ?>
                            <span class="place-tag <?= $is_pmr ? 'pmr' : '' ?>">
                                <?= htmlspecialchars($sieges[$index]) ?>
                                <?= $is_pmr ? ' ♿' : '' ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="total-price">
                    <p>Total: <?= number_format($prix_total, 2) ?> €</p>
                </div>

                <div class="info-item">
                    <h3><i class="fas fa-receipt"></i> Informations de Réservation</h3>
                    <p>Code de réservation: <strong><?= htmlspecialchars($code_reservation) ?></strong></p>
                    <p>Numéro de commande: <strong>#<?= $commande_id ?></strong></p>
                    <p>Numéro de réservation: <strong><?= htmlspecialchars($numero_reservation) ?></strong></p>
                    <p>Date de réservation: <strong><?= date('d/m/Y à H:i') ?></strong></p>
                </div>

                <div class="ticket-download">
                    <a href="#"><i class="fas fa-download"></i> Télécharger mes billets (PDF)</a>
                </div>

                <div class="actions">
                    <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Retour à l'accueil</a>
                    <a href="moncompte.php" class="btn btn-secondary"><i class="fas fa-user"></i> Mes réservations</a>
                </div>

            <?php else: ?>
                <div class="confirmation-card confirmation-error">
                    <h2><i class="fas fa-exclamation-circle"></i> Erreur de Réservation</h2>
                    <p>Une erreur est survenue lors de l'enregistrement de votre réservation.</p>
                    <p><?= isset($error_message) ? htmlspecialchars($error_message) : 'Veuillez réessayer.' ?></p>
                </div>

                <div class="actions">
                    <a href="reservation.php?film_id=<?= $film_id ?>&seance_id=<?= $seance_id ?>" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Retour à la sélection des places
                    </a>
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