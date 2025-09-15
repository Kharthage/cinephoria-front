<?php
// Désactiver l'affichage des erreurs en production, mais activer pour le test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de configuration SMTP - Cinéphoria</h1>";

// Test 1: Vérifier que le fichier de configuration est accessible
echo "<h2>1. Vérification du fichier de configuration</h2>";
if (file_exists('config/smtp.php')) {
    echo "<p style='color: green;'>✓ Fichier config/smtp.php trouvé</p>";
    
    // Inclure le fichier de configuration
    require_once 'config/smtp.php';
    echo "<p style='color: green;'>✓ Configuration SMTP chargée</p>";
    
} else {
    echo "<p style='color: red;'>✗ Fichier config/smtp.php introuvable</p>";
    exit;
}

// Test 2: Vérifier que PHPMailer est installé
echo "<h2>2. Vérification de PHPMailer</h2>";
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    echo "<p style='color: green;'>✓ PHPMailer installé</p>";
} else {
    echo "<p style='color: red;'>✗ PHPMailer non installé. Exécutez: composer require phpmailer/phpmailer</p>";
    exit;
}

// Test 3: Tester l'envoi d'email
echo "<h2>3. Test d'envoi d'email</h2>";

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
    $mail->addAddress('khalilskanderadjam@gmail.com', 'Khalil');
    
    // Contenu
    $mail->isHTML(true);
    $mail->Subject = 'Test SMTP - Cinéphoria';
    $mail->Body = '
    <html>
    <body style="font-family: Arial, sans-serif; background-color: #0D0D15; color: #FFFFFF; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #1A1A2E; padding: 30px; border-radius: 10px;">
            <div style="text-align: center; padding: 20px; border-bottom: 3px solid #E50914;">
                <h2 style="color: #E50914;">Cinéphoria</h2>
            </div>
            <div style="padding: 30px;">
                <h3>Test de configuration SMTP réussi !</h3>
                <p>Félicitations ! Votre configuration SMTP fonctionne correctement.</p>
                <p><strong>Date :</strong> ' . date('Y-m-d H:i:s') . '</p>
                <p><strong>Serveur :</strong> ' . SMTP_HOST . '</p>
            </div>
            <div style="background: #0D0D15; padding: 20px; text-align: center; color: #B8B8B8; font-size: 12px;">
                <p>Email de test - Cinéphoria</p>
            </div>
        </div>
    </body>
    </html>';
    
    $mail->AltBody = 'Test de configuration SMTP réussi! Date: ' . date('Y-m-d H:i:s');
    
    $mail->send();
    echo "<p style='color: green;'>✓ Email envoyé avec succès à khalilskanderadjam@gmail.com</p>";
    echo "<p>Vérifiez votre boîte de réception.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur lors de l'envoi: " . $mail->ErrorInfo . "</p>";
    echo "<p>Vérifiez vos paramètres SMTP et votre mot de passe d'application.</p>";
}

echo "<hr>";
echo "<h2>Configuration actuelle</h2>";
echo "<pre>";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_AUTH: " . (SMTP_AUTH ? 'true' : 'false') . "\n";
echo "SMTP_USERNAME: " . SMTP_USERNAME . "\n";
echo "SMTP_PASSWORD: " . str_repeat('*', strlen(SMTP_PASSWORD)) . "\n";
echo "SMTP_SECURE: " . SMTP_SECURE . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_FROM_EMAIL: " . SMTP_FROM_EMAIL . "\n";
echo "SMTP_FROM_NAME: " . SMTP_FROM_NAME . "\n";
echo "</pre>";

// Test 4: Vérifier la sécurité
echo "<h2>4. Test de sécurité</h2>";
$test_url = 'http://' . $_SERVER['HTTP_HOST'] . '/cinephoria-front/config/smtp.php';
echo "<p>Tentative d'accès direct au fichier de configuration...</p>";

// Utiliser cURL pour tester l'accès
function testUrlAccess($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode;
}

$httpCode = testUrlAccess($test_url);
if ($httpCode == 403 || $httpCode == 404) {
    echo "<p style='color: green;'>✓ Accès direct bloqué (Code HTTP: $httpCode)</p>";
} else {
    echo "<p style='color: red;'>✗ Problème de sécurité: Le fichier est accessible (Code HTTP: $httpCode)</p>";
    echo "<p>Assurez-vous d'avoir créé le fichier .htaccess dans le dossier config/</p>";
}
?>