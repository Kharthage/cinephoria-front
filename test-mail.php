<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - Cinéphoria</title>
    <style>
        :root {
            --primary: #0D0D15;
            --secondary: #1A1A2E;
            --accent: #E50914;
            --text: #FFFFFF;
            --text-secondary: #B8B8B8;
            --success: #4CAF50;
            --error: #F44336;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: var(--primary);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(29, 29, 49, 0.6) 0%, transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(29, 29, 49, 0.6) 0%, transparent 25%);
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            background: linear-gradient(135deg, var(--secondary) 0%, #16213E 100%);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .header::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: var(--accent);
            margin: 1rem auto;
            border-radius: 2px;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(to right, #fff, var(--accent));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }
        
        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 1.5rem 0 1rem;
            color: var(--text);
            display: flex;
            align-items: center;
        }
        
        h2::before {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            background: var(--accent);
            margin-right: 10px;
            border-radius: 2px;
        }
        
        .status {
            padding: 1.2rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            text-align: center;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .success {
            background: rgba(76, 175, 80, 0.15);
            color: var(--success);
            border-color: rgba(76, 175, 80, 0.3);
        }
        
        .error {
            background: rgba(244, 67, 54, 0.15);
            color: var(--error);
            border-color: rgba(244, 67, 54, 0.3);
        }
        
        .info-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .info-item {
            display: flex;
            margin-bottom: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 180px;
        }
        
        .info-value {
            color: var(--text);
            font-family: monospace;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 500;
            color: var(--text);
        }
        
        input, textarea {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.2);
        }
        
        textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        button {
            background: linear-gradient(135deg, var(--accent) 0%, #B80712 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: block;
            margin: 2rem auto;
            width: 220px;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .terminal {
            background: rgba(0, 0, 0, 0.3);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            border: 1px solid rgba(255, 255, 255, 0.08);
            overflow-x: auto;
        }
        
        .terminal span.green {
            color: #4CAF50;
        }
        
        .terminal span.red {
            color: #F44336;
        }
        
        .code-block {
            background: rgba(0, 0, 0, 0.3);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            overflow-x: auto;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .note {
            background: rgba(229, 9, 20, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--accent);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                margin-bottom: 0.5rem;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Test d'envoi d'email</h1>
            <p>Vérifiez votre configuration SMTP avec PHPMailer</p>
        </div>
        
        <h2>État de l'installation</h2>
        
        <div class="terminal">
            <?php
            // Vérifier si Composer est installé
            echo "<strong>Vérification de Composer :</strong>\n";
            $composerCheck = shell_exec('composer --version 2>&1');
            if (strpos($composerCheck, 'Composer version') !== false) {
                echo "<span class='green'>✓ Composer est installé</span>\n";
                echo $composerCheck . "\n";
            } else {
                echo "<span class='red'>✗ Composer n'est pas installé</span>\n";
            }
            
            echo "\n<strong>Vérification de PHPMailer :</strong>\n";
            // Vérifier si PHPMailer est installé via Composer
            if (file_exists('vendor/autoload.php')) {
                require 'vendor/autoload.php';
                $phpmailerCheck = shell_exec('composer show phpmailer/phpmailer 2>&1');
                if (strpos($phpmailerCheck, 'phpmailer/phpmailer') !== false) {
                    echo "<span class='green'>✓ PHPMailer est installé via Composer</span>\n";
                    // Extraire la version
                    preg_match('/versions : (.*)\n/', $phpmailerCheck, $matches);
                    if (!empty($matches[1])) {
                        echo "Version : " . $matches[1] . "\n";
                    }
                } else {
                    echo "<span class='red'>✗ PHPMailer n'est pas installé via Composer</span>\n";
                }
            } else {
                echo "<span class='red'>✗ Le dossier vendor/ n'existe pas. PHPMailer n'est pas installé.</span>\n";
            }
            ?>
        </div>
        
        <h2>Configuration Gmail</h2>
        
        <div class="info-box">
            <div class="info-item">
                <span class="info-label">Serveur SMTP:</span>
                <span class="info-value">smtp.gmail.com</span>
            </div>
            <div class="info-item">
                <span class="info-label">Port:</span>
                <span class="info-value">587</span>
            </div>
            <div class="info-item">
                <span class="info-label">Protocole de sécurité:</span>
                <span class="info-value">TLS</span>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value">khalilskanderadjam@gmail.com</span>
            </div>
            <div class="info-item">
                <span class="info-label">Mot de passe d'application:</span>
                <span class="info-value">yixs bgsq jfse dcdh</span>
            </div>
        </div>
        
        <div class="note">
            <p>Assurez-vous d'avoir activé la validation en 2 étapes sur votre compte Google et d'utiliser un mot de passe d'application.</p>
        </div>
        
        <h2>Test d'envoi d'email</h2>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email destinataire:</label>
                <input type="email" id="email" name="email" value="khalilskanderadjam@gmail.com" required>
            </div>
            
            <div class="form-group">
                <label for="subject">Sujet:</label>
                <input type="text" id="subject" name="subject" value="Test PHPMailer - Cinéphoria" required>
            </div>
            
            <div class="form-group">
                <label for="message">Message:</label>
                <textarea id="message" name="message" required>Ceci est un test de configuration email pour Cinéphoria. Si vous recevez cet email, cela signifie que votre configuration PHPMailer fonctionne correctement!</textarea>
            </div>
            
            <button type="submit" name="test_email">Tester l'envoi d'email</button>
        </form>
        
        <?php
        if (isset($_POST['test_email'])) {
            if (!file_exists('vendor/autoload.php')) {
                echo '<div class="status error">PHPMailer n\'est pas installé. Veuillez exécuter: composer require phpmailer/phpmailer</div>';
            } else {
                require 'vendor/autoload.php';
                
                $to = $_POST['email'];
                $subject = $_POST['subject'];
                $message = $_POST['message'];
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                try {
                    // Configuration du serveur
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'khalilskanderadjam@gmail.com';
                    $mail->Password = 'yixs bgsq jfse dcdh';
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    // Destinataires
                    $mail->setFrom('khalilskanderadjam@gmail.com', 'Cinéphoria');
                    $mail->addAddress($to);
                    
                    // Contenu
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = '
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; background-color: #0D0D15; color: #FFFFFF; padding: 20px; }
                            .container { max-width: 600px; margin: 0 auto; }
                            .header { text-align: center; padding: 20px 0; }
                            .content { background: #1A1A2E; padding: 30px; border-radius: 10px; }
                            .footer { text-align: center; padding: 20px; color: #B8B8B8; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h2 style="color: #E50914;">Cinéphoria</h2>
                            </div>
                            <div class="content">
                                <h3>'.$subject.'</h3>
                                <p>'.nl2br(htmlspecialchars($message)).'</p>
                                <p><strong>Date :</strong> ' . date('Y-m-d H:i:s') . '</p>
                            </div>
                            <div class="footer">
                                <p>Cet email a été envoyé depuis le système de test Cinéphoria</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    
                    $mail->AltBody = strip_tags($message);
                    
                    $mail->send();
                    echo '<div class="status success">Email envoyé avec succès à ' . $to . '</div>';
                } catch (Exception $e) {
                    echo '<div class="status error">Erreur lors de l\'envoi: ' . $mail->ErrorInfo . '</div>';
                }
            }
        }
        ?>
        
        <h2>Code d'exemple</h2>
        
        <div class="code-block">
// Inclure l'autoloader de Composer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Créer une instance
$mail = new PHPMailer(true);

try {
    // Configuration du serveur
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'khalilskanderadjam@gmail.com';
    $mail->Password = 'yixs bgsq jfse dcdh';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Destinataires
    $mail->setFrom('khalilskanderadjam@gmail.com', 'Cinéphoria');
    $mail->addAddress('destinataire@example.com');
    
    // Contenu
    $mail->isHTML(true);
    $mail->Subject = 'Sujet de l\'email';
    $mail->Body = '&lt;h1 style="color: #E50914;">Contenu HTML de l\'email&lt;/h1>&lt;p>Message...&lt;/p>';
    
    $mail->send();
    echo 'Email envoyé avec succès';
} catch (Exception $e) {
    echo "Erreur: {$mail->ErrorInfo}";
}
        </div>
    </div>
</body>
</html>