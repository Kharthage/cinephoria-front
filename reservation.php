<?php
session_start();
require 'testpdo.php';

// --- Récupérer les paramètres ---
$film_id = isset($_GET['film_id']) ? (int)$_GET['film_id'] : 0;
$seance_id = isset($_GET['seance_id']) ? (int)$_GET['seance_id'] : 0;

if ($seance_id <= 0 && isset($_GET['id_seance'])) $seance_id = (int)$_GET['id_seance'];

if ($film_id <= 0 && $seance_id > 0) {
    $stmt = $pdo->prepare("SELECT id_film FROM seance WHERE id = ?");
    $stmt->execute([$seance_id]);
    $data = $stmt->fetch();
    if ($data) $film_id = (int)$data['id_film'];
}

// --- Vérification critique ---
if ($film_id <= 0 || $seance_id <= 0) {
    echo "<div style='padding:40px; text-align:center; background:#f8f9fa; min-height:100vh;'>
            <div style='max-width:600px; margin:0 auto; background:white; padding:30px; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.1);'>
                <h2 style='color:#dc3545; margin-bottom:20px;'><i class='fas fa-exclamation-triangle'></i> Séance non sélectionnée</h2>
                <p style='margin-bottom:20px;'>Veuillez retourner à la page des films et sélectionner une séance.</p>
                <p style='margin-bottom:20px; color:#666;'><small>Paramètres reçus: film_id=$film_id, seance_id=$seance_id</small></p>
                <a href='films.php' style='background:#d4af37; color:white; padding:12px 30px; text-decoration:none; border-radius:25px; font-weight:bold;'>
                    <i class='fas fa-arrow-left'></i> Retour aux films
                </a>
            </div>
          </div>";
    exit;
}

// --- Stocker IDs en session ---
$_SESSION['selected_film_id'] = $film_id;
$_SESSION['selected_seance_id'] = $seance_id;

// --- Infos utilisateur ---
$user_nom = $_SESSION['user']['nom'] ?? '';
$user_prenom = $_SESSION['user']['prenom'] ?? '';
$user_email = $_SESSION['user']['email'] ?? '';
$user_id = $_SESSION['user']['id'] ?? null;

// --- Infos film ---
$stmt = $pdo->prepare("SELECT id, titre, age_minimum FROM film WHERE id = ?");
$stmt->execute([$film_id]);
$film_info = $stmt->fetch();
if (!$film_info) die("Erreur: Film introuvable.");

