<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test final de configuration email</h1>";

// Inclure la configuration
require_once 'config/smtp.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Configuration du serveur
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = SMTP_AUTH;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    
    // Destinataires
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress('khalilskanderadjam@gmail.com', 'Test');
    
    // Contenu
    $mail->isHTML(true);
    $mail->Subject = 'Test SMTP - Cinéphoria';
    $mail->Body = 'Test réussi!';
    $mail->AltBody = 'Test réussi!';
    
    $mail->send();
    echo "<p style='color: green;'>✓ Email envoyé avec succès</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Vérifiez votre configuration SMTP et mot de passe.</p>";
}

echo "<h2>Configuration utilisée:</h2>";
echo "<pre>";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_USERNAME: " . SMTP_USERNAME . "\n";
echo "SMTP_PASSWORD: " . SMTP_PASSWORD . "\n";
echo "SMTP_SECURE: " . SMTP_SECURE . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "</pre>";
?>