<?php
session_start();

if (!isset($_SESSION['reservation_data'])) {
    header("Location: reservation.php");
    exit;
}

$reservation = $_SESSION['reservation_data'];

// Débuggage: Afficher le contenu de la réservation
error_log("Données de réservation: " . print_r($reservation, true));

// Assurer que toutes les clés nécessaires existent avec des valeurs par défaut
$reservation['numero'] = $reservation['numero'] ?? $reservation['commande_id'] ?? 'RES-' . time();
$reservation['sieges'] = $reservation['sieges'] ?? 'Non spécifié';
$reservation['prix_total'] = $reservation['prix_total'] ?? '0.00';
$reservation['nombre_places'] = $reservation['nombre_places'] ?? substr_count($reservation['sieges'], ',') + 1;
$reservation['prix_unitaire'] = $reservation['prix_unitaire'] ?? ($reservation['prix_total'] / $reservation['nombre_places']);
$reservation['email'] = $reservation['email'] ?? $_SESSION['user']['email'] ?? 'Non spécifié';

// Générer le QR code si non présent
if (!isset($reservation['qr_code'])) {
    $qr_data = "CINEPHORIA|" . $reservation['numero'] . "|" . time();
    $reservation['qr_code'] = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Confirmation de Réservation - Cinéphoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a2a3a;
            --primary: #2c3e50;
            --accent: #d4af37;
            --accent-hover: #c19b2e;
            --light-bg: #f8f9fa;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .confirmation-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 800px;
            margin: 40px auto;
        }

        .confirmation-header {
            background: linear-gradient(45deg, var(--primary-dark), var(--primary));
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .confirmation-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--accent);
        }

        .confirmation-body {
            padding: 40px;
        }

        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .qr-section {
            background: var(--light-bg);
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            border: 2px dashed #dee2e6;
        }

        .qr-code {
            max-width: 200px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            background: white;
        }

        .reservation-details {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--accent), var(--accent-hover));
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        @media (max-width: 768px) {
            .confirmation-body {
                padding: 20px;
            }

            .btn-primary,
            .btn-outline {
                display: block;
                width: 100%;
                margin: 10px 0;
            }
        }

        .ticket-animation {
            animation: ticketAppear 0.6s ease-out;
        }

        @keyframes ticketAppear {
            0% {
                transform: translateY(50px);
                opacity: 0;
            }

            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <div class="confirmation-card ticket-animation">
        <div class="confirmation-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Réservation Confirmée !</h1>
            <p class="lead">Votre expérience cinéma vous attend</p>
        </div>

        <div class="confirmation-body">
            <div class="qr-section text-center">
                <h4><i class="fas fa-qrcode"></i> Votre code d'accès</h4>
                <div class="qr-code">
                    <img src="<?= htmlspecialchars($reservation['qr_code']) ?>" alt="QR Code de réservation" class="img-fluid">
                </div>
                <p class="text-muted mt-3">Présentez ce code QR à l'entrée de la salle</p>
            </div>

            <div class="reservation-details">
                <h4><i class="fas fa-receipt"></i> Détails de la réservation</h4>

                <div class="detail-item">
                    <strong>Numéro de réservation:</strong>
                    <span>#<?= htmlspecialchars($reservation['numero']) ?></span>
                </div>

                <div class="detail-item">
                    <strong>Date de réservation:</strong>
                    <span><?= date('d/m/Y à H:i') ?></span>
                </div>

                <div class="detail-item">
                    <strong>Places réservées:</strong>
                    <span><?= htmlspecialchars($reservation['sieges']) ?></span>
                </div>

                <div class="detail-item">
                    <strong>Nombre de places:</strong>
                    <span><?= $reservation['nombre_places'] ?></span>
                </div>

                <div class="detail-item">
                    <strong>Prix unitaire:</strong>
                    <span><?= number_format($reservation['prix_unitaire'], 2) ?> €</span>
                </div>

                <div class="detail-item" style="border-top: 2px solid #ddd; padding-top: 15px;">
                    <strong style="font-size: 1.1rem;">Total:</strong>
                    <span style="font-size: 1.1rem; font-weight: bold; color: var(--primary);">
                        <?= number_format($reservation['prix_total'], 2) ?> €
                    </span>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="moncompte.php" class="btn btn-primary">
                    <i class="fas fa-ticket-alt"></i> Mes réservations
                </a>

                <a href="films.php" class="btn btn-outline">
                    <i class="fas fa-film"></i> Voir les films
                </a>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Un email de confirmation a été envoyé à <?= htmlspecialchars($reservation['email']) ?>
                </small>
            </div>
        </div>
    </div>

    <script>
        // Animation d'apparition
        document.addEventListener('DOMContentLoaded', function() {
            const ticket = document.querySelector('.ticket-animation');
            ticket.style.opacity = '0';
            ticket.style.transform = 'translateY(50px)';

            setTimeout(() => {
                ticket.style.transition = 'all 0.6s ease-out';
                ticket.style.opacity = '1';
                ticket.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>

</html>