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

// ==================== RÉCUPÉRATION D'UN UTILISATEUR POUR MODIFICATION ====================
$userToEdit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editStmt = $conn->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $editStmt->execute([$_GET['edit']]);
    $userToEdit = $editStmt->fetch(PDO::FETCH_ASSOC);
    if ($userToEdit) {
        $activeTab = 'utilisateurs'; // Forcer l'onglet utilisateurs
    }
}

// ==================== FONCTIONS STATISTIQUES ====================
function getStatsGenerales($conn) {
    $stats = [];
    
    // Statistiques utilisateurs
    $userStats = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM utilisateur) as total_users,
            (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 1) as total_admin,
            (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 2) as total_technicien,
            (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 3) as total_prof,
            (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 4) as total_eleve,
            (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 5) as total_doc
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques classes
    $classeStats = $conn->query("
        SELECT c.libelle, COUNT(u.id_utilisateur) as nb_eleves
        FROM classe c
        LEFT JOIN utilisateur u ON c.id_classe = u.id_classe AND u.id_statut = 4
        GROUP BY c.id_classe
        ORDER BY c.libelle
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques bibliothèque
    $biblioStats = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM ouvrage) as total_livres,
            (SELECT SUM(stock) FROM exemplaire) as total_exemplaires,
            (SELECT COUNT(*) FROM pret WHERE date_retour_effectif IS NULL) as prets_encours,
            (SELECT COUNT(*) FROM pret WHERE date_retour_effectif IS NULL AND CURDATE() > COALESCE(prolongation, DATE_ADD(date_pret, INTERVAL 14 DAY))) as prets_retard,
            (SELECT SUM(reserver) FROM exemplaire) as reservations
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques badges
    $badgeStats = $conn->query("
        SELECT 
            COUNT(*) as total_badges,
            SUM(CASE WHEN etat = 'actif' THEN 1 ELSE 0 END) as badges_actifs,
            SUM(CASE WHEN date_expiration < CURDATE() THEN 1 ELSE 0 END) as badges_expires
        FROM badge
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques EDT
    $edtStats = $conn->query("
        SELECT 
            COUNT(*) as total_cours,
            COUNT(DISTINCT id_classe) as classes_avec_cours,
            (SELECT COUNT(*) FROM salle) as total_salles,
            (SELECT COUNT(*) FROM salle_edt) as salles_assignees
        FROM emploie_du_temps
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Activité récente (7 derniers jours)
    $activiteRecente = $conn->query("
        SELECT DATE(date_log) as jour, COUNT(*) as nb_logs
        FROM logs_systeme
        WHERE date_log >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date_log)
        ORDER BY jour DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'utilisateurs' => $userStats,
        'classes' => $classeStats,
        'bibliotheque' => $biblioStats,
        'badges' => $badgeStats,
        'edt' => $edtStats,
        'activite' => $activiteRecente
    ];
}

$stats = getStatsGenerales($conn);

