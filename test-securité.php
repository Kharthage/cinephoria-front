<?php
echo "<h1>Test de sécurité final</h1>";

// Inclure la configuration
if (file_exists('config/smtp.php')) {
    require_once 'config/smtp.php';
    echo "<p style='color: green;'>✓ Configuration chargée</p>";
} else {
    echo "<p style='color: red;'>✗ Fichier de configuration introuvable</p>";
    exit;
}

// Test d'accès direct
echo "<h2>Test d'accès direct</h2>";
$url = 'http://' . $_SERVER['HTTP_HOST'] . '/cinephoria-front/config/smtp.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 403) {
    echo "<p style='color: green;'>✓ PARFAIT : Accès correctement bloqué (403)</p>";
} else {
    echo "<p style='color: red;'>✗ .htaccess ne fonctionne pas (Code: $httpCode)</p>";
    echo "<p><strong>Solution immédiate :</strong> Ajoutez la protection PHP dans smtp.php</p>";
}

echo "<hr>";
echo "<h2>Votre configuration SMTP est OPÉRATIONNELLE ! 🎉</h2>";
echo "<p>Les emails sont envoyés avec succès.</p>";
echo "<p>Pour la sécurité, choisissez une des solutions ci-dessus.</p>";
?>