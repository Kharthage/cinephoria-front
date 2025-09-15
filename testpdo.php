<?php
try {
    $pdo = new PDO(
        "mysql:host=sql7.freesqldatabase.com;dbname=sql7798672;charset=utf8mb4",
        'sql7798672',
        'ndviH1KDRs',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur DB: " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}