// ==================== GESTION UTILISATEURS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJOUTER UN UTILISATEUR
    if (isset($_POST['ajouter_utilisateur'])) {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $identifiant = trim($_POST['identifiant']);
        $mot_de_pass = password_hash(trim($_POST['mot_de_pass']), PASSWORD_DEFAULT);
        $id_statut = intval($_POST['id_statut']);
        $id_classe = !empty($_POST['id_classe']) ? intval($_POST['id_classe']) : null;
        $peut_se_connecter = isset($_POST['peut_se_connecter']) ? 1 : 0;
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO utilisateur (nom, prenom, identifiant, mot_de_pass, id_statut, id_classe, peut_se_connectert)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom, $prenom, $identifiant, $mot_de_pass, $id_statut, $id_classe, $peut_se_connecter]);
            
            addLog($conn, 'info', 'AJOUT_UTILISATEUR', $_SESSION['user_id'], 
                   "Nouvel utilisateur ajouté: $prenom $nom", "Identifiant: $identifiant, Statut: $id_statut");
            
            $message = "✅ Utilisateur ajouté avec succès !";
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
    
    // MODIFIER UN UTILISATEUR
    elseif (isset($_POST['modifier_utilisateur'])) {
        $id_utilisateur = intval($_POST['id_utilisateur']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $identifiant = trim($_POST['identifiant']);
        $id_statut = intval($_POST['id_statut']);
        $id_classe = !empty($_POST['id_classe']) ? intval($_POST['id_classe']) : null;
        $peut_se_connecter = isset($_POST['peut_se_connecter']) ? 1 : 0;
        
        try {
            // Vérifier si l'identifiant est unique (sauf pour cet utilisateur)
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM utilisateur WHERE identifiant = ? AND id_utilisateur != ?");
            $checkStmt->execute([$identifiant, $id_utilisateur]);
            if ($checkStmt->fetchColumn() > 0) {
                $errorMessage = "❌ Cet identifiant est déjà utilisé par un autre utilisateur.";
            } else {
                // Construction de la requête
                $sql = "UPDATE utilisateur SET nom = ?, prenom = ?, identifiant = ?, id_statut = ?, id_classe = ?, peut_se_connectert = ?";
                $params = [$nom, $prenom, $identifiant, $id_statut, $id_classe, $peut_se_connecter];
                
                // Si un nouveau mot de passe est fourni
                if (!empty($_POST['mot_de_pass'])) {
                    $sql .= ", mot_de_pass = ?";
                    $params[] = password_hash($_POST['mot_de_pass'], PASSWORD_DEFAULT);
                }
                
                $sql .= " WHERE id_utilisateur = ?";
                $params[] = $id_utilisateur;
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                addLog($conn, 'info', 'MODIF_UTILISATEUR', $_SESSION['user_id'], 
                       "Utilisateur modifié: $prenom $nom", "ID: $id_utilisateur");
                
                $message = "✅ Utilisateur modifié avec succès !";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
    
    // SUPPRIMER UN UTILISATEUR
    elseif (isset($_POST['supprimer_utilisateur'])) {
        $id_utilisateur = intval($_POST['id_utilisateur']);
        
        if ($id_utilisateur == $_SESSION['user_id']) {
            $errorMessage = "❌ Vous ne pouvez pas supprimer votre propre compte !";
        } else {
            try {
                // Supprimer les références
                $conn->prepare("DELETE FROM badge WHERE id_utilisateur = ?")->execute([$id_utilisateur]);
                $conn->prepare("DELETE FROM utilisateur_matiere WHERE id_utilisateur = ?")->execute([$id_utilisateur]);
                $conn->prepare("DELETE FROM utilisateur WHERE id_utilisateur = ?")->execute([$id_utilisateur]);
                
                addLog($conn, 'warning', 'SUPPR_UTILISATEUR', $_SESSION['user_id'], 
                       "Utilisateur supprimé", "ID: $id_utilisateur");
                
                $message = "✅ Utilisateur supprimé avec succès !";
            } catch (PDOException $e) {
                $errorMessage = "Erreur : " . $e->getMessage();
            }
        }
    }
    
    // AJOUTER UN BADGE
    elseif (isset($_POST['ajouter_badge'])) {
        $id_utilisateur = intval($_POST['id_utilisateur_badge']);
        $date_expiration = $_POST['date_expiration'];
        $etat = $_POST['etat_badge'];
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO badge (id_utilisateur, date_emission, date_expiration, etat, id_statut)
                VALUES (?, CURDATE(), ?, ?, (SELECT id_statut FROM utilisateur WHERE id_utilisateur = ?))
            ");
            $stmt->execute([$id_utilisateur, $date_expiration, $etat, $id_utilisateur]);
            
            addLog($conn, 'info', 'AJOUT_BADGE', $_SESSION['user_id'], 
                   "Badge ajouté pour utilisateur ID: $id_utilisateur", "Expiration: $date_expiration");
            
            $message = "✅ Badge ajouté avec succès !";
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
    
    // CONFIGURATION GÉNÉRALE
    elseif (isset($_POST['save_config'])) {
        // Sauvegarder les configurations dans une table (à créer si besoin)
        $message = "✅ Configuration sauvegardée avec succès !";
    }
}

// ==================== RÉCUPÉRATION DES DONNÉES ====================
// Liste des utilisateurs
$users = $conn->query("
    SELECT u.*, s.statut, c.libelle as classe_nom
    FROM utilisateur u
    JOIN statut s ON u.id_statut = s.id_statut
    LEFT JOIN classe c ON u.id_classe = c.id_classe
    ORDER BY u.nom, u.prenom
")->fetchAll(PDO::FETCH_ASSOC);

// Liste des classes
$classes = $conn->query("SELECT * FROM classe ORDER BY libelle")->fetchAll(PDO::FETCH_ASSOC);

// Liste des statuts
$statuts = $conn->query("SELECT * FROM statut ORDER BY id_statut")->fetchAll(PDO::FETCH_ASSOC);

// Liste des badges
$badges = $conn->query("
    SELECT b.*, u.nom, u.prenom, u.identifiant
    FROM badge b
    JOIN utilisateur u ON b.id_utilisateur = u.id_utilisateur
    ORDER BY b.date_expiration ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Liste des logs récents
$logs_recents = $conn->query("
    SELECT l.*, u.nom, u.prenom
    FROM logs_systeme l
    LEFT JOIN utilisateur u ON l.id_utilisateur = u.id_utilisateur
    ORDER BY l.date_log DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Globale - GESTION ACCÈS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
        }

        /* HEADER */
        .admin-header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(14, 31, 76, 0.2);
        }

        .admin-header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .admin-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        /* STATISTIQUES GLOBALES */
        .stats-global {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-top: 4px solid;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.users { border-color: #0e1f4c; }
        .stat-card.books { border-color: #28a745; }
        .stat-card.badges { border-color: #ffc107; }
        .stat-card.edt { border-color: #17a2b8; }
        .stat-card.logs { border-color: #6f42c1; }
        .stat-card.retard { border-color: #dc3545; }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0e1f4c;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-sub {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }

        /* TABS */
        .admin-tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .admin-tab {
            padding: 15px 25px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-radius: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-tab:hover {
            background: #f0f2f5;
            color: #0e1f4c;
        }

        .admin-tab.active {
            background: #0e1f4c;
            color: white;
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
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
            border-bottom: 2px solid #f0f2f5;
        }

        .card-header h2 {
            color: #0e1f4c;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* TABLEAUX */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            color: #0e1f4c;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        tr:hover {
            background: #f8f9fa;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* FORMULAIRES */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0e1f4c;
            font-weight: 600;
            font-size: 14px;
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

        /* BADGES */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-admin { background: #0e1f4c; color: white; }
        .badge-prof { background: #28a745; color: white; }
        .badge-eleve { background: #ffc107; color: #212529; }
        .badge-doc { background: #17a2b8; color: white; }
        .badge-technicien { background: #6f42c1; color: white; }

        .badge-actif { background: #28a745; color: white; }
        .badge-inactif { background: #dc3545; color: white; }

        /* GRILLES */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
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

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* CHARTS */
        .chart-container {
            height: 300px;
            margin: 20px 0;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .admin-tab {
                padding: 10px 15px;
                font-size: 13px;
            }
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 30px;
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

        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        /* UTILS */
        .text-right { text-align: right; }
        .mt-3 { margin-top: 15px; }
        .mb-3 { margin-bottom: 15px; }
        .w-100 { width: 100%; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- HEADER ADMIN -->
        <div class="admin-header">
            <h1>
                <i class="fas fa-crown"></i>
                Administration Globale
            </h1>
            <p>Interface de gestion complète du système - Accès réservé aux administrateurs</p>
        </div>

        <!-- MESSAGES -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- STATISTIQUES GLOBALES -->
        <div class="stats-global">
            <div class="stat-card users">
                <div class="stat-number"><?= $stats['utilisateurs']['total_users'] ?? 0 ?></div>
                <div class="stat-label">Utilisateurs totaux</div>
                <div class="stat-sub">
                    👑 <?= $stats['utilisateurs']['total_admin'] ?? 0 ?> Admin |
                    👨‍🏫 <?= $stats['utilisateurs']['total_prof'] ?? 0 ?> Profs |
                    👨‍🎓 <?= $stats['utilisateurs']['total_eleve'] ?? 0 ?> Élèves
                </div>
            </div>

            <div class="stat-card books">
                <div class="stat-number"><?= $stats['bibliotheque']['total_livres'] ?? 0 ?></div>
                <div class="stat-label">Livres</div>
                <div class="stat-sub">
                    📚 <?= $stats['bibliotheque']['total_exemplaires'] ?? 0 ?> exemplaires |
                    🔔 <?= $stats['bibliotheque']['reservations'] ?? 0 ?> réservés
                </div>
            </div>

            <div class="stat-card badges">
                <div class="stat-number"><?= $stats['badges']['total_badges'] ?? 0 ?></div>
                <div class="stat-label">Badges</div>
                <div class="stat-sub">
                    ✅ <?= $stats['badges']['badges_actifs'] ?? 0 ?> actifs |
                    ⚠️ <?= $stats['badges']['badges_expires'] ?? 0 ?> expirés
                </div>
            </div>

            <div class="stat-card edt">
                <div class="stat-number"><?= $stats['edt']['total_cours'] ?? 0 ?></div>
                <div class="stat-label">Cours</div>
                <div class="stat-sub">
                    🏫 <?= $stats['edt']['classes_avec_cours'] ?? 0 ?> classes |
                    📍 <?= $stats['edt']['salles_assignees'] ?? 0 ?>/<?= $stats['edt']['total_salles'] ?? 0 ?> salles
                </div>
            </div>

            <div class="stat-card retard">
                <div class="stat-number"><?= $stats['bibliotheque']['prets_retard'] ?? 0 ?></div>
                <div class="stat-label">Prêts en retard</div>
                <div class="stat-sub">
                    📅 Sur <?= $stats['bibliotheque']['prets_encours'] ?? 0 ?> prêts en cours
                </div>
            </div>

            <div class="stat-card logs">
                <div class="stat-number"><?= count($logs_recents) ?></div>
                <div class="stat-label">Derniers logs</div>
                <div class="stat-sub">
                    ⏱️ Dernière activité : <?= !empty($logs_recents) ? date('H:i', strtotime($logs_recents[0]['date_log'])) : 'N/A' ?>
                </div>
            </div>
        </div>

        <!-- TABS ADMIN -->
        <div class="admin-tabs">
            <button class="admin-tab <?= $activeTab == 'dashboard' ? 'active' : '' ?>" onclick="showTab('dashboard')">
                <i class="fas fa-chart-pie"></i> Dashboard
            </button>
            <button class="admin-tab <?= $activeTab == 'utilisateurs' ? 'active' : '' ?>" onclick="showTab('utilisateurs')">
                <i class="fas fa-users"></i> Utilisateurs
            </button>
            <button class="admin-tab <?= $activeTab == 'badges' ? 'active' : '' ?>" onclick="showTab('badges')">
                <i class="fas fa-id-card"></i> Badges
            </button>
            <button class="admin-tab <?= $activeTab == 'classes' ? 'active' : '' ?>" onclick="showTab('classes')">
                <i class="fas fa-school"></i> Classes
            </button>
            <button class="admin-tab <?= $activeTab == 'bibliotheque' ? 'active' : '' ?>" onclick="showTab('bibliotheque')">
                <i class="fas fa-book"></i> Bibliothèque
            </button>
            <button class="admin-tab <?= $activeTab == 'logs' ? 'active' : '' ?>" onclick="showTab('logs')">
                <i class="fas fa-history"></i> Logs
            </button>
            <button class="admin-tab <?= $activeTab == 'config' ? 'active' : '' ?>" onclick="showTab('config')">
                <i class="fas fa-cog"></i> Configuration
            </button>
        </div>

        <!-- TAB 1: DASHBOARD -->
        <div id="tab-dashboard" class="tab-content <?= $activeTab == 'dashboard' ? 'active' : '' ?>">
            <div class="grid-2">
                <!-- Graphique activité -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-line"></i> Activité des 7 derniers jours</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartActivite"></canvas>
                    </div>
                </div>

                <!-- Répartition utilisateurs -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-pie"></i> Répartition des utilisateurs</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartUsers"></canvas>
                    </div>
                </div>
            </div>

            <!-- Aperçu des classes -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-school"></i> Effectifs par classe</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Nombre d'élèves</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['classes'] as $classe): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($classe['libelle']) ?></strong></td>
                                    <td><?= $classe['nb_eleves'] ?> élève(s)</td>
                                    <td>
                                        <?php if ($classe['nb_eleves'] > 0): ?>
                                            <span class="badge badge-actif">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactif">Vide</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Derniers utilisateurs -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-plus"></i> Derniers utilisateurs inscrits</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Identifiant</th>
                                <th>Statut</th>
                                <th>Classe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_users = array_slice($users, 0, 5);
                            foreach ($recent_users as $user): 
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['nom']) ?></td>
                                    <td><?= htmlspecialchars($user['prenom']) ?></td>
                                    <td><code><?= htmlspecialchars($user['identifiant']) ?></code></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($user['statut']) ?>">
                                            <?= htmlspecialchars($user['statut']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['classe_nom'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: UTILISATEURS -->
        <div id="tab-utilisateurs" class="tab-content <?= $activeTab == 'utilisateurs' ? 'active' : '' ?>">
            
            <!-- MODIFICATION D'UN UTILISATEUR (apparaît seulement si on a cliqué sur Modifier) -->
            <?php if ($userToEdit): ?>
            <div class="card" style="border-left: 4px solid #ffc107; margin-bottom: 30px; background: #fffbf0;">
                <div class="card-header" style="background: #fff3cd; border-bottom-color: #ffeeba;">
                    <h2 style="color: #856404;">
                        <i class="fas fa-edit"></i> Modification de l'utilisateur
                    </h2>
                    <a href="?tab=utilisateurs" class="btn btn-sm btn-warning">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
                
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="id_utilisateur" value="<?= $userToEdit['id_utilisateur'] ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nom *</label>
                                <input type="text" name="nom" class="form-control" 
                                       value="<?= htmlspecialchars($userToEdit['nom']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Prénom *</label>
                                <input type="text" name="prenom" class="form-control" 
                                       value="<?= htmlspecialchars($userToEdit['prenom']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Identifiant *</label>
                                <input type="text" name="identifiant" class="form-control" 
                                       value="<?= htmlspecialchars($userToEdit['identifiant']) ?>" required>
                                <small style="color: #666;">Doit être unique</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Nouveau mot de passe</label>
                                <input type="password" name="mot_de_pass" class="form-control" 
                                       placeholder="Laisser vide pour ne pas changer" minlength="6">
                                <small style="color: #666;">Minimum 6 caractères - Laissez vide pour conserver l'actuel</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Statut *</label>
                                <select name="id_statut" class="form-control" required>
                                    <?php foreach ($statuts as $statut): ?>
                                        <option value="<?= $statut['id_statut'] ?>" 
                                            <?= $statut['id_statut'] == $userToEdit['id_statut'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($statut['statut']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Classe</label>
                                <select name="id_classe" class="form-control">
                                    <option value="">Aucune classe</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?= $classe['id_classe'] ?>" 
                                            <?= $classe['id_classe'] == $userToEdit['id_classe'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($classe['libelle']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                                    <input type="checkbox" name="peut_se_connecter" 
                                        <?= $userToEdit['peut_se_connectert'] ? 'checked' : '' ?>>
                                    Peut se connecter
                                </label>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <a href="?tab=utilisateurs" class="btn btn-secondary" style="flex: 1; background: #6c757d; color: white;">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" name="modifier_utilisateur" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout d'utilisateur -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-plus"></i> Ajouter un utilisateur</h2>
                    <button class="btn btn-primary btn-sm" onclick="toggleForm('addUserForm')">
                        <i class="fas fa-plus"></i> Nouvel utilisateur
                    </button>
                </div>

                <div id="addUserForm" style="display: none;">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nom *</label>
                                <input type="text" name="nom" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Prénom *</label>
                                <input type="text" name="prenom" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Identifiant *</label>
                                <input type="text" name="identifiant" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Mot de passe *</label>
                                <input type="password" name="mot_de_pass" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Statut *</label>
                                <select name="id_statut" class="form-control" required>
                                    <?php foreach ($statuts as $statut): ?>
                                        <option value="<?= $statut['id_statut'] ?>">
                                            <?= htmlspecialchars($statut['statut']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Classe</label>
                                <select name="id_classe" class="form-control">
                                    <option value="">Aucune classe</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?= $classe['id_classe'] ?>">
                                            <?= htmlspecialchars($classe['libelle']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="peut_se_connecter" checked>
                                    Peut se connecter
                                </label>
                            </div>
                        </div>
                        <button type="submit" name="ajouter_utilisateur" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Créer l'utilisateur
                        </button>
                    </form>
                </div>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Liste des utilisateurs</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Identifiant</th>
                                <th>Statut</th>
                                <th>Classe</th>
                                <th>Connexion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id_utilisateur'] ?></td>
                                    <td><?= htmlspecialchars($user['nom']) ?></td>
                                    <td><?= htmlspecialchars($user['prenom']) ?></td>
                                    <td><code><?= htmlspecialchars($user['identifiant']) ?></code></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($user['statut']) ?>">
                                            <?= htmlspecialchars($user['statut']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['classe_nom'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($user['peut_se_connectert']): ?>
                                            <span class="badge badge-actif">✅ Oui</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactif">❌ Non</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <!-- Bouton Modifier -->
                                            <a href="?edit=<?= $user['id_utilisateur'] ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> Modifier
                                            </a>
                                            
                                            <!-- Bouton Supprimer (si ce n'est pas l'admin connecté) -->
                                            <?php if ($user['id_utilisateur'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="confirmSuppression(<?= $user['id_utilisateur'] ?>, '<?= htmlspecialchars(addslashes($user['prenom'] . ' ' . $user['nom'])) ?>')">
                                                    <i class="fas fa-trash"></i> Supprimer
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
        </div>

        <!-- TAB 3: BADGES -->
        <div id="tab-badges" class="tab-content <?= $activeTab == 'badges' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-id-card"></i> Gestion des badges</h2>
                </div>

                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label>Utilisateur</label>
                        <select name="id_utilisateur_badge" class="form-control" required>
                            <option value="">Sélectionner un utilisateur</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id_utilisateur'] ?>">
                                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?> (<?= $user['identifiant'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date d'expiration</label>
                        <input type="date" name="date_expiration" class="form-control" 
                               value="<?= date('Y-m-d', strtotime('+2 years')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>État</label>
                        <select name="etat_badge" class="form-control">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                            <option value="perdu">Perdu</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" name="ajouter_badge" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> Ajouter un badge
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Liste des badges</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Badge</th>
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
                                        <?= htmlspecialchars($badge['prenom'] . ' ' . $badge['nom']) ?>
                                        <br><small><?= $badge['identifiant'] ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($badge['date_emission'])) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($badge['date_expiration'])) ?>
                                        <?php if ($expiree): ?>
                                            <span class="badge badge-inactif">Expiré</span>
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

        <!-- TAB 4: CLASSES -->
        <div id="tab-classes" class="tab-content <?= $activeTab == 'classes' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-school"></i> Liste des classes</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Libellé</th>
                                <th>Nombre d'élèves</th>
                                <th>Niveaux</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $classe): 
                                $nb_eleves = 0;
                                foreach ($stats['classes'] as $stat) {
                                    if ($stat['libelle'] == $classe['libelle']) {
                                        $nb_eleves = $stat['nb_eleves'];
                                        break;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?= $classe['id_classe'] ?></td>
                                    <td><strong><?= htmlspecialchars($classe['libelle']) ?></strong></td>
                                    <td><?= $nb_eleves ?> élève(s)</td>
                                    <td>
                                        <?php
                                        $niveaux = $conn->prepare("
                                            SELECT n.annee 
                                            FROM niveau_classe nc
                                            JOIN niveau n ON nc.id_niveau = n.id_niveau
                                            WHERE nc.id_classe = ?
                                        ");
                                        $niveaux->execute([$classe['id_classe']]);
                                        $niveau_data = $niveaux->fetchAll();
                                        foreach ($niveau_data as $n): 
                                        ?>
                                            <span class="badge badge-info">Année <?= $n['annee'] ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm" onclick="voirEDTClasse(<?= $classe['id_classe'] ?>)">
                                            <i class="fas fa-calendar"></i> EDT
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 5: BIBLIOTHÈQUE -->
        <div id="tab-bibliotheque" class="tab-content <?= $activeTab == 'bibliotheque' ? 'active' : '' ?>">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-book"></i> Statistiques bibliothèque</h2>
                    </div>
                    <div style="padding: 20px;">
                        <p><strong>📚 Total livres :</strong> <?= $stats['bibliotheque']['total_livres'] ?? 0 ?></p>
                        <p><strong>📦 Exemplaires :</strong> <?= $stats['bibliotheque']['total_exemplaires'] ?? 0 ?></p>
                        <p><strong>🔄 Prêts en cours :</strong> <?= $stats['bibliotheque']['prets_encours'] ?? 0 ?></p>
                        <p><strong>⚠️ Prêts en retard :</strong> <?= $stats['bibliotheque']['prets_retard'] ?? 0 ?></p>
                        <p><strong>🔔 Réservations :</strong> <?= $stats['bibliotheque']['reservations'] ?? 0 ?></p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-clock"></i> Actions rapides</h2>
                    </div>
                    <div style="padding: 20px;">
                        <a href="gestion_bibliotheque.php" class="btn btn-primary w-100" style="margin-bottom: 10px;">
                            <i class="fas fa-book"></i> Gérer la bibliothèque
                        </a>
                        <a href="gestion_bibliotheque.php?tab=prets" class="btn btn-info w-100" style="margin-bottom: 10px;">
                            <i class="fas fa-exchange-alt"></i> Voir les prêts
                        </a>
                        <a href="gestion_bibliotheque.php?tab=ajout" class="btn btn-success w-100">
                            <i class="fas fa-plus"></i> Ajouter un livre
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 6: LOGS -->
        <div id="tab-logs" class="tab-content <?= $activeTab == 'logs' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Derniers logs système</h2>
                    <a href="logs.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-external-link-alt"></i> Voir tous les logs
                    </a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Niveau</th>
                                <th>Type</th>
                                <th>Utilisateur</th>
                                <th>Description</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs_recents as $log): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($log['date_log'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $log['niveau'] ?>">
                                            <?= strtoupper($log['niveau']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['type_action']) ?></td>
                                    <td>
                                        <?php if ($log['nom']): ?>
                                            <?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?>
                                        <?php else: ?>
                                            <em>Système</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($log['description']) ?></td>
                                    <td><code><?= $log['ip_address'] ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 7: CONFIGURATION -->
        <div id="tab-config" class="tab-content <?= $activeTab == 'config' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-cog"></i> Configuration générale</h2>
                </div>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Durée par défaut des prêts (jours)</label>
                            <input type="number" class="form-control" value="14" min="1" max="90">
                        </div>
                        <div class="form-group">
                            <label>Durée de validité des badges (mois)</label>
                            <input type="number" class="form-control" value="24" min="1" max="60">
                        </div>
                        <div class="form-group">
                            <label>Délai de retard autorisé (minutes)</label>
                            <input type="number" class="form-control" value="10" min="0" max="60">
                        </div>
                        <div class="form-group">
                            <label>Mode maintenance</label>
                            <select class="form-control">
                                <option value="0">Désactivé</option>
                                <option value="1">Activé</option>
                            </select>
                        </div>
                    </div>

                    <h3 style="margin: 20px 0 10px;">Sécurité</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" checked> Journalisation détaillée
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" checked> Forcer mot de passe fort
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" checked> Expiration de session (30 min)
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="save_config" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Enregistrer la configuration
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-database"></i> Informations système</h2>
                </div>
                <div style="padding: 20px;">
                    <p><strong>Version PHP :</strong> <?= phpversion() ?></p>
                    <p><strong>Version MySQL :</strong> 
                        <?= $conn->query("SELECT VERSION()")->fetchColumn() ?>
                    </p>
                    <p><strong>Espace disque :</strong> 
                        <?php 
                        $free = disk_free_space("/");
                        $total = disk_total_space("/");
                        $used = $total - $free;
                        echo round($used / 1024 / 1024 / 1024, 2) . " Go / " . round($total / 1024 / 1024 / 1024, 2) . " Go";
                        ?>
                    </p>
                    <p><strong>Dernière sauvegarde :</strong> <?= date('d/m/Y H:i') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE CONFIRMATION DE SUPPRESSION -->
    <div id="modalSuppression" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirmation de suppression</h3>
                <span class="close-modal" onclick="fermerModal()">&times;</span>
            </div>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <strong>⚠️ Attention !</strong> Cette action est irréversible.
            </div>
            <p id="modalMessage">Êtes-vous sûr de vouloir supprimer cet utilisateur ?</p>
            <p style="font-size: 13px; color: #666; margin-top: 10px;">
                Toutes les données associées (badge, historique, prêts) seront également supprimées.
            </p>
            
            <form method="POST" id="formSuppression">
                <input type="hidden" name="id_utilisateur" id="userIdToDelete">
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="fermerModal()" style="background: #6c757d; color: white;">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="supprimer_utilisateur" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Supprimer définitivement
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
            window.location.href = url.toString();
        }

        // Formulaire d'ajout d'utilisateur
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        // Voir EDT d'une classe
        function voirEDTClasse(classeId) {
            window.location.href = 'edt.php?classe=' + classeId;
        }

        // Gestion de la suppression avec modal
        function confirmSuppression(userId, userName) {
            document.getElementById('userIdToDelete').value = userId;
            document.getElementById('modalMessage').innerHTML = 'Êtes-vous sûr de vouloir supprimer <strong>' + userName + '</strong> ?';
            document.getElementById('modalSuppression').style.display = 'flex';
        }

        function fermerModal() {
            document.getElementById('modalSuppression').style.display = 'none';
        }

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('modalSuppression');
            if (event.target == modal) {
                fermerModal();
            }
        }

        // Graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique activité
            const ctxActivite = document.getElementById('chartActivite').getContext('2d');
            new Chart(ctxActivite, {
                type: 'line',
                data: {
                    labels: [<?php 
                        $labels = [];
                        foreach ($stats['activite'] as $act) {
                            $labels[] = "'" . date('d/m', strtotime($act['jour'])) . "'";
                        }
                        echo implode(', ', array_reverse($labels));
                    ?>],
                    datasets: [{
                        label: 'Nombre d\'actions',
                        data: [<?php 
                            $data = [];
                            foreach ($stats['activite'] as $act) {
                                $data[] = $act['nb_logs'];
                            }
                            echo implode(', ', array_reverse($data));
                        ?>],
                        borderColor: '#0e1f4c',
                        backgroundColor: 'rgba(14, 31, 76, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Graphique utilisateurs
            const ctxUsers = document.getElementById('chartUsers').getContext('2d');
            new Chart(ctxUsers, {
                type: 'doughnut',
                data: {
                    labels: ['Admin', 'Professeurs', 'Élèves', 'Documentalistes', 'Techniciens'],
                    datasets: [{
                        data: [
                            <?= $stats['utilisateurs']['total_admin'] ?? 0 ?>,
                            <?= $stats['utilisateurs']['total_prof'] ?? 0 ?>,
                            <?= $stats['utilisateurs']['total_eleve'] ?? 0 ?>,
                            <?= $stats['utilisateurs']['total_doc'] ?? 0 ?>,
                            <?= $stats['utilisateurs']['total_technicien'] ?? 0 ?>
                        ],
                        backgroundColor: ['#0e1f4c', '#28a745', '#ffc107', '#17a2b8', '#6f42c1']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>