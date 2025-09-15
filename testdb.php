<?php
$conn = new mysqli('localhost', 'root', '', 'cinephoria');
if ($conn->connect_error) {
    die("Connection failed je sais pas quoi mettre: " . $conn->connect_error);
}
echo "Connexion réussie à la base cinema !";
$conn->close();
