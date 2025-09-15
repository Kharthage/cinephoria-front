<?php
// Vérifier si le fichier est appelé directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Accès interdit');
}

// Configuration SMTP pour Cinéphoria
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'khalilskanderadjam@gmail.com');
define('SMTP_PASSWORD', 'yixs bgsq jfse dcdh');
define('SMTP_SECURE', 'tls');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'khalilskanderadjam@gmail.com');
define('SMTP_FROM_NAME', 'Cinéphoria');
define('SMTP_DEBUG', 0);
define('SMTP_CHARSET', 'UTF-8');
?>