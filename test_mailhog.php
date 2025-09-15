<?php
// Test simple de la fonction mail()
echo "<h2>Test de la fonction mail()</h2>";

// Afficher la configuration PHP mail
echo "<h3>Configuration PHP mail actuelle :</h3>";
echo "SMTP : " . ini_get('SMTP') . "<br>";
echo "smtp_port : " . ini_get('smtp_port') . "<br>";
echo "sendmail_from : " . ini_get('sendmail_from') . "<br>";
echo "<hr>";

// Test d'envoi
$to = "test@example.com";
$subject = "Test MailHog";
$message = "Ceci est un test de la fonction mail()";
$headers = "From: test@localhost.com";

echo "<h3>Tentative d'envoi d'email...</h3>";

if (mail($to, $subject, $message, $headers)) {
    echo "<p style='color: green;'>✅ Email envoyé avec succès !</p>";
    echo "<p>Vérifiez MailHog sur <a href='http://localhost:8025' target='_blank'>http://localhost:8025</a></p>";
} else {
    echo "<p style='color: red;'>❌ Échec de l'envoi de l'email</p>";

    // Afficher les erreurs PHP
    $error = error_get_last();
    if ($error) {
        echo "<p>Dernière erreur PHP : " . $error['message'] . "</p>";
    }

    echo "<h4>Solutions possibles :</h4>";
    echo "<ul>";
    echo "<li>Vérifiez que MailHog est bien lancé</li>";
    echo "<li>Vérifiez la configuration dans php.ini</li>";
    echo "<li>Redémarrez Apache après modification du php.ini</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Pour configurer MailHog dans php.ini :</h3>";
echo "<pre>
[mail function]
SMTP = localhost
smtp_port = 1025
sendmail_from = test@localhost.com
</pre>";

echo "<p><strong>Chemin du php.ini :</strong> " . php_ini_loaded_file() . "</p>";
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Test Mail</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <a href="mdp_oublie.php">← Retour au mot de passe oublié</a>
</body>

</html>