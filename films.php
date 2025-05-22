<?php
// Connexion à la base 'cinephoria2' avec l'utilisateur dbuser sans mot de passe
$conn = new mysqli('localhost', 'dbuser', '', 'cinephoria2');
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

if (!isset($_GET['id'])) {
    die("Erreur : aucun ID de film transmis dans l'URL.");
}

$film_id = (int)$_GET['id'];

if ($film_id <= 0) {
    die("ID de film invalide. Valeur reçue : " . htmlspecialchars($_GET['id']));
}

// Requête SQL avec noms de colonnes sans espaces, en snake_case
$sql = "SELECT id, titre, description, age_minimum, coup_de_coeur, note_moyenne, affiche, bande_annonce FROM film WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $film_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $film = $result->fetch_assoc();

    echo "<h1>" . htmlspecialchars($film['titre']) . "</h1>";
    echo "<p><strong>Description :</strong> " . nl2br(htmlspecialchars($film['description'])) . "</p>";
    echo "<p><strong>Âge minimum :</strong> " . htmlspecialchars($film['age_minimum']) . "</p>";
    echo "<p><strong>Coup de cœur :</strong> " . htmlspecialchars($film['coup_de_coeur']) . "</p>";
    echo "<p><strong>Note moyenne :</strong> " . htmlspecialchars($film['note_moyenne']) . "</p>";
    echo "<p><strong>Affiche :</strong><br><img src='" . htmlspecialchars($film['affiche']) . "' alt='Affiche du film' style='max-width:200px;'></p>";
    echo "<p><strong>Bande annonce :</strong><br><iframe width='560' height='315' src='" . htmlspecialchars($film['bande_annonce']) . "' frameborder='0' allowfullscreen></iframe></p>";
} else {
    echo "Film non trouvé.";
}

$stmt->close();
$conn->close();
