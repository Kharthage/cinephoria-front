<?php
session_start();
include("testpdo.php");

$message = "";

// Système de routing simple
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = dirname($script_name); // /cinephoria-front
$path = str_replace($base_path, '', $request_uri);
$path = ltrim($path, '/');

// Retirer les paramètres GET de l'URL
$path = parse_url($path, PHP_URL_PATH);

// Extraire la page à partir du chemin
if ($path == '' || $path == 'index.php') {
    $page = 'accueil';
} elseif (strpos($path, 'film/') === 0) {
    $page = 'film';
    $film_id = substr($path, 5); // Extraire l'ID après "film/"
} elseif ($path == 'films') {
    $page = 'film'; // Corriger : films -> film
} else {
    $page = $path;
}

// Si on utilise encore l'ancien système GET, on le maintient pour compatibilité
if (isset($_GET['page'])) {
    $page = $_GET['page'];
}
if (isset($_GET['id'])) {
    $film_id = $_GET['id'];
}

// Login simple
if (isset($_POST['login'])) {
    if ($_POST['email'] == "admin@cinema.com" && $_POST['password'] == "123") {
        $_SESSION['user'] = $_POST['email'];
        $message = "✅ Connecté avec succès !";
    } else {
        $message = "❌ Email ou mot de passe incorrect";
    }
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    $message = "👋 Déconnecté avec succès !";
    header("Location: index.php");
    exit;
}

// Réservation - CORRECTION DES ERREURS
if (isset($_POST['reserver'])) {
    if (isset($_SESSION['user'])) {
        $film_id = $_POST['film_id'];
        $nom = $_POST['nom'];
        $places = $_POST['places'];
        $type_place = $_POST['type_place'];
        $seance_id = $_POST['seance_id'] ?? null;

        // Récupérer les informations du film avec gestion d'erreurs
        $stmt = $pdo->prepare("SELECT * FROM film WHERE id=?");
        $stmt->execute([$film_id]);
        $film = $stmt->fetch();

        if ($film) {
            // Vérifier que les colonnes prix existent
            $prix_normal = isset($film['prix']) ? $film['prix'] : 10.00; // Prix par défaut
            $prix_pmr = isset($film['prix_pmr']) ? $film['prix_pmr'] : 8.00; // Prix par défaut

            $prix_place = ($type_place == 'pmr') ? $prix_pmr : $prix_normal;
            $total = $prix_place * $places;
            $numero = rand(10000, 99999);

            try {
                // Créer la table reservation si elle n'existe pas
                $createTableSQL = "
                CREATE TABLE IF NOT EXISTS reservation (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    film_id INT NOT NULL,
                    seance_id INT NULL,
                    nom VARCHAR(255) NOT NULL,
                    places INT NOT NULL,
                    type_place ENUM('normal', 'pmr') DEFAULT 'normal',
                    numero INT NOT NULL UNIQUE,
                    prix_unitaire DECIMAL(5,2) NOT NULL,
                    total DECIMAL(7,2) NOT NULL,
                    date_reservation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('active', 'cancelled') DEFAULT 'active'
                )";
                $pdo->exec($createTableSQL);

                // Insérer la réservation
                $insertSQL = "INSERT INTO reservation (film_id, seance_id, nom, places, type_place, numero, prix_unitaire, total) VALUES (?,?,?,?,?,?,?,?)";
                $pdo->prepare($insertSQL)->execute([$film_id, $seance_id, $nom, $places, $type_place, $numero, $prix_place, $total]);

                $_SESSION['derniere_reservation'] = [
                    'numero' => $numero,
                    'film' => $film['titre'],
                    'places' => $places,
                    'type_place' => $type_place,
                    'prix' => $prix_place,
                    'total' => $total,
                    'nom' => $nom
                ];

                $message = "🎉 Réservation confirmée ! N° $numero";
                header("Location: ticket.php");
                exit;
            } catch (PDOException $e) {
                $message = "❌ Erreur lors de la réservation : " . $e->getMessage();
            }
        } else {
            $message = "❌ Film non trouvé";
        }
    } else {
        $message = "⚠️ Connectez-vous d'abord !";
        header("Location: login.php");
        exit;
    }
}

// --- Avant-premières (4 films max, 2 séances max par film)
$sqlAvant = "
    SELECT f.id, f.titre, f.affiche_url, f.description, s.debut, s.prix
    FROM film f
    JOIN seance s ON f.id = s.id_film
    WHERE f.avant_premiere = 1
    ORDER BY f.date_ajout DESC
    LIMIT 8
";
try {
    $avant = $pdo->query($sqlAvant)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avant = [];
}

// --- Films à l'affiche (nouvelle section)
$sqlAffiche = "
    SELECT f.id, f.titre, f.affiche_url, f.description, s.debut, s.prix
    FROM film f
    JOIN seance s ON f.id = s.id_film
    WHERE s.debut > NOW() AND s.debut <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY f.date_ajout DESC
    LIMIT 12
";
try {
    $affiche = $pdo->query($sqlAffiche)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $affiche = [];
}

// --- Films ajoutés un mercredi (5-6 séances)
$sqlMercredi = "
    SELECT f.id, f.titre, f.affiche_url, f.description, s.debut, s.prix
    FROM film f
    JOIN seance s ON f.id = s.id_film
    WHERE DAYOFWEEK(f.date_ajout) = 4
    ORDER BY f.date_ajout DESC
    LIMIT 20
";
try {
    $mercredi = $pdo->query($sqlMercredi)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mercredi = [];
}

// Regrouper les séances par film
function groupFilms($rows)
{
    $films = [];
    foreach ($rows as $r) {
        $id = $r['id'];
        if (!isset($films[$id])) {
            $films[$id] = [
                'id' => $id,
                'titre' => $r['titre'],
                'affiche' => $r['affiche_url'],
                'description' => $r['description'],
                'prix' => $r['prix'] ?? 10.00,
                'seances' => []
            ];
        }
        $films[$id]['seances'][] = date("H:i", strtotime($r['debut']));
    }
    return $films;
}

$avant = groupFilms($avant);
$affiche = groupFilms($affiche);
$mercredi = groupFilms($mercredi);

