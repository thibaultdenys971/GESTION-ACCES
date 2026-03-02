<?php
// pages/gestion_bibliotheque.php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['id_statut'] != 2 && $_SESSION['id_statut'] != 1)) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

$message = '';
$errorMessage = '';
$search = $_GET['search'] ?? '';

// TRAITEMENT DES ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJOUTER UN OUVRAGE
    if (isset($_POST['ajouter_ouvrage'])) {
        $titre = trim($_POST['titre']);
        $code_barre = trim($_POST['code_barre']);
        $etat = trim($_POST['etat']);
        $cote = trim($_POST['cote']);
        $stock = intval($_POST['stock']);

        try {
            // Ajouter l'ouvrage
            $ouvrageStmt = $conn->prepare("INSERT INTO ouvrage (titre, code_barre, etat, cote) VALUES (?, ?, ?, ?)");
            $ouvrageStmt->execute([$titre, $code_barre, $etat, $cote]);
            $ouvrage_id = $conn->lastInsertId();

            // Ajouter les exemplaires
            $exemplaireStmt = $conn->prepare("INSERT INTO exemplaire (stock, reserver, id_ouvrage) VALUES (?, 0, ?)");
            $exemplaireStmt->execute([$stock, $ouvrage_id]);

            $message = "✅ Ouvrage ajouté avec succès !";
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }

    // ENREGISTRER UN PRÊT (par un élève/prof)
    elseif (isset($_POST['enregistrer_pret'])) {
        $id_ouvrage = intval($_POST['id_ouvrage']);
        $id_utilisateur = $_SESSION['user_id']; // L'utilisateur qui fait le prêt

        try {
            // Vérifier le stock disponible (non réservé)
            $checkStmt = $conn->prepare("
                SELECT id_exemplaire, stock, reserver 
                FROM exemplaire 
                WHERE id_ouvrage = ? AND stock > 0
            ");
            $checkStmt->execute([$id_ouvrage]);
            $exemplaire = $checkStmt->fetch();

            if ($exemplaire && $exemplaire['stock'] > 0) {
                // Récupérer le badge de l'utilisateur
                $badgeStmt = $conn->prepare("SELECT id_badge FROM badge WHERE id_utilisateur = ? AND etat = 'actif' LIMIT 1");
                $badgeStmt->execute([$id_utilisateur]);
                $badge = $badgeStmt->fetch();

                if ($badge) {
                    // Calculer la date de retour (14 jours)
                    $date_retour = date('Y-m-d', strtotime('+14 days'));

                    // Créer le prêt avec date automatique (14 jours)
                    $pretStmt = $conn->prepare("
                        INSERT INTO pret (date_pret, date_retour_prevu) 
                        VALUES (CURDATE(), ?)
                    ");
                    $pretStmt->execute([$date_retour]);
                    $pret_id = $conn->lastInsertId();

                    // Lier l'ouvrage au prêt
                    $etreStmt = $conn->prepare("INSERT INTO etre (id_ouvrage, id_pret) VALUES (?, ?)");
                    $etreStmt->execute([$id_ouvrage, $pret_id]);

                    // Lier le badge au prêt (via la table realiser)
                    $realiserStmt = $conn->prepare("INSERT INTO realiser (id_pret, id_badge) VALUES (?, ?)");
                    $realiserStmt->execute([$pret_id, $badge['id_badge']]);

                    // Mettre à jour le stock (diminuer)
                    $updateStmt = $conn->prepare("UPDATE exemplaire SET stock = stock - 1 WHERE id_exemplaire = ?");
                    $updateStmt->execute([$exemplaire['id_exemplaire']]);

                    // Marquer comme réservé
                    $reserverStmt = $conn->prepare("UPDATE exemplaire SET reserver = reserver + 1 WHERE id_ouvrage = ?");
                    $reserverStmt->execute([$id_ouvrage]);

                    $date_retour_fr = date('d/m/Y', strtotime($date_retour));
                    $message = "✅ Prêt enregistré avec succès ! Date limite de retour : $date_retour_fr";
                } else {
                    $errorMessage = "❌ Vous n'avez pas de badge actif pour emprunter.";
                }
            } else {
                $errorMessage = "❌ Cet ouvrage n'est plus disponible ou est déjà réservé.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }

    // RÉSERVER UN LIVRE (sans l'emprunter)
    elseif (isset($_POST['reserver_livre'])) {
        $id_ouvrage = intval($_POST['id_ouvrage']);

        try {
            // Vérifier s'il y a du stock
            $checkStmt = $conn->prepare("
                SELECT id_exemplaire, stock 
                FROM exemplaire 
                WHERE id_ouvrage = ? AND stock > 0
            ");
            $checkStmt->execute([$id_ouvrage]);
            $exemplaire = $checkStmt->fetch();

            if ($exemplaire) {
                // Marquer comme réservé
                $reserverStmt = $conn->prepare("
                    UPDATE exemplaire 
                    SET reserver = reserver + 1 
                    WHERE id_exemplaire = ?
                ");
                $reserverStmt->execute([$exemplaire['id_exemplaire']]);

                $message = "✅ Livre réservé avec succès ! Il est mis de côté pour vous.";
            } else {
                $errorMessage = "❌ Cet ouvrage n'est plus disponible pour réservation.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }

    // ANNULER UNE RÉSERVATION
    elseif (isset($_POST['annuler_reservation'])) {
        $id_ouvrage = intval($_POST['id_ouvrage']);

        try {
            // Annuler la réservation
            $annulerStmt = $conn->prepare("
                UPDATE exemplaire 
                SET reserver = CASE WHEN reserver > 0 THEN reserver - 1 ELSE 0 END
                WHERE id_ouvrage = ?
            ");
            $annulerStmt->execute([$id_ouvrage]);

            $message = "✅ Réservation annulée avec succès !";
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }

    // RETOURNER UN LIVRE
    elseif (isset($_POST['retourner_livre'])) {
        $pret_id = intval($_POST['pret_id']);

        try {
            // Récupérer l'ouvrage associé
            $ouvrageStmt = $conn->prepare("
                SELECT o.id_ouvrage 
                FROM pret p
                JOIN etre e ON p.id_pret = e.id_pret
                JOIN ouvrage o ON e.id_ouvrage = o.id_ouvrage
                WHERE p.id_pret = ?
            ");
            $ouvrageStmt->execute([$pret_id]);
            $ouvrage = $ouvrageStmt->fetch();

            if ($ouvrage) {
                // Marquer le prêt comme retourné
                $retourStmt = $conn->prepare("
                    UPDATE pret 
                    SET date_retour_effectif = CURDATE()
                    WHERE id_pret = ?
                ");
                $retourStmt->execute([$pret_id]);

                // Réaugmenter le stock et diminuer les réservations
                $updateStmt = $conn->prepare("
                    UPDATE exemplaire 
                    SET stock = stock + 1,
                        reserver = CASE WHEN reserver > 0 THEN reserver - 1 ELSE 0 END
                    WHERE id_ouvrage = ?
                ");
                $updateStmt->execute([$ouvrage['id_ouvrage']]);

                $message = "✅ Livre retourné avec succès !";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }

    // PROLONGER UN PRÊT (ajout de 7 jours)
    elseif (isset($_POST['prolonger_pret'])) {
        $pret_id = intval($_POST['pret_id']);

        try {
            // Vérifier si déjà prolongé
            $checkStmt = $conn->prepare("SELECT prolongation, date_retour_prevu FROM pret WHERE id_pret = ?");
            $checkStmt->execute([$pret_id]);
            $pret = $checkStmt->fetch();

            if (!$pret['prolongation'] && $pret['date_retour_prevu']) {
                // Ajouter 7 jours à la date de retour prévue
                $nouvelle_date = date('Y-m-d', strtotime($pret['date_retour_prevu'] . ' +7 days'));

                $prolongStmt = $conn->prepare("
                    UPDATE pret 
                    SET prolongation = ?
                    WHERE id_pret = ?
                ");
                $prolongStmt->execute([$nouvelle_date, $pret_id]);
                $message = "✅ Prêt prolongé de 7 jours supplémentaires !";
            } else {
                $errorMessage = "❌ Ce prêt a déjà été prolongé ou n'a pas de date de retour.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
}

// RÉCUPÉRER LES OUVRAGES AVEC RÉSERVATIONS
$ouvrageQuery = "
    SELECT o.*, 
           COALESCE(SUM(e.stock), 0) as total_stock,
           COALESCE(SUM(e.reserver), 0) as total_reserver
    FROM ouvrage o 
    LEFT JOIN exemplaire e ON o.id_ouvrage = e.id_ouvrage 
    GROUP BY o.id_ouvrage
";

if (!empty($search)) {
    $searchTerm = "%$search%";
    $ouvrageQuery .= " HAVING o.titre LIKE ? OR o.code_barre LIKE ? OR o.cote LIKE ?";
    $ouvrageStmt = $conn->prepare($ouvrageQuery);
    $ouvrageStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
} else {
    $ouvrageStmt = $conn->prepare($ouvrageQuery);
    $ouvrageStmt->execute();
}

$ouvrages = $ouvrageStmt->fetchAll(PDO::FETCH_ASSOC);

// RÉCUPÉRER LES PRÊTS EN COURS - REQUÊTE SIMPLIFIÉE
$pretStmt = $conn->prepare("
    SELECT p.*, o.titre, o.code_barre, 
           u.nom, u.prenom, u.identifiant,
           DATE_ADD(p.date_pret, INTERVAL 14 DAY) as date_limite_calculee,
           COALESCE(p.prolongation, DATE_ADD(p.date_pret, INTERVAL 14 DAY)) as date_limite_finale,
           DATEDIFF(COALESCE(p.prolongation, DATE_ADD(p.date_pret, INTERVAL 14 DAY)), CURDATE()) as jours_restants
    FROM pret p
    JOIN etre et ON p.id_pret = et.id_pret
    JOIN ouvrage o ON et.id_ouvrage = o.id_ouvrage
    LEFT JOIN realiser r ON p.id_pret = r.id_pret
    LEFT JOIN badge b ON r.id_badge = b.id_badge
    LEFT JOIN utilisateur u ON b.id_utilisateur = u.id_utilisateur
    WHERE p.date_retour_effectif IS NULL
    ORDER BY COALESCE(p.prolongation, DATE_ADD(p.date_pret, INTERVAL 14 DAY)) ASC
");
$pretStmt->execute();
$prets = $pretStmt->fetchAll(PDO::FETCH_ASSOC);

// DEBUG: Vérifier ce que contient $prets
// echo "<pre>"; print_r($prets); echo "</pre>";

// RÉCUPÉRER LES EMPRUNTEURS POUR CHAQUE LIVRE
$emprunteursStmt = $conn->prepare("
    SELECT o.id_ouvrage, u.nom, u.prenom
    FROM ouvrage o
    JOIN etre e ON o.id_ouvrage = e.id_ouvrage
    JOIN pret p ON e.id_pret = p.id_pret
    LEFT JOIN realiser r ON p.id_pret = r.id_pret
    LEFT JOIN badge b ON r.id_badge = b.id_badge
    LEFT JOIN utilisateur u ON b.id_utilisateur = u.id_utilisateur
    WHERE p.date_retour_effectif IS NULL
");
$emprunteursStmt->execute();
$emprunteurs = $emprunteursStmt->fetchAll(PDO::FETCH_ASSOC);

// Créer un tableau associatif pour trouver rapidement l'emprunteur d'un ouvrage
$emprunteursParOuvrage = [];
foreach ($emprunteurs as $emprunteur) {
    if ($emprunteur['id_ouvrage'] && $emprunteur['nom']) {
        $emprunteursParOuvrage[$emprunteur['id_ouvrage']] = $emprunteur;
    }
}

// STATISTIQUES
$statsStmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM ouvrage) as total_ouvrages,
        (SELECT COUNT(*) FROM pret WHERE date_retour_effectif IS NULL) as prets_en_cours,
        (SELECT COUNT(*) FROM pret WHERE date_retour_effectif IS NULL 
         AND CURDATE() > COALESCE(prolongation, DATE_ADD(date_pret, INTERVAL 14 DAY))) as prets_en_retard,
        (SELECT SUM(reserver) FROM exemplaire) as livres_reserves,
        (SELECT SUM(stock) FROM exemplaire) as exemplaires_disponibles
");
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Bibliothèque - GESTION ACCES</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
        }

        .header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(14, 31, 76, 0.2);
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-mini-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-top: 4px solid;
            transition: transform 0.3s;
        }

        .stat-mini-card:hover {
            transform: translateY(-3px);
        }

        .stat-mini-card.ouvrages {
            border-color: #28a745;
        }

        .stat-mini-card.prets {
            border-color: #17a2b8;
        }

        .stat-mini-card.retard {
            border-color: #dc3545;
        }

        .stat-mini-card.reserves {
            border-color: #ffc107;
        }

        .stat-mini-card.dispo {
            border-color: #6f42c1;
        }

        .stat-mini-card h3 {
            font-size: 32px;
            color: #0e1f4c;
            margin-bottom: 5px;
        }

        .stat-mini-card p {
            color: #666;
            font-size: 14px;
        }

        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 15px;
        }

        .tab.active {
            background: #0e1f4c;
            color: white;
            box-shadow: 0 4px 6px rgba(14, 31, 76, 0.2);
        }

        .tab:hover:not(.active) {
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
        }

        .badge-disponible {
            background: #28a745;
            color: white;
        }

        .badge-indisponible {
            background: #6c757d;
            color: white;
        }

        .badge-reserve {
            background: #ffc107;
            color: #212529;
        }

        .badge-retard {
            background: #dc3545;
            color: white;
        }

        .badge-prolonge {
            background: #17a2b8;
            color: white;
        }

        .ouvrage-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-left: 5px solid #0e1f4c;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .ouvrage-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .ouvrage-card h3 {
            color: #0e1f4c;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .ouvrage-card p {
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .search-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .search-form {
            display: flex;
            gap: 15px;
        }

        .search-input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #e0e4e8;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #0e1f4c;
        }

        .search-btn {
            background: #0e1f4c;
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background: #1a3a7a;
        }

        .grid-ouvrages {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            margin-right: 10px;
        }

        .btn-emprunter {
            background: #0e1f4c;
            color: white;
        }

        .btn-emprunter:hover {
            background: #1a3a7a;
            transform: translateY(-2px);
        }

        .btn-emprunter:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-reserver {
            background: #ffc107;
            color: #212529;
        }

        .btn-reserver:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .btn-annuler-resa {
            background: #6c757d;
            color: white;
        }

        .btn-annuler-resa:hover {
            background: #5a6268;
        }

        .btn-retourner {
            background: #28a745;
            color: white;
        }

        .btn-retourner:hover {
            background: #218838;
        }

        .btn-prolonger {
            background: #17a2b8;
            color: white;
        }

        .btn-prolonger:hover {
            background: #138496;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            color: #0e1f4c;
            font-weight: 600;
            border-bottom: 2px solid #e0e4e8;
        }

        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #e0e4e8;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .row-retard {
            background: #ffe6e6 !important;
            border-left: 4px solid #dc3545;
        }

        .form-ajout {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0e1f4c;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e4e8;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0e1f4c;
        }

        .btn-submit {
            background: #0e1f4c;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: #1a3a7a;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .date-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .livre-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .emprunteur-info {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
        }

        .reservation-count {
            font-weight: bold;
            color: #e0a800;
        }

        .user-badge {
            display: inline-flex;
            align-items: center;
            background: #0e1f4c;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 5px;
        }

        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: monospace;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>📚 Gestion du distributeur/Interface Documentaliste</h1>
            <p>Gérez les livres, les prêts et les réservations</p>
        </div>

        <!-- MESSAGES -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- STATISTIQUES -->
        <div class="stats-mini">
            <div class="stat-mini-card ouvrages">
                <h3><?= $stats['total_ouvrages'] ?? 0 ?></h3>
                <p>Livres au catalogue</p>
            </div>
            <div class="stat-mini-card prets">
                <h3><?= $stats['prets_en_cours'] ?? 0 ?></h3>
                <p>Prêts en cours</p>
            </div>
            <div class="stat-mini-card retard">
                <h3><?= $stats['prets_en_retard'] ?? 0 ?></h3>
                <p>Prêts en retard</p>
            </div>
            <div class="stat-mini-card reserves">
                <h3><?= $stats['livres_reserves'] ?? 0 ?></h3>
                <p>Livres réservés</p>
            </div>
            <div class="stat-mini-card dispo">
                <h3><?= $stats['exemplaires_disponibles'] ?? 0 ?></h3>
                <p>Exemplaires dispo</p>
            </div>
        </div>

        <!-- ONGLETS -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('ouvrages')">📖 Livres</button>
            <button class="tab" onclick="showTab('prets')">🔄 Prêts en cours</button>
            <button class="tab" onclick="showTab('ajout')">➕ Ajouter un livre</button>
        </div>

        <!-- ONGLET 1: LIVRES -->
        <div id="tab-ouvrages" class="tab-content active">
            <div class="search-container">
                <form method="GET" class="search-form">
                    <input type="text" name="search" class="search-input"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Rechercher un livre par titre, code barre ou cote...">
                    <button type="submit" class="search-btn">
                        🔍 Rechercher
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?search=" class="btn" style="background: #dc3545; color: white;">✖ Effacer</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="grid-ouvrages">
                <?php foreach ($ouvrages as $ouvrage): ?>
                    <?php
                    $disponible = ($ouvrage['total_stock'] > 0);
                    $reserve = ($ouvrage['total_reserver'] > 0);
                    $stock_dispo = $ouvrage['total_stock'] - $ouvrage['total_reserver'];
                    $emprunteur = isset($emprunteursParOuvrage[$ouvrage['id_ouvrage']]) ? $emprunteursParOuvrage[$ouvrage['id_ouvrage']] : null;
                    ?>
                    <div class="ouvrage-card">
                        <h3><?= htmlspecialchars($ouvrage['titre']) ?></h3>

                        <!-- BADGES DE STATUT -->
                        <div style="margin-bottom: 15px;">
                            <?php if ($disponible && $stock_dispo > 0): ?>
                                <span class="badge badge-disponible">
                                    ✅ <?= $stock_dispo ?> exemplaire(s) disponible(s)
                                </span>
                            <?php else: ?>
                                <span class="badge badge-indisponible">
                                    ❌ Indisponible
                                </span>
                            <?php endif; ?>

                            <?php if ($reserve): ?>
                                <span class="badge badge-reserve">
                                    🔔 <?= $ouvrage['total_reserver'] ?> réservation(s)
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- INFOS DU LIVRE -->
                        <p><strong>📋 Code barre:</strong> <?= htmlspecialchars($ouvrage['code_barre']) ?></p>
                        <p><strong>🏷️ Cote:</strong> <?= htmlspecialchars($ouvrage['cote']) ?></p>
                        <p><strong>📝 État:</strong> <?= htmlspecialchars($ouvrage['etat']) ?></p>

                        <!-- EMPRUNTEUR ACTUEL -->
                        <?php if ($emprunteur): ?>
                            <div class="emprunteur-info">
                                <strong>👤 Emprunté par:</strong>
                                <?= htmlspecialchars($emprunteur['prenom']) ?>
                                <?= htmlspecialchars($emprunteur['nom']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- ACTIONS -->
                        <div class="action-buttons">
                            <form method="POST" onsubmit="return confirmEmprunt()">
                                <input type="hidden" name="id_ouvrage" value="<?= $ouvrage['id_ouvrage'] ?>">
                                <button type="submit" name="enregistrer_pret"
                                    class="btn btn-emprunter"
                                    <?= !$disponible || $stock_dispo <= 0 ? 'disabled' : '' ?>
                                    title="<?= !$disponible ? 'Plus d\'exemplaires disponibles' : '' ?>">
                                    📚 Emprunter
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirmReservation()">
                                <input type="hidden" name="id_ouvrage" value="<?= $ouvrage['id_ouvrage'] ?>">
                                <button type="submit" name="reserver_livre"
                                    class="btn btn-reserver"
                                    <?= !$disponible ? 'disabled' : '' ?>
                                    title="<?= !$disponible ? 'Plus d\'exemplaires disponibles' : '' ?>">
                                    🔔 Réserver
                                </button>
                            </form>

                            <?php if ($reserve): ?>
                                <form method="POST" onsubmit="return confirmAnnulation()">
                                    <input type="hidden" name="id_ouvrage" value="<?= $ouvrage['id_ouvrage'] ?>">
                                    <button type="submit" name="annuler_reservation"
                                        class="btn btn-annuler-resa">
                                        ❌ Annuler réservation
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ONGLET 2: PRÊTS EN COURS -->
        <div id="tab-prets" class="tab-content">
            <div class="table-container">
                <?php if (empty($prets)): ?>
                    <div style="text-align: center; color: #666; padding: 30px;">
                        <p>Aucun prêt en cours pour le moment.</p>
                        <p class="date-info">(Vérifiez que vous avez bien des prêts enregistrés dans la base de données)</p>
                        <!-- DEBUG: Afficher le nombre de prêts trouvés -->
                        <div class="debug-info">
                            Nombre de prêts trouvés: <?= count($prets) ?><br>
                            <?php if (count($prets) > 0): ?>
                                Premier prêt: <?= htmlspecialchars($prets[0]['titre'] ?? 'N/A') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Livre</th>
                                <th>Emprunteur</th>
                                <th>Date emprunt</th>
                                <th>Date limite</th>
                                <th>Prolongation</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prets as $pret): ?>
                                <?php
                                // Utiliser la date limite calculée
                                $date_limite = $pret['date_limite_finale'];
                                $prolongation = $pret['prolongation'] ? date('d/m/Y', strtotime($pret['prolongation'])) : null;
                                $en_retard = $pret['jours_restants'] < 0;
                                ?>
                                <tr class="<?= $en_retard ? 'row-retard' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($pret['titre'] ?? 'Livre inconnu') ?></strong><br>
                                        <small class="date-info"><?= $pret['code_barre'] ?? 'N/A' ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($pret['nom'])): ?>
                                            <strong><?= htmlspecialchars($pret['prenom'] ?? '') ?> <?= htmlspecialchars($pret['nom']) ?></strong><br>
                                            <small class="date-info"><?= $pret['identifiant'] ?? 'N/A' ?></small>
                                        <?php else: ?>
                                            <span style="color: #666;">Utilisateur inconnu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($pret['date_pret']) ? date('d/m/Y', strtotime($pret['date_pret'])) : 'N/A' ?></td>
                                    <td>
                                        <?= !empty($date_limite) ? date('d/m/Y', strtotime($date_limite)) : 'N/A' ?>
                                        <div class="date-info">
                                            <?php if ($pret['jours_restants'] >= 0): ?>
                                                <span style="color: #28a745;">
                                                    ⏱️ <?= $pret['jours_restants'] ?> jour(s) restant(s)
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #dc3545;">
                                                    ⚠️ En retard de <?= abs($pret['jours_restants']) ?> jour(s)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($prolongation): ?>
                                            <span class="badge badge-prolonge">
                                                Jusqu'au <?= $prolongation ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #666;">Non prolongé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($en_retard): ?>
                                            <span class="badge badge-retard">EN RETARD</span>
                                        <?php else: ?>
                                            <span class="badge badge-disponible">EN COURS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="pret_id" value="<?= $pret['id_pret'] ?>">
                                                <button type="submit" name="retourner_livre"
                                                    class="btn btn-retourner"
                                                    onclick="return confirm('Confirmer le retour de ce livre ?')">
                                                    ✅ Retourner
                                                </button>
                                            </form>

                                            <?php if (!$pret['prolongation']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="pret_id" value="<?= $pret['id_pret'] ?>">
                                                    <button type="submit" name="prolonger_pret"
                                                        class="btn btn-prolonger"
                                                        onclick="return confirm('Prolonger le prêt de 7 jours supplémentaires ?')">
                                                        ⏱️ Prolonger
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ONGLET 3: AJOUTER UN LIVRE -->
        <div id="tab-ajout" class="tab-content">
            <div class="form-ajout">
                <h2 style="color: #0e1f4c; margin-bottom: 25px; text-align: center;">
                    Ajouter un nouveau livre
                </h2>

                <form method="POST">
                    <div class="form-group">
                        <label for="titre">📖 Titre du livre *</label>
                        <input type="text" id="titre" name="titre" class="form-control"
                            placeholder="Ex: Le Petit Prince" required>
                    </div>

                    <div class="form-group">
                        <label for="code_barre">📋 Code barre (ISBN) *</label>
                        <input type="text" id="code_barre" name="code_barre" class="form-control"
                            placeholder="Ex: 9782070612758" required>
                    </div>

                    <div class="form-group">
                        <label for="etat">📝 État du livre *</label>
                        <select id="etat" name="etat" class="form-control" required>
                            <option value="">Sélectionner un état</option>
                            <option value="Neuf">Neuf</option>
                            <option value="Très bon état">Très bon état</option>
                            <option value="Bon état">Bon état</option>
                            <option value="Usé">Usé</option>
                            <option value="Abîmé">Abîmé</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cote">🏷️ Cote de rangement *</label>
                        <input type="text" id="cote" name="cote" class="form-control"
                            placeholder="Ex: A2, B3, C1..." required>
                        <small style="color: #666;">Système de classement en bibliothèque</small>
                    </div>

                    <div class="form-group">
                        <label for="stock">📚 Nombre d'exemplaires *</label>
                        <input type="number" id="stock" name="stock" class="form-control"
                            min="1" value="1" required>
                        <small style="color: #666;">Nombre d'exemplaires à ajouter au stock</small>
                    </div>

                    <button type="submit" name="ajouter_ouvrage" class="btn-submit">
                        ➕ Ajouter le livre
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Masquer tous les onglets
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
                tab.classList.remove('active');
            });

            // Désactiver tous les boutons d'onglet
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Afficher l'onglet sélectionné
            const activeTab = document.getElementById('tab-' + tabName);
            if (activeTab) {
                activeTab.style.display = 'block';
                activeTab.classList.add('active');
            }

            // Activer le bouton de l'onglet
            event.target.classList.add('active');
        }

        function confirmEmprunt() {
            const dateLimite = new Date();
            dateLimite.setDate(dateLimite.getDate() + 14);
            const dateFormat = dateLimite.toLocaleDateString('fr-FR');

            return confirm(`Confirmer l'emprunt de ce livre ?\n\n📅 Date limite de retour : ${dateFormat}\n⏱️ Durée : 14 jours maximum`);
        }

        function confirmReservation() {
            return confirm("Réserver ce livre ?\n\nIl sera mis de côté pour vous.");
        }

        function confirmAnnulation() {
            return confirm("Annuler la réservation de ce livre ?\n\nIl redeviendra disponible pour les autres.");
        }

        // Afficher une notification si des prêts sont en retard
        window.onload = function() {
            const retardCount = <?= $stats['prets_en_retard'] ?? 0 ?>;
            const livresReserves = <?= $stats['livres_reserves'] ?? 0 ?>;

            if (retardCount > 0) {
                setTimeout(() => {
                    alert(`⚠️ Attention : ${retardCount} prêt(s) sont en retard !\nVeuillez vérifier l'onglet "Prêts en cours".`);
                }, 1000);
            }

            if (livresReserves > 0) {
                console.log(`ℹ️ ${livresReserves} livre(s) sont actuellement réservés.`);
            }
        };
    </script>
</body>

</html>