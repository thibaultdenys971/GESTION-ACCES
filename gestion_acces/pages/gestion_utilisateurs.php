<?php
// pages/gestion_utilisateurs.php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['id_statut'] != 1 && $_SESSION['id_statut'] != 2)) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

$message = '';
$errorMessage = '';
$search = $_GET['search'] ?? '';

// TRAITEMENT : AJOUTER UN ÉLÈVE AVEC BADGE AUTOMATIQUE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_eleve'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $identifiant = trim($_POST['identifiant']);
    $mot_de_pass = password_hash(trim($_POST['mot_de_pass']), PASSWORD_DEFAULT);
    $id_classe = intval($_POST['id_classe']);
    
    try {
        $conn->beginTransaction();
        
        // 1. Créer l'utilisateur (statut 4 = Élève)
        $userStmt = $conn->prepare("
            INSERT INTO utilisateur (nom, prenom, identifiant, mot_de_pass, id_statut, id_classe, peut_se_connectert) 
            VALUES (?, ?, ?, ?, 4, ?, 0)
        ");
        $userStmt->execute([$nom, $prenom, $identifiant, $mot_de_pass, $id_classe]);
        $id_utilisateur = $conn->lastInsertId();
        
        // 2. Créer automatiquement un badge pour cet élève
        $date_emission = date('Y-m-d');
        $date_expiration = date('Y-m-d', strtotime('+2 years'));
        
        $badgeStmt = $conn->prepare("
            INSERT INTO badge (date_emission, date_expiration, etat, id_utilisateur, id_statut) 
            VALUES (?, ?, 'actif', ?, 4)
        ");
        $badgeStmt->execute([$date_emission, $date_expiration, $id_utilisateur]);
        $id_badge = $conn->lastInsertId();
        
        // 3. Lier le badge à l'utilisateur
        $updateUserStmt = $conn->prepare("
            UPDATE utilisateur 
            SET id_badge = ? 
            WHERE id_utilisateur = ?
        ");
        $updateUserStmt->execute([$id_badge, $id_utilisateur]);
        
        $conn->commit();
        
        $message = "✅ Élève ajouté avec succès ! Un badge actif a été créé automatiquement (valide jusqu'au " . date('d/m/Y', strtotime($date_expiration)) . ").";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $errorMessage = "❌ Erreur : " . $e->getMessage();
    }
}

// TRAITEMENT : AJOUTER UN PROFESSEUR AVEC BADGE AUTOMATIQUE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_professeur'])) {
    $nom = trim($_POST['nom_prof']);
    $prenom = trim($_POST['prenom_prof']);
    $identifiant = trim($_POST['identifiant_prof']);
    $mot_de_pass = password_hash(trim($_POST['mot_de_pass_prof']), PASSWORD_DEFAULT);
    
    try {
        $conn->beginTransaction();
        
        // 1. Créer le professeur (statut 3 = Utilisateur/Professeur)
        $userStmt = $conn->prepare("
            INSERT INTO utilisateur (nom, prenom, identifiant, mot_de_pass, id_statut, id_classe, peut_se_connectert) 
            VALUES (?, ?, ?, ?, 3, 1, 0)
        ");
        $userStmt->execute([$nom, $prenom, $identifiant, $mot_de_pass]);
        $id_utilisateur = $conn->lastInsertId();
        
        // 2. Créer automatiquement un badge pour ce professeur
        $date_emission = date('Y-m-d');
        $date_expiration = date('Y-m-d', strtotime('+2 years'));
        
        $badgeStmt = $conn->prepare("
            INSERT INTO badge (date_emission, date_expiration, etat, id_utilisateur, id_statut) 
            VALUES (?, ?, 'actif', ?, 3)
        ");
        $badgeStmt->execute([$date_emission, $date_expiration, $id_utilisateur]);
        $id_badge = $conn->lastInsertId();
        
        // 3. Lier le badge à l'utilisateur
        $updateUserStmt = $conn->prepare("
            UPDATE utilisateur 
            SET id_badge = ? 
            WHERE id_utilisateur = ?
        ");
        $updateUserStmt->execute([$id_badge, $id_utilisateur]);
        
        $conn->commit();
        
        $message = "✅ Professeur ajouté avec succès ! Un badge actif a été créé automatiquement (valide jusqu'au " . date('d/m/Y', strtotime($date_expiration)) . ").";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $errorMessage = "❌ Erreur : " . $e->getMessage();
    }
}

// TRAITEMENT : CRÉER UN BADGE POUR UN UTILISATEUR EXISTANT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_badge'])) {
    $id_utilisateur = intval($_POST['id_utilisateur']);
    
    try {
        // Vérifier si l'utilisateur existe et a déjà un badge
        $checkStmt = $conn->prepare("SELECT id_badge, id_statut FROM utilisateur WHERE id_utilisateur = ?");
        $checkStmt->execute([$id_utilisateur]);
        $user = $checkStmt->fetch();
        
        if ($user) {
            if ($user['id_badge']) {
                $errorMessage = "❌ Cet utilisateur a déjà un badge.";
            } else {
                $date_emission = date('Y-m-d');
                $date_expiration = date('Y-m-d', strtotime('+2 years'));
                
                $badgeStmt = $conn->prepare("
                    INSERT INTO badge (date_emission, date_expiration, etat, id_utilisateur, id_statut) 
                    VALUES (?, ?, 'actif', ?, ?)
                ");
                $badgeStmt->execute([$date_emission, $date_expiration, $id_utilisateur, $user['id_statut']]);
                $id_badge = $conn->lastInsertId();
                
                $updateUserStmt = $conn->prepare("
                    UPDATE utilisateur 
                    SET id_badge = ? 
                    WHERE id_utilisateur = ?
                ");
                $updateUserStmt->execute([$id_badge, $id_utilisateur]);
                
                $message = "✅ Badge créé avec succès ! (valide jusqu'au " . date('d/m/Y', strtotime($date_expiration)) . ")";
            }
        } else {
            $errorMessage = "❌ Utilisateur non trouvé.";
        }
    } catch (PDOException $e) {
        $errorMessage = "❌ Erreur : " . $e->getMessage();
    }
}

// RÉCUPÉRER LA LISTE DES UTILISATEURS AVEC LEURS BADGES
$userQuery = "
    SELECT u.*, s.statut, c.libelle as classe, b.date_emission, b.date_expiration, b.etat as etat_badge
    FROM utilisateur u
    LEFT JOIN statut s ON u.id_statut = s.id_statut
    LEFT JOIN classe c ON u.id_classe = c.id_classe
    LEFT JOIN badge b ON u.id_badge = b.id_badge
    WHERE 1=1
";

if (!empty($search)) {
    $searchTerm = "%$search%";
    $userQuery .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.identifiant LIKE ?)";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
} else {
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute();
}

$utilisateurs = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// RÉCUPÉRER LES CLASSES POUR LE FORMULAIRE
$classeStmt = $conn->prepare("SELECT * FROM classe ORDER BY libelle");
$classeStmt->execute();
$classes = $classeStmt->fetchAll(PDO::FETCH_ASSOC);

// STATISTIQUES
$statsStmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 4) as total_eleves,
        (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 3) as total_profs,
        (SELECT COUNT(*) FROM badge WHERE etat = 'actif') as badges_actifs,
        (SELECT COUNT(*) FROM badge WHERE etat = '' OR etat IS NULL OR etat = 'inactif') as badges_inactifs,
        (SELECT COUNT(*) FROM utilisateur WHERE id_badge IS NULL) as sans_badge
");
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Utilisateurs - GESTION ACCES</title>
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-top: 4px solid;
            transition: transform 0.3s;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-mini-card.eleves { border-color: #28a745; }
        .stat-mini-card.profs { border-color: #17a2b8; }
        .stat-mini-card.badges-actifs { border-color: #ffc107; }
        .stat-mini-card.badges-inactifs { border-color: #dc3545; }
        .stat-mini-card.sans-badge { border-color: #6c757d; }
        
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .search-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
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
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
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
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .form-card h2 {
            color: #0e1f4c;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
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
            margin-right: 10px;
            margin-bottom: 5px;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
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
        
        .badge-info-card {
            background: #e8f4fd;
            border-left: 4px solid #0e1f4c;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .badge-details {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
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
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>👥 Gestion des Utilisateurs</h1>
            <p>Créez et gérez les élèves, professeurs et leurs badges</p>
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
            <div class="stat-mini-card eleves">
                <h3><?= $stats['total_eleves'] ?? 0 ?></h3>
                <p>Élèves</p>
            </div>
            <div class="stat-mini-card profs">
                <h3><?= $stats['total_profs'] ?? 0 ?></h3>
                <p>Professeurs</p>
            </div>
            <div class="stat-mini-card badges-actifs">
                <h3><?= $stats['badges_actifs'] ?? 0 ?></h3>
                <p>Badges actifs</p>
            </div>
            <div class="stat-mini-card badges-inactifs">
                <h3><?= $stats['badges_inactifs'] ?? 0 ?></h3>
                <p>Badges inactifs</p>
            </div>
            <div class="stat-mini-card sans-badge">
                <h3><?= $stats['sans_badge'] ?? 0 ?></h3>
                <p>Sans badge</p>
            </div>
        </div>

        <!-- ONGLETS -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('liste')">📋 Liste des utilisateurs</button>
            <button class="tab" onclick="showTab('ajout_eleve')">➕ Ajouter un élève</button>
            <button class="tab" onclick="showTab('ajout_prof')">👨‍🏫 Ajouter un professeur</button>
        </div>

        <!-- ONGLET 1: LISTE DES UTILISATEURS -->
        <div id="tab-liste" class="tab-content active">
            <div class="search-container">
                <form method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Rechercher un utilisateur par nom, prénom ou identifiant...">
                    <button type="submit" class="search-btn">
                        🔍 Rechercher
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="?search=" class="btn btn-warning">✖ Effacer</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-container">
                <?php if (empty($utilisateurs)): ?>
                    <p style="text-align: center; color: #666; padding: 30px;">
                        Aucun utilisateur trouvé.
                    </p>
                <?php else: ?>
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
                            <?php foreach ($utilisateurs as $user): ?>
                                <?php 
                                $badge_actif = ($user['etat_badge'] == 'actif');
                                $badge_expire = $user['date_expiration'] ? (strtotime($user['date_expiration']) < time()) : false;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($user['prenom']) ?> <?= htmlspecialchars($user['nom']) ?></strong>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($user['identifiant']) ?></code>
                                    </td>
                                    <td>
                                        <?php if ($user['id_statut'] == 1): ?>
                                            <span class="badge badge-admin">Admin</span>
                                        <?php elseif ($user['id_statut'] == 3): ?>
                                            <span class="badge badge-prof">Professeur</span>
                                        <?php elseif ($user['id_statut'] == 4): ?>
                                            <span class="badge badge-eleve">Élève</span>
                                        <?php else: ?>
                                            <span class="badge"><?= htmlspecialchars($user['statut']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $user['classe'] ? htmlspecialchars($user['classe']) : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($user['id_badge']): ?>
                                            <?php if ($badge_actif && !$badge_expire): ?>
                                                <span class="badge badge-actif">✅ Actif</span>
                                            <?php elseif ($badge_expire): ?>
                                                <span class="badge badge-expire">⚠️ Expiré</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactif">❌ Inactif</span>
                                            <?php endif; ?>
                                            <div class="badge-details">
                                                Émis: <?= $user['date_emission'] ? date('d/m/Y', strtotime($user['date_emission'])) : '—' ?><br>
                                                Expire: <?= $user['date_expiration'] ? date('d/m/Y', strtotime($user['date_expiration'])) : '—' ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #dc3545; font-weight: bold;">❌ Pas de badge</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="voirUtilisateur(<?= $user['id_utilisateur'] ?>)">
                                                👁️ Voir
                                            </button>
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="editerUtilisateur(<?= $user['id_utilisateur'] ?>)">
                                                ✏️ Éditer
                                            </button>
                                            <?php if (!$user['id_badge']): ?>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="creerBadgeModal(<?= $user['id_utilisateur'] ?>)">
                                                    🪪 Créer badge
                                                </button>
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

        <!-- ONGLET 2: AJOUTER UN ÉLÈVE -->
        <div id="tab-ajout_eleve" class="tab-content">
            <div class="form-card">
                <h2>➕ Ajouter un nouvel élève</h2>
                
                <div class="badge-info-card">
                    <strong>ℹ️ Information :</strong> Un badge actif sera automatiquement créé pour cet élève avec une validité de 2 ans.
                </div>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" class="form-control" 
                                   placeholder="Ex: Dupont" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="prenom">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" class="form-control" 
                                   placeholder="Ex: Jean" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="identifiant">Identifiant de connexion *</label>
                            <input type="text" id="identifiant" name="identifiant" class="form-control" 
                                   placeholder="Ex: jean.dupont" required>
                            <small style="color: #666;">Utilisé pour se connecter au système</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="mot_de_pass">Mot de passe *</label>
                            <input type="password" id="mot_de_pass" name="mot_de_pass" class="form-control" 
                                   placeholder="●●●●●●●●" required minlength="6">
                            <small style="color: #666;">Minimum 6 caractères</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_classe">Classe *</label>
                        <select id="id_classe" name="id_classe" class="form-control" required>
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?= $classe['id_classe'] ?>">
                                    <?= htmlspecialchars($classe['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="ajouter_eleve" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
                        ➕ Ajouter l'élève et créer son badge
                    </button>
                </form>
            </div>
        </div>

        <!-- ONGLET 3: AJOUTER UN PROFESSEUR -->
        <div id="tab-ajout_prof" class="tab-content">
            <div class="form-card">
                <h2>👨‍🏫 Ajouter un nouveau professeur</h2>
                
                <div class="badge-info-card">
                    <strong>ℹ️ Information :</strong> Un badge actif sera automatiquement créé pour ce professeur avec une validité de 2 ans.
                </div>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom_prof">Nom *</label>
                            <input type="text" id="nom_prof" name="nom_prof" class="form-control" 
                                   placeholder="Ex: Martin" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="prenom_prof">Prénom *</label>
                            <input type="text" id="prenom_prof" name="prenom_prof" class="form-control" 
                                   placeholder="Ex: Sophie" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="identifiant_prof">Identifiant de connexion *</label>
                            <input type="text" id="identifiant_prof" name="identifiant_prof" class="form-control" 
                                   placeholder="Ex: sophie.martin" required>
                            <small style="color: #666;">Utilisé pour se connecter au système</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="mot_de_pass_prof">Mot de passe *</label>
                            <input type="password" id="mot_de_pass_prof" name="mot_de_pass_prof" class="form-control" 
                                   placeholder="●●●●●●●●" required minlength="6">
                            <small style="color: #666;">Minimum 6 caractères</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="ajouter_professeur" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
                        👨‍🏫 Ajouter le professeur et créer son badge
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL POUR CRÉER UN BADGE -->
    <div id="modalCreerBadge" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #0e1f4c;">🪪 Créer un badge</h3>
                <button class="modal-close" onclick="fermerModal()">×</button>
            </div>
            <div class="badge-info-card">
                <strong>ℹ️ Information :</strong> Le badge sera créé avec une validité de 2 ans à partir d'aujourd'hui.
            </div>
            <form method="POST" id="formCreerBadge">
                <input type="hidden" name="id_utilisateur" id="modalUserId">
                <div style="text-align: center; margin: 20px 0;">
                    <p>Confirmer la création d'un badge pour cet utilisateur ?</p>
                    <p><strong>Validité :</strong> <?= date('d/m/Y') ?> → <?= date('d/m/Y', strtotime('+2 years')) ?></p>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="btn btn-warning" onclick="fermerModal()">
                        ❌ Annuler
                    </button>
                    <button type="submit" name="creer_badge" class="btn btn-success">
                        ✅ Créer le badge
                    </button>
                </div>
            </form>
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
        
        function creerBadgeModal(userId) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalCreerBadge').style.display = 'flex';
        }
        
        function fermerModal() {
            document.getElementById('modalCreerBadge').style.display = 'none';
        }
        
        function voirUtilisateur(userId) {
            alert('Voir les détails de l\'utilisateur ID: ' + userId);
            // Ici tu pourras ajouter une fonction pour afficher plus de détails
        }
        
        function editerUtilisateur(userId) {
            alert('Éditer l\'utilisateur ID: ' + userId);
            // Ici tu pourras ajouter une fonction d'édition
        }
        
        // Fermer le modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('modalCreerBadge');
            if (event.target == modal) {
                fermerModal();
            }
        }
    </script>
</body>
</html>