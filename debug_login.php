<?php
include("testpdo.php");

// Test manuel - remplacez par vos identifiants réels
$test_email = "test@example.com"; // Remplacez par un email existant
$test_password = "votre_mot_de_passe"; // Remplacez par le mot de passe

echo "<h2>Debug Connexion</h2>";

try {
    // 1. Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$test_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "✅ Utilisateur trouvé:<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Nom: " . $user['nom'] . "<br>";
        echo "Prénom: " . $user['prenom'] . "<br>";
        echo "Mot de passe hashé: " . $user['password'] . "<br><br>";

        // 2. Vérifier le mot de passe
        $password_verified = password_verify($test_password, $user['password']);

        echo "Mot de passe testé: " . $test_password . "<br>";
        echo "Résultat verification: " . ($password_verified ? "✅ CORRECT" : "❌ INCORRECT") . "<br><br>";

        // 3. Vérifier l'algorithme de hash
        if (!$password_verified) {
            $hash_info = password_get_info($user['password']);
            echo "Algorithme de hash: " . $hash_info['algoName'] . "<br>";
            echo "Options de hash: " . print_r($hash_info['options'], true) . "<br><br>";

            // Test de création d'un nouveau hash
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "Nouveau hash généré: " . $new_hash . "<br>";
            echo "Verification avec nouveau hash: " . (password_verify($test_password, $new_hash) ? "✅ OK" : "❌ KO") . "<br>";
        }
    } else {
        echo "❌ Aucun utilisateur trouvé avec l'email: " . $test_email . "<br>";

        // Lister tous les utilisateurs pour debug
        $all_users = $pdo->query("SELECT id, email, nom, prenom FROM users")->fetchAll();
        echo "<br>Utilisateurs existants:<br>";
        foreach ($all_users as $u) {
            echo "- " . $u['email'] . " (" . $u['prenom'] . " " . $u['nom'] . ")<br>";
        }
    }
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
