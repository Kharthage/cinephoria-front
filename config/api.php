<?php
// config/api.php

try {
    $dsn = "mysql:host=localhost;port=3306;dbname=" . (getenv('DB_NAME') ?: 'cinephoria') . ";charset=utf8";
    $username = "root";
    $password = "";  // laisse vide si pas de mot de passe
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
