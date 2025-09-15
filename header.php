<?php
// Récupère le nom de la page actuelle (ex: "index.php")
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav>
    <a href="index.php" class="<?= ($current_page === 'index.php') ? 'active' : '' ?>">Accueil</a>
    <a href="login.php" class="<?= ($current_page === 'login.php') ? 'active' : '' ?>">Se connecter</a>
    <a href="reservation.php" class="<?= ($current_page === 'reservation.php') ? 'active' : '' ?>">Réservation</a>
    <a href="films.php" class="<?= ($current_page === 'films.php') ? 'active' : '' ?>">Films</a>
    <a href="contact.php" class="<?= ($current_page === 'contact.php') ? 'active' : '' ?>">Contact</a>
</nav>