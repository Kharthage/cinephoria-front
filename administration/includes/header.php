<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Évite la boucle de redirection : si pas connecté et pas sur index.php, redirige vers index.php
if (!isset($_SESSION['utilisateur'])) {
    if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
        header('Location: /cinephoria-front/index.php');
        exit;
    }
}

// Si l'utilisateur doit changer son mot de passe et n'est pas déjà sur changer_mdp.php, redirige
if (isset($_SESSION['utilisateur']['changer_mdp']) && $_SESSION['utilisateur']['changer_mdp']) {
    if (basename($_SERVER['PHP_SELF']) !== 'changer_mdp.php') {
        header('Location: /cinephoria-front/changer_mdp.php?auto');
        exit;
    }
}

// Contrôle du rôle admin (à adapter selon ta logique)
if (isset($_SESSION['utilisateur']['role']) && $_SESSION['utilisateur']['role'] != "admin") {
    if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
        header('Location: /cinephoria-front/index.php?non_autorisee');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-100">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cinéphoria</title>
    <link href="/cinephoria-front/assets/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
</head>

<body class="d-flex flex-column h-100">
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
            <a class="navbar-brand" href="/cinephoria-front/index.php">Cinéphoria</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/cinephoria-front/reservation.php">Réservation</a></li>
                    <li class="nav-item"><a class="nav-link" href="/cinephoria-front/films.php">Films</a></li>
                    <li class="nav-item"><a class="nav-link" href="/cinephoria-front/contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="/cinephoria-front/commandes.php">Commandes</a></li>

                    <?php if (isset($_SESSION['utilisateur']['role']) && $_SESSION['utilisateur']['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="/cinephoria-front/administration/index.php">Administration</a></li>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['utilisateur']['role']) && $_SESSION['utilisateur']['role'] === 'employe'): ?>
                        <li class="nav-item"><a class="nav-link" href="/cinephoria-front/intranet.php">Intranet</a></li>
                    <?php endif; ?>

                    <li class="nav-item"><a class="nav-link" href="/cinephoria-front/profile.php">Profil</a></li>
                    <li class="nav-item"><a class="nav-link" href="/cinephoria-front/logout.php">Déconnexion</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="flex-shrink-0">
        <div class="container mt-4">