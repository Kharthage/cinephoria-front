<?php
// validation.php

// Inclusion du fichier de connexion PDO (testpdo.php à la racine cinephoria-front)
require_once __DIR__ . '/../../testpdo.php';

// Header JSON pour la réponse API
header('Content-Type: application/json; charset=utf-8');

// Récupération du paramètre ticketId depuis la requête GET ou POST
$ticketId = $_GET['ticketId'] ?? $_POST['ticketId'] ?? null;

if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre ticketId manquant']);
    exit;
}

try {
    // Préparation de la requête SQL pour récupérer la commande liée au billet
    $stmt = $pdo->prepare('
        SELECT c.id AS commande_id, c.date_commande, c.nombre_personnes, s.id AS seance_id, s.date_seance, f.titre AS film_titre, sa.numero AS salle_numero
        FROM commande c
        JOIN billet b ON b.commande_id = c.id
        JOIN seance s ON c.seance_id = s.id
        JOIN film f ON s.film_id = f.id
        JOIN salle sa ON s.salle_id = sa.id
        WHERE b.id = :ticketId
    ');
    $stmt->execute(['ticketId' => $ticketId]);
    $result = $stmt->fetch();

    if (!$result) {
        http_response_code(404);
        echo json_encode(['error' => 'Billet non trouvé']);
        exit;
    }

    // Réponse JSON avec les infos de la séance et nombre de personnes
    echo json_encode([
        'commande_id' => $result['commande_id'],
        'date_commande' => $result['date_commande'],
        'nombre_personnes' => $result['nombre_personnes'],
        'seance_id' => $result['seance_id'],
        'date_seance' => $result['date_seance'],
        'film_titre' => $result['film_titre'],
        'salle_numero' => $result['salle_numero']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()]);
    exit;
}
