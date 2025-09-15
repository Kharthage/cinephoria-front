<?php
// Protection contre l'accès direct
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('<h1>Accès interdit</h1><p>Vous ne pouvez pas accéder directement à ce fichier.</p>');
}

// Configuration SMTP pour Outlook/Hotmail
define('SMTP_HOST', 'smtp.gmail.com'); // ou 'smtp-mail.outlook.com'
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'khalilskanderadjam@gmail.com'); // Votre adresse Hotmail
define('SMTP_PASSWORD', 'sqbe svwu axfp kulj'); // Votre mot de passe Hotmail
define('SMTP_SECURE', 'tls'); // ou 'ssl'
define('SMTP_PORT', 587); // Port pour Outlook (ou 465 avec SSL)
define('SMTP_FROM_EMAIL', 'khalil_adjam@hotmail.fr');
define('SMTP_FROM_NAME', 'Cinéphoria');
define('SMTP_DEBUG', 0); // 0 = pas de debug, 1 = erreurs seulement, 2 = tout
define('SMTP_CHARSET', 'UTF-8');
