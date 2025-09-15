<?php
session_start();
require 'testpdo.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    h2 { color: #2c3e50; border-bottom: 2px solid #d4af37; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .highlight { background-color: #fff3cd; }
    .match { background-color: #d4edda; }
    .debug { background-color: #e7f3ff; padding: 15px; margin: 15px 0; border-radius: 5px; }
</style>";

echo "<h1>🔍 Diagnostic Complet - Utilisateur: " . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . " (ID: {$user['id']})</h1>";

// 1. Vos commandes
echo "<h2>📋 Vos Commandes (13 trouvées)</h2>";
try {
    $sql = "SELECT * FROM commande WHERE id_utilisateur = ? ORDER BY date_commande DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
    $commandes = $stmt->fetchAll();

    if (!empty($commandes)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Code Résa</th><th>Date</th><th>Total</th><th>Statut</th><th>Autres infos</th></tr>";
        foreach ($commandes as $cmd) {
            echo "<tr class='match'>";
            echo "<td>{$cmd['id']}</td>";
            echo "<td>" . htmlspecialchars($cmd['code_reservation'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($cmd['date_commande'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($cmd['total'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($cmd['statut'] ?? '-') . "</td>";

            $autres = [];
            foreach ($cmd as $key => $value) {
                if (!in_array($key, ['id', 'code_reservation', 'date_commande', 'total', 'statut', 'id_utilisateur'])) {
                    $autres[] = "$key: " . htmlspecialchars($value ?? 'NULL');
                }
            }
            echo "<td style='font-size: 11px;'>" . implode('<br>', $autres) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Récupérer les IDs de commandes pour la suite
        $ids_commandes = array_column($commandes, 'id');
    } else {
        echo "<p>Aucune commande trouvée.</p>";
        $ids_commandes = [];
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
    $ids_commandes = [];
}

// 2. Réservations liées à vos commandes
echo "<h2>🎫 Réservations liées à vos commandes</h2>";
if (!empty($ids_commandes)) {
    try {
        $placeholders = implode(',', array_fill(0, count($ids_commandes), '?'));
        $sql = "SELECT * FROM reservation WHERE id_commande IN ($placeholders) ORDER BY date_reservation DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids_commandes);
        $reservations_liees = $stmt->fetchAll();

        if (!empty($reservations_liees)) {
            echo "<table>";
            echo "<tr><th>ID</th><th>ID Commande</th><th>Nom</th><th>Date Résa</th><th>Places</th><th>Total</th><th>Statut</th><th>Autres</th></tr>";
            foreach ($reservations_liees as $resa) {
                echo "<tr class='match'>";
                echo "<td>{$resa['id']}</td>";
                echo "<td>{$resa['id_commande']}</td>";
                echo "<td><strong>\"" . htmlspecialchars($resa['nom'] ?? '-') . "\"</strong></td>";
                echo "<td>" . htmlspecialchars($resa['date_reservation'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($resa['places'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($resa['total'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($resa['status'] ?? '-') . "</td>";

                $autres = [];
                foreach ($resa as $key => $value) {
                    if (!in_array($key, ['id', 'id_commande', 'nom', 'date_reservation', 'places', 'total', 'status'])) {
                        $autres[] = "$key: " . htmlspecialchars($value ?? 'NULL');
                    }
                }
                echo "<td style='font-size: 10px;'>" . implode('<br>', array_slice($autres, 0, 3)) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='debug'><strong>⚠️ PROBLÈME DÉTECTÉ:</strong> Vous avez 13 commandes mais aucune réservation liée à ces commandes !</div>";
        }
    } catch (PDOException $e) {
        echo "Erreur: " . $e->getMessage();
    }
} else {
    echo "<p>Aucune commande trouvée pour rechercher des réservations.</p>";
}

// 3. Toutes les réservations avec les noms similaires
echo "<h2>👥 Toutes les réservations avec noms contenant 'adjam' ou 'khalil'</h2>";
try {
    $sql = "SELECT * FROM reservation WHERE LOWER(nom) LIKE '%adjam%' OR LOWER(nom) LIKE '%khalil%' ORDER BY date_reservation DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reservations_similaires = $stmt->fetchAll();

    if (!empty($reservations_similaires)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>ID Commande</th><th>Nom</th><th>Date</th><th>Places</th><th>Total</th><th>Correspondance</th></tr>";
        foreach ($reservations_similaires as $resa) {
            $nom_lower = strtolower($resa['nom'] ?? '');
            $class = '';
            $correspondance = '';

            if ($nom_lower === 'adjam khalil' || $nom_lower === 'khalil adjam') {
                $class = 'match';
                $correspondance = '✅ Correspondance exacte';
            } elseif (strpos($nom_lower, 'adjam') !== false && strpos($nom_lower, 'khalil') !== false) {
                $class = 'highlight';
                $correspondance = '⚠️ Correspondance partielle';
            } else {
                $correspondance = '❓ Nom similaire';
            }

            echo "<tr class='$class'>";
            echo "<td>{$resa['id']}</td>";
            echo "<td>" . htmlspecialchars($resa['id_commande'] ?? '-') . "</td>";
            echo "<td><strong>\"" . htmlspecialchars($resa['nom'] ?? '-') . "\"</strong></td>";
            echo "<td>" . htmlspecialchars($resa['date_reservation'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($resa['places'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($resa['total'] ?? '-') . "</td>";
            echo "<td>$correspondance</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Aucune réservation trouvée avec des noms similaires.</p>";
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}

// 4. Échantillon de toutes les réservations pour voir les noms
echo "<h2>📊 Échantillon de toutes les réservations (20 dernières)</h2>";
try {
    $sql = "SELECT id, nom, date_reservation, id_commande FROM reservation ORDER BY date_reservation DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $echantillon = $stmt->fetchAll();

    if (!empty($echantillon)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nom</th><th>Date</th><th>ID Commande</th></tr>";
        foreach ($echantillon as $resa) {
            $nom_lower = strtolower($resa['nom'] ?? '');
            $class = '';
            if (strpos($nom_lower, 'adjam') !== false || strpos($nom_lower, 'khalil') !== false) {
                $class = 'highlight';
            }

            echo "<tr class='$class'>";
            echo "<td>{$resa['id']}</td>";
            echo "<td>\"" . htmlspecialchars($resa['nom'] ?? '-') . "\"</td>";
            echo "<td>" . htmlspecialchars($resa['date_reservation'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($resa['id_commande'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}

// 5. Analyse et recommandations
echo "<h2>💡 Analyse et Solutions</h2>";
echo "<div class='debug'>";

if (isset($reservations_liees) && empty($reservations_liees) && !empty($ids_commandes)) {
    echo "<h3>🔥 PROBLÈME PRINCIPAL DÉTECTÉ:</h3>";
    echo "<p><strong>Vous avez 13 commandes mais aucune réservation liée à ces commandes.</strong></p>";
    echo "<p>Cela peut indiquer :</p>";
    echo "<ul>";
    echo "<li>❌ Les réservations ne sont pas correctement liées aux commandes (problème de clé étrangère)</li>";
    echo "<li>❌ Les réservations sont dans une autre table ou avec des IDs de commande incorrects</li>";
    echo "<li>❌ Il y a eu un problème lors de l'insertion des réservations</li>";
    echo "</ul>";

    echo "<h3>🛠️ Solutions possibles :</h3>";
    echo "<ol>";
    echo "<li><strong>Vérifier la liaison :</strong> Regarder si vos réservations existent mais avec des id_commande différents</li>";
    echo "<li><strong>Réparer les liaisons :</strong> Mettre à jour les id_commande dans la table reservation</li>";
    echo "<li><strong>Recherche par nom :</strong> En attendant, utiliser le nom 'adjam khalil' pour trouver vos réservations</li>";
    echo "</ol>";
}

echo "</div>";

echo "<p><a href='moncompte.php' style='padding: 10px 20px; background: #d4af37; color: #1a2a3a; text-decoration: none; border-radius: 4px;'>← Retour à Mon Compte</a></p>";
