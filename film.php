<?php
session_start();
require_once 'testpdo.php';

// Vérifier si un ID de film est fourni
if (!isset($_GET['id']) && !isset($_GET['id_film'])) {
    header('Location: index.php');
    exit;
}

$filmId = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_GET['id_film'];
$messageAvis = "";

// Message de connexion
$messageConnexion = "";
$messageAuth = "";
if (isset($_SESSION['user'])) {
    $username = is_array($_SESSION['user']) ? $_SESSION['user']['nom'] ?? 'Utilisateur' : $_SESSION['user'];
    $messageConnexion = "Connecté en tant que " . htmlspecialchars($username);
    if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
        $messageAuth = "🎉 Bienvenue " . htmlspecialchars($username) . " !";
        unset($_SESSION['just_logged_in']);
    }
}

// Traitement de l'ajout d'un avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_avis']) && isset($_SESSION['user'])) {
    $note = (int)$_POST['note'];
    $commentaire = trim($_POST['commentaire']);
    $userId = is_array($_SESSION['user']) ? $_SESSION['user']['id'] : null;

    if ($userId && $note >= 1 && $note <= 5) {
        try {
            // Vérifier si l'utilisateur a déjà donné un avis pour ce film
            $stmt = $pdo->prepare("SELECT id FROM avis_utilisateur WHERE id_utilisateur = ? AND id_film = ?");
            $stmt->execute([$userId, $filmId]);

            if ($stmt->fetch()) {
                $messageAvis = "❌ Vous avez déjà donné votre avis sur ce film.";
            } else {
                // Ajouter l'avis
                $stmt = $pdo->prepare("INSERT INTO avis_utilisateur (id_utilisateur, id_film, note, commentaire, date_ajout, est_valide) VALUES (?, ?, ?, ?, NOW(), 1)");
                $stmt->execute([$userId, $filmId, $note, $commentaire]);
                $messageAvis = "✅ Votre avis a été ajouté avec succès !";
            }
        } catch (PDOException $e) {
            $messageAvis = "❌ Erreur lors de l'ajout de votre avis.";
        }
    } else {
        $messageAvis = "❌ Veuillez donner une note entre 1 et 5 étoiles.";
    }
}

// Récupérer le film
$film = null;
try {
    $stmt = $pdo->prepare("
        SELECT f.id, f.titre, f.description, f.affiche, f.age_minimum, 
               f.coup_de_coeur, f.note_moyenne, f.affiche_url, f.bande_annonce_url,
               GROUP_CONCAT(DISTINCT g.nom ORDER BY g.nom SEPARATOR ', ') AS genres,
               AVG(a.note) as note_avis_utilisateurs,
               COUNT(a.note) as nb_avis
        FROM film f
        LEFT JOIN film_genre fg ON f.id = fg.id_film
        LEFT JOIN genre g ON fg.id_genre = g.id
        LEFT JOIN avis_utilisateur a ON f.id = a.id_film AND a.est_valide = 1
        WHERE f.id = ?
        GROUP BY f.id
    ");
    $stmt->execute([$filmId]);
    $film = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération du film : " . $e->getMessage());
}

if (!$film) {
    header('Location: index.php');
    exit;
}

// Récupérer les avis des utilisateurs
$avis = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.nom as nom_utilisateur, u.prenom as prenom_utilisateur
        FROM avis_utilisateur a
        LEFT JOIN utilisateur u ON a.id_utilisateur = u.id
        WHERE a.id_film = ? AND a.est_valide = 1
        ORDER BY a.date_ajout DESC
    ");
    $stmt->execute([$filmId]);
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorer l'erreur
}

// Vérifier si l'utilisateur connecté a déjà donné un avis
$utilisateurADonneAvis = false;
if (isset($_SESSION['user'])) {
    $userId = is_array($_SESSION['user']) ? $_SESSION['user']['id'] : null;
    if ($userId) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM avis_utilisateur WHERE id_utilisateur = ? AND id_film = ?");
            $stmt->execute([$userId, $filmId]);
            $utilisateurADonneAvis = (bool)$stmt->fetch();
        } catch (PDOException $e) {
            // Ignorer l'erreur
        }
    }
}

// Récupérer les séances futures
$seances = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.*, sa.reference as salle_nom
        FROM seance s
        JOIN salle sa ON s.id_salle = sa.id
        WHERE s.id_film = ? 
        AND s.status = 'active' 
        AND s.debut > NOW()
        ORDER BY s.debut
    ");
    $stmt->execute([$filmId]);
    $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorer l'erreur, $seances restera vide
}

// Fonctions utilitaires
function getYouTubeEmbedUrl($url)
{
    if (empty($url)) return null;
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }
    return null;
}

function afficherEtoiles($note, $max = 5)
{
    if ($note === null) return "Pas encore noté";
    $etoiles = '';
    $noteArrondie = round($note * 2) / 2;
    for ($i = 1; $i <= $max; $i++) {
        if ($i <= $noteArrondie) {
            $etoiles .= '⭐';
        } elseif ($i - 0.5 <= $noteArrondie) {
            $etoiles .= '⭐';
        } else {
            $etoiles .= '☆';
        }
    }
    return $etoiles . " ({$note}/5)";
}

