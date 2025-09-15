<?php
include("testpdo.php");
echo "✅ Connexion DB réussie !";
$result = $pdo->query("SELECT COUNT(*) as total FROM film");
$count = $result->fetch();
echo "<br>📊 Nombre de films : " . $count['total'];