// --- Infos séance ---
$stmt = $pdo->prepare("
    SELECT s.*, f.titre as film_titre, sa.reference as salle_nom, sa.nb_places, sa.nb_places_pmr 
    FROM seance s
    JOIN film f ON s.id_film = f.id
    JOIN salle sa ON s.id_salle = sa.id
    WHERE s.id = ? AND s.id_film = ?
");
$stmt->execute([$seance_id, $film_id]);
$seance_info = $stmt->fetch();
if (!$seance_info) die("Erreur: Séance introuvable.");

// --- Places ---
$places_disponibles = [];
$places_occupees = [];
$places_pmr = [];

$stmt = $pdo->prepare("SELECT * FROM place WHERE id_salle = ? ORDER BY reference");
$stmt->execute([$seance_info['id_salle']]);
$all_places = $stmt->fetchAll();

foreach ($all_places as $place) {
    $places_disponibles[] = [
        'reference' => $place['reference'],
        'id' => $place['id'],
        'est_pmr' => $place['est_pmr']
    ];
    if ($place['est_pmr']) $places_pmr[] = $place['reference'];
}

// --- Places déjà réservées ---
$stmt = $pdo->prepare("
    SELECT p.reference 
    FROM commande c
    JOIN billet b ON c.id = b.id_commande
    JOIN place p ON b.id_place = p.id
    WHERE c.id_seance = ?
");
$stmt->execute([$seance_id]);
$reservations = $stmt->fetchAll();
foreach ($reservations as $r) $places_occupees[] = $r['reference'];

// --- Code réservation ---
$code_reservation = 'RES-' . date('Ymd') . '-' . uniqid();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation - Cinéphoria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
            max-width: 1000px;
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

        .sieges-container {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 8px;
            max-width: 600px;
            margin: 0 auto;
        }

        .siege {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
            border-radius: 8px;
            transition: 0.3s;
        }

        .siege.disponible {
            background: #28a745;
            color: white;
        }

        .siege.pmr {
            background: #ffc107;
            color: #212529;
            position: relative;
        }

        .siege.pmr::after {
            content: '♿';
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 8px;
            background: white;
            border-radius: 50%;
            width: 12px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ffc107;
        }

        .siege.occupe {
            background: #dc3545;
            color: white;
            cursor: not-allowed;
        }

        .siege.selected {
            background: #2c3e50;
            color: white;
        }

        .reservation-summary {
            margin-top: 20px;
            padding: 20px;
            background: #2c3e50;
            color: white;
            border-radius: 8px;
            display: none;
        }

        .btn {
            background: #d4af37;
            color: #1a2a3a;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            display: block;
            margin: 20px auto;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #c19b2e;
        }

        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
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
            .container {
                margin: 20px 15px;
                padding: 20px;
            }

            .sieges-container {
                grid-template-columns: repeat(5, 1fr);
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
                <h1><i class="fas fa-ticket-alt"></i> Réservation de places</h1>
                <p><?= htmlspecialchars($seance_info['film_titre']) ?> - <?= date('d/m/Y H:i', strtotime($seance_info['debut'])) ?> - Salle <?= htmlspecialchars($seance_info['salle_nom']) ?></p>
            </div>

            <form action="reserver.php" method="POST" id="reservationForm">
                <input type="hidden" name="film_id" value="<?= $film_id ?>">
                <input type="hidden" name="seance_id" value="<?= $seance_id ?>">
                <input type="hidden" name="code_reservation" value="<?= $code_reservation ?>">
                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <input type="hidden" name="sieges" id="siegesInput">
                <input type="hidden" name="place_ids" id="placesIdsInput">

                <div class="sieges-container" id="siegesContainer">
                    <?php foreach ($places_disponibles as $place):
                        $is_occupe = in_array($place['reference'], $places_occupees);
                        $is_pmr = $place['est_pmr'];
                        $class = $is_occupe ? 'occupe' : ($is_pmr ? 'pmr' : 'disponible');
                    ?>
                        <div class="siege <?= $class ?>" data-siege="<?= $place['reference'] ?>" data-place-id="<?= $place['id'] ?>" onclick="<?= !$is_occupe ? 'selectSiege(this)' : '' ?>">
                            <?= $place['reference'] ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="reservation-summary" id="reservationSummary">
                    <p id="selectedPlacesList"></p>
                    <p id="totalPrice"></p>
                    <div id="qrcode"></div>
                </div>

                <button type="submit" class="btn" id="submitBtn" disabled>Valider la réservation</button>
            </form>
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

    <script>
        let selectedSieges = [];
        let selectedPlaceIds = [];
        const pricePerPlace = <?= $seance_info['prix'] ?? 10 ?>;
        const reservationCode = '<?= $code_reservation ?>';

        function selectSiege(el) {
            if (el.classList.contains('occupe')) return;
            const ref = el.getAttribute('data-siege');
            const id = el.getAttribute('data-place-id');
            const index = selectedSieges.indexOf(ref);
            if (index > -1) {
                selectedSieges.splice(index, 1);
                selectedPlaceIds.splice(index, 1);
                el.classList.remove('selected');
            } else {
                selectedSieges.push(ref);
                selectedPlaceIds.push(id);
                el.classList.add('selected');
            }
            updateSummary();
        }

        function updateSummary() {
            const summary = document.getElementById('reservationSummary');
            const submit = document.getElementById('submitBtn');
            const siegesInput = document.getElementById('siegesInput');
            const idsInput = document.getElementById('placesIdsInput');
            const selectedList = document.getElementById('selectedPlacesList');
            const total = document.getElementById('totalPrice');

            if (selectedSieges.length > 0) {
                summary.style.display = 'block';
                submit.disabled = false;
                siegesInput.value = selectedSieges.join(',');
                idsInput.value = selectedPlaceIds.join(',');
                selectedList.innerHTML = 'Places: ' + selectedSieges.join(',');
                total.innerHTML = 'Total: ' + (selectedSieges.length * pricePerPlace).toFixed(2) + '€';
                generateQRCode();
            } else {
                summary.style.display = 'none';
                submit.disabled = true;
                siegesInput.value = '';
                idsInput.value = '';
            }
        }

        function generateQRCode() {
            const qrDiv = document.getElementById('qrcode');
            qrDiv.innerHTML = '';
            new QRCode(qrDiv, {
                text: `CINEPHORIA-${reservationCode}-PLACES:${selectedSieges.join(',')}`,
                width: 150,
                height: 150,
                colorDark: "#2c3e50",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    </script>
</body>

</html>