// Fonction pour générer les URLs vers les fichiers PHP
function getPhpUrl($page, $id = null)
{
    switch ($page) {
        case 'accueil':
            return 'index.php';
        case 'film':
            return $id ? "film.php?id=" . $id : 'film.php';
        case 'login':
            return 'login.php';
        case 'reserver':
        case 'reservation':
            return 'reserver.php';
        case 'compte':
            return 'moncompte.php';
        default:
            return $page . '.php';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Cinéphoria - Votre cinéma de référence</title>
    <style>
        /* STYLES EXISTANTS */
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

        nav a:hover {
            background-color: #d4af37;
            color: #1a2a3a;
        }

        main {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h2 {
            color: #2c3e50;
            padding-left: 15px;
            margin-top: 40px;
            border-left: 4px solid #d4af37;
            font-size: 24px;
        }

        .films {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 1px solid #eaeaea;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card img {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }

        .info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .info h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #2c3e50;
            min-height: 40px;
        }

        .desc {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
            flex-grow: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .seances {
            font-size: 13px;
            color: #444;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        .seance-time {
            display: inline-block;
            background-color: #2c3e50;
            color: white;
            padding: 4px 8px;
            margin: 3px;
            border-radius: 3px;
            font-size: 12px;
        }

        .btn-details {
            background-color: #d4af37;
            color: #1a2a3a;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s ease;
            font-size: 13px;
        }

        .btn-details:hover {
            background-color: #c19b2e;
        }

        footer {
            background: #2c3e50;
            color: #e0e0e0;
            padding: 25px 0;
            margin-top: 60px;
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
            .films {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
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
            }

            nav a {
                margin: 0 8px;
            }
        }

        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        form {
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        input,
        select,
        button {
            padding: 8px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        button {
            background-color: #2c3e50;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background-color: #1a2a3a;
        }

        .ticket {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: 0 auto;
        }

        .qrcode {
            text-align: center;
            margin: 20px 0;
        }

        /* NOUVEAUX STYLES POUR LA PAGE FILM */
        .film-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }

        .film-header {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 40px;
            margin-bottom: 40px;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .film-poster {
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        .film-poster:hover {
            transform: scale(1.02);
        }

        .film-info h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin: 0 0 15px 0;
            font-weight: 700;
        }

        .film-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .film-description {
            font-size: 1.1em;
            line-height: 1.8;
            color: #555;
            margin-bottom: 25px;
        }

        .pricing {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .price-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .price-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
        }

        .price-amount {
            font-size: 1.8em;
            font-weight: bold;
            margin: 0;
        }

        .seances-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .seances-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin: 0 0 25px 0;
            padding-left: 15px;
            border-left: 4px solid #d4af37;
        }

        .seances-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
        }

        .seance-button {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 14px;
        }

        .seance-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
        }

        .seance-button.selected {
            background: linear-gradient(135deg, #d4af37 0%, #bf953f 100%);
            color: #1a2a3a;
        }

        .reservation-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .reservation-form h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin: 0 0 25px 0;
            padding-left: 15px;
            border-left: 4px solid #d4af37;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }

        .btn-reserver {
            background: linear-gradient(135deg, #d4af37 0%, #bf953f 100%);
            color: #1a2a3a;
            border: none;
            padding: 18px 35px;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-reserver:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }

        .login-prompt {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }

        .login-prompt h3 {
            margin: 0 0 15px 0;
            font-size: 1.5em;
        }

        .btn-login {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 768px) {
            .film-header {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .film-info h1 {
                font-size: 2em;
            }

            .seances-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }

            .form-grid {
                grid-template-columns: 1fr;
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
            <a href="moncompte.php">🏠 Mon Compte</a>
            <a href="film.php">🎭 Films</a>
            <a href="reserver.php">🎫 Ma Réservation</a>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="?logout=1">🔒 Déconnexion</a>
            <?php else: ?>
                <a href="login.php">🔑 Connexion</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <?php if ($message): ?>
            <div class="message <?= strpos($message, '✅') !== false || strpos($message, '🎉') !== false ? 'success' : 'error' ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php
        switch ($page) {
            case 'accueil':
                echo "<h2>🎬 Avant-Premières</h2>";
                echo "<div class='films'>";
                foreach ($avant as $film) {
                    echo "<div class='card'>";
                    echo "<img src='" . htmlspecialchars($film['affiche']) . "' alt='" . htmlspecialchars($film['titre']) . "'>";
                    echo "<div class='info'>";
                    echo "<h3>" . htmlspecialchars($film['titre']) . "</h3>";
                    echo "<div class='desc'>" . htmlspecialchars($film['description']) . "</div>";
                    echo "<div class='seances'>";
                    $seancesToShow = array_slice($film['seances'], 0, 2);
                    foreach ($seancesToShow as $seance) {
                        echo "<span class='seance-time'>" . $seance . "</span>";
                    }
                    echo "</div>";
                    echo "<a href='" . getPhpUrl('film', $film['id']) . "' class='btn-details'>Voir détails</a>";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";

                echo "<h2>🎬 Films à l'affiche</h2>";
                echo "<div class='films'>";
                foreach ($affiche as $film) {
                    echo "<div class='card'>";
                    echo "<img src='" . htmlspecialchars($film['affiche']) . "' alt='" . htmlspecialchars($film['titre']) . "'>";
                    echo "<div class='info'>";
                    echo "<h3>" . htmlspecialchars($film['titre']) . "</h3>";
                    echo "<div class='desc'>" . htmlspecialchars($film['description']) . "</div>";
                    echo "<div class='seances'>";
                    $seancesToShow = array_slice($film['seances'], 0, 4);
                    foreach ($seancesToShow as $seance) {
                        echo "<span class='seance-time'>" . $seance . "</span>";
                    }
                    echo "</div>";
                    echo "<a href='" . getPhpUrl('film', $film['id']) . "' class='btn-details'>Voir détails</a>";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";

                echo "<h2>🎬 Films ajoutés mercredi</h2>";
                echo "<div class='films'>";
                foreach ($mercredi as $film) {
                    echo "<div class='card'>";
                    echo "<img src='" . htmlspecialchars($film['affiche']) . "' alt='" . htmlspecialchars($film['titre']) . "'>";
                    echo "<div class='info'>";
                    echo "<h3>" . htmlspecialchars($film['titre']) . "</h3>";
                    echo "<div class='desc'>" . htmlspecialchars($film['description']) . "</div>";
                    echo "<div class='seances'>";
                    $seancesToShow = array_slice($film['seances'], 0, 6);
                    foreach ($seancesToShow as $seance) {
                        echo "<span class='seance-time'>" . $seance . "</span>";
                    }
                    echo "</div>";
                    echo "<a href='" . getPhpUrl('film', $film['id']) . "' class='btn-details'>Voir détails</a>";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";
                break;

            case 'film':
                // Si pas d'ID, afficher tous les films
                if (!isset($film_id)) {
                    try {
                        $films = $pdo->query("SELECT * FROM film ORDER BY titre")->fetchAll();
                        echo "<h2>🎭 Tous les films</h2>";
                        echo "<div class='films'>";
                        foreach ($films as $film) {
                            echo "<div class='card'>";
                            echo "<img src='" . htmlspecialchars($film['affiche_url']) . "' alt='" . htmlspecialchars($film['titre']) . "'>";
                            echo "<div class='info'>";
                            echo "<h3>" . htmlspecialchars($film['titre']) . "</h3>";
                            echo "<div class='desc'>" . htmlspecialchars(substr($film['description'], 0, 100)) . "...</div>";
                            echo "<a href='" . getPhpUrl('film', $film['id']) . "' class='btn-details'>Voir détails et séances</a>";
                            echo "</div>";
                            echo "</div>";
                        }
                        echo "</div>";
                    } catch (PDOException $e) {
                        echo "<p style='color: red;'>Erreur lors du chargement des films : " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    break;
                }

                // Sinon afficher le détail du film
                if (isset($film_id)) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM film WHERE id = ?");
                        $stmt->execute([$film_id]);
                        $film = $stmt->fetch();

                        if ($film) {
                            $stmt_seances = $pdo->prepare("SELECT * FROM seance WHERE id_film = ? AND debut > NOW() ORDER BY debut");
                            $stmt_seances->execute([$film_id]);
                            $seances = $stmt_seances->fetchAll();
        ?>

                            <div class="film-container">
                                <div class="film-header">
                                    <div>
                                        <img src="<?= htmlspecialchars($film['affiche_url']) ?>" alt="<?= htmlspecialchars($film['titre']) ?>" class="film-poster">
                                    </div>

                                    <div class="film-info">
                                        <h1><?= htmlspecialchars($film['titre']) ?></h1>

                                        <div class="film-meta">
                                            <?php if (isset($film['duree']) && $film['duree']): ?>
                                                <span class="meta-item">🎬 <?= htmlspecialchars($film['duree']) ?> min</span>
                                            <?php endif; ?>
                                            <?php if (isset($film['avant_premiere']) && $film['avant_premiere']): ?>
                                                <span class="meta-item">⭐ Avant-première</span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="film-description"><?= htmlspecialchars($film['description']) ?></p>

                                        <div class="pricing">
                                            <div class="price-card">
                                                <h3>Tarif Normal</h3>
                                                <p class="price-amount"><?= isset($film['prix']) ? htmlspecialchars($film['prix']) : '10.00' ?> €</p>
                                            </div>
                                            <div class="price-card">
                                                <h3>Tarif PMR</h3>
                                                <p class="price-amount"><?= isset($film['prix_pmr']) ? htmlspecialchars($film['prix_pmr']) : '8.00' ?> €</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (count($seances) > 0): ?>
                                    <div class="seances-section">
                                        <h2>🎬 Séances disponibles</h2>
                                        <div class="seances-grid">
                                            <?php foreach ($seances as $seance): ?>
                                                <button class="seance-button" onclick="selectSeance(<?= $seance['id'] ?>, '<?= date("H:i", strtotime($seance['debut'])) ?>')">
                                                    <?= date("d/m à H:i", strtotime($seance['debut'])) ?>
                                                    <br><small>Salle <?= $seance['id_salle'] ?></small>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="seances-section">
                                        <h2>🎬 Séances</h2>
                                        <p>Aucune séance programmée pour ce film actuellement.</p>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['user'])): ?>
                                    <div class="reservation-form">
                                        <h2>📋 Réserver votre séance</h2>
                                        <form method="POST">
                                            <input type="hidden" name="film_id" value="<?= $film['id'] ?>">
                                            <input type="hidden" name="seance_id" id="seance_id" value="">

                                            <div id="seance-info" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                                <p><strong>Séance sélectionnée :</strong> <span id="selected-seance"></span></p>
                                            </div>

                                            <div class="form-grid">
                                                <div class="form-group">
                                                    <label for="nom">Nom complet</label>
                                                    <input type="text" id="nom" name="nom" placeholder="Votre nom" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="places">Nombre de places</label>
                                                    <select id="places" name="places" required>
                                                        <option value="">Sélectionnez</option>
                                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?> place<?= $i > 1 ? 's' : '' ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="type_place">Type de place</label>
                                                    <select id="type_place" name="type_place" required>
                                                        <option value="">Sélectionnez</option>
                                                        <option value="normal">Normal (<?= isset($film['prix']) ? $film['prix'] : '10.00' ?>€)</option>
                                                        <option value="pmr">PMR (<?= isset($film['prix_pmr']) ? $film['prix_pmr'] : '8.00' ?>€)</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <button type="submit" name="reserver" class="btn-reserver" id="btn-reserver" disabled>
                                                🎫 Sélectionnez d'abord une séance
                                            </button>
                                        </form>
                                    </div>

                                    <script>
                                        function selectSeance(seanceId, horaire) {
                                            // Retirer la sélection précédente
                                            document.querySelectorAll('.seance-button').forEach(btn => {
                                                btn.classList.remove('selected');
                                            });

                                            // Sélectionner le bouton cliqué
                                            event.target.classList.add('selected');

                                            // Mettre à jour le formulaire
                                            document.getElementById('seance_id').value = seanceId;
                                            document.getElementById('selected-seance').textContent = horaire;
                                            document.getElementById('seance-info').style.display = 'block';

                                            // Activer le bouton de réservation
                                            const btnReserver = document.getElementById('btn-reserver');
                                            btnReserver.disabled = false;
                                            btnReserver.textContent = '🎫 Réserver maintenant';
                                        }
                                    </script>
                                <?php else: ?>
                                    <div class="login-prompt">
                                        <h3>🔒 Connectez-vous pour réserver</h3>
                                        <p>Vous devez être connecté pour effectuer une réservation</p>
                                        <a href="login.php" class="btn-login">Se connecter</a>
                                    </div>
                                <?php endif; ?>
                            </div>
        <?php
                        } else {
                            echo "<p style='text-align: center; padding: 40px;'>Film non trouvé</p>";
                        }
                    } catch (PDOException $e) {
                        echo "<p style='text-align: center; padding: 40px; color: red;'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                } else {
                    echo "<p style='text-align: center; padding: 40px;'>Aucun film sélectionné</p>";
                }
                break;

            case 'films': // Ancienne URL de compatibilité, rediriger vers film.php
                header("Location: film.php");
                exit;

            case 'reservation':
                if (isset($_SESSION['derniere_reservation'])) {
                    $res = $_SESSION['derniere_reservation'];
                    echo "<div class='ticket'>";
                    echo "<h3>🎫 Ticket de réservation</h3>";
                    echo "<p><strong>Numéro:</strong> #{$res['numero']}</p>";
                    echo "<p><strong>Film:</strong> {$res['film']}</p>";
                    echo "<p><strong>Nom:</strong> {$res['nom']}</p>";
                    echo "<p><strong>Places:</strong> {$res['places']} ({$res['type_place']})</p>";
                    echo "<p><strong>Prix unitaire:</strong> {$res['prix']} €</p>";
                    echo "<p><strong>Total:</strong> {$res['total']} €</p>";

                    $qrcode_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("Réservation #{$res['numero']} - {$res['film']} - {$res['nom']}");
                    echo "<div class='qrcode'>";
                    echo "<img src='{$qrcode_url}' alt='QR Code de la réservation'>";
                    echo "<p>Scannez ce QR Code à l'entrée</p>";
                    echo "</div>";
                    echo "</div>";
                } else {
                    echo "<p style='text-align: center;'>Aucune réservation récente. <a href='film.php'>Voir les films</a></p>";
                }
                break;

            case 'login':
                if (!isset($_SESSION['user'])) {
                    echo "<form method='POST' style='max-width: 400px; margin: 0 auto;'>";
                    echo "<h3>🔑 Connexion</h3>";
                    echo "<input type='email' name='email' placeholder='Email' required style='width: 100%; padding: 10px; margin: 10px 0;'>";
                    echo "<input type='password' name='password' placeholder='Mot de passe' required style='width: 100%; padding: 10px; margin: 10px 0;'>";
                    echo "<button name='login' style='width: 100%; padding: 12px; background-color: #2c3e50; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;'>Se connecter</button>";
                    echo "<p style='text-align: center; margin-top: 20px; color: #666;'><small>Email: admin@cinema.com | Mot de passe: 123</small></p>";
                    echo "</form>";
                } else {
                    echo "<p style='text-align: center;'>Vous êtes déjà connecté. <a href='index.php'>Retour à l'accueil</a></p>";
                }
                break;

            case 'compte':
                if (isset($_SESSION['user'])) {
                    echo "<h2>🏠 Mon Compte</h2>";
                    echo "<p>Bienvenue, " . htmlspecialchars($_SESSION['user']) . "</p>";

                    // Afficher les réservations de l'utilisateur
                    try {
                        $stmt = $pdo->prepare("
                            SELECT r.*, f.titre, f.affiche_url 
                            FROM reservation r 
                            JOIN film f ON r.film_id = f.id 
                            WHERE r.status = 'active' 
                            ORDER BY r.date_reservation DESC 
                            LIMIT 10
                        ");
                        $stmt->execute();
                        $reservations = $stmt->fetchAll();

                        if ($reservations) {
                            echo "<h3>Vos réservations récentes</h3>";
                            echo "<div style='display: grid; gap: 20px;'>";
                            foreach ($reservations as $reservation) {
                                echo "<div style='background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>";
                                echo "<h4>#{$reservation['numero']} - " . htmlspecialchars($reservation['titre']) . "</h4>";
                                echo "<p><strong>Nom:</strong> " . htmlspecialchars($reservation['nom']) . "</p>";
                                echo "<p><strong>Places:</strong> {$reservation['places']} ({$reservation['type_place']})</p>";
                                echo "<p><strong>Total:</strong> " . number_format($reservation['total'], 2) . " €</p>";
                                echo "<p><strong>Date:</strong> " . date('d/m/Y H:i', strtotime($reservation['date_reservation'])) . "</p>";
                                echo "</div>";
                            }
                            echo "</div>";
                        } else {
                            echo "<p>Aucune réservation trouvée.</p>";
                        }
                    } catch (PDOException $e) {
                        echo "<p>Impossible de charger les réservations.</p>";
                    }
                } else {
                    header("Location: login.php");
                    exit;
                }
                break;

            case 'horaires':
                // Page horaires depuis le premier document
                $sqlHoraires = "
                    SELECT f.id, f.titre, f.affiche_url, s.debut, s.fin, s.id_salle, s.prix, s.format
                    FROM film f
                    JOIN seance s ON f.id = s.id_film
                    WHERE s.debut > NOW() AND s.status = 'active'
                    ORDER BY f.titre, s.debut
                ";
                try {
                    $horaires = $pdo->query($sqlHoraires)->fetchAll(PDO::FETCH_ASSOC);

                    // Regrouper les horaires par film
                    function groupHoraires($rows)
                    {
                        $films = [];
                        foreach ($rows as $r) {
                            $id = $r['id'];
                            if (!isset($films[$id])) {
                                $films[$id] = [
                                    'id' => $id,
                                    'titre' => $r['titre'],
                                    'affiche' => $r['affiche_url'],
                                    'horaires' => []
                                ];
                            }
                            $films[$id]['horaires'][] = [
                                'debut' => date("H:i", strtotime($r['debut'])),
                                'fin' => date("H:i", strtotime($r['fin'])),
                                'salle' => $r['id_salle'],
                                'format' => $r['format'] ?? 'VF',
                                'prix' => $r['prix']
                            ];
                        }
                        return $films;
                    }

                    $films_horaires = groupHoraires($horaires);

                    echo "<h2>🕐 Horaires des Séances</h2>";
                    echo "<div style='display: grid; gap: 30px; margin-top: 25px;'>";

                    foreach ($films_horaires as $film) {
                        echo "<div style='background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); border: 1px solid #eaeaea;'>";
                        echo "<div style='font-size: 20px; color: #2c3e50; margin-bottom: 20px; font-weight: bold; border-bottom: 2px solid #d4af37; padding-bottom: 10px;'>" . htmlspecialchars($film['titre']) . "</div>";
                        echo "<div style='display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;'>";

                        foreach ($film['horaires'] as $horaire) {
                            echo "<div style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 15px; border-radius: 8px; text-align: center; transition: all 0.3s ease; cursor: pointer;'>";
                            echo "<div style='font-size: 18px; font-weight: bold; margin-bottom: 5px;'>" . $horaire['debut'] . " - " . $horaire['fin'] . "</div>";
                            echo "<div style='font-size: 12px; opacity: 0.9;'>Salle " . $horaire['salle'] . " | " . htmlspecialchars($horaire['format']) . "</div>";
                            echo "<div style='font-size: 14px; font-weight: bold; margin-top: 5px; background: rgba(255, 255, 255, 0.2); padding: 3px 8px; border-radius: 4px; display: inline-block;'>" . number_format($horaire['prix'], 2) . " €</div>";
                            echo "</div>";
                        }

                        echo "</div>";
                        echo "</div>";
                    }

                    echo "</div>";
                } catch (PDOException $e) {
                    echo "<p style='color: red;'>Erreur lors du chargement des horaires : " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                break;

            default:
                header("Location: index.php");
                exit;
        }
        ?>
    </main>

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
<?php
session_start();
include("testpdo.php");

$message = "";

// Système de routing simple
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = dirname($script_name);
$path = str_replace($base_path, '', $request_uri);
$path = ltrim($path, '/');

$path = parse_url($path, PHP_URL_PATH);

if ($path == '' || $path == 'index.php') {
    $page = 'accueil';
} elseif (strpos($path, 'film/') === 0) {
    $page = 'film';
    $film_id = substr($path, 5);
} elseif ($path == 'films') {
    $page = 'film';
} else {
    $page = $path;
}

if (isset($_GET['page'])) {
    $page = $_GET['page'];
}
if (isset($_GET['id'])) {
    $film_id = $_GET['id'];
}

// Login simple
if (isset($_POST['login'])) {
    if ($_POST['email'] == "admin@cinema.com" && $_POST['password'] == "123") {
        $_SESSION['user'] = $_POST['email'];
        $message = "✅ Connecté avec succès !";
    } else {
        $message = "❌ Email ou mot de passe incorrect";
    }
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    $message = "👋 Déconnecté avec succès !";
    header("Location: index.php");
    exit;
}

// Réservation
if (isset($_POST['reserver'])) {
    if (isset($_SESSION['user'])) {
        $film_id = $_POST['film_id'];
        $nom = $_POST['nom'];
        $places = $_POST['places'];
        $type_place = $_POST['type_place'];
        $seance_id = $_POST['seance_id'] ?? null;

        $stmt = $pdo->prepare("SELECT * FROM film WHERE id=?");
        $stmt->execute([$film_id]);
        $film = $stmt->fetch();

        if ($film) {
            // Utiliser des prix par défaut puisque votre table n'a pas de colonne prix
            $prix_normal = 10.00;
            $prix_pmr = 8.00;

            $prix_place = ($type_place == 'pmr') ? $prix_pmr : $prix_normal;
            $total = $prix_place * $places;
            $numero = rand(10000, 99999);

            try {
                $createTableSQL = "
                CREATE TABLE IF NOT EXISTS reservation (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    film_id INT NOT NULL,
                    seance_id INT NULL,
                    nom VARCHAR(255) NOT NULL,
                    places INT NOT NULL,
                    type_place ENUM('normal', 'pmr') DEFAULT 'normal',
                    numero INT NOT NULL UNIQUE,
                    prix_unitaire DECIMAL(5,2) NOT NULL,
                    total DECIMAL(7,2) NOT NULL,
                    date_reservation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('active', 'cancelled') DEFAULT 'active'
                )";
                $pdo->exec($createTableSQL);

                $insertSQL = "INSERT INTO reservation (film_id, seance_id, nom, places, type_place, numero, prix_unitaire, total) VALUES (?,?,?,?,?,?,?,?)";
                $pdo->prepare($insertSQL)->execute([$film_id, $seance_id, $nom, $places, $type_place, $numero, $prix_place, $total]);

                $_SESSION['derniere_reservation'] = [
                    'numero' => $numero,
                    'film' => $film['titre'],
                    'places' => $places,
                    'type_place' => $type_place,
                    'prix' => $prix_place,
                    'total' => $total,
                    'nom' => $nom
                ];

                $message = "🎉 Réservation confirmée ! N° $numero";
                header("Location: reserver.php");
                exit;
            } catch (PDOException $e) {
                $message = "❌ Erreur lors de la réservation : " . $e->getMessage();
            }
        } else {
            $message = "❌ Film non trouvé";
        }
    } else {
        $message = "⚠️ Connectez-vous d'abord !";
        header("Location: login.php");
        exit;
    }
}

// Fonction pour formater correctement les URLs d'affiches
function formatAfficheUrl($url)
{
    if (empty($url)) {
        return 'https://via.placeholder.com/220x300/2c3e50/ffffff?text=Affiche+non+disponible';
    }

    // Si l'URL commence par un backslash, c'est probablement un chemin local
    if (strpos($url, '\\') === 0 || strpos($url, '/') === 0) {
        // Essayer de créer une URL absolue à partir du chemin relatif
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        return $base_url . str_replace('\\', '/', $url);
    }

    // Si c'est une URL complète, la retourner telle quelle
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    // Sinon, considérer que c'est un nom de fichier et essayer de le résoudre
    return 'https://via.placeholder.com/220x300/2c3e50/ffffff?text=' . urlencode($url);
}

// CORRECTION: Adapter les requêtes à votre structure de base de données
// --- Avant-premières - VERSION CORRIGÉE
$avant = [];
try {
    $sqlAvant = "
        SELECT id, titre, affiche_url, description
        FROM film
        WHERE avant_premiere = 1
        ORDER BY date_ajout DESC
        LIMIT 4
    ";
    $avant = $pdo->query($sqlAvant)->fetchAll(PDO::FETCH_ASSOC);

    echo "<!-- DEBUG Avant-premières: " . count($avant) . " films trouvés -->";
} catch (PDOException $e) {
    echo "<!-- DEBUG ERROR Avant-premières: " . $e->getMessage() . " -->";
    $avant = [];
}

// --- Films à l'affiche - VERSION CORRIGÉE
$affiche = [];
try {
    $sqlAffiche = "
        SELECT id, titre, affiche_url, description
        FROM film
        WHERE (avant_premiere = 0 OR avant_premiere IS NULL)
        ORDER BY date_ajout DESC
        LIMIT 8
    ";
    $affiche = $pdo->query($sqlAffiche)->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- DEBUG Films à l'affiche: " . count($affiche) . " films trouvés -->";
} catch (PDOException $e) {
    echo "<!-- DEBUG ERROR Films à l'affiche: " . $e->getMessage() . " -->";
    $affiche = [];
}

// --- Films du mercredi - VERSION CORRIGÉE
$mercredi = [];
try {
    $sqlMercredi = "
        SELECT id, titre, affiche_url, description
        FROM film
        WHERE DAYOFWEEK(date_ajout) = 4
        ORDER BY date_ajout DESC
        LIMIT 6
    ";
    $mercredi = $pdo->query($sqlMercredi)->fetchAll(PDO::FETCH_ASSOC);

    // Si pas de résultats, prendre des films au hasard
    if (empty($mercredi)) {
        $sqlMercredi = "
            SELECT id, titre, affiche_url, description
            FROM film
            ORDER BY RAND()
            LIMIT 6
        ";
        $mercredi = $pdo->query($sqlMercredi)->fetchAll(PDO::FETCH_ASSOC);
    }

    echo "<!-- DEBUG Films mercredi: " . count($mercredi) . " films trouvés -->";
} catch (PDOException $e) {
    echo "<!-- DEBUG ERROR Films mercredi: " . $e->getMessage() . " -->";
    $mercredi = [];
}

// Fonction pour récupérer les séances d'un film
function getSeancesForFilm($pdo, $film_id)
{
    try {
        // Vérifier si la table seance existe
        $tableExists = $pdo->query("SHOW TABLES LIKE 'seance'")->fetch();

        if ($tableExists) {
            // Requête adaptée à votre structure de base
            $stmt = $pdo->prepare("
                SELECT 
                    s.id,
                    s.debut, 
                    s.fin,
                    s.prix,
                    s.format,
                    s.id_salle,
                    sa.reference as salle_nom
                FROM seance s 
                LEFT JOIN salle sa ON s.id_salle = sa.id
                WHERE s.id_film = ? 
                AND s.status = 'active' 
                AND DATE(s.debut) >= CURDATE()
                ORDER BY s.debut 
                LIMIT 20
            ");
            $stmt->execute([$film_id]);
            $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $horaires = [];
            foreach ($seances as $seance) {
                // Formater l'horaire avec plus d'infos
                $horaire = date("H:i", strtotime($seance['debut']));
                
                // Ajouter le format si ce n'est pas "Standard"
                if ($seance['format'] && $seance['format'] !== 'Standard') {
                    $horaire .= ' (' . $seance['format'] . ')';
                }
                
                // Ajouter le prix si disponible
                if ($seance['prix'] && $seance['prix'] > 0) {
                    $horaire .= ' - ' . number_format($seance['prix'], 2) . '€';
                }
                
                $horaires[] = $horaire;
            }

            // Si pas de séances réelles, créer des horaires fictifs pour la démo
            if (empty($horaires)) {
                $horaires = ['14:00', '16:30', '19:00', '21:30'];
            }

            return $horaires;
        } else {
            // Table n'existe pas, retourner des horaires par défaut
            return ['14:00', '16:30', '19:00', '21:30'];
        }
    } catch (PDOException $e) {
        // En cas d'erreur, retourner des horaires par défaut
        error_log("Erreur lors de la récupération des séances : " . $e->getMessage());
        return ['14:00', '16:30', '19:00', '21:30'];
    }
}
// Préparer les données pour l'affichage
function prepareFilmsWithSeances($pdo, $films)
{
    $result = [];
    foreach ($films as $film) {
        $film['seances'] = getSeancesForFilm($pdo, $film['id']);
        $result[] = $film;
    }
    return $result;
}

$avant = prepareFilmsWithSeances($pdo, $avant);
$affiche = prepareFilmsWithSeances($pdo, $affiche);
$mercredi = prepareFilmsWithSeances($pdo, $mercredi);

// Fonction pour générer les URLs vers les fichiers PHP
function getPhpUrl($page, $id = null)
{
    switch ($page) {
        case 'accueil':
            return 'index.php';
        case 'film':
            return $id ? "film.php?id=" . $id : 'films.php';
        case 'login':
            return 'login.php';
        case 'reserver':
        case 'reservation':
            return 'reserver.php';
        case 'compte':
            return 'moncompte.php';
        default:
            return $page . '.php';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Cinéphoria - Votre cinéma de référence</title>
    <style>
        /* Tous vos styles CSS existants restent identiques */
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

        nav a:hover {
            background-color: #d4af37;
            color: #1a2a3a;
        }

        main {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h2 {
            color: #2c3e50;
            padding-left: 15px;
            margin-top: 40px;
            border-left: 4px solid #d4af37;
            font-size: 24px;
        }

        .films {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 1px solid #eaeaea;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            background: #f0f0f0;
        }

        .info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .info h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #2c3e50;
            min-height: 40px;
        }

        .desc {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
            flex-grow: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .seances {
            font-size: 13px;
            color: #444;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        .seance-time {
            display: inline-block;
            background-color: #2c3e50;
            color: white;
            padding: 4px 8px;
            margin: 3px;
            border-radius: 3px;
            font-size: 12px;
        }

        .btn-details {
            background-color: #d4af37;
            color: #1a2a3a;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s ease;
            font-size: 13px;
        }

        .btn-details:hover {
            background-color: #c19b2e;
        }

        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-films {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .no-films h3 {
            color: #666;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .no-films p {
            color: #888;
            font-size: 1.1em;
        }

        footer {
            background: #2c3e50;
            color: #e0e0e0;
            padding: 25px 0;
            margin-top: 60px;
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
            .films {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
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
            }

            nav a {
                margin: 0 8px;
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
            <a href="moncompte.php">🏠 Mon Compte</a>
            <a href="films.php">🎭 Films</a>
            <a href="reserver.php">🎫 Ma Réservation</a>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="?logout=1">🔒 Déconnexion</a>
            <?php else: ?>
                <a href="login.php">🔑 Connexion</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <?php if ($message): ?>
            <div class="message <?= strpos($message, '✅') !== false || strpos($message, '🎉') !== false ? 'success' : 'error' ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php
        switch ($page) {
            case 'accueil':
    // SECTION AVANT-PREMIERES
    echo "<h2>🎬 Avant-Premières</h2>";
    if (!empty($avant)) {
        echo "<div class='films'>";
        foreach ($avant as $film) {
            echo "<div class='card'>";
            
            $image_url = formatAfficheUrl($film['affiche_url']);
            echo "<img src='" . htmlspecialchars($image_url) . "' alt='" . htmlspecialchars($film['titre']) . "' onerror=\"this.src='https://via.placeholder.com/220x300/2c3e50/ffffff?text=Film'\">";
            
            echo "<div class='info'>";
            echo "<h3>" . htmlspecialchars($film['titre']) . "</h3>";
            echo "<div class='desc'>" . (!empty($film['description']) ? htmlspecialchars(substr($film['description'], 0, 150)) . "..." : "Description non disponible") . "</div>";
            echo "<div class='seances'>";
            
            $seancesToShow = array_slice($film['seances'], 0, 2);
            foreach ($seancesToShow as $seance) {
                echo "<span class='seance-time'>" . $seance . "</span>";
            }
            echo "</div>";
            // CHANGEMENT ICI : films.php -> film.php
            echo "<a href='film.php?id=" . $film['id'] . "' class='btn-details'>Voir détails</a>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    }
                    echo "</div>";
                } 
                else 
                {
                    echo "<div class='no-films'>";
                    echo "<h3>Aucune avant-première disponible</h3>";
                    echo "<p>Les avant-premières seront bientôt annoncées !</p>";
                    echo "</div>";
                }
            echo "<h2>🎬 Films à l'affiche</h2>";
                if (!empty($affiche)) {
            echo "<div class='films'>";
        foreach ($affiche as $film) {
            echo "<div class='card'>";
            
            $image_url = formatAfficheUrl($film['affiche_url']);
            echo "<img src='" . htmlspecialchars($image_url) . "' alt='" . htmlspecialchars($film['titre']) . "' onerror=\"this.src='https://via.placeholder.com/220x300/2c3e50/ffffff?text=Film'\">";
            
            echo "<div class='info'>";
            echo "<h3>" . htmlspecialchars($film['titre']) . "</h3>";
            echo "<div class='desc'>" . (!empty($film['description']) ? htmlspecialchars(substr($film['description'], 0, 150)) . "..." : "Description non disponible") . "</div>";
            echo "<div class='seances'>";
            
            $seancesToShow = array_slice($film['seances'], 0, 4);
            foreach ($seancesToShow as $seance) {
                echo "<span class='seance-time'>" . $seance . "</span>";
            }
            echo "</div>";
            // CHANGEMENT ICI : films.php -> film.php
            echo "<a href='film.php?id=" . $film['id'] . "' class='btn-details'>Voir détails</a>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    }
                } else {
                    echo "<div class='no-films'>";
                    echo "<h3>Aucun film à l'affiche</h3>";
                    echo "<p>Nos prochaines programmations arrivent bientôt !</p>";
                    echo "</div>";
                }

                // SECTION FILMS DU MERCREDI
                echo "<h2>🎬 Sélection de la semaine</h2>";
    if (!empty($mercredi)) {
        echo "<div class='films'>";
        foreach ($mercredi as $film) {
            echo "<div class='card'>";
            
            $image_url = formatAfficheUrl($film['affiche_url']);
            echo "<img src='" . htmlspecialchars($image_url) . "' alt='" . htmlspecialchars($film['titre']) . "' onerror=\"this.src='https://via.placeholder.com/220x300/2c3e50/ffffff?text=Film'\">";
            
            echo "<div class='info'>";
            echo "<h3>" . htmlspecialchars($film['titre']) . "</h3>";
            echo "<div class='desc'>" . (!empty($film['description']) ? htmlspecialchars(substr($film['description'], 0, 150)) . "..." : "Description non disponible") . "</div>";
            echo "<div class='seances'>";
            
            $seancesToShow = array_slice($film['seances'], 0, 6);
            foreach ($seancesToShow as $seance) {
                echo "<span class='seance-time'>" . $seance . "</span>";
            }
            echo "</div>";
            // CHANGEMENT ICI : films.php -> film.php
            echo "<a href='film.php?id=" . $film['id'] . "' class='btn-details'>Voir détails</a>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    }
    break;
                // Message d'aide si aucun film n'est trouvé
                if (empty($avant) && empty($affiche) && empty($mercredi)) {
                    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 20px; border-radius: 8px; margin: 30px 0; text-align: center;'>";
                    echo "<h3>🎬 Base de données vide</h3>";
                    echo "<p><strong>Il semble que votre base de données ne contienne aucun film.</strong></p>";
                    echo "<p>Pour afficher des films, vous devez :</p>";
                    echo "<ol style='text-align: left; display: inline-block;'>";
                    echo "<li>Vérifier que la table 'film' existe dans votre base de données</li>";
                    echo "<li>Insérer des films avec les colonnes : id, titre, description, affiche_url, prix, avant_premiere, date_ajout</li>";
                    echo "<li>Optionnel : créer une table 'seance' pour les horaires</li>";
                    echo "</ol>";
                    echo "</div>";
                }
                break;

            case 'login':
                if (!isset($_SESSION['user'])) {
                    echo "<form method='POST' style='max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);'>";
                    echo "<h3 style='text-align: center; margin-bottom: 25px; color: #2c3e50;'>🔑 Connexion</h3>";
                    echo "<input type='email' name='email' placeholder='Email' required style='width: 100%; padding: 15px; margin: 10px 0; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1em;'>";
                    echo "<input type='password' name='password' placeholder='Mot de passe' required style='width: 100%; padding: 15px; margin: 10px 0; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1em;'>";
                    echo "<button name='login' style='width: 100%; padding: 15px; background: linear-gradient(135deg, #d4af37 0%, #bf953f 100%); color: #1a2a3a; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1.1em; margin-top: 10px;'>Se connecter</button>";
                    echo "<p style='text-align: center; margin-top: 20px; color: #666; background: #f8f9fa; padding: 15px; border-radius: 8px;'><small><strong>Compte de test :</strong><br>Email: admin@cinema.com<br>Mot de passe: 123</small></p>";
                    echo "</form>";
                } else {
                    echo "<p style='text-align: center; padding: 40px;'>Vous êtes déjà connecté. <a href='index.php'>Retour à l'accueil</a></p>";
                }
                break;

            default:
                header("Location: index.php");
                exit;
        }
        ?>
    </main>
<?php include 'includes/header.php';  include 'api_helper.php';  if (!isset($_SESSION['utilisateur'])) {     header('Location: login.php');     exit; }  $userId = $_SESSION['utilisateur']['id']; $reponse = appelerApi('/utilisateurs/' . $userId); $infos = $reponse['success'] ? $reponse['data'] : null; ?>  <h1 class="mb-4">Mon profil</h1>  <?php if ($infos): ?> <div class="card" style="max-width: 500px;">     <div class="card-body">         <h5 class="card-title">Informations utilisateur</h5>         <p><strong>Nom :</strong> <?= htmlspecialchars($infos['nom']) ?></p>         <p><strong>Prénom :</strong> <?= htmlspecialchars($infos['prenom']) ?></p>         <p><strong>Email :</strong> <?= htmlspecialchars($infos['email']) ?></p>         <p><strong>Rôle :</strong> <?= htmlspecialchars($infos['role']) ?></p>         <p><strong>Email confirmé :</strong> <?= $infos['email_confirme'] ? 'Oui' : 'Non' ?></p>     </div> </div> <a href="changer_mdp.php">Changer mot de passe</a> <?php else: ?> <div class="alert alert-danger"><?php echo($reponse['message'] ?? 'Impossible de charger les informations utilisateur.');?></div> <?php endif; ?>  <?php include 'includes/footer.php'; ?> SELECT * FROM reset_mdp

Profilage

[ Éditer en ligne ] [ Éditer ] [ Expliquer SQL ] [ Créer le code source PHP ] [ Actualiser ]

idid_utilisateurtokendate_expiration FieldTypeNullKeyDefaultExtraidint(11)NOPRINULLauto_incrementnomvarchar(100)YESNULLprenomvarchar(100)YESNULLemailvarchar(150)NOUNINULLmot_de_passevarchar(255)NONULLemail_confirmetinyint(1)YES0roleenum('utilisateur','employe','admin')NONULLdate_creationdatetimeYEScurrent_timestamp()confirmation_tokenvarchar(255)NONULLchanger_mdptinyint(1)NONULLmot_de_passe_temporairetinyint(1)YES0. avec toutes ces infos tu peux m'harmoniser le tout stp 
film;php
session_start();

try {
    $pdo = new PDO("mysql:host=sql7.freesqldatabase.com;dbname=sql7798672;charset=utf8mb4", 'sql7798672', 'ndviH1KDRs', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Message si redirection après login
$info_message = null;
if (isset($_GET['login']) && $_GET['login'] === 'success') {
    $info_message = "✅ Vous êtes connecté(e) avec succès !";
}

// Gestion de la recherche
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

// REQUÊTE AVEC RECHERCHE OPTIONNELLE
if (!empty($recherche)) {
    $sql = "SELECT * FROM film WHERE titre LIKE :recherche ORDER BY titre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':recherche' => '%' . $recherche . '%']);
    $films = $stmt->fetchAll();
} else {
    $sql = "SELECT * FROM film ORDER BY titre";
    $films = $pdo->query($sql)->fetchAll();
}

// Récupérer toutes les séances avec informations des salles ET films
$seances_par_film = [];
$seances_sql = "
    SELECT 
        s.*, 
        sa.reference as salle_nom,
        sa.nb_places,
        sa.nb_places_pmr,
        f.avant_premiere
    FROM seance s 
    JOIN salle sa ON s.id_salle = sa.id 
    JOIN film f ON s.id_film = f.id
    WHERE s.status = 'active' 
    ORDER BY s.id_film, s.debut
";
$all_seances = $pdo->query($seances_sql)->fetchAll();

// Organiser les séances par film
foreach ($all_seances as $seance) {
    $film_id = $seance['id_film'];
    if (!isset($seances_par_film[$film_id])) {
        $seances_par_film[$film_id] = [];
    }
    $seances_par_film[$film_id][] = $seance;
}

$prenom = $_SESSION['prenom'] ?? null;
$connecte = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Cinephoria - Tous les films</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .search-bar {
            background-color: #1a237e;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            color: white;
        }

        .film-card {
            transition: transform 0.3s ease;
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }

        .film-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background-color: #1a237e;
            border-color: #1a237e;
        }

        .btn-primary:hover {
            background-color: #283593;
            border-color: #283593;
        }

        .navbar-dark {
            background-color: #1a237e !important;
        }

        .price-badge {
            background-color: #2e7d32;
            color: white;
            font-weight: 500;
        }

        .btn-outline-primary {
            border-color: #1a237e;
            color: #1a237e;
        }

        .btn-outline-primary:hover {
            background-color: #1a237e;
            color: white;
        }

        .seance-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #1a237e;
            transition: all 0.3s ease;
        }

        .seance-item:hover {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left-color: #2e7d32;
        }

        .format-badge {
            background-color: #ff5722;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
        }

        .no-seances {
            background-color: #fff3e0;
            border: 1px solid #ffb74d;
            color: #e65100;
        }

        .film-info {
            background: linear-gradient(135deg, #1a237e 0%, #3949ab 100%);
            color: white;
        }
    </style>
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">🎬 Cinephoria</a>
            <div class="ms-auto">
                <?php if ($connecte): ?>
                    <span class="text-white me-3">👋 Bonjour <?= htmlspecialchars($prenom) ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm">Connexion</a>
                    <a href="register.php" class="btn btn-light btn-sm ms-2">Créer un compte</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <?php if ($info_message): ?>
            <div class="alert alert-success text-center"><?= htmlspecialchars($info_message) ?></div>
        <?php endif; ?>

        <!-- BARRE DE RECHERCHE -->
        <div class="search-bar">
            <h2 class="text-center mb-3">🔍 Rechercher un film</h2>
            <form method="GET" action="">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" name="recherche" class="form-control form-control-lg"
                                placeholder="Trouvez votre film..." value="<?= htmlspecialchars($recherche) ?>">
                            <button class="btn btn-light btn-lg" type="submit">
                                <i class="bi bi-search"></i> Rechercher
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <?php if (!empty($recherche)): ?>
                <div class="text-center mt-3">
                    <span class="badge bg-light text-dark fs-6">
                        Recherche : "<?= htmlspecialchars($recherche) ?>"
                        <a href="films.php" class="text-danger ms-2 text-decoration-none">✕</a>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <h1 class="mb-4 text-center text-dark">
            🎬 <?= empty($recherche) ? 'Tous nos films' : 'Résultats de recherche' ?>
            <small class="text-muted d-block mt-2"><?= count($films) ?> film(s) trouvé(s)</small>
        </h1>

        <?php if (empty($films)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-search display-4 text-muted"></i>
                <h4 class="mt-3">Aucun film trouvé</h4>
                <p class="mb-0">Aucun film ne correspond à votre recherche "<?= htmlspecialchars($recherche) ?>".</p>
                <a href="films.php" class="btn btn-primary mt-3">Voir tous les films</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($films as $film): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow film-card">
                            <!-- En-tête du film -->
                            <div class="film-info p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($film['titre']) ?></h6>
                                        <?php if ($film['age_minimum']): ?>
                                            <span class="badge bg-warning text-dark">+<?= $film['age_minimum'] ?> ans</span>
                                        <?php endif; ?>
                                        <?php if ($film['coup_de_coeur']): ?>
                                            <span class="badge bg-danger ms-1">💖 Coup de cœur</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($film['note_moyenne']): ?>
                                        <div class="text-end">
                                            <span class="badge bg-success">⭐ <?= $film['note_moyenne'] ?>/5</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- LIEN VERS FILM.PHP SUR L'IMAGE -->
                            <a href="film.php?id=<?= $film['id'] ?>" class="text-decoration-none">
                                <?php if (!empty($film['affiche_url'])): ?>
                                    <img src="<?= htmlspecialchars($film['affiche_url']) ?>" class="card-img-top"
                                        alt="Affiche de <?= htmlspecialchars($film['titre']) ?>"
                                        style="height: 300px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary text-white text-center p-5 d-flex align-items-center justify-content-center" style="height: 300px;">
                                        <div>
                                            <i class="bi bi-camera-reels" style="font-size: 2rem;"></i>
                                            <p class="mt-2 mb-0">Pas d'affiche</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <!-- Description courte -->
                            <?php if (!empty($film['description'])): ?>
                                <div class="card-body pb-0">
                                    <p class="card-text small text-muted">
                                        <?= htmlspecialchars(substr($film['description'], 0, 120)) ?>
                                        <?= strlen($film['description']) > 120 ? '...' : '' ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- SÉANCES DISPONIBLES -->
                            <div class="card-footer bg-white pt-0">
                                <?php if (isset($seances_par_film[$film['id']]) && !empty($seances_par_film[$film['id']])): ?>
                                    <h6 class="mb-3 text-dark">
                                        <i class="bi bi-calendar-event"></i>
                                        Séances programmées (<?= count($seances_par_film[$film['id']]) ?>)
                                    </h6>
                                    <div class="seances-list" style="max-height: 300px; overflow-y: auto;">
                                        <?php foreach ($seances_par_film[$film['id']] as $index => $seance): ?>
                                            <div class="seance-item p-3 mb-2 rounded <?=
                                                                                        // Ajouter une classe pour les séances passées
                                                                                        (strtotime($seance['debut']) < time()) ? 'opacity-50' : ''
                                                                                        ?>">
                                                <div class="row align-items-center">
                                                    <div class="col-8">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <strong class="text-dark me-2">
                                                                <?= date('d/m/Y', strtotime($seance['debut'])) ?>
                                                            </strong>
                                                            <?php if ($seance['format'] && $seance['format'] !== 'Standard'): ?>
                                                                <span class="badge format-badge"><?= htmlspecialchars($seance['format']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <i class="bi bi-clock"></i>
                                                            <?= date('H:i', strtotime($seance['debut'])) ?> - <?= date('H:i', strtotime($seance['fin'])) ?>
                                                            <br>
                                                            <i class="bi bi-geo-alt"></i>
                                                            Salle <?= htmlspecialchars($seance['salle_nom']) ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-4 text-end">
                                                        <?php if (!empty($seance['prix']) && $seance['prix'] > 0): ?>
                                                            <div class="mb-2">
                                                                <span class="badge price-badge fs-6"><?= number_format($seance['prix'], 2) ?> €</span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (strtotime($seance['debut']) < time()): ?>
                                                            <span class="badge bg-secondary">Séance passée</span>
                                                        <?php elseif ($seance['status'] !== 'active'): ?>
                                                            <span class="badge bg-warning text-dark">
                                                                <?= ucfirst($seance['status']) ?>
                                                            </span>
                                                        <?php elseif ($connecte): ?>
                                                            <a href="reservation.php?id_seance=<?= $seance['id'] ?>"
                                                                class="btn btn-primary btn-sm w-100">
                                                                <i class="bi bi-ticket-perforated"></i> Réserver
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="login.php?redirect=reservation.php?id_seance=<?= $seance['id'] ?>"
                                                                class="btn btn-outline-primary btn-sm w-100">
                                                                <i class="bi bi-person-check"></i> Se connecter
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if (isset($seance['avant_premiere']) && $seance['avant_premiere']): ?>
                                                    <div class="mt-2">
                                                        <span class="badge bg-info">🎉 Avant-première</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-seances p-3 rounded text-center">
                                        <i class="bi bi-calendar-x display-6"></i>
                                        <p class="mb-2 mt-2"><strong>Aucune séance programmée</strong></p>
                                        <small>Ce film n'a pas de séances disponibles pour le moment.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <?php require_once 'footer.php'; ?>
            <p class="mb-0">© 2025 Cinephoria. Tous droits réservés. 🎬</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
mdpoublie.php
</html>
<?php
session_start();
require_once 'testpdo.php'; // ta connexion PDO

$message = '';
$show_form = false;

// Vérifier que le token est présent
if (!isset($_GET['token'])) {
    die("Token manquant.");
}
$token = $_GET['token'];

// Vérifier le token en base
$stmt = $pdo->prepare("SELECT * FROM reset_mdp WHERE token = ?");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset || strtotime($reset['date_expiration']) < time()) {
    die("Token invalide ou expiré.");
}

$show_form = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $message = "Veuillez remplir tous les champs.";
    } elseif ($password !== $confirm_password) {
        $message = "Les mots de passe ne correspondent pas.";
    } else {
        // Mettre à jour le mot de passe
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id = ?");
        $stmt->execute([$hash, $reset['id_utilisateur']]);

        // Supprimer le token pour éviter réutilisation
        $stmt = $pdo->prepare("DELETE FROM reset_mdp WHERE token = ?");
        $stmt->execute([$token]);

        $message = "Votre mot de passe a bien été réinitialisé. Vous pouvez maintenant vous connecter.";
        $show_form = false;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Réinitialisation du mot de passe - Cinephoria</title>
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <h1>Réinitialisation du mot de passe</h1>

    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($show_form): ?>
        <form method="post">
            <label for="password">Nouveau mot de passe :</label><br />
            <input type="password" name="password" id="password" required /><br /><br />
            <label for="confirm_password">Confirmer le mot de passe :</label><br />
            <input type="password" name="confirm_password" id="confirm_password" required /><br /><br />
            <button type="submit">Réinitialiser le mot de passe</button>
        </form>
    <?php endif; ?>

    <p>Vous n'avez pas de compte ? <a href="register.php">Inscrivez-vous</a></p>
    <p><a href="login.php">Retour à la connexion</a></p>
    <p><a href="index.php">Accueil</a></p>
</body>

</html>
reset_mdp.php
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
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
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

        <!-- Affichage conditionnel selon la présence du token -->
        <div id="token-missing">
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Token manquant. Veuillez utiliser le lien reçu par email.
            </div>

            <div class="alert alert-info" role="alert">
                <h5><i class="fas fa-info-circle me-2"></i>Que faire maintenant ?</h5>
                <p class="mb-1">1. Vérifiez votre boîte email pour le lien de réinitialisation</p>
                <p class="mb-1">2. Assurez-vous d'utiliser le lien complet reçu</p>
                <p class="mb-0">3. Si vous n'avez pas reçu d'email, vérifiez vos spams</p>
            </div>

            <form>
                <div class="mb-3">
                    <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="newPassword" disabled>
                        <span class="password-toggle" id="toggleNewPassword">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-rules">
                        Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="confirmPassword" disabled>
                        <span class="password-toggle" id="toggleConfirmPassword">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>

                <button type="button" class="btn btn-cinephoria w-100 mb-3" disabled>
                    <i class="fas fa-lock me-2"></i>Réinitialiser le mot de passe
                </button>
            </form>

            <div class="divider"><span>OU</span></div>

            <div class="text-center">
                <a href="mdp_oublie.php" class="btn btn-outline-gold">
                    <i class="fas fa-envelope me-2"></i>Demander un nouveau lien
                </a>
            </div>
        </div>

        <!-- Section qui serait affichée si le token était présent -->
        <div id="token-present" style="display: none;">
            <div class="alert alert-info" role="alert">
                <i class="fas fa-user me-2"></i>
                Réinitialisation pour : example@email.com
            </div>

            <form>
                <div class="mb-3">
                    <label for="newPassword2" class="form-label">Nouveau mot de passe</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="newPassword2">
                        <span class="password-toggle" onclick="togglePassword('newPassword2', this)">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-rules">
                        Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirmPassword2" class="form-label">Confirmer le mot de passe</label>
                    <div class="position-relative">
                        <input type="password" class="form-control" id="confirmPassword2">
                        <span class="password-toggle" onclick="togglePassword('confirmPassword2', this)">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>

                <button type="button" class="btn btn-cinephoria w-100 mb-3">
                    <i class="fas fa-lock me-2"></i>Réinitialiser le mot de passe
                </button>
            </form>
        </div>

        <div class="card-footer">
            <p class="mb-0">Revenir à la <a href="login.php" class="link-gold">page de connexion</a></p>
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

        document.getElementById('toggleNewPassword').addEventListener('click', function() {
            togglePassword('newPassword', this);
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            togglePassword('confirmPassword', this);
        });

        // Vérifier si un token est présent dans l'URL
        function getParameterByName(name, url = window.location.href) {
            name = name.replace(/[\[\]]/g, '\\$&');
            const regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
                results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return '';
            return decodeURIComponent(results[2].replace(/\+/g, ' '));
        }

        const token = getParameterByName('token');
        if (token) {
            // Si un token est présent, on pourrait basculer vers le formulaire actif
            // document.getElementById('token-missing').style.display = 'none';
            // document.getElementById('token-present').style.display = 'block';
        }
    </script>
</body>

</html>
reservation.php
<?php
session_start();
require 'testpdo.php';

// Réinitialiser les données de réservation à chaque nouvelle visite
unset($_SESSION['reservation_data']);

// Récupérer les paramètres GET - Gestion des deux formats de paramètres
$film_id = isset($_GET['film_id']) ? (int)$_GET['film_id'] : 0;
$seance_id = isset($_GET['seance_id']) ? (int)$_GET['seance_id'] : 0;

// Si le paramètre seance_id n'est pas trouvé, chercher id_seance (pour compatibilité)
if ($seance_id <= 0 && isset($_GET['id_seance'])) {
    $seance_id = (int)$_GET['id_seance'];
}

// Si film_id est 0 mais seance_id est valide, récupérer le film_id depuis la séance
if ($film_id <= 0 && $seance_id > 0) {
    $stmt = $pdo->prepare("SELECT id_film FROM seance WHERE id = ?");
    $stmt->execute([$seance_id]);
    $seance_data = $stmt->fetch();

    if ($seance_data) {
        $film_id = (int)$seance_data['id_film'];
    }
}

// VÉRIFICATION CRITIQUE - Si les IDs sont manquants
if ($film_id <= 0 || $seance_id <= 0) {
    echo "<div style='padding: 40px; text-align: center; background: #f8f9fa; min-height: 100vh;'>";
    echo "<div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);'>";
    echo "<h2 style='color: #dc3545; margin-bottom: 20px;'><i class='fas fa-exclamation-triangle'></i> Séance non sélectionnée</h2>";
    echo "<p style='margin-bottom: 20px;'>Veuillez retourner à la page des films et sélectionner une séance.</p>";
    echo "<p style='margin-bottom: 20px; color: #666;'><small>Paramètres reçus: film_id=$film_id, seance_id=$seance_id</small></p>";
    echo "<a href='films.php' style='background: #d4af37; color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold;'>";
    echo "<i class='fas fa-arrow-left'></i> Retour aux films";
    echo "</a>";
    echo "</div></div>";
    exit;
}

// Stocker les IDs en session pour les pages suivantes
$_SESSION['selected_film_id'] = $film_id;
$_SESSION['selected_seance_id'] = $seance_id;

// Récupérer les informations utilisateur depuis la session (si connecté)
$user_nom = $_SESSION['user']['nom'] ?? '';
$user_prenom = $_SESSION['user']['prenom'] ?? '';
$user_email = $_SESSION['user']['email'] ?? '';
$user_id = $_SESSION['user']['id'] ?? null;

// Récupérer les informations du film et séance
$film_info = [];
$seance_info = [];

// Vérifier que le film existe
$stmt = $pdo->prepare("SELECT id, titre, age_minimum FROM film WHERE id = ?");
$stmt->execute([$film_id]);
$film_info = $stmt->fetch();

if (!$film_info) {
    die("Erreur: Film introuvable avec l'ID $film_id");
}

// Vérifier que la séance existe et est liée au film
$stmt = $pdo->prepare("
    SELECT s.*, f.titre as film_titre, sa.reference as salle_nom, sa.nb_places, sa.nb_places_pmr 
    FROM seance s 
    JOIN film f ON s.id_film = f.id 
    JOIN salle sa ON s.id_salle = sa.id 
    WHERE s.id = ? AND s.id_film = ?
");
$stmt->execute([$seance_id, $film_id]);
$seance_info = $stmt->fetch();

if (!$seance_info) {
    die("Erreur: Séance introuvable ou ne correspondant pas au film sélectionné.");
}

// Récupérer les places de la salle avec leur statut
$places_disponibles = [];
$places_occupees = [];
$places_pmr = [];

if ($seance_id > 0 && !empty($seance_info)) {
    // Récupérer toutes les places de la salle
    $stmt = $pdo->prepare("SELECT * FROM place WHERE id_salle = ? ORDER BY reference");
    $stmt->execute([$seance_info['id_salle']]);
    $all_places = $stmt->fetchAll();

    // Organiser les places par statut
    foreach ($all_places as $place) {
        $places_disponibles[] = [
            'reference' => $place['reference'],
            'id' => $place['id'],
            'est_pmr' => $place['est_pmr']
        ];

        if ($place['est_pmr']) {
            $places_pmr[] = $place['reference'];
        }
    }

    // Récupérer les places déjà réservées pour cette séance
    try {
        $stmt = $pdo->prepare("
            SELECT p.reference 
            FROM commande c
            JOIN billet b ON c.id = b.id_commande
            JOIN place p ON b.id_place = p.id
            WHERE c.id_seance = ?
        ");
        $stmt->execute([$seance_id]);
        $reservations = $stmt->fetchAll();

        foreach ($reservations as $reservation) {
            $places_occupees[] = $reservation['reference'];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération places occupées: " . $e->getMessage());
    }
}

// Générer un code de réservation unique
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
        /* [VOTRE CSS EXISTANT - À CONSERVER] */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(45deg, #1a2a3a, #2c3e50);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: #d4af37;
            border-radius: 2px;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #d4af37;
        }

        .film-info {
            background: linear-gradient(45deg, #2c3e50, #1a2a3a);
            color: white;
            padding: 25px;
            border-bottom: 3px solid #d4af37;
        }

        .film-info h2 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #d4af37;
        }

        .seance-details {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            align-items: center;
        }

        .detail-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 15px;
            border-radius: 10px;
            border-left: 4px solid #d4af37;
        }

        .content {
            padding: 40px;
        }

        .form-section {
            margin-bottom: 40px;
        }

        .form-section h3 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #d4af37;
        }

        .user-info-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid #dee2e6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        input[type="text"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }

        .places-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            border: 2px solid #e9ecef;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 12px 20px;
            border-radius: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            font-weight: 500;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid #333;
        }

        .legend-color.disponible {
            background: #28a745;
            border-color: #28a745;
        }

        .legend-color.pmr {
            background: #ffc107;
            border-color: #ffc107;
        }

        .legend-color.occupe {
            background: #dc3545;
            border-color: #dc3545;
        }

        .legend-color.selected {
            background: #2c3e50;
            border-color: #2c3e50;
        }

        .screen {
            background: linear-gradient(135deg, #2c3e50, #1a2a3a);
            color: white;
            padding: 15px;
            text-align: center;
            margin: 0 auto 30px;
            max-width: 400px;
            border-radius: 50px 50px 10px 10px;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .sieges-container {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 8px;
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .siege {
            aspect-ratio: 1;
            border: 2px solid #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.8rem;
            min-width: 40px;
            min-height: 40px;
        }

        .siege:hover:not(.occupe) {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .siege.disponible {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .siege.pmr {
            background: #ffc107;
            color: #212529;
            border-color: #ffc107;
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
            border-color: #dc3545;
            cursor: not-allowed;
        }

        .siege.selected {
            background: #2c3e50;
            color: white;
            border-color: #2c3e50;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(44, 62, 80, 0.4);
        }

        .reservation-summary {
            background: linear-gradient(135deg, #2c3e50, #1a2a3a);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            display: none;
        }

        .summary-content {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .selected-info h4 {
            color: #d4af37;
            margin-bottom: 10px;
        }

        .selected-places {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .total-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #d4af37;
        }

        .qr-code {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .qr-code canvas {
            display: block;
            margin: 0 auto;
        }

        .qr-label {
            color: #2c3e50;
            font-size: 0.8rem;
            font-weight: bold;
            margin-top: 5px;
        }

        .btn {
            background: linear-gradient(45deg, #d4af37, #f4d03f);
            color: #2c3e50;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
            display: block;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
            background: linear-gradient(45deg, #f4d03f, #d4af37);
        }

        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            color: white;
        }

        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }

            .sieges-container {
                grid-template-columns: repeat(8, 1fr);
                gap: 6px;
                padding: 20px;
            }

            .siege {
                font-size: 0.7rem;
                min-width: 35px;
                min-height: 35px;
            }

            .legend {
                gap: 15px;
                flex-direction: column;
                align-items: center;
            }

            .seance-details {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .summary-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-ticket-alt"></i> Réservation de places</h1>
            <p>Sélectionnez vos places pour une expérience cinéma inoubliable</p>
        </div>

        <?php if (!empty($film_info) && !empty($seance_info)): ?>
            <div class="film-info">
                <h2><?= htmlspecialchars($seance_info['film_titre']) ?></h2>
                <div class="seance-details">
                    <div class="detail-item">
                        <i class="fas fa-calendar"></i>
                        <strong>Date:</strong> <?= date('d/m/Y', strtotime($seance_info['debut'])) ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <strong>Heure:</strong> <?= date('H:i', strtotime($seance_info['debut'])) ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-door-open"></i>
                        <strong>Salle:</strong> <?= htmlspecialchars($seance_info['salle_nom']) ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-euro-sign"></i>
                        <strong>Prix:</strong> <?= number_format($seance_info['prix'], 2) ?>€
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="content">
            <form action="reserver.php" method="POST" id="reservationForm">
                <input type="hidden" name="film_id" value="<?= $film_id ?>">
                <input type="hidden" name="seance_id" value="<?= $seance_id ?>">
                <input type="hidden" name="code_reservation" value="<?= $code_reservation ?>">
                <input type="hidden" name="user_id" value="<?= $user_id ?>">

                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Vos informations</h3>
                    <?php if (!empty($user_nom) || !empty($user_prenom) || !empty($user_email)): ?>
                        <div class="user-info-section">
                            <p style="margin-bottom: 15px; color: #28a745; font-weight: 500;">
                                <i class='fas fa-check-circle'></i> Informations récupérées depuis votre compte
                            </p>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                <div>
                                    <label><i class='fas fa-user'></i> Nom:</label>
                                    <input type="text" name="nom" value="<?= htmlspecialchars($user_nom) ?>" readonly style="background: #f8f9fa;">
                                </div>
                                <div>
                                    <label><i class='fas fa-user'></i> Prénom:</label>
                                    <input type="text" name="prenom" value="<?= htmlspecialchars($user_prenom) ?>" readonly style="background: #f8f9fa;">
                                </div>
                                <div>
                                    <label><i class='fas fa-envelope'></i> Email:</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user_email) ?>" readonly style="background: #f8f9fa;">
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="nom"><i class='fas fa-user'></i> Nom:</label>
                            <input type="text" id="nom" name="nom" required>
                        </div>

                        <div class="form-group">
                            <label for="prenom"><i class='fas fa-user'></i> Prénom:</label>
                            <input type="text" id="prenom" name="prenom" required>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class='fas fa-envelope'></i> Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-chair"></i> Choix des places</h3>
                    <div class="places-section">
                        <div class="legend">
                            <div class="legend-item">
                                <div class="legend-color disponible"></div>
                                <span>Disponible</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color pmr"></div>
                                <span>PMR</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color occupe"></div>
                                <span>Occupée</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color selected"></div>
                                <span>Sélectionnée</span>
                            </div>
                        </div>

                        <div class="screen">
                            <i class="fas fa-desktop"></i> ÉCRAN
                        </div>

                        <div class="sieges-container" id="siegesContainer">
                            <?php if (!empty($places_disponibles)): ?>
                                <?php foreach ($places_disponibles as $place): ?>
                                    <?php
                                    $is_occupe = in_array($place['reference'], $places_occupees);
                                    $is_pmr = $place['est_pmr'];
                                    $class = $is_occupe ? 'occupe' : ($is_pmr ? 'pmr' : 'disponible');
                                    ?>
                                    <div class="siege <?= $class ?>"
                                        data-siege="<?= $place['reference'] ?>"
                                        data-place-id="<?= $place['id'] ?>"
                                        onclick="<?= !$is_occupe ? 'selectSiege(this)' : '' ?>">
                                        <?= $place['reference'] ?>
                                        <?php if ($is_occupe): ?>
                                            <div style="position: absolute; top: 2px; left: 2px; font-size: 8px;">❌</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="grid-column: 1/-1; text-align: center; color: #6c757d;">
                                    Aucune place disponible pour cette séance.<br>
                                    <small>Vérifiez que des places sont configurées pour cette salle.</small>
                                </p>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" name="sieges" id="siegesInput">
                        <input type="hidden" name="place_ids" id="placesIdsInput">

                        <div class="reservation-summary" id="reservationSummary">
                            <div class="summary-content">
                                <div class="selected-info">
                                    <h4><i class="fas fa-check-circle"></i> Récapitulatif de votre sélection</h4>
                                    <div class="selected-places" id="selectedPlacesList"></div>
                                    <div class="total-price" id="totalPrice">Total: 0.00€</div>
                                </div>
                                <div class="qr-code">
                                    <canvas id="qrcode" width="100" height="100"></canvas>
                                    <div class="qr-label">Code de réservation</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn" id="submitBtn" disabled>
                    <i class="fas fa-check"></i> Valider la réservation
                </button>
            </form>
        </div>
    </div>

    <script>
        let selectedSieges = [];
        let selectedPlaceIds = [];
        const pricePerPlace = <?= $seance_info['prix'] ?? 10.00 ?>;
        const reservationCode = '<?= $code_reservation ?>';

        function selectSiege(element) {
            if (element.classList.contains('occupe')) return;

            const siegeRef = element.getAttribute('data-siege');
            const placeId = element.getAttribute('data-place-id');
            const index = selectedSieges.indexOf(siegeRef);

            if (index > -1) {
                // Déselectionner
                selectedSieges.splice(index, 1);
                selectedPlaceIds.splice(index, 1);
                element.classList.remove('selected');
            } else {
                // Sélectionner
                selectedSieges.push(siegeRef);
                selectedPlaceIds.push(placeId);
                element.classList.add('selected');
            }

            updateReservationSummary();
        }

        function updateReservationSummary() {
            const summaryDiv = document.getElementById('reservationSummary');
            const submitBtn = document.getElementById('submitBtn');
            const siegesInput = document.getElementById('siegesInput');
            const placesIdsInput = document.getElementById('placesIdsInput');
            const selectedPlacesList = document.getElementById('selectedPlacesList');
            const totalPrice = document.getElementById('totalPrice');

            if (selectedSieges.length > 0) {
                summaryDiv.style.display = 'block';
                submitBtn.disabled = false;

                siegesInput.value = selectedSieges.join(',');
                placesIdsInput.value = selectedPlaceIds.join(',');
                selectedPlacesList.innerHTML = `Places sélectionnées: ${selectedSieges.join(', ')}`;
                totalPrice.innerHTML = `Total: ${(selectedSieges.length * pricePerPlace).toFixed(2)}€`;

                // Générer le QR code
                generateQRCode();
            } else {
                summaryDiv.style.display = 'none';
                submitBtn.disabled = true;
                siegesInput.value = '';
                placesIdsInput.value = '';
            }
        }
function generateQRCode() {
    const qrDiv = document.getElementById('qrcode');
    qrDiv.innerHTML = ''; // reset
    new QRCode(qrDiv, {
        text: `CINEPHORIA-${reservationCode}-PLACES:${selectedSieges.join(',')}`,
        width: 100,
        height: 100,
        colorDark : "#2c3e50",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
}
        // Animation d'entrée pour les places
        document.addEventListener('DOMContentLoaded', function() {
            const places = document.querySelectorAll('.siege');
            places.forEach((place, index) => {
                place.style.opacity = '0';
                place.style.transform = 'scale(0.8)';

                setTimeout(() => {
                    place.style.transition = 'all 0.3s ease';
                    place.style.opacity = '1';
                    place.style.transform = 'scale(1)';
                }, index * 30);
            });
        });
    </script>
</body>

</html>
requetesqlagareder
SELECT film.* 
FROM `cinema` 
JOIN salle ON salle.id_cinema = cinema.id
JOIN seance on seance.id_salle = salle.id
join film on seance.id_film = film.id
WHERE cinema.id=1.;
<moncomptr>
<div class="php">  <p><strong>Nom:</strong> <?= htmlspecialchars($reservation['nom']) ?></p>
                                <p><strong>Places:</strong> <?= $reservation['places'] ?></p>
                                <p><strong>Type:</strong> <?= $reservation['type_place'] ?></p>
                                <p><strong>Numéro:</strong> <?= $reservation['numero'] ?></p>
                                <p><strong>Prix unitaire:</strong> <?= $reservation['prix_unitaire'] ?> €</p>
                                <p><strong>Total:</strong> <?= $reservation['total'] ?> €</p>
                                <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($reservation['date_reservation'])) ?></p><fieldset></fieldset></div>
                               <reservation class="php">







                               
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Cinéphoria - Votre cinéma de référence</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #ffffff;
            /* ✅ fond blanc */
            color: #333;
        }

        header {
            background: linear-gradient(to right, #1a2a3a, #2c3e50);
            color: #d4af37;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            margin: 0;
            font-size: 28px;
            color: #d4af37;
        }

        nav a {
            color: #e0e0e0;
            margin-left: 20px;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
        }

        nav a:hover {
            background-color: #d4af37;
            color: #1a2a3a;
        }

        main {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h2 {
            color: #2c3e50;
            padding-left: 15px;
            border-left: 4px solid #d4af37;
        }

        .films {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .card img {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }

        .info {
            padding: 15px;
        }

        .info h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #2c3e50;
        }

        .desc {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
        }

        .seances {
            font-size: 13px;
            color: #444;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        .seance-time {
            display: inline-block;
            background-color: #2c3e50;
            color: white;
            padding: 4px 8px;
            margin: 3px;
            border-radius: 3px;
            font-size: 12px;
        }

        .btn-details {
            background-color: #d4af37;
            color: #1a2a3a;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .no-films {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        /* ✅ Footer réorganisé */
        footer {
            background: #2c3e50;
            color: #e0e0e0;
            padding: 40px 20px 20px 20px;
            margin-top: 60px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            gap: 20px;
        }

        .footer-section {
            flex: 1;
            min-width: 250px;
        }

        .footer-section h3 {
            color: #d4af37;
            margin-bottom: 12px;
            font-size: 18px;
        }

        .footer-section p {
            margin: 5px 0;
            color: #e0e0e0;
            font-size: 14px;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #444;
            font-size: 14px;
            color: #bbb;
        }
    </style>
</head>

<body>
    <header>
        <a href="index.php" class="logo">
            <h1>Cinéphoria</h1>
        </a>
        <nav>
            <a href="moncompte.php">🏠 Mon Compte</a>
            <a href="films.php">🎭 Films</a>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="index.php?logout=1">🔒 Déconnexion</a>
            <?php else: ?>
                <a href="login.php">🔑 Connexion</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <!-- SECTION AVANT-PREMIERES -->
        <h2>🎬 Avant-Premières</h2>
        <?php if (!empty($avant)): ?>
            <div class='films'>
                <?php foreach ($avant as $film): ?>
                    <div class='card'>
                        <img src="<?= formatAfficheUrl($film['affiche_url']) ?>" alt="<?= htmlspecialchars($film['titre']) ?>">
                        <div class='info'>
                            <h3><?= htmlspecialchars($film['titre']) ?></h3>
                            <div class='desc'><?= !empty($film['description']) ? substr($film['description'], 0, 100) . "..." : "Description non disponible" ?></div>
                            <div class='seances'>
                                <span class='seance-time'>14:00</span>
                                <span class='seance-time'>16:30</span>
                                <span class='seance-time'>19:00</span>
                            </div>
                            <a href='film.php?id=<?= $film['id'] ?>' class='btn-details'>Voir détails</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class='no-films'>
                <h3>Aucune avant-première disponible</h3>
            </div>
        <?php endif; ?>

        <!-- SECTION FILMS A L'AFFICHE -->
        <h2>🎬 Films à l'affiche</h2>
        <?php if (!empty($affiche)): ?>
            <div class='films'>
                <?php foreach ($affiche as $film): ?>
                    <div class='card'>
                        <img src="<?= formatAfficheUrl($film['affiche_url']) ?>" alt="<?= htmlspecialchars($film['titre']) ?>">
                        <div class='info'>
                            <h3><?= htmlspecialchars($film['titre']) ?></h3>
                            <div class='desc'><?= !empty($film['description']) ? substr($film['description'], 0, 100) . "..." : "Description non disponible" ?></div>
                            <div class='seances'>
                                <span class='seance-time'>14:00</span>
                                <span class='seance-time'>16:30</span>
                                <span class='seance-time'>19:00</span>
                                <span class='seance-time'>21:30</span>
                            </div>
                            <a href='film.php?id=<?= $film['id'] ?>' class='btn-details'>Voir détails</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class='no-films'>
                <h3>Aucun film à l'affiche</h3>
            </div>
        <?php endif; ?>

        <!-- SECTION COUPS DE COEUR -->
        <h2>❤️ Nos coups de cœur</h2>
        <?php if (!empty($mercredi)): ?>
            <div class='films'>
                <?php foreach ($mercredi as $film): ?>
                    <div class='card'>
                        <img src="<?= formatAfficheUrl($film['affiche_url']) ?>" alt="<?= htmlspecialchars($film['titre']) ?>">
                        <div class='info'>
                            <h3><?= htmlspecialchars($film['titre']) ?></h3>
                            <div class='desc'><?= !empty($film['description']) ? substr($film['description'], 0, 100) . "..." : "Description non disponible" ?></div>
                            <div class='seances'>
                                <span class='seance-time'>14:00</span>
                                <span class='seance-time'>16:30</span>
                                <span class='seance-time'>19:00</span>
                                <span class='seance-time'>21:30</span>
                            </div>
                            <a href='film.php?id=<?= $film['id'] ?>' class='btn-details'>Voir détails</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class='no-films'>
                <h3>Aucun coup de cœur disponible</h3>
            </div>
        <?php endif; ?>
    </main>

    <!-- ✅ Nouveau footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>À propos de Cinéphoria</h3>
                <p>Cinéphoria est votre cinéma de référence pour découvrir les dernières sorties et les classiques du cinéma dans un cadre exceptionnel.</p>
            </div>
            <div class="footer-section">
                <h3>Nos horaires</h3>
                <p>Lundi au vendredi: 14h - 23h<br>Samedi et dimanche: 12h - 00h</p>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>123 Avenue du Cinéma<br>75000 Paris<br>contact@cinephoria.fr<br>01 23 45 67 89</p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date("Y") ?> Cinéphoria. Tous droits réservés.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonctions pour gérer le modal de connexion         
        function openLoginModal() {
            document.getElementById('loginModal').style.display = 'flex';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        // Fermer le modal en cliquant à l'extérieur         
        window.onclick = function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target === modal) {
                closeLoginModal();
            }
        }

        // Ouvrir automatiquement le modal si erreur de connexion         
        <?php if (!empty($login_error)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                openLoginModal();
            });
        <?php endif; ?>
    </script>
</body>

</html>
                               </reservation>
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
                if ($user_data && $current_password === $user_data['mot_de_passe']) {
                    // Mettre à jour le mot de passe (vous pourriez vouloir hasher ici)
                    $sql = "UPDATE utilisateur SET mot_de_passe = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$new_password, $user['id']]);

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

// Récupérer TOUTES les réservations de l'utilisateur - APPROCHE DIRECTE
$reservations = [];

try {
    // Recherche par différentes variations du nom trouvées dans la base
    $noms_possibles = [
        'Adjam khalil',
        'adjam khalil',
        'Adjam',
        'adjam',
        'khalil Adjam',
        'khalil adjam'
    ];

    // Préparer les placeholders pour la requête
    $placeholders = implode(',', array_fill(0, count($noms_possibles), '?'));

    $sql = "  SELECT `id`, `id_utilisateur`, `id_seance`, `date_commande`, `prix_total`, `code_reservation`, `numero_reservation` FROM `commande` 
        WHERE id_utilisateur = ?
        ORDER BY date_commande DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur récupération réservations: " . $e->getMessage());
    $reservations = [];
}

// Compter le nombre de réservations avec le nom "Adjam khalil"
try {
    $stmt_res_count = $pdo->prepare("SELECT COUNT(*) as count FROM reservation WHERE nom = ?");
    $stmt_res_count->execute(['Adjam khalil']);
    $reservations_count = $stmt_res_count->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $reservations_count = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte - Cinéphoria</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
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
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
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

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-start {
            align-items: flex-start;
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

            .reservation-item {
                flex-direction: column;
            }

            .reservation-qr {
                margin-top: 20px;
            }
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .reservation-actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
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
                    🎫 Dernières réservations
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
                    </div>
                </form>
            </div>
        </div>

        <div class="tab-content" id="reservations">
            <div class="account-card">
                <h4>🎫 Mes réservations</h4>

                <?php if (empty($reservations)): ?>
                    <p class="text-muted">Vous n'avez aucune réservation pour le moment.</p>
                <?php else: ?>
                    <p>Vous avez <?= count($reservations) ?> réservation(s) au total.</p>

                    <?php foreach ($reservations as $reservation): ?>
                        <div class="reservation-item">
                            <div class="reservation-details">
                                <div class="reservation-header">
                                    <h5>Réservation #<?= $reservation['id'] ?></h5>
                                    <span class="badge"><?= $reservation['id'] ?></span>
                                </div>

                            </div>
                            <div class="reservation-qr">
                                <div class="qr-container" id="qr-<?= $reservation['id'] ?>"></div>
                                <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                                    🖨️ Imprimer
                                </button>
                            </div>
                        </div>
                        <!-- script à remettre à chaque boucle pour générer le QR code -->
                        <script>
                            // Générer le QR code pour cette réservation
                            var qr = qrcode(0, 'M');
                            qr.addData('Réservation #<?= $reservation['id'] ?> | <?= htmlspecialchars($user['nom']) ?> | <?= $reservation['id_seance'] ?> seances | <?= $reservation['prix_total'] ?>€');
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
<reserver class="php">

</reserver>
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

</html> garde le au cas ou ton code n'est 