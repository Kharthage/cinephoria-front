<?php
include("testpdo.php");

try {
    // Créer la table reset_tokens si elle n'existe pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(100) NOT NULL UNIQUE,
            expiration DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    echo "✅ Table 'reset_tokens' créée ou déjà existante<br>";

    // Vérifier si la table users a la colonne password
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('password', $columns)) {
        // Ajouter la colonne password si elle n'existe pas
        $pdo->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER email");
        echo "✅ Colonne 'password' ajoutée à la table 'users'<br>";
    } else {
        echo "✅ Colonne 'password' existe déjà<br>";
    }

    echo "✅ Structure de base de données prête pour la réinitialisation de mot de passe";
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
