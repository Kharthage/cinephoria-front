<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*var_dump($_SESSION);*/
if (isset($_SESSION['utilisateur']) && $_SESSION['utilisateur']['changer_mdp']) {
    // Sauf si on est déjà sur la page de changement
    if (basename($_SERVER['PHP_SELF']) !== 'changer_mdp.php') {
        header('Location: changer_mdp.php?auto');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="fr" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cinéphoria</title>
    <link href="/cinephoria-front/assets/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body class="d-flex flex-column h-100">
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
        <a class="navbar-brand" href="index.php">Cinéphoria</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="reservation.php">Réservation</a></li>
                    <li class="nav-item"><a class="nav-link" href="films.php">Films</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                <?php if (isset($_SESSION['utilisateur'])): ?>
                    <li class="nav-item"><a class="nav-link" href="commandes.php">Commandes</a></li>

                    <?php if ($_SESSION['utilisateur']['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="administration/index.php">Administration</a></li>
                    <?php endif; ?>

                    <?php if ($_SESSION['utilisateur']['role'] === 'employe'): ?>
                        <li class="nav-item"><a class="nav-link" href="intranet.php">Intranet</a></li>
                    <?php endif; ?>

                    <li class="nav-item"><a class="nav-link" href="profile.php">Profil</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Déconnexion</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Connexion</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</header>
<main class="flex-shrink-0">
    <div class="container mt-4">
