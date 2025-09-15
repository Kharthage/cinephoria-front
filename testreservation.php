<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Réservation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 50px;
        }

        .test-link {
            display: block;
            background: #1b1f3b;
            color: white;
            padding: 15px 25px;
            text-decoration: none;
            border-radius: 10px;
            margin: 10px 0;
            text-align: center;
        }

        .test-link:hover {
            background: #2c3e73;
        }
    </style>
</head>

<body>
    <h1>Test de la page de réservation</h1>
    <p>Cliquez sur un des liens ci-dessous pour tester la page de réservation :</p>

    <a href="reservation.php?film_id=1012&film_titre=Matrix%204&cinema_id=1&date=<?= date('Y-m-d', strtotime('+1 day')) ?>&heure=20:00" class="test-link">
        🎬 Tester avec Matrix 4 - Demain 20h00
    </a>

    <a href="reservation.php?film_id=1007&film_titre=Jurassic%20Park%203D&cinema_id=1&date=<?= date('Y-m-d', strtotime('+2 days')) ?>&heure=18:30" class="test-link">
        🦕 Tester avec Jurassic Park 3D - Après-demain 18h30
    </a>

    <a href="reservation.php?film_id=1010&film_titre=Mortal%20Kombat%202&cinema_id=1&date=<?= date('Y-m-d', strtotime('+3 days')) ?>&heure=21:15" class="test-link">
        🥊 Tester avec Mortal Kombat 2 - Dans 3 jours 21h15
    </a>

    <hr style="margin: 30px 0;">

    <h2>Informations de débogage</h2>
    <p><strong>URL de test complet :</strong></p>
    <code style="background: #f5f5f5; padding: 10px; display: block; margin: 10px 0;">
        reservation.php?film_id=1012&film_titre=Matrix%204&cinema_id=1&date=<?= date('Y-m-d', strtotime('+1 day')) ?>&heure=20:00
    </code>

    <p><strong>Paramètres nécessaires :</strong></p>
    <ul>
        <li><strong>film_id</strong> : ID du film (ex: 1012)</li>
        <li><strong>film_titre</strong> : Titre du film (ex: Matrix 4)</li>
        <li><strong>cinema_id</strong> : ID du cinéma (ex: 1)</li>
        <li><strong>date</strong> : Date de la séance (format Y-m-d)</li>
        <li><strong>heure</strong> : Heure de la séance (format H:i)</li>
    </ul>
</body>

</html>