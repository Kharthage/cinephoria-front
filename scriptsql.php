<?php
// ================================================
// SCRIPT D'ANALYSE SÉCURISÉ - BASE cinephoria
// ================================================

try {
    $pdo = new PDO("mysql:host=sql7.freesqldatabase.com;dbname=sql7798672;charset=utf8mb4", 'sql7798672', 'ndviH1KDRs', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "<h1>🎬 Analyse sécurisée de la base cinephoria</h1>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .table-section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table-name { color: #2563eb; font-size: 24px; margin-bottom: 15px; }
        .structure { background: #f8fafc; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .data-table th { background: #4f46e5; color: white; }
        .data-table tr:nth-child(even) { background: #f9f9f9; }
        .count { background: #10b981; color: white; padding: 5px 10px; border-radius: 20px; font-size: 14px; }
        .error { background: #ef4444; color: white; padding: 5px 10px; border-radius: 20px; font-size: 14px; }
        .no-data { color: #ef4444; font-style: italic; }
        pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .warning { background: #f59e0b; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>";

    echo "<div class='container'>";

    // ================================================
    // 1. LISTE DE TOUTES LES TABLES AVEC VÉRIFICATION
    // ================================================
    echo "<div class='table-section'>";
    echo "<h2>📋 Analyse des tables dans cinephoria</h2>";

    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $validTables = [];
    $errorTables = [];

    echo "<p>🔍 <strong>" . count($allTables) . " table(s) détectée(s), vérification en cours...</strong></p>";

    // Vérifier chaque table
    foreach ($allTables as $table) {
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $countStmt->fetch()['count'];
            $validTables[$table] = $count;
            echo "<li>✅ <strong>$table</strong> <span class='count'>$count enregistrements</span></li>";
        } catch (Exception $e) {
            $errorTables[$table] = $e->getMessage();
            echo "<li>❌ <strong>$table</strong> <span class='error'>ERREUR</span> - " . htmlspecialchars($e->getMessage()) . "</li>";
        }
    }

    echo "<div class='warning'>";
    echo "<strong>⚠️ Résumé :</strong><br>";
    echo "✅ Tables accessibles : " . count($validTables) . "<br>";
    echo "❌ Tables avec erreurs : " . count($errorTables);
    if (!empty($errorTables)) {
        echo " (probablement des tables temporaires ou corrompues)";
    }
    echo "</div>";

    echo "</div>";

    // ================================================
    // 2. ANALYSE DÉTAILLÉE DES TABLES VALIDES UNIQUEMENT
    // ================================================
    foreach ($validTables as $table => $count) {
        echo "<div class='table-section'>";
        echo "<h2 class='table-name'>🗂️ Table: $table</h2>";

        try {
            // Structure de la table
            echo "<h3>📐 Structure</h3>";
            echo "<div class='structure'>";
            $stmt = $pdo->query("DESCRIBE `$table`");
            $structure = $stmt->fetchAll();

            echo "<table class='data-table'>";
            echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
            foreach ($structure as $col) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>" . (isset($col['Default']) ? $col['Default'] : 'NULL') . "</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";

            // Données de la table
            echo "<h3>📊 Données ($count enregistrements)</h3>";

            if ($count > 0) {
                // Afficher les 3 premiers enregistrements pour éviter la surcharge
                $limit = min(3, $count);
                $stmt = $pdo->query("SELECT * FROM `$table` LIMIT $limit");
                $data = $stmt->fetchAll();

                if (!empty($data)) {
                    echo "<p><em>Affichage des $limit premiers enregistrements :</em></p>";
                    echo "<table class='data-table'>";

                    // En-têtes
                    echo "<tr>";
                    foreach (array_keys($data[0]) as $column) {
                        echo "<th>$column</th>";
                    }
                    echo "</tr>";

                    // Données (limitées pour lisibilité)
                    foreach ($data as $row) {
                        echo "<tr>";
                        foreach ($row as $value) {
                            $displayValue = $value;
                            if (is_string($value) && strlen($value) > 30) {
                                $displayValue = substr($value, 0, 30) . '...';
                            }
                            if (is_null($value)) {
                                $displayValue = '<em style="color: #999;">NULL</em>';
                            }
                            echo "<td>" . htmlspecialchars($displayValue) . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</table>";

                    if ($count > 3) {
                        echo "<p><em>... et " . ($count - 3) . " autres enregistrements</em></p>";
                    }
                }
            } else {
                echo "<p class='no-data'>⚠️ Table vide</p>";
            }
        } catch (Exception $e) {
            echo "<p class='no-data'>❌ Erreur lors de l'analyse de cette table : " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        echo "</div>";
    }

    // ================================================
    // 3. GÉNÉRATION DES REQUÊTES SQL POUR LES TABLES VALIDES
    // ================================================
    echo "<div class='table-section'>";
    echo "<h2>⚙️ Scripts SQL de création (tables valides uniquement)</h2>";

    foreach (array_keys($validTables) as $table) {
        echo "<h3>📝 CREATE TABLE: $table</h3>";

        try {
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch();

            echo "<pre>" . htmlspecialchars($createTable['Create Table']) . ";</pre>";
        } catch (Exception $e) {
            echo "<p class='no-data'>❌ Impossible de récupérer la structure : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    echo "</div>";

    // ================================================
    // 4. RÉSUMÉ POUR L'API
    // ================================================
    echo "<div class='table-section'>";
    echo "<h2>📋 Résumé pour l'API</h2>";
    echo "<p><strong>Tables utilisables pour l'API Cinéphoria :</strong></p>";
    echo "<ul>";
    foreach ($validTables as $table => $count) {
        $tableType = "Autre";
        if (strpos($table, 'film') !== false) $tableType = "🎬 Films";
        elseif (strpos($table, 'user') !== false || strpos($table, 'client') !== false) $tableType = "👤 Utilisateurs";
        elseif (strpos($table, 'seance') !== false || strpos($table, 'session') !== false) $tableType = "🎭 Séances";
        elseif (strpos($table, 'reservation') !== false || strpos($table, 'booking') !== false) $tableType = "🎫 Réservations";
        elseif (strpos($table, 'cinema') !== false) $tableType = "🏢 Cinémas";
        elseif (strpos($table, 'salle') !== false) $tableType = "🪑 Salles";
        elseif (strpos($table, 'avis_utilisateur') !== false) $tableType = "⭐ Avis";

        echo "<li>$tableType <strong>$table</strong> ($count enregistrements)</li>";
    }
    echo "</ul>";

    echo "<h3>🔧 Configuration PDO validée :</h3>";
    echo "<pre>";
    echo htmlspecialchars('<?php
// ✅ Configuration validée pour cinephoria
$pdo = new PDO("mysql:host=localhost;dbname=cinephoria;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Tables disponibles et utilisables :
' . '/*
' . implode("\n", array_map(function ($table, $count) {
        return "- $table ($count enregistrements)";
    }, array_keys($validTables), $validTables)) . '
*/
?>');
    echo "</pre>";

    echo "</div>";

    echo "</div>"; // Fermeture container

} catch (PDOException $e) {
    echo "<h1>❌ Erreur de connexion majeure</h1>";
    echo "<p>Impossible de se connecter à la base cinephoria :</p>";
    echo "<pre>Erreur : " . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><strong>Solutions possibles :</strong></p>";
    echo "<ul>";
    echo "<li>🔄 Redémarrez MySQL (XAMPP/WAMP)</li>";
    echo "<li>🗂️ Vérifiez que la base 'cinephoria' existe vraiment</li>";
    echo "<li>🔧 Testez avec phpMyAdmin d'abord</li>";
    echo "<li>🗑️ Supprimez les tables temporaires corrompues</li>";
    echo "</ul>";
}
