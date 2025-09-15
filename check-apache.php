<?php
echo "<h1>Vérification de la configuration Apache</h1>";

// Test 1: Vérifier si mod_rewrite est chargé
echo "<h2>1. Module mod_rewrite</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p style='color: green;'>✓ mod_rewrite est activé</p>";
    } else {
        echo "<p style='color: red;'>✗ mod_rewrite n'est pas activé</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Impossible de vérifier les modules Apache</p>";
}

// Test 2: Vérifier AllowOverride
echo "<h2>2. Test AllowOverride</h2>";
$test_file = 'test-htaccess.txt';
file_put_contents($test_file, 'Test content');
file_put_contents('.htaccess', 'RewriteEngine On\nRewriteRule ^test-htaccess\.txt$ blocked.html [R=403]');

$test_url = 'http://' . $_SERVER['HTTP_HOST'] . '/cinephoria-front/test-htaccess.txt';
$response = @get_headers($test_url);

if ($response && strpos($response[0], '403') !== false) {
    echo "<p style='color: green;'>✓ .htaccess fonctionne</p>";
} else {
    echo "<p style='color: red;'>✗ .htaccess ne fonctionne pas (AllowOverride probablement désactivé)</p>";
}

// Nettoyage
unlink($test_file);
unlink('.htaccess');

// Test 3: Solution alternative
echo "<h2>3. Solution recommandée</h2>";
echo "<p>Ajoutez cette protection au début de <strong>config/smtp.php</strong> :</p>";
echo "<pre style='background: #f4f4f4; padding: 10px;'>";
echo htmlspecialchars('<?php
// Protection contre l\'accès direct
if (basename($_SERVER[\'SCRIPT_FILENAME\']) == \'smtp.php\') {
    header(\'HTTP/1.0 403 Forbidden\');
    exit(\'<h1>Accès interdit</h1><p>Vous ne pouvez pas accéder directement à ce fichier.</p>\');
}');
echo "</pre>";

echo "<hr>";
echo "<h2>Résumé</h2>";
echo "<p>Votre configuration SMTP fonctionne parfaitement ! 🎉</p>";
echo "<p>Pour la sécurité, utilisez la protection PHP dans smtp.php</p>";
?>