<?php
session_start();

try {
    // sql7.freesqldatabase.com   dbname=sql7798672 'sql7798672', 'ndviH1KDRs'
    $pdo = new PDO("mysql:host=sql7.freesqldatabase.com;dbname=sql7798672;charset=utf8mb4", 'sql7798672', 'ndviH1KDRs', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Traitement du formulaire de connexion
$login_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        // Recherche de l'utilisateur avec email (insensible à la casse)
        $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Vérification du mot de passe - CORRECTION ICI
            if (password_verify($password, $user['mot_de_passe'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['email'] = $user['email'];

                // Rediriger pour éviter la resoumission du formulaire
                header("Location: films.php?login=success");
                exit();
            } else {
                $login_error = "Mot de passe incorrect.";
            }
        } else {
            $login_error = "Aucun compte trouvé avec cet email.";
        }
    } else {
        $login_error = "Veuillez remplir tous les champs.";
    }
}

// Message si redirection après login
$info_message = null;
if (isset($_GET['login']) && $_GET['login'] === 'success') {
    $info_message = "✅ Vous êtes connecté(e) avec succès !";
}

// Récupération des cinémas
$cinemas_sql = "SELECT * FROM cinema ORDER BY ville, nom";
$cinemas = $pdo->query($cinemas_sql)->fetchAll();

// Gestion de la recherche et du filtre cinéma
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$cinema_id = isset($_GET['cinema_id']) ? intval($_GET['cinema_id']) : 0;

// REQUÊTE AVEC RECHERCHE ET FILTRE CINÉMA
$sql_conditions = [];
$params = [];

// Condition de recherche
if (!empty($recherche)) {
    $sql_conditions[] = "f.titre LIKE :recherche";
    $params[':recherche'] = '%' . $recherche . '%';
}

// Construction de la requête principale
$sql = "SELECT DISTINCT f.* FROM film f";

if ($cinema_id > 0) {
    $sql .= " JOIN seance s ON f.id = s.id_film 
              JOIN salle sa ON s.id_salle = sa.id 
              WHERE sa.id_cinema = :cinema_id";
    $params[':cinema_id'] = $cinema_id;

    if (!empty($sql_conditions)) {
        $sql .= " AND " . implode(' AND ', $sql_conditions);
    }
} else {
    if (!empty($sql_conditions)) {
        $sql .= " WHERE " . implode(' AND ', $sql_conditions);
    }
}

$sql .= " ORDER BY f.titre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$films = $stmt->fetchAll();

// Récupérer toutes les séances avec informations des salles, films ET cinémas
$seances_par_film = [];
$seances_sql = "
    SELECT 
        s.*, 
        sa.reference as salle_nom,
        sa.nb_places,
        sa.nb_places_pmr,
        sa.id_cinema,
        f.avant_premiere,
        c.nom as cinema_nom,
        c.ville as cinema_ville
    FROM seance s 
    JOIN salle sa ON s.id_salle = sa.id 
    JOIN film f ON s.id_film = f.id
    JOIN cinema c ON sa.id_cinema = c.id
    WHERE s.status = 'active'";

// Filtrer les séances par cinéma si sélectionné
if ($cinema_id > 0) {
    $seances_sql .= " AND sa.id_cinema = :cinema_id_seance";
}

$seances_sql .= " ORDER BY s.id_film, s.debut";

$stmt_seances = $pdo->prepare($seances_sql);
if ($cinema_id > 0) {
    $stmt_seances->execute([':cinema_id_seance' => $cinema_id]);
} else {
    $stmt_seances->execute();
}
$all_seances = $stmt_seances->fetchAll();

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

// Récupérer le nom du cinéma sélectionné
$cinema_selectionne = null;
if ($cinema_id > 0) {
    foreach ($cinemas as $cinema) {
        if ($cinema['id'] == $cinema_id) {
            $cinema_selectionne = $cinema;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Cinephoria - Tous les films</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a2a3a;
            --primary: #2c3e50;
            --accent: #d4af37;
            --accent-hover: #c19b2e;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-dark: #333;
            --text-light: #e0e0e0;
            --success: #2e7d32;
            --warning: #ff5722;
            --info: #3949ab;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .navbar-dark {
            background-color: var(--primary-dark) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: bold;
            color: var(--accent) !important;
            font-size: 1.5rem;
        }

        .btn-outline-light {
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-outline-light:hover {
            background-color: var(--accent);
            color: var(--primary-dark);
        }

        .btn-light {
            background-color: var(--accent);
            color: var(--primary-dark);
            border-color: var(--accent);
        }

        .btn-light:hover {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            color: var(--primary-dark);
        }

        .search-bar {
            background: linear-gradient(to right, var(--primary-dark), var(--primary));
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            color: var(--text-light);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .search-bar input::placeholder {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .search-bar input,
        .search-bar select {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }

        .search-bar input:focus,
        .search-bar select:focus {
            background-color: rgba(255, 255, 255, 0.25) !important;
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25) !important;
            color: white !important;
        }

        .search-bar select option {
            background-color: var(--primary-dark);
            color: white;
        }

        .search-bar h2 {
            color: white !important;
        }

        .film-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            background-color: var(--card-bg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .film-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .price-badge {
            background-color: var(--success);
            color: white;
            font-weight: 500;
        }

        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
        }

        .film-info {
            background: linear-gradient(to right, var(--primary-dark), var(--primary));
            color: white;
            /* Changé en blanc */
            padding: 15px;
        }

        .film-info h6 {
            color: white !important;
            /* Titre du film en blanc */
        }

        .seance-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            border-radius: 6px;
        }

        .seance-item:hover {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left-color: var(--accent);
        }

        .format-badge {
            background-color: var(--warning);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .no-seances {
            background-color: #fff3e0;
            border: 1px solid #ffb74d;
            color: #e65100;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-info {
            background-color: #e8f4fd;
            border-color: #b6e0fe;
            color: #0c5460;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: var(--primary-dark);
            font-weight: 600;
        }

        footer {
            background: var(--primary-dark);
            color: var(--text-light);
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
            color: var(--accent);
            border-bottom: 2px solid var(--accent);
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
            .footer-content {
                flex-direction: column;
            }

            .footer-section {
                margin-bottom: 20px;
            }
        }

        .user-welcome {
            color: white;
            margin-right: 15px;
            display: inline-flex;
            align-items: center;
        }

        .user-welcome i {
            margin-right: 5px;
        }

        /* Modal de connexion */
        .login-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .login-modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
        }

        .login-modal h2 {
            color: var(--primary);
            margin-bottom: 20px;
            text-align: center;
        }

        .login-modal .form-group {
            margin-bottom: 20px;
        }

        .login-modal .btn-close {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .cinema-badge {
            background-color: var(--info);
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .filter-summary {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 15px;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">🎬 Cinéphoria</a>
            <div class="ms-auto d-flex align-items-center">
                <?php if ($connecte): ?>
                    <span class="user-welcome">
                        <i class="bi bi-person-circle"></i> Bonjour <?= htmlspecialchars($prenom) ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
                <?php else: ?>
                    <button class="btn btn-outline-light btn-sm" onclick="openLoginModal()">Connexion</button>
                    <a href="register.php" class="btn btn-light btn-sm ms-2">Créer un compte</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Modal de connexion -->
    <div id="loginModal" class="login-modal">
        <div class="login-modal-content">
            <button type="button" class="btn-close" onclick="closeLoginModal()"></button>
            <h2>Connexion</h2>
            <?php if ($login_error): ?>
                <div class="alert alert-danger"><?= $login_error ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>
            <div class="text-center mt-3">
                <p>Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
            </div>
        </div>
    </div>

    <div class="container my-4">
        <?php if ($info_message): ?>
            <div class="alert alert-success text-center"><?= htmlspecialchars($info_message) ?></div>
        <?php endif; ?>

        <!-- BARRE DE RECHERCHE ET FILTRE CINÉMA -->
        <div class="search-bar">
            <h2 class="text-center mb-4" style="color: white !important;">🔍 Rechercher un film</h2>
            <form method="GET" action="">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label text-white">Nom du film</label>
                            <input type="text" name="recherche" class="form-control form-control-lg"
                                placeholder="Trouvez votre film..." value="<?= htmlspecialchars($recherche) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label text-white">Cinéma</label>
                            <select name="cinema_id" class="form-select form-select-lg">
                                <option value="0">Tous les cinémas</option>
                                <?php foreach ($cinemas as $cinema): ?>
                                    <option value="<?= $cinema['id'] ?>"
                                        <?= ($cinema_id == $cinema['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cinema['nom']) ?> - <?= htmlspecialchars($cinema['ville']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-light btn-lg w-100" type="submit">
                            <i class="bi bi-search"></i> Rechercher
                        </button>
                    </div>
                </div>
            </form>

            <!-- Résumé des filtres actifs -->
            <?php if (!empty($recherche) || $cinema_id > 0): ?>
                <div class="filter-summary">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-white-50">Filtres actifs:</span>
                        <?php if (!empty($recherche)): ?>
                            <span class="badge bg-light text-dark">
                                Film: "<?= htmlspecialchars($recherche) ?>"
                                <a href="?<?= $cinema_id > 0 ? 'cinema_id=' . $cinema_id : '' ?>"
                                    class="text-danger ms-1 text-decoration-none">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($cinema_selectionne): ?>
                            <span class="badge bg-light text-dark">
                                Cinéma: <?= htmlspecialchars($cinema_selectionne['nom']) ?> - <?= htmlspecialchars($cinema_selectionne['ville']) ?>
                                <a href="?<?= !empty($recherche) ? 'recherche=' . urlencode($recherche) : '' ?>"
                                    class="text-danger ms-1 text-decoration-none">✕</a>
                            </span>
                        <?php endif; ?>
                        <a href="films.php" class="btn btn-sm btn-outline-light ms-2">
                            <i class="bi bi-x-circle"></i> Effacer tous les filtres
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <h1 class="mb-4 text-center">
            🎬 <?= empty($recherche) && $cinema_id == 0 ? 'Tous nos films' : 'Résultats de recherche' ?>
            <small class="text-muted d-block mt-2"><?= count($films) ?> film(s) trouvé(s)</small>
            <?php if ($cinema_selectionne): ?>
                <small class="text-info d-block">
                    📍 <?= htmlspecialchars($cinema_selectionne['nom']) ?> - <?= htmlspecialchars($cinema_selectionne['ville']) ?>
                </small>
            <?php endif; ?>
        </h1>

        <?php if (empty($films)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-search display-4 text-muted"></i>
                <h4 class="mt-3">Aucun film trouvé</h4>
                <p class="mb-0">
                    Aucun film ne correspond à vos critères de recherche.
                    <?php if (!empty($recherche)): ?>
                        <br>Recherche: "<?= htmlspecialchars($recherche) ?>"
                    <?php endif; ?>
                    <?php if ($cinema_selectionne): ?>
                        <br>Cinéma: <?= htmlspecialchars($cinema_selectionne['nom']) ?>
                    <?php endif; ?>
                </p>
                <a href="films.php" class="btn btn-primary mt-3">Voir tous les films</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($films as $film): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow film-card">
                            <!-- En-tête du film -->
                            <div class="film-info">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1" style="color: white !important;"><?= htmlspecialchars($film['titre']) ?></h6>
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
                                                            <?php if ($cinema_id == 0): ?>
                                                                <br>
                                                                <span class="cinema-badge">
                                                                    <?= htmlspecialchars($seance['cinema_nom']) ?> - <?= htmlspecialchars($seance['cinema_ville']) ?>
                                                                </span>
                                                            <?php endif; ?>
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
                                                            <button onclick="openLoginModal()" class="btn btn-outline-primary btn-sm w-100">
                                                                <i class="bi bi-person-check"></i> Se connecter
                                                            </button>
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
        <?php if ($login_error): ?>
            document.addEventListener('DOMContentLoaded', function() {
                openLoginModal();
            });
        <?php endif; ?>
    </script>
</body>

</html>