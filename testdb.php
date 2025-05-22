<?php
$conn = new mysqli('localhost', 'dbuser', '', 'cinephoria2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connexion réussie à la base cinema !";
$conn->close();
