<?php
// pages/admin_global.php
session_start();

// VÉRIFICATION ADMIN SEULEMENT
if (!isset($_SESSION['user_id']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

$message = '';
$errorMessage = '';
$activeTab = $_GET['tab'] ?? 'dashboard';

// ==================== FONCTIONS UTILITAIRES ====================

// Fonction de vérification d'unicité de l'identifiant
function verifierIdentifiantUnique($conn, $identifiant, $id_utilisateur_exclu = null)
{
    $sql = "SELECT COUNT(*) FROM utilisateur WHERE identifiant = ?";
    $params = [$identifiant];

    if ($id_utilisateur_exclu !== null) {
        $sql .= " AND id_utilisateur != ?";
        $params[] = $id_utilisateur_exclu;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
}

// Fonction de génération de code barre unique
function genererCodeBarreUnique($conn)
{
    do {
        // Format: LIV-YYYYMMDD-XXXXX (ex: LIV-20250310-00123)
        $date = date('Ymd');
        $random = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
        $code_barre = "LIV-{$date}-{$random}";

        // Vérifier si le code existe déjà
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ouvrage WHERE code_barre = ?");
        $stmt->execute([$code_barre]);
        $existe = $stmt->fetchColumn() > 0;
    } while ($existe);

    return $code_barre;
}

// Fonction de génération de code barre ISBN-like
function genererISBNLike($conn)
{
    do {
        // Format: 978-2-XXXX-XXXX-X (style ISBN)
        $part1 = "978";
        $part2 = "2";
        $part3 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $part4 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $part5 = rand(0, 9);

        $code_barre = "{$part1}-{$part2}-{$part3}-{$part4}-{$part5}";

        $stmt = $conn->prepare("SELECT COUNT(*) FROM ouvrage WHERE code_barre = ?");
        $stmt->execute([$code_barre]);
        $existe = $stmt->fetchColumn() > 0;
    } while ($existe);

    return $code_barre;
}

// Fonction de génération de code barre simple
function genererCodeBarreSimple($conn)
{
    do {
        $prefix = 'BC';
        $timestamp = time();
        $random = rand(100, 999);
        $code_barre = $prefix . $timestamp . $random;

        $stmt = $conn->prepare("SELECT COUNT(*) FROM ouvrage WHERE code_barre = ?");
        $stmt->execute([$code_barre]);
        $existe = $stmt->fetchColumn() > 0;
    } while ($existe);

    return $code_barre;
}

// Fonction de génération QR code
function generateQRCode($data, $filename = null)
{
    $qr_url = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($data) . "&choe=UTF-8";

    if ($filename) {
        $qr_content = @file_get_contents($qr_url);
        if ($qr_content) {
            file_put_contents($filename, $qr_content);
            return true;
        }
        return false;
    }

    return $qr_url;
}

function ensureQRFolder()
{
    $qr_folder = '../assets/qr_codes/';
    if (!file_exists($qr_folder)) {
        mkdir($qr_folder, 0777, true);
    }
    return $qr_folder;
}

// ==================== TRAITEMENT DES ACTIONS ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- GESTION UTILISATEURS ---

    // AJOUTER UN ÉLÈVE
    if (isset($_POST['ajouter_eleve'])) {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $identifiant = trim($_POST['identifiant']);
        $mot_de_pass = $_POST['mot_de_pass'];
        $id_classe = intval($_POST['id_classe']);

        if (!verifierIdentifiantUnique($conn, $identifiant)) {
            $errorMessage = "❌ Cet identifiant est déjà utilisé.";
        } else {
            try {
                $conn->beginTransaction();

                $mot_de_pass_hash = password_hash($mot_de_pass, PASSWORD_DEFAULT);

                $userStmt = $conn->prepare("
                    INSERT INTO utilisateur (nom, prenom, identifiant, mot_de_pass, id_statut, id_classe, peut_se_connectert) 
                    VALUES (?, ?, ?, ?, 4, ?, 0)
                ");
                $userStmt->execute([$nom, $prenom, $identifiant, $mot_de_pass_hash, $id_classe]);
                $id_utilisateur = $conn->lastInsertId();

                $date_expiration = date('Y-m-d', strtotime('+2 years'));
                $badgeStmt = $conn->prepare("
                    INSERT INTO badge (date_emission, date_expiration, etat, id_utilisateur, id_statut) 
                    VALUES (CURDATE(), ?, 'actif', ?, 4)
                ");
                $badgeStmt->execute([$date_expiration, $id_utilisateur]);
                $id_badge = $conn->lastInsertId();

                $updateUserStmt = $conn->prepare("UPDATE utilisateur SET id_badge = ? WHERE id_utilisateur = ?");
                $updateUserStmt->execute([$id_badge, $id_utilisateur]);

                $conn->commit();

                $message = "✅ Élève ajouté avec badge créé automatiquement !";
            } catch (PDOException $e) {
                $conn->rollBack();
                $errorMessage = "❌ Erreur : " . $e->getMessage();
            }
        }
    }

    // AJOUTER UN PROFESSEUR
    elseif (isset($_POST['ajouter_professeur'])) {
        $nom = trim($_POST['nom_prof']);
        $prenom = trim($_POST['prenom_prof']);
        $identifiant = trim($_POST['identifiant_prof']);
        $mot_de_pass = $_POST['mot_de_pass_prof'];

        if (!verifierIdentifiantUnique($conn, $identifiant)) {
            $errorMessage = "❌ Cet identifiant est déjà utilisé.";
        } else {
            try {
                $conn->beginTransaction();

                $mot_de_pass_hash = password_hash($mot_de_pass, PASSWORD_DEFAULT);

                $userStmt = $conn->prepare("
                    INSERT INTO utilisateur (nom, prenom, identifiant, mot_de_pass, id_statut, peut_se_connectert) 
                    VALUES (?, ?, ?, ?, 3, 0)
                ");
                $userStmt->execute([$nom, $prenom, $identifiant, $mot_de_pass_hash]);
                $id_utilisateur = $conn->lastInsertId();

                $date_expiration = date('Y-m-d', strtotime('+2 years'));
                $badgeStmt = $conn->prepare("
                    INSERT INTO badge (date_emission, date_expiration, etat, id_utilisateur, id_statut) 
                    VALUES (CURDATE(), ?, 'actif', ?, 3)
                ");
                $badgeStmt->execute([$date_expiration, $id_utilisateur]);
                $id_badge = $conn->lastInsertId();

                $updateUserStmt = $conn->prepare("UPDATE utilisateur SET id_badge = ? WHERE id_utilisateur = ?");
                $updateUserStmt->execute([$id_badge, $id_utilisateur]);

                $conn->commit();

                $message = "✅ Professeur ajouté avec badge créé automatiquement !";
            } catch (PDOException $e) {
                $conn->rollBack();
                $errorMessage = "❌ Erreur : " . $e->getMessage();
            }
        }
    }

    // CRÉER UN BADGE POUR UN UTILISATEUR EXISTANT
    elseif (isset($_POST['creer_badge'])) {
        $id_utilisateur = intval($_POST['id_utilisateur']);

        try {
            $checkStmt = $conn->prepare("SELECT id_badge, id_statut, nom, prenom FROM utilisateur WHERE id_utilisateur = ?");
            $checkStmt->execute([$id_utilisateur]);
            $user = $checkStmt->fetch();

            if ($user) {
                if ($user['id_badge']) {
                    $errorMessage = "❌ Cet utilisateur a déjà un badge.";
                } else {
                    $date_expiration = date('Y-m-d', strtotime('+2 years'));

                    $badgeStmt = $conn->prepare("
                        INSERT INTO badge (date_emission, date_expiration, etat, id_utilisateur, id_statut) 
                        VALUES (CURDATE(), ?, 'actif', ?, ?)
                    ");
                    $badgeStmt->execute([$date_expiration, $id_utilisateur, $user['id_statut']]);
                    $id_badge = $conn->lastInsertId();

                    $updateUserStmt = $conn->prepare("UPDATE utilisateur SET id_badge = ? WHERE id_utilisateur = ?");
                    $updateUserStmt->execute([$id_badge, $id_utilisateur]);

                    $message = "✅ Badge créé avec succès ! (valide 2 ans)";
                }
            } else {
                $errorMessage = "❌ Utilisateur non trouvé.";
            }
        } catch (PDOException $e) {
            $errorMessage = "❌ Erreur : " . $e->getMessage();
        }
    }

    // MODIFIER UN UTILISATEUR
    elseif (isset($_POST['modifier_utilisateur'])) {
        $id_utilisateur = intval($_POST['id_utilisateur']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $identifiant = trim($_POST['identifiant']);
        $id_classe = !empty($_POST['id_classe']) ? intval($_POST['id_classe']) : null;

        if (!verifierIdentifiantUnique($conn, $identifiant, $id_utilisateur)) {
            $errorMessage = "❌ Cet identifiant est déjà utilisé.";
        } else {
            try {
                $sql = "UPDATE utilisateur SET nom = ?, prenom = ?, identifiant = ?, id_classe = ?";
                $params = [$nom, $prenom, $identifiant, $id_classe];

                if (!empty($_POST['mot_de_pass'])) {
                    $sql .= ", mot_de_pass = ?";
                    $params[] = password_hash($_POST['mot_de_pass'], PASSWORD_DEFAULT);
                }

                $sql .= " WHERE id_utilisateur = ?";
                $params[] = $id_utilisateur;

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);

                $message = "✅ Utilisateur modifié avec succès !";
            } catch (PDOException $e) {
                $errorMessage = "❌ Erreur : " . $e->getMessage();
            }
        }
    }

    // --- GESTION BIBLIOTHÈQUE AVEC QR CODES ---

    // AJOUTER UN LIVRE AVEC CODE BARRE AUTO ET QR CODE
    elseif (isset($_POST['ajouter_livre'])) {
        $titre = trim($_POST['titre']);
        $etat = trim($_POST['etat']);
        $cote = trim($_POST['cote']);
        $stock = intval($_POST['stock']);
        $type_code = $_POST['type_code'] ?? 'auto';
        $generer_qr = isset($_POST['generer_qr']);

        try {
            $conn->beginTransaction();

            // GÉNÉRER LE CODE BARRE AUTOMATIQUEMENT
            if ($type_code === 'auto') {
                $code_barre = genererCodeBarreUnique($conn);
            } elseif ($type_code === 'isbn') {
                $code_barre = genererISBNLike($conn);
            } else {
                $code_barre = genererCodeBarreSimple($conn);
            }

            // Ajouter l'ouvrage
            $ouvrageStmt = $conn->prepare("INSERT INTO ouvrage (titre, code_barre, etat, cote) VALUES (?, ?, ?, ?)");
            $ouvrageStmt->execute([$titre, $code_barre, $etat, $cote]);
            $ouvrage_id = $conn->lastInsertId();

            // Ajouter les exemplaires
            $exemplaireStmt = $conn->prepare("INSERT INTO exemplaire (stock, reserver, id_ouvrage) VALUES (?, 0, ?)");
            $exemplaireStmt->execute([$stock, $ouvrage_id]);

            // GÉNÉRER LE QR CODE
            if ($generer_qr) {
                $qr_folder = ensureQRFolder();
                $qr_filename = $qr_folder . 'livre_' . $ouvrage_id . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $code_barre) . '.png';
                generateQRCode($code_barre, $qr_filename);
            }

            $conn->commit();

            $message = "✅ Livre ajouté avec succès !<br>";
            $message .= "📋 Code barre généré : <strong>{$code_barre}</strong><br>";
            $message .= $generer_qr ? "📱 QR Code généré automatiquement." : "";
        } catch (PDOException $e) {
            $conn->rollBack();
            $errorMessage = "❌ Erreur : " . $e->getMessage();
        }
    }

    // GÉNÉRER QR CODE POUR LIVRE EXISTANT
    elseif (isset($_POST['generer_qr_livre'])) {
        $id_ouvrage = intval($_POST['id_ouvrage']);
        $code_barre = $_POST['code_barre'];
        $titre = $_POST['titre'];

        try {
            $qr_folder = ensureQRFolder();
            $qr_filename = $qr_folder . 'livre_' . $id_ouvrage . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $code_barre) . '.png';

            if (generateQRCode($code_barre, $qr_filename)) {
                $message = "✅ QR Code généré avec succès !";
            } else {
                $errorMessage = "❌ Erreur lors de la génération du QR Code.";
            }
        } catch (Exception $e) {
            $errorMessage = "❌ Erreur : " . $e->getMessage();
        }
    }

    // REGÉNÉRER UN CODE BARRE POUR UN LIVRE
    elseif (isset($_POST['regenerer_code_barre'])) {
        $id_ouvrage = intval($_POST['id_ouvrage']);
        $ancien_code = $_POST['ancien_code'];

        try {
            // Générer un nouveau code barre unique
            $nouveau_code = genererCodeBarreUnique($conn);

            // Mettre à jour dans la base
            $updateStmt = $conn->prepare("UPDATE ouvrage SET code_barre = ? WHERE id_ouvrage = ?");
            $updateStmt->execute([$nouveau_code, $id_ouvrage]);

            // Régénérer le QR code si nécessaire
            $qr_folder = ensureQRFolder();
            $ancien_qr = $qr_folder . 'livre_' . $id_ouvrage . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ancien_code) . '.png';
            $nouveau_qr = $qr_folder . 'livre_' . $id_ouvrage . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $nouveau_code) . '.png';

            // Supprimer l'ancien QR s'il existe
            if (file_exists($ancien_qr)) {
                unlink($ancien_qr);
            }

            // Générer le nouveau QR
            generateQRCode($nouveau_code, $nouveau_qr);

            $message = "✅ Code barre régénéré avec succès !<br>";
            $message .= "Ancien : {$ancien_code}<br>";
            $message .= "Nouveau : <strong>{$nouveau_code}</strong>";
        } catch (PDOException $e) {
            $errorMessage = "❌ Erreur : " . $e->getMessage();
        }
    }
}

// ==================== RÉCUPÉRATION DES DONNÉES ====================

// Utilisateurs (avec leurs badges)
$utilisateurs = $conn->query("
    SELECT u.*, s.statut, c.libelle as classe_nom, 
           b.date_emission, b.date_expiration, b.etat as etat_badge
    FROM utilisateur u
    LEFT JOIN statut s ON u.id_statut = s.id_statut
    LEFT JOIN classe c ON u.id_classe = c.id_classe
    LEFT JOIN badge b ON u.id_badge = b.id_badge
    ORDER BY u.nom, u.prenom
")->fetchAll(PDO::FETCH_ASSOC);

// Classes
$classes = $conn->query("SELECT * FROM classe ORDER BY libelle")->fetchAll(PDO::FETCH_ASSOC);

// Statuts
$statuts = $conn->query("SELECT * FROM statut ORDER BY id_statut")->fetchAll(PDO::FETCH_ASSOC);

// Badges
$badges = $conn->query("
    SELECT b.*, u.nom, u.prenom, u.identifiant
    FROM badge b
    JOIN utilisateur u ON b.id_utilisateur = u.id_utilisateur
    ORDER BY b.date_expiration ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Livres avec QR codes
$livres = $conn->query("
    SELECT o.*, 
           COALESCE(SUM(e.stock), 0) as total_stock,
           COALESCE(SUM(e.reserver), 0) as total_reserver
    FROM ouvrage o 
    LEFT JOIN exemplaire e ON o.id_ouvrage = e.id_ouvrage 
    GROUP BY o.id_ouvrage
    ORDER BY o.titre
")->fetchAll(PDO::FETCH_ASSOC);

// STATISTIQUES
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 4) as total_eleves,
        (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 3) as total_profs,
        (SELECT COUNT(*) FROM badge WHERE etat = 'actif') as badges_actifs,
        (SELECT COUNT(*) FROM badge WHERE date_expiration < CURDATE()) as badges_expires,
        (SELECT COUNT(*) FROM utilisateur WHERE id_badge IS NULL) as sans_badge,
        (SELECT COUNT(*) FROM ouvrage) as total_livres,
        (SELECT COUNT(*) FROM pret WHERE date_retour_effectif IS NULL) as prets_encours,
        (SELECT COUNT(*) FROM pret WHERE date_retour_effectif IS NULL AND CURDATE() > COALESCE(prolongation, DATE_ADD(date_pret, INTERVAL 14 DAY))) as prets_retard
")->fetch(PDO::FETCH_ASSOC);

// Vérifier QR codes existants
$qr_folder = '../assets/qr_codes/';
$qr_count = file_exists($qr_folder) ? count(glob($qr_folder . '*.png')) : 0;
$qr_files = file_exists($qr_folder) ? scandir($qr_folder) : [];

// Récupérer un utilisateur spécifique pour édition
$userToEdit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editStmt = $conn->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $editStmt->execute([$_GET['edit']]);
    $userToEdit = $editStmt->fetch(PDO::FETCH_ASSOC);
    if ($userToEdit) {
        $activeTab = 'utilisateurs';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Globale - GESTION ACCÈS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ==================== STYLES ==================== */
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

        /* HEADER */
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

        /* STATISTIQUES */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-top: 4px solid;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card.eleves {
            border-color: #28a745;
        }

        .stat-card.profs {
            border-color: #17a2b8;
        }

        .stat-card.badges {
            border-color: #ffc107;
        }

        .stat-card.livres {
            border-color: #0e1f4c;
        }

        .stat-card.qr {
            border-color: #6f42c1;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0e1f4c;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .stat-sub {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }

        /* TABS */
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
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
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab.active {
            background: #0e1f4c;
            color: white;
            box-shadow: 0 4px 6px rgba(14, 31, 76, 0.2);
        }

        .tab:hover:not(.active) {
            background: #f8f9fa;
        }

        /* CONTENU DES TABS */
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

        /* CARTES */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h2 {
            color: #0e1f4c;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* FORMULAIRES */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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
            padding: 12px 15px;
            border: 2px solid #e0e4e8;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0e1f4c;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        /* BOUTONS */
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
            margin-right: 5px;
            margin-bottom: 5px;
            text-decoration: none;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-primary {
            background: #0e1f4c;
            color: white;
        }

        .btn-primary:hover {
            background: #1a3a7a;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* BADGES */
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .badge-actif {
            background: #28a745;
            color: white;
        }

        .badge-inactif {
            background: #dc3545;
            color: white;
        }

        .badge-expire {
            background: #ffc107;
            color: #212529;
        }

        .badge-eleve {
            background: #17a2b8;
            color: white;
        }

        .badge-prof {
            background: #6f42c1;
            color: white;
        }

        .badge-admin {
            background: #0e1f4c;
            color: white;
        }

        .badge-code-barre {
            background: #0e1f4c;
            color: white;
            font-family: monospace;
        }

        /* TABLEAUX */
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

        /* MESSAGES */
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

        /* BADGE INFO CARD */
        .badge-info-card {
            background: #e8f4fd;
            border-left: 4px solid #0e1f4c;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .badge-details {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #0e1f4c;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        /* VERIFICATION IDENTIFIANT */
        .identifiant-unique-info {
            color: #28a745;
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .identifiant-pris-info {
            color: #dc3545;
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* QR CODE */
        .qr-code-container {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }

        .qr-code-image {
            max-width: 150px;
            max-height: 150px;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 5px;
            background: white;
        }

        .qr-code-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        /* GRILLE POUR LIVRES */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        /* CODE BARRE AFFICHAGE */
        .code-barre-display {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
            color: #0e1f4c;
            border: 1px dashed #0e1f4c;
            display: inline-block;
            margin: 5px 0;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .tab {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- HEADER -->
        <div class="header">
            <h1><i class="fas fa-crown"></i> Administration Globale</h1>
            <p>Gestion centralisée des utilisateurs, badges, livres et QR codes</p>
        </div>

        <!-- MESSAGES -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- STATISTIQUES GLOBALES -->
        <div class="stats-grid">
            <div class="stat-card eleves">
                <div class="stat-number"><?= $stats['total_eleves'] ?? 0 ?></div>
                <div class="stat-label">Élèves</div>
                <div class="stat-sub">👨‍🏫 <?= $stats['total_profs'] ?? 0 ?> professeurs</div>
            </div>

            <div class="stat-card badges">
                <div class="stat-number"><?= $stats['badges_actifs'] ?? 0 ?></div>
                <div class="stat-label">Badges actifs</div>
                <div class="stat-sub">
                    ⚠️ <?= $stats['badges_expires'] ?? 0 ?> expirés |
                    ❌ <?= $stats['sans_badge'] ?? 0 ?> sans badge
                </div>
            </div>

            <div class="stat-card livres">
                <div class="stat-number"><?= $stats['total_livres'] ?? 0 ?></div>
                <div class="stat-label">Livres</div>
                <div class="stat-sub">
                    📦 <?= $stats['prets_encours'] ?? 0 ?> prêts |
                    ⚠️ <?= $stats['prets_retard'] ?? 0 ?> retards
                </div>
            </div>

            <div class="stat-card qr">
                <div class="stat-number"><?= $qr_count ?></div>
                <div class="stat-label">QR codes</div>
                <div class="stat-sub">📱 Prêts à imprimer</div>
            </div>
        </div>

        <!-- TABS PRINCIPAUX -->
        <div class="tabs">
            <button class="tab <?= $activeTab == 'dashboard' ? 'active' : '' ?>" onclick="showTab('dashboard')">
                <i class="fas fa-chart-pie"></i> Dashboard
            </button>
            <button class="tab <?= $activeTab == 'utilisateurs' ? 'active' : '' ?>" onclick="showTab('utilisateurs')">
                <i class="fas fa-users"></i> Utilisateurs
            </button>
            <button class="tab <?= $activeTab == 'badges' ? 'active' : '' ?>" onclick="showTab('badges')">
                <i class="fas fa-id-card"></i> Badges
            </button>
            <button class="tab <?= $activeTab == 'livres' ? 'active' : '' ?>" onclick="showTab('livres')">
                <i class="fas fa-book"></i> Livres & QR
            </button>
            <button class="tab <?= $activeTab == 'classes' ? 'active' : '' ?>" onclick="showTab('classes')">
                <i class="fas fa-school"></i> Classes
            </button>
        </div>

        <!-- TAB 1: DASHBOARD -->
        <div id="tab-dashboard" class="tab-content <?= $activeTab == 'dashboard' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-rocket"></i> Actions rapides</h2>
                </div>
                <div class="grid-2">
                    <a href="?tab=utilisateurs" class="btn btn-primary" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-user-plus"></i> Ajouter un utilisateur
                    </a>
                    <a href="?tab=livres" class="btn btn-success" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-plus"></i> Ajouter un livre
                    </a>
                    <a href="gestion_bibliotheque.php" class="btn btn-info" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-qrcode"></i> Scanner des QR codes
                    </a>
                    <a href="edt.php" class="btn btn-warning" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-calendar"></i> Voir l'EDT
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Alertes et notifications</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if (($stats['badges_expires'] ?? 0) > 0): ?>
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                            ⚠️ <strong><?= $stats['badges_expires'] ?> badge(s) expiré(s)</strong> - Pensez à les renouveler
                        </div>
                    <?php endif; ?>

                    <?php if (($stats['prets_retard'] ?? 0) > 0): ?>
                        <div style="background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;">
                            📚 <strong><?= $stats['prets_retard'] ?> prêt(s) en retard</strong> - Relancez les emprunteurs
                        </div>
                    <?php endif; ?>

                    <?php if (($stats['sans_badge'] ?? 0) > 0): ?>
                        <div style="background: #e2e3e5; padding: 15px; border-radius: 8px; border-left: 4px solid #6c757d;">
                            🪪 <strong><?= $stats['sans_badge'] ?> utilisateur(s) sans badge</strong> - Créez-leur un badge
                        </div>
                    <?php endif; ?>

                    <?php if (($stats['badges_expires'] ?? 0) == 0 && ($stats['prets_retard'] ?? 0) == 0 && ($stats['sans_badge'] ?? 0) == 0): ?>
                        <div style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                            ✅ Tout est en ordre ! Aucune alerte à signaler.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-clock"></i> Derniers utilisateurs inscrits</h2>
                </div>
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Identifiant</th>
                                <th>Statut</th>
                                <th>Classe</th>
                                <th>Badge</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_users = array_slice($utilisateurs, 0, 5);
                            foreach ($recent_users as $user):
                                $badge_actif = ($user['etat_badge'] == 'actif' && strtotime($user['date_expiration']) > time());
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($user['prenom']) ?> <?= htmlspecialchars($user['nom']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($user['identifiant']) ?></code></td>
                                    <td>
                                        <?php if ($user['id_statut'] == 1): ?>
                                            <span class="badge badge-admin">Admin</span>
                                        <?php elseif ($user['id_statut'] == 3): ?>
                                            <span class="badge badge-prof">Professeur</span>
                                        <?php elseif ($user['id_statut'] == 4): ?>
                                            <span class="badge badge-eleve">Élève</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $user['classe_nom'] ?? '-' ?></td>
                                    <td>
                                        <?php if ($user['id_badge']): ?>
                                            <?php if ($badge_actif): ?>
                                                <span class="badge badge-actif">✅ Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-expire">⚠️ Expiré</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-inactif">❌ Aucun</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: UTILISATEURS -->
        <div id="tab-utilisateurs" class="tab-content <?= $activeTab == 'utilisateurs' ? 'active' : '' ?>">

            <!-- Formulaire d'ajout d'élève -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-graduate"></i> Ajouter un élève</h2>
                </div>

                <div class="badge-info-card">
                    <strong>ℹ️ Information :</strong> Un badge actif sera automatiquement créé pour cet élève avec une validité de 2 ans.
                </div>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" name="nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom *</label>
                            <input type="text" name="prenom" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Identifiant *</label>
                            <input type="text" name="identifiant" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe *</label>
                            <input type="password" name="mot_de_pass" class="form-control" required minlength="6">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Classe *</label>
                        <select name="id_classe" class="form-control" required>
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?= $classe['id_classe'] ?>"><?= htmlspecialchars($classe['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="ajouter_eleve" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter l'élève et créer son badge
                    </button>
                </form>
            </div>

            <!-- Formulaire d'ajout de professeur -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chalkboard-teacher"></i> Ajouter un professeur</h2>
                </div>

                <div class="badge-info-card">
                    <strong>ℹ️ Information :</strong> Un badge actif sera automatiquement créé pour ce professeur avec une validité de 2 ans.
                </div>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" name="nom_prof" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom *</label>
                            <input type="text" name="prenom_prof" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Identifiant *</label>
                            <input type="text" name="identifiant_prof" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe *</label>
                            <input type="password" name="mot_de_pass_prof" class="form-control" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" name="ajouter_professeur" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter le professeur et créer son badge
                    </button>
                </form>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Liste des utilisateurs</h2>
                    <form method="GET" style="display: flex; gap: 10px;">
                        <input type="hidden" name="tab" value="utilisateurs">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher..." style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary">🔍</button>
                    </form>
                </div>

                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Identifiant</th>
                                <th>Statut</th>
                                <th>Classe</th>
                                <th>Badge</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $user):
                                $badge_actif = ($user['etat_badge'] == 'actif' && strtotime($user['date_expiration']) > time());
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($user['prenom']) ?> <?= htmlspecialchars($user['nom']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($user['identifiant']) ?></code></td>
                                    <td>
                                        <?php if ($user['id_statut'] == 1): ?>
                                            <span class="badge badge-admin">Admin</span>
                                        <?php elseif ($user['id_statut'] == 3): ?>
                                            <span class="badge badge-prof">Professeur</span>
                                        <?php elseif ($user['id_statut'] == 4): ?>
                                            <span class="badge badge-eleve">Élève</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $user['classe_nom'] ?? '-' ?></td>
                                    <td>
                                        <?php if ($user['id_badge']): ?>
                                            <?php if ($badge_actif): ?>
                                                <span class="badge badge-actif">✅ Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-expire">⚠️ Expiré</span>
                                            <?php endif; ?>
                                            <div class="badge-details">
                                                Exp: <?= $user['date_expiration'] ? date('d/m/Y', strtotime($user['date_expiration'])) : '—' ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-inactif">❌ Pas de badge</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= $user['id_utilisateur'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if (!$user['id_badge']): ?>
                                                <button class="btn btn-sm btn-success" onclick="creerBadgeModal(<?= $user['id_utilisateur'] ?>)">
                                                    <i class="fas fa-plus"></i> Badge
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODIFICATION UTILISATEUR -->
            <?php if ($userToEdit): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-edit"></i> Modifier l'utilisateur</h2>
                        <a href="?tab=utilisateurs" class="btn btn-sm btn-warning">✖ Annuler</a>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="id_utilisateur" value="<?= $userToEdit['id_utilisateur'] ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom *</label>
                                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($userToEdit['nom']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Prénom *</label>
                                <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($userToEdit['prenom']) ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Identifiant *</label>
                                <input type="text" name="identifiant" class="form-control" value="<?= htmlspecialchars($userToEdit['identifiant']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Nouveau mot de passe</label>
                                <input type="password" name="mot_de_pass" class="form-control" placeholder="Laisser vide pour ne pas changer" minlength="6">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Classe</label>
                            <select name="id_classe" class="form-control">
                                <option value="">Aucune classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?= $classe['id_classe'] ?>" <?= ($classe['id_classe'] == $userToEdit['id_classe']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($classe['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="modifier_utilisateur" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 3: BADGES -->
        <div id="tab-badges" class="tab-content <?= $activeTab == 'badges' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-id-card"></i> Liste des badges</h2>
                </div>
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Propriétaire</th>
                                <th>Date émission</th>
                                <th>Date expiration</th>
                                <th>État</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($badges as $badge):
                                $expiree = strtotime($badge['date_expiration']) < time();
                            ?>
                                <tr>
                                    <td><?= $badge['id_badge'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($badge['prenom'] . ' ' . $badge['nom']) ?></strong>
                                        <br><small><?= $badge['identifiant'] ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($badge['date_emission'])) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($badge['date_expiration'])) ?>
                                        <?php if ($expiree): ?>
                                            <span class="badge badge-expire">Expiré</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $badge['etat'] == 'actif' ? 'actif' : 'inactif' ?>">
                                            <?= $badge['etat'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($badge['etat'] == 'actif' && !$expiree): ?>
                                            <span class="badge badge-actif">✅ Valide</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactif">❌ Invalide</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: LIVRES & QR -->
        <div id="tab-livres" class="tab-content <?= $activeTab == 'livres' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-plus"></i> Ajouter un livre avec code barre auto et QR code</h2>
                </div>

                <div class="badge-info-card">
                    <strong>ℹ️ Information :</strong> Le code barre est généré automatiquement et unique. QR code optionnel.
                </div>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Titre du livre *</label>
                            <input type="text" name="titre" class="form-control" placeholder="Ex: Le Petit Prince" required>
                        </div>

                        <div class="form-group">
                            <label>État *</label>
                            <select name="etat" class="form-control" required>
                                <option value="Neuf">Neuf</option>
                                <option value="Très bon état">Très bon état</option>
                                <option value="Bon état">Bon état</option>
                                <option value="Usé">Usé</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Cote *</label>
                            <input type="text" name="cote" class="form-control" placeholder="Ex: A2, B3, C1..." required>
                            <small>Emplacement dans la bibliothèque</small>
                        </div>

                        <div class="form-group">
                            <label>Nombre d'exemplaires</label>
                            <input type="number" name="stock" class="form-control" value="1" min="1">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Type de code barre</label>
                            <select name="type_code" class="form-control">
                                <option value="auto">Auto (LIV-20250310-00123)</option>
                                <option value="isbn">Style ISBN (978-2-XXXX-XXXX-X)</option>
                                <option value="simple">Simple (BC + timestamp)</option>
                            </select>
                            <small>Format du code barre généré automatiquement</small>
                        </div>

                        <div class="form-group form-check">
                            <input type="checkbox" name="generer_qr" id="generer_qr" checked>
                            <label for="generer_qr">Générer un QR code</label>
                        </div>
                    </div>

                    <button type="submit" name="ajouter_livre" class="btn btn-success" style="width: 100%; padding: 15px;">
                        <i class="fas fa-magic"></i> Générer le code barre et ajouter le livre
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-qrcode"></i> Livres avec codes barres et QR codes</h2>
                </div>

                <div class="grid-2">
                    <?php foreach ($livres as $livre):
                        $qr_filename = 'livre_' . $livre['id_ouvrage'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $livre['code_barre']) . '.png';
                        $qr_path = '../assets/qr_codes/' . $qr_filename;
                        $qr_exists = file_exists($qr_path);
                        $qr_url = $qr_exists ? '../assets/qr_codes/' . $qr_filename : "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode($livre['code_barre']);
                    ?>
                        <div class="card" style="padding: 15px;">
                            <h3 style="color: #0e1f4c; margin-bottom: 10px;"><?= htmlspecialchars($livre['titre']) ?></h3>

                            <div style="margin-bottom: 15px;">
                                <span class="badge badge-code-barre">
                                    <i class="fas fa-barcode"></i> <?= htmlspecialchars($livre['code_barre']) ?>
                                </span>
                            </div>

                            <p><strong>Cote:</strong> <?= $livre['cote'] ?></p>
                            <p><strong>État:</strong> <?= $livre['etat'] ?></p>
                            <p><strong>Stock:</strong> <?= $livre['total_stock'] - $livre['total_reserver'] ?> dispo / <?= $livre['total_stock'] ?> total</p>

                            <div class="qr-code-container">
                                <img src="<?= $qr_url ?>" alt="QR Code" class="qr-code-image"
                                    onerror="this.src='https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=<?= urlencode($livre['code_barre']) ?>'">

                                <div class="qr-code-actions">
                                    <?php if ($qr_exists): ?>
                                        <a href="<?= $qr_path ?>" download="<?= $qr_filename ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-download"></i> QR
                                        </a>
                                        <button onclick="printQR('<?= $qr_path ?>', '<?= addslashes($livre['titre']) ?>')" class="btn btn-sm btn-info">
                                            <i class="fas fa-print"></i> Imprimer
                                        </button>
                                    <?php endif; ?>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_ouvrage" value="<?= $livre['id_ouvrage'] ?>">
                                        <input type="hidden" name="code_barre" value="<?= $livre['code_barre'] ?>">
                                        <input type="hidden" name="titre" value="<?= htmlspecialchars($livre['titre']) ?>">
                                        <button type="submit" name="generer_qr_livre" class="btn btn-sm btn-primary">
                                            <?= $qr_exists ? '🔄 Régénérer QR' : '➕ Générer QR' ?>
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Régénérer le code barre ? Cette action modifiera le QR code associé.')">
                                        <input type="hidden" name="id_ouvrage" value="<?= $livre['id_ouvrage'] ?>">
                                        <input type="hidden" name="ancien_code" value="<?= $livre['code_barre'] ?>">
                                        <button type="submit" name="regenerer_code_barre" class="btn btn-sm btn-warning">
                                            <i class="fas fa-sync-alt"></i> Nouveau code
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div style="font-size: 11px; color: #666; text-align: center; margin-top: 5px;">
                                Scan QR pour trouver ce livre
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TAB 5: CLASSES -->
        <div id="tab-classes" class="tab-content <?= $activeTab == 'classes' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-school"></i> Liste des classes</h2>
                </div>
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Libellé</th>
                                <th>Nombre d'élèves</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $classe):
                                $nb_eleves = 0;
                                foreach ($utilisateurs as $user) {
                                    if ($user['id_classe'] == $classe['id_classe'] && $user['id_statut'] == 4) {
                                        $nb_eleves++;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?= $classe['id_classe'] ?></td>
                                    <td><strong><?= htmlspecialchars($classe['libelle']) ?></strong></td>
                                    <td><?= $nb_eleves ?> élève(s)</td>
                                    <td>
                                        <a href="edt.php?classe=<?= $classe['id_classe'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-calendar"></i> Voir EDT
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL POUR CRÉER UN BADGE -->
    <div id="modalCreerBadge" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-id-card"></i> Créer un badge</h3>
                <button class="modal-close" onclick="fermerModal()">×</button>
            </div>
            <div class="badge-info-card">
                <strong>ℹ️ Information :</strong> Le badge sera créé avec une validité de 2 ans.
            </div>
            <form method="POST" id="formBadge">
                <input type="hidden" name="id_utilisateur" id="badgeUserId">
                <p style="text-align: center; margin: 20px 0;">
                    Valide du <?= date('d/m/Y') ?> au <?= date('d/m/Y', strtotime('+2 years')) ?>
                </p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="btn btn-warning" onclick="fermerModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="creer_badge" class="btn btn-success">
                        <i class="fas fa-check"></i> Créer le badge
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Navigation par onglets
        function showTab(tabName) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            url.searchParams.delete('edit');
            window.location.href = url.toString();
        }

        // Modal de création de badge
        function creerBadgeModal(userId) {
            document.getElementById('badgeUserId').value = userId;
            document.getElementById('modalCreerBadge').style.display = 'flex';
        }

        function fermerModal() {
            document.getElementById('modalCreerBadge').style.display = 'none';
        }

        // Impression de QR code
        function printQR(qrUrl, titre) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head><title>QR Code - ${titre}</title></head>
                <body style="text-align: center; padding: 20px; font-family: Arial;">
                    <h2 style="color: #0e1f4c;">${titre}</h2>
                    <img src="${qrUrl}" style="max-width: 300px; border: 2px solid #ddd; padding: 10px; margin: 20px;">
                    <p style="margin-bottom: 30px;">Scanner ce QR code pour trouver le livre rapidement</p>
                    <button onclick="window.print()" style="padding: 10px 20px; background: #0e1f4c; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        🖨️ Imprimer
                    </button>
                </body>
                </html>
            `);
        }

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('modalCreerBadge');
            if (event.target == modal) {
                fermerModal();
            }
        }
    </script>
</body>

</html>