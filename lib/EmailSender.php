<?php
require_once __DIR__ . '/../config/smtp.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{
    private $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }

    private function configure()
    {
        // Configuration du serveur
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = SMTP_AUTH;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = SMTP_SECURE;
        $this->mailer->Port = SMTP_PORT;

        // Configuration de l'expéditeur
        $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Options supplémentaires
        $this->mailer->CharSet = SMTP_CHARSET;
        $this->mailer->SMTPDebug = SMTP_DEBUG;
    }

    public function sendEmail($to, $toName, $subject, $body, $altBody = '')
    {
        try {
            // Ajouter le destinataire
            $this->mailer->addAddress($to, $toName);

            // Contenu de l'email
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $this->wrapInTemplate($body);
            $this->mailer->AltBody = $altBody ?: strip_tags($body);

            // Envoyer l'email
            $this->mailer->send();

            // Réinitialiser pour le prochain email
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            return true;
        } catch (Exception $e) {
            error_log("Erreur d'envoi d'email: " . $this->mailer->ErrorInfo);
            // Pour débogage, afficher l'erreur (à retirer en production)
            echo "Erreur: " . $this->mailer->ErrorInfo;
            return false;
        }
    }

    private function wrapInTemplate($content)
    {
        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Cinéphoria</title>
            <style>
                body { 
                    font-family: "Poppins", Arial, sans-serif; 
                    background-color: #0D0D15; 
                    color: #FFFFFF; 
                    margin: 0; 
                    padding: 0; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: #1A1A2E; 
                }
                .header { 
                    background: linear-gradient(135deg, #0D0D15 0%, #1A1A2E 100%);
                    padding: 30px 20px; 
                    text-align: center; 
                    border-bottom: 3px solid #E50914; 
                }
                .logo { 
                    color: #E50914; 
                    font-size: 28px; 
                    font-weight: bold; 
                    text-decoration: none;
                }
                .content { 
                    padding: 30px; 
                    line-height: 1.6; 
                }
                .footer { 
                    background: #0D0D15; 
                    padding: 20px; 
                    text-align: center; 
                    color: #B8B8B8; 
                    font-size: 12px; 
                }
                .button {
                    display: inline-block;
                    background: #E50914;
                    color: white;
                    padding: 12px 25px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <a href="#" class="logo">Cinéphoria</a>
                </div>
                <div class="content">'
            . $content .
            '</div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Cinéphoria. Tous droits réservés.</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    // Méthodes pour différents types d'emails
    public function sendWelcomeEmail($to, $toName)
    {
        $subject = "Bienvenue sur Cinéphoria !";
        $body = "
            <h2>Bienvenue $toName !</h2>
            <p>Merci de vous être inscrit sur Cinéphoria, votre plateforme de réservation de cinéma.</p>
            <p>Découvrez dès maintenant les derniers films à l'affiche et réservez vos places en quelques clics.</p>
            <p><a href='http://localhost/cinephoria-front/' class='button'>Explorer les films</a></p>
            <p>À très vite sur Cinéphoria !</p>
        ";

        return $this->sendEmail($to, $toName, $subject, $body);
    }

    public function sendBookingConfirmation($to, $toName, $bookingDetails)
    {
        $subject = "Confirmation de réservation - Cinéphoria";
        $body = "
            <h2>Confirmation de réservation</h2>
            <p>Merci $toName pour votre réservation !</p>
            <p><strong>Détails de la réservation :</strong></p>
            <p>Film : <strong>{$bookingDetails['movie']}</strong></p>
            <p>Date : <strong>{$bookingDetails['date']}</strong></p>
            <p>Heure : <strong>{$bookingDetails['time']}</strong></p>
            <p>Place(s) : <strong>{$bookingDetails['seats']}</strong></p>
            <p>Référence : <strong>{$bookingDetails['reference']}</strong></p>
            <p>Présentez cette référence à l'accueil du cinéma.</p>
            <p><a href='http://localhost/cinephoria-front/moncompte.php' class='button'>Voir mes réservations</a></p>
        ";

        return $this->sendEmail($to, $toName, $subject, $body);
    }

    public function sendPasswordReset($to, $toName, $resetToken)
    {
        $subject = "Réinitialisation de mot de passe - Cinéphoria";
        $resetLink = "http://localhost/cinephoria-front/reinitialisation.php?token=$resetToken";
        $body = "
            <h2>Réinitialisation de mot de passe</h2>
            <p>Bonjour $toName,</p>
            <p>Vous avez demandé à réinitialiser votre mot de passe.</p>
            <p>Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe :</p>
            <p><a href='$resetLink' class='button'>Réinitialiser mon mot de passe</a></p>
            <p>Ce lien expirera dans 1 heure pour des raisons de sécurité.</p>
            <p>Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.</p>
        ";

        return $this->sendEmail($to, $toName, $subject, $body);
    }
}
