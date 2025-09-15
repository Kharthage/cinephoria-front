<?php
// Démarrer la session
session_start();

// Inclure la connexion DB (SANS ECHO)
include("testpdo.php");

$message = "";

// Gestion déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Fonction pour formater les URLs d'affiches
function formatAfficheUrl($url)
{
    if (empty($url)) return 'https://via.placeholder.com/220x300/2c3e50/ffffff?text=Affiche+non+disponible';
    if (strpos($url, '\\') === 0 || strpos($url, '/') === 0) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        return $base_url . str_replace('\\', '/', $url);
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
    return 'https://via.placeholder.com/220x300/2c3e50/ffffff?text=' . urlencode($url);
}

// Fonction pour afficher les étoiles
function afficherEtoiles($note, $max = 5)
{
    if ($note === null) return "Pas encore noté";
    $etoiles = '';
    $noteArrondie = round($note * 2) / 2;
    for ($i = 1; $i <= $max; $i++) {
        if ($i <= $noteArrondie) {
            $etoiles .= '⭐';
        } elseif ($i - 0.5 <= $noteArrondie) {
            $etoiles .= '⭐';
        } else {
            $etoiles .= '☆';
        }
    }
    return $etoiles . " ({$note}/5)";
}

// Fonction pour récupérer les films avec leur note moyenne des avis utilisateurs
function getFilmsWithUserRatings($pdo, $condition = "", $limit = 8)
{
    $sql = "SELECT f.id, f.titre, f.affiche_url, f.description, f.note_moyenne, f.coup_de_coeur, f.age_minimum,
                   AVG(a.note) as note_avis_utilisateurs,
                   COUNT(a.note) as nb_avis
            FROM film f
            LEFT JOIN avis_utilisateur a ON f.id = a.id_film AND a.est_valide = 1
            " . $condition . "
            GROUP BY f.id
            ORDER BY f.date_ajout DESC
            LIMIT " . $limit;

    return $pdo->query($sql)->fetchAll();
}

// Récupération des films avec les avis
$avant = $affiche = $mercredi = [];

try {
    $avant = getFilmsWithUserRatings($pdo, "WHERE f.avant_premiere = 1", 4);
    $affiche = getFilmsWithUserRatings($pdo, "WHERE (f.avant_premiere = 0 OR f.avant_premiere IS NULL)", 8);

    // Pour les coups de cœur, on priorise par note des avis utilisateurs puis par note moyenne
    $mercredi = $pdo->query("
        SELECT f.id, f.titre, f.affiche_url, f.description, f.note_moyenne, f.coup_de_coeur, f.age_minimum,
               AVG(a.note) as note_avis_utilisateurs,
               COUNT(a.note) as nb_avis
        FROM film f
        LEFT JOIN avis_utilisateur a ON f.id = a.id_film AND a.est_valide = 1
        WHERE f.coup_de_coeur = 1
        GROUP BY f.id
        ORDER BY AVG(a.note) DESC, f.note_moyenne DESC, f.date_ajout DESC
        LIMIT 6
    ")->fetchAll();

    if (empty($mercredi)) {
        $mercredi = getFilmsWithUserRatings($pdo, "WHERE f.note_moyenne IS NOT NULL ORDER BY f.note_moyenne DESC, f.date_ajout DESC", 6);
    }
} catch (PDOException $e) {
    // Silencieux
}
?>
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
            background: #f8f9fa;
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

        .ratings {
            margin-bottom: 12px;
            font-size: 12px;
        }

        .rating-official {
            background-color: #e9ecef;
            padding: 4px 8px;
            border-radius: 3px;
            margin-bottom: 4px;
            display: inline-block;
        }

        .rating-users {
            background-color: #fff3cd;
            padding: 4px 8px;
            border-radius: 3px;
            display: inline-block;
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

        footer {
            background: #2c3e50;
            color: #e0e0e0;
            padding: 25px 0;
            margin-top: 60px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            padding: 0 30px;
        }

        .footer-section h3 {
            color: #d4af37;
            margin-bottom: 15px;
        }

        .footer-section a {
            color: #e0e0e0;
            text-decoration: none;
        }

        .footer-section a:hover {
            color: #d4af37;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #34495e;
        }
    </style>
</head>

<body>
    <header>
        <a href="index.php" class="logo">
            <h1>Cinéphoria</h1>
        </a>
        <nav>

            <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
                <a href="/cinephoria-front/administration/index.php">Administration</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'employe'): ?>
                <a href="/cinephoria-front/intranet.php">Intranet</a>
            <?php endif; ?>
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

                            <div class='ratings'>
                                <?php if ($film['note_moyenne']): ?>
                                    <div class='rating-official'>
                                        📊 Note officielle: <?= afficherEtoiles($film['note_moyenne']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($film['note_avis_utilisateurs'] && $film['nb_avis'] > 0): ?>
                                    <div class='rating-users'>
                                        👥 Avis utilisateurs: <?= afficherEtoiles($film['note_avis_utilisateurs']) ?> (<?= $film['nb_avis'] ?> avis)
                                    </div>
                                <?php endif; ?>
                            </div>

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

                            <div class='ratings'>
                                <?php if ($film['note_moyenne']): ?>
                                    <div class='rating-official'>
                                        📊 Note officielle: <?= afficherEtoiles($film['note_moyenne']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($film['note_avis_utilisateurs'] && $film['nb_avis'] > 0): ?>
                                    <div class='rating-users'>
                                        👥 Avis utilisateurs: <?= afficherEtoiles($film['note_avis_utilisateurs']) ?> (<?= $film['nb_avis'] ?> avis)
                                    </div>
                                <?php endif; ?>
                            </div>

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

                            <div class='ratings'>
                                <?php if ($film['note_moyenne']): ?>
                                    <div class='rating-official'>
                                        📊 Note officielle: <?= afficherEtoiles($film['note_moyenne']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($film['note_avis_utilisateurs'] && $film['nb_avis'] > 0): ?>
                                    <div class='rating-users'>
                                        👥 Avis utilisateurs: <?= afficherEtoiles($film['note_avis_utilisateurs']) ?> (<?= $film['nb_avis'] ?> avis)
                                    </div>
                                <?php endif; ?>
                            </div>

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

    <footer>
        <div class="footer-container">
            <div class="footer-section">
                <h3>🎥 À propos</h3>
                <p>
                    Cinéphoria est votre cinéma de référence pour découvrir les dernières sorties et
                    les classiques du cinéma dans un cadre exceptionnel.
                </p>
            </div>
            <div class="footer-section">
                <h3>🕒 Horaires</h3>
                <p>
                    Lundi - Vendredi : 14h - 23h <br>
                    Samedi & Dimanche : 12h - 00h
                </p>
            </div>
            <div class="footer-section">
                <h3>📞 Contact</h3>
                <p>
                    123 Avenue du Cinéma <br>
                    75000 Paris <br>
                    <a href="mailto:contact@cinephoria.fr">contact@cinephoria.fr</a><br>
                    01 23 45 67 89
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date("Y") ?> Cinéphoria. Tous droits réservés.
        </div>
    </footer>
</body>

</html>