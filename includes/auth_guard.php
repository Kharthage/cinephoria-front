<?php

if (!isset($_SESSION['utilisateur'])) {
    header('Location: login.php');
    exit;
}

/*if ($_SESSION['utilisateur']['role']) {
    // Sauf si on est déjà sur la page de changement
    if (basename($_SERVER['PHP_SELF']) !== 'changer_mdp.php') {
        header('Location: changer_mdp.php');
        exit;
    }
}*/