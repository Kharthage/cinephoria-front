<?php
session_start();

// Vérifie si un utilisateur est déjà stocké
if (isset($_SESSION['id_utilisateur'])) {
    echo "Utilisateur connecté, ID : " . $_SESSION['id_utilisateur'];
} else {
    // Sinon, on simule un login
    $_SESSION['id_utilisateur'] = 42; // mettons un ID fictif
    echo "Session créée avec ID 42. Recharge la page pour tester si elle persiste.";
}
