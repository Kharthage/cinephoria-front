<?php
// Connexion à la base de données avec PDO
try {
    $pdo = new PDO("mysql:host=sql7.freesqldatabase.com;dbname=sql7798672;charset=utf8mb4", 'sql7798672', 'ndviH1KDRs', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Fonction API interne simulée
function appelerApi($endpoint, $method = 'GET', $params = [])
{
    global $pdo;

    // Gestion endpoint film par ID : /films/{id}
    if (preg_match('#^/films(?:/(\d+))?$#', $endpoint, $matches)) {
        $filmId = isset($m[1]) ? (int)$m[1] : null;

        if ($filmId === null) {
            $stmt = $pdo->query("SELECT * FROM film");
            $film = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['data' => $film];
        }
        // Récupérer les infos principales du film
        $stmt = $pdo->prepare("SELECT * FROM film WHERE id = ?");
        $stmt->execute([$filmId]);
        $film = $stmt->fetch();

        if (!$film) {
            return ['data' => null]; // Film non trouvé
        }

        // Récupérer les genres liés au film
        $stmtGenres = $pdo->prepare("
            SELECT g.nom 
            FROM genre g
            JOIN film_genre fg ON fg.id_genre = g.id
            WHERE fg.id_film = ?");
        $stmtGenres->execute([$filmId]);
        $genres = $stmtGenres->fetchAll(PDO::FETCH_COLUMN);

        // Récupérer les séances futures du film
        $stmtSeances = $pdo->prepare("
            SELECT id_salle, debut, fin, prix 
            FROM seance 
            WHERE id_film = ? AND debut > NOW() 
            ORDER BY debut ASC");
        $stmtSeances->execute([$filmId]);
        $seances = $stmtSeances->fetchAll();

        // Ajouter genres et séances au tableau film
        $film['genres'] = array_map(fn($nom) => ['nom' => $nom], $genres);
        $film['seances'] = $seances;

        return ['data' => $film];
    }

    // Gestion endpoint mot de passe oublié
    if ($endpoint === '/mdp-oublie' && $method === 'POST' && isset($params['email'])) {
        $email = $params['email'];

        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            return ['message' => "📩 Un lien de réinitialisation a été envoyé à $email."];
        } else {
            return ['message' => "❌ Aucun compte trouvé avec cet email."];
        }
    }

    return ['message' => "❌ Endpoint inconnu."];
}