function formatDate($date)
{
    return date('d/m/Y à H:i', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($film['titre']) ?> - Cinephoria</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .film-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .seance-item {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s;
        }

        .seance-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }

        .avis-item {
            border-left: 4px solid #28a745;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
        }

        .avis-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .avis-auteur {
            font-weight: bold;
            color: #2c3e50;
        }

        .avis-note {
            color: #f39c12;
        }

        .avis-date {
            font-size: 0.9em;
            color: #6c757d;
        }

        .formulaire-avis {
            background-color: #e9f7ef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .star-rating {
            display: flex;
            gap: 5px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s;
        }

        .star-rating label:hover,
        .star-rating input:checked~label,
        .star-rating label:hover~label {
            color: #ffd700;
        }

        .stats-avis {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">🎬 Cinephoria</a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Messages -->
        <?php if ($messageAuth): ?>
            <div class="alert alert-success"><?= $messageAuth ?></div>
        <?php endif; ?>
        <?php if ($messageConnexion): ?>
            <div class="alert alert-info"><?= $messageConnexion ?></div>
        <?php endif; ?>
        <?php if ($messageAvis): ?>
            <div class="alert alert-<?= strpos($messageAvis, '✅') === 0 ? 'success' : 'danger' ?>"><?= $messageAvis ?></div>
        <?php endif; ?>

        <a href="films.php" class="btn btn-secondary mb-3">← Retour aux films</a>

        <div class="row">
            <!-- Image du film -->
            <div class="col-md-4">
                <div class="film-card">
                    <?php
                    $imagePath = !empty($film['affiche_url']) ? $film['affiche_url'] : ("images/films/" . ($film['affiche'] ?? 'default.png'));
                    if (!file_exists($imagePath) && !filter_var($imagePath, FILTER_VALIDATE_URL)) {
                        $imagePath = "images/default.png";
                    }
                    ?>
                    <img src="<?= htmlspecialchars($imagePath) ?>" class="img-fluid" alt="<?= htmlspecialchars($film['titre']) ?>">
                </div>
            </div>

            <!-- Informations du film -->
            <div class="col-md-8">
                <h1 class="mb-3"><?= htmlspecialchars($film['titre']) ?></h1>

                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <?php if ($film['note_moyenne']): ?>
                        <span class="badge bg-warning text-dark">
                            📊 Note officielle: <?= afficherEtoiles($film['note_moyenne']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($film['note_avis_utilisateurs'] && $film['nb_avis'] > 0): ?>
                        <span class="badge bg-success">
                            👥 Avis utilisateurs: <?= afficherEtoiles($film['note_avis_utilisateurs']) ?> (<?= $film['nb_avis'] ?> avis)
                        </span>
                    <?php endif; ?>
                    <?php if ($film['age_minimum']): ?>
                        <span class="badge bg-danger">+<?= $film['age_minimum'] ?> ans</span>
                    <?php endif; ?>
                    <?php if ($film['coup_de_coeur']): ?>
                        <span class="badge bg-danger">❤️ Coup de cœur</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($film['genres'])): ?>
                    <p class="text-muted">🎭 <?= htmlspecialchars($film['genres']) ?></p>
                <?php endif; ?>

                <p class="lead"><?= nl2br(htmlspecialchars($film['description'])) ?></p>

                <!-- Bande-annonce -->
                <?php $embedUrl = getYouTubeEmbedUrl($film['bande_annonce_url']); ?>
                <?php if ($embedUrl): ?>
                    <div class="mt-4">
                        <h4>🎬 Bande-annonce</h4>
                        <div class="ratio ratio-16x9">
                            <iframe src="<?= htmlspecialchars($embedUrl) ?>" allowfullscreen></iframe>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Séances -->
        <div class="mt-5">
            <h3 class="mb-4">🎫 Séances disponibles</h3>

            <?php if (empty($seances)): ?>
                <div class="alert alert-warning">
                    <h5>🚫 Aucune séance programmée</h5>
                    <p>Ce film n'a pas de séances prévues pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($seances as $seance): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card seance-item">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?= date('d/m/Y à H:i', strtotime($seance['debut'])) ?>
                                    </h5>
                                    <p class="card-text">
                                        <strong>Salle:</strong> <?= htmlspecialchars($seance['salle_nom']) ?><br>
                                        <strong>Fin:</strong> <?= date('H:i', strtotime($seance['fin'])) ?><br>
                                        <strong>Format:</strong> <?= htmlspecialchars($seance['format']) ?><br>
                                        <strong>Prix:</strong> <?= number_format($seance['prix'], 2) ?> €
                                    </p>
                                    <?php if (isset($_SESSION['user'])): ?>
                                        <a href="reservation.php?seance_id=<?= $seance['id'] ?>"
                                            class="btn btn-primary">
                                            Réserver
                                        </a>
                                    <?php else: ?>
                                        <a href="login.php?redirect=reservation.php?seance_id=<?= $seance['id'] ?>"
                                            class="btn btn-outline-primary">
                                            Se connecter pour réserver
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section Avis des utilisateurs -->
        <div class="mt-5">
            <h3 class="mb-4">💬 Avis des spectateurs</h3>

            <!-- Statistiques des avis -->
            <?php if (!empty($avis)): ?>
                <div class="stats-avis">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>📊 Statistiques des avis</h5>
                            <p><strong>Note moyenne:</strong> <?= afficherEtoiles($film['note_avis_utilisateurs']) ?></p>
                            <p><strong>Nombre d'avis:</strong> <?= $film['nb_avis'] ?> spectateur<?= $film['nb_avis'] > 1 ? 's' : '' ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php
                            // Calculer la répartition des notes
                            $repartition = array_fill(1, 5, 0);
                            foreach ($avis as $a) {
                                if ($a['note'] >= 1 && $a['note'] <= 5) {
                                    $repartition[$a['note']]++;
                                }
                            }
                            ?>
                            <h6>Répartition des notes:</h6>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <div class="mb-1">
                                    <?= $i ?> ⭐: <?= $repartition[$i] ?> avis
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout d'avis (si connecté et pas encore d'avis) -->
            <?php if (isset($_SESSION['user']) && !$utilisateurADonneAvis): ?>
                <div class="formulaire-avis">
                    <h4>✍️ Donnez votre avis</h4>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Votre note:</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="note" value="<?= $i ?>" id="star<?= $i ?>" required>
                                    <label for="star<?= $i ?>">⭐</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Votre commentaire:</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="4"
                                placeholder="Partagez votre opinion sur ce film..."></textarea>
                        </div>
                        <button type="submit" name="ajouter_avis" class="btn btn-success">
                            📝 Publier mon avis
                        </button>
                    </form>
                </div>
            <?php elseif (!isset($_SESSION['user'])): ?>
                <div class="alert alert-info">
                    <p class="mb-0">
                        <a href="login.php?redirect=film.php?id=<?= $filmId ?>" class="btn btn-outline-primary">
                            🔑 Connectez-vous pour donner votre avis
                        </a>
                    </p>
                </div>
            <?php elseif ($utilisateurADonneAvis): ?>
                <div class="alert alert-success">
                    <p class="mb-0">✅ Vous avez déjà donné votre avis sur ce film. Merci pour votre contribution !</p>
                </div>
            <?php endif; ?>

            <!-- Affichage des avis -->
            <div class="mt-4">
                <h4>📝 Tous les avis (<?= count($avis) ?>)</h4>

                <?php if (empty($avis)): ?>
                    <div class="alert alert-info">
                        <h5>👻 Aucun avis pour le moment</h5>
                        <p>Soyez le premier à donner votre opinion sur ce film !</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($avis as $avisItem): ?>
                        <div class="avis-item">
                            <div class="avis-header">
                                <div>
                                    <span class="avis-auteur">
                                        👤 <?= htmlspecialchars($avisItem['nom_utilisateur'] ?? 'Utilisateur') ?>
                                        <?= htmlspecialchars($avisItem['prenom_utilisateur'] ?? '') ?>
                                    </span>
                                    <span class="avis-note ms-3">
                                        <?= str_repeat('⭐', $avisItem['note']) ?>
                                        <?= str_repeat('☆', 5 - $avisItem['note']) ?>
                                        (<?= $avisItem['note'] ?>/5)
                                    </span>
                                </div>
                                <span class="avis-date">
                                    🕒 <?= formatDate($avisItem['date_ajout']) ?>
                                </span>
                            </div>
                            <?php if (!empty($avisItem['commentaire'])): ?>
                                <div class="avis-commentaire">
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($avisItem['commentaire'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>© 2025 Cinephoria. Tous droits réservés. 🎬</p>
        </div>
    </footer>

    <script>
        // Script pour le système d'étoiles interactif
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-rating input');
            const labels = document.querySelectorAll('.star-rating label');

            labels.forEach((label, index) => {
                label.addEventListener('mouseover', function() {
                    // Surligner toutes les étoiles jusqu'à celle survolée
                    for (let i = labels.length - 1; i >= labels.length - 1 - index; i--) {
                        labels[i].style.color = '#ffd700';
                    }
                    // Désurligner les autres
                    for (let i = 0; i < labels.length - 1 - index; i++) {
                        labels[i].style.color = '#ddd';
                    }
                });
            });

            // Restaurer la couleur par défaut quand on quitte la zone
            document.querySelector('.star-rating').addEventListener('mouseleave', function() {
                labels.forEach(label => {
                    if (!label.previousElementSibling.checked) {
                        label.style.color = '#ddd';
                    }
                });

                // Réafficher les étoiles sélectionnées
                const checked = document.querySelector('.star-rating input:checked');
                if (checked) {
                    const checkedIndex = Array.from(stars).indexOf(checked);
                    for (let i = labels.length - 1; i >= checkedIndex; i--) {
                        labels[i].style.color = '#ffd700';
                    }
                }
            });
        });
    </script>
</body>

</html>