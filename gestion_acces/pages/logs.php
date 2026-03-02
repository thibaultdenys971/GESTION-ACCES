<?php
// pages/logs.php
session_start();

// VÉRIFICATION ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}


include '../includes/db.php';

// 🔥 AJOUTER ICI - Log de consultation des logs
addLog(
    $conn,
    'info',
    'PAGE_VUE',
    $_SESSION['user_id'],
    "Consultation des logs système",
    "Page: logs.php"
);

// Dans la purge des logs
if (isset($_POST['purge_logs'])) {
    // 🔥 AJOUTER ICI - Log de purge
    addLog(
        $conn,
        'warning',
        'PURGE_LOGS',
        $_SESSION['user_id'],
        "Purge des logs effectuée",
        "Jours supprimés: $jours"
    );
}

$message = '';
$errorMessage = '';
$logs = [];

// Vérifier si la table logs existe, sinon la créer
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'logs_systeme'");
    if ($checkTable->rowCount() == 0) {
        $conn->exec("
            CREATE TABLE logs_systeme (
                id_log INT AUTO_INCREMENT PRIMARY KEY,
                date_log DATETIME DEFAULT CURRENT_TIMESTAMP,
                niveau ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
                type_action VARCHAR(50),
                id_utilisateur INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                description TEXT,
                details TEXT,
                INDEX idx_date (date_log),
                INDEX idx_niveau (niveau),
                INDEX idx_type (type_action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $message = "✅ Table des logs créée avec succès.";
    }
} catch (PDOException $e) {
    $errorMessage = "Erreur création table logs : " . $e->getMessage();
}

// Paramètres de filtrage
$filters = [
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
    'niveau' => $_GET['niveau'] ?? '',
    'type_action' => $_GET['type_action'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Construire la requête avec filtres
$query = "SELECT l.*, u.nom, u.prenom, u.identifiant, s.statut 
          FROM logs_systeme l
          LEFT JOIN utilisateur u ON l.id_utilisateur = u.id_utilisateur
          LEFT JOIN statut s ON u.id_statut = s.id_statut
          WHERE 1=1";

$params = [];

// Filtre par date
if (!empty($filters['date_debut'])) {
    $query .= " AND DATE(l.date_log) >= ?";
    $params[] = $filters['date_debut'];
}
if (!empty($filters['date_fin'])) {
    $query .= " AND DATE(l.date_log) <= ?";
    $params[] = $filters['date_fin'];
}

// Filtre par niveau
if (!empty($filters['niveau'])) {
    $query .= " AND l.niveau = ?";
    $params[] = $filters['niveau'];
}

// Filtre par type d'action
if (!empty($filters['type_action'])) {
    $query .= " AND l.type_action = ?";
    $params[] = $filters['type_action'];
}

// Filtre par recherche
if (!empty($filters['search'])) {
    $query .= " AND (l.description LIKE ? OR l.details LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Ordre et limite
$query .= " ORDER BY l.date_log DESC LIMIT 1000";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des logs : " . $e->getMessage();
}

// Récupérer les statistiques
$stats = [
    'total' => 0,
    'par_niveau' => [],
    'par_type' => [],
    'par_jour' => []
];

try {
    // Total logs
    $statsStmt = $conn->query("SELECT COUNT(*) as total FROM logs_systeme");
    $stats['total'] = $statsStmt->fetchColumn();

    // Par niveau
    $niveauStmt = $conn->query("
        SELECT niveau, COUNT(*) as count 
        FROM logs_systeme 
        GROUP BY niveau 
        ORDER BY FIELD(niveau, 'critical', 'error', 'warning', 'info')
    ");
    $stats['par_niveau'] = $niveauStmt->fetchAll(PDO::FETCH_ASSOC);

    // Par type d'action (top 10)
    $typeStmt = $conn->query("
        SELECT type_action, COUNT(*) as count 
        FROM logs_systeme 
        WHERE type_action IS NOT NULL 
        GROUP BY type_action 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stats['par_type'] = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Par jour (7 derniers jours)
    $jourStmt = $conn->prepare("
        SELECT DATE(date_log) as jour, COUNT(*) as count 
        FROM logs_systeme 
        WHERE date_log >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date_log) 
        ORDER BY jour DESC
    ");
    $jourStmt->execute();
    $stats['par_jour'] = $jourStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorer les erreurs de statistiques si la table est vide
}

// Récupérer les types d'actions uniques pour le filtre
$typesActions = [];
try {
    $typeActionStmt = $conn->query("
        SELECT DISTINCT type_action 
        FROM logs_systeme 
        WHERE type_action IS NOT NULL 
        ORDER BY type_action
    ");
    $typesActions = $typeActionStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Table peut être vide
}



// Action : Vider les logs anciens
if (isset($_POST['purge_logs'])) {
    $jours = intval($_POST['jours_purge']);
    if ($jours > 0) {
        try {
            $purgeStmt = $conn->prepare("
                DELETE FROM logs_systeme 
                WHERE date_log < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $deleted = $purgeStmt->execute([$jours]);

            // Logger l'action
            addLog(
                $conn,
                'info',
                'PURGE_LOGS',
                $_SESSION['user_id'],
                "Purge des logs de plus de $jours jours",
                "Nombre de lignes supprimées : " . $purgeStmt->rowCount()
            );

            $message = "✅ Logs purgés avec succès (plus de $jours jours).";
        } catch (PDOException $e) {
            $errorMessage = "Erreur lors de la purge : " . $e->getMessage();
        }
    }
}

// Action : Exporter les logs
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d_H-i-s') . '.csv"');

    $output = fopen('php://output', 'w');

    // En-têtes CSV
    fputcsv($output, [
        'ID',
        'Date/Heure',
        'Niveau',
        'Type Action',
        'Utilisateur',
        'Statut',
        'IP',
        'Description',
        'Détails'
    ]);

    // Données
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id_log'],
            $log['date_log'],
            $log['niveau'],
            $log['type_action'],
            $log['prenom'] . ' ' . $log['nom'] . ' (' . $log['identifiant'] . ')',
            $log['statut'],
            $log['ip_address'],
            $log['description'],
            $log['details']
        ]);
    }

    fclose($output);
    exit();
}

// Initialiser des logs de démonstration si la table est vide
if (empty($logs) && $stats['total'] == 0) {
    // Ajouter des logs de démonstration
    $demo_logs = [
        ['info', 'CONNEXION', $_SESSION['user_id'], 'Connexion administrateur réussie', 'Session démarrée'],
        ['info', 'PAGE_VUE', $_SESSION['user_id'], 'Accès page administration', 'Page : admin.php'],
        ['warning', 'TENTATIVE_ECHOUEE', null, 'Tentative de connexion échouée', 'IP: 192.168.1.100, Identifiant: test'],
        ['error', 'BADGE_INVALIDE', null, 'Badge expiré détecté', 'Badge ID: 123, Utilisateur: 8'],
        ['critical', 'ACCES_NON_AUTORISE', null, 'Tentative accès non autorisé', 'Page: /admin/logs.php, IP: 10.0.0.50'],
        ['info', 'CREATION_UTILISATEUR', $_SESSION['user_id'], 'Nouvel utilisateur créé', 'Type: Élève, Classe: CIEL'],
        ['info', 'MODIFICATION_EDT', $_SESSION['user_id'], 'Emploi du temps modifié', 'Classe: CIEL, Cours: Math'],
        ['warning', 'BADGE_PERDU', null, 'Signalement badge perdu', 'Utilisateur: Alice Dupont, Badge: 456'],
        ['info', 'APPEL_VALIDE', $_SESSION['user_id'], 'Appel validé', 'Cours: Français, Classe: GPME'],
        ['error', 'DOUBLON_BADGE', null, 'Détection doublon badge', 'Badge ID: 789 déjà assigné']
    ];

    foreach ($demo_logs as $demo) {
        addLog($conn, $demo[0], $demo[1], $demo[2], $demo[3], $demo[4]);
    }

    $message = "✅ Logs de démonstration initialisés. Rafraîchissez la page.";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal des logs - Administration</title>
    <style>
        /* VARIABLES */
        :root {
            --primary: #0e1f4c;
            --primary-light: #1a3a7a;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #dee2e6;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* RESET ET BASES */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        /* HEADER */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .page-header h1 {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 14px;
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
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            border-top: 4px solid;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total {
            border-color: var(--primary);
        }

        .stat-card.info {
            border-color: var(--info);
        }

        .stat-card.warning {
            border-color: var(--warning);
        }

        .stat-card.error {
            border-color: var(--danger);
        }

        .stat-card.critical {
            border-color: #6f42c1;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* FILTRES */
        .filters-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .filters-header h3 {
            color: var(--primary);
            font-size: 18px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .form-control {
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(14, 31, 76, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
        }

        /* BOUTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--info);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: #212529;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* TABLEAU DES LOGS */
        .logs-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .logs-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logs-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }

        .logs-table-container {
            overflow-x: auto;
            max-height: 600px;
            position: relative;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .logs-table th {
            background: #f8f9fa;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .logs-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .logs-table tr:hover {
            background: #f8f9fa;
        }

        /* BADGES NIVEAU */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-critical {
            background: #6f42c1;
            color: white;
        }

        /* DÉTAILS LOG */
        .log-details {
            max-width: 300px;
            word-wrap: break-word;
        }

        .log-user {
            font-weight: 600;
            color: var(--primary);
        }

        .log-ip {
            font-family: monospace;
            font-size: 11px;
            color: #666;
            background: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: white;
            border-top: 1px solid var(--border);
        }

        .page-btn {
            padding: 8px 12px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .page-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* MESSAGES */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid;
            animation: fadeIn 0.5s ease-out;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: var(--success);
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: var(--danger);
        }

        /* MODAL POUR LES DÉTAILS */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 25px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .modal-header h3 {
            color: var(--primary);
            font-size: 20px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }

        /* GRAPHIQUES */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: var(--primary);
            font-weight: 600;
        }

        .chart-container {
            height: 200px;
            position: relative;
        }

        /* ANIMATIONS */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .logs-table {
                font-size: 12px;
            }

            .logs-table th,
            .logs-table td {
                padding: 8px;
            }
        }

        /* LOADER */
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>

    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header fade-in">
            <h1>📊 Journal des logs système</h1>
            <p>Surveillance et historisation des accès, incidents et activités système</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success fade-in"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger fade-in"><?= $errorMessage ?></div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid fade-in">
            <div class="stat-card total">
                <div class="stat-number"><?= number_format($stats['total']) ?></div>
                <div class="stat-label">Total logs</div>
            </div>

            <?php
            $niveaux = ['info', 'warning', 'error', 'critical'];
            foreach ($niveaux as $niveau):
                $count = 0;
                foreach ($stats['par_niveau'] as $stat) {
                    if ($stat['niveau'] == $niveau) {
                        $count = $stat['count'];
                        break;
                    }
                }
            ?>
                <div class="stat-card <?= $niveau ?>">
                    <div class="stat-number"><?= number_format($count) ?></div>
                    <div class="stat-label"><?= strtoupper($niveau) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Graphiques -->
        <div class="charts-grid fade-in">
            <div class="chart-card">
                <div class="chart-header">
                    📈 Activité par jour (7 derniers jours)
                </div>
                <div class="chart-container">
                    <canvas id="chartActivite"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    🏷️ Top 10 types d'actions
                </div>
                <div class="chart-container">
                    <canvas id="chartTypes"></canvas>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters-card fade-in">
            <div class="filters-header">
                <span>🔍</span>
                <h3>Filtres de recherche</h3>
            </div>

            <form method="GET" id="filterForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Date début</label>
                        <input type="date" name="date_debut" class="form-control"
                            value="<?= htmlspecialchars($filters['date_debut']) ?>">
                    </div>

                    <div class="filter-group">
                        <label>Date fin</label>
                        <input type="date" name="date_fin" class="form-control"
                            value="<?= htmlspecialchars($filters['date_fin']) ?>">
                    </div>

                    <div class="filter-group">
                        <label>Niveau</label>
                        <select name="niveau" class="form-control">
                            <option value="">Tous les niveaux</option>
                            <option value="info" <?= $filters['niveau'] == 'info' ? 'selected' : '' ?>>Info</option>
                            <option value="warning" <?= $filters['niveau'] == 'warning' ? 'selected' : '' ?>>Warning</option>
                            <option value="error" <?= $filters['niveau'] == 'error' ? 'selected' : '' ?>>Error</option>
                            <option value="critical" <?= $filters['niveau'] == 'critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Type d'action</label>
                        <select name="type_action" class="form-control">
                            <option value="">Tous les types</option>
                            <?php foreach ($typesActions as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"
                                    <?= $filters['type_action'] == $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Recherche texte</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="Description, utilisateur, détails..."
                            value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        🔍 Appliquer les filtres
                    </button>
                    <a href="logs.php" class="btn btn-secondary">
                        ❌ Réinitialiser
                    </a>
                    <a href="logs.php?export=1" class="btn btn-primary">
                        📥 Exporter CSV
                    </a>
                </div>
            </form>
        </div>

        <!-- Actions d'administration -->
        <div class="filters-card fade-in">
            <div class="filters-header">
                <span>⚙️</span>
                <h3>Actions d'administration</h3>
            </div>

            <form method="POST" onsubmit="return confirm('Vider les logs de plus de X jours ?')">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Purger les logs anciens :
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="jours_purge" class="form-control" style="width: 150px;">
                                <option value="30">30 jours</option>
                                <option value="90">90 jours</option>
                                <option value="180">180 jours</option>
                                <option value="365">1 an</option>
                            </select>
                            <button type="submit" name="purge_logs" class="btn btn-danger">
                                🗑️ Purger
                            </button>
                        </div>
                    </div>

                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Tester le système de logs :
                        </label>
                        <button type="button" onclick="testLogSystem()" class="btn btn-warning">
                            🧪 Tester un log
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tableau des logs -->
        <div class="logs-container fade-in">
            <div class="logs-header">
                <h3>
                    📋 Liste des logs
                    <span style="font-size: 14px; font-weight: normal; opacity: 0.9;">
                        (<?= count($logs) ?> résultat<?= count($logs) > 1 ? 's' : '' ?>)
                    </span>
                </h3>
                <div style="font-size: 12px; opacity: 0.9;">
                    Dernière mise à jour : <?= date('H:i:s') ?>
                </div>
            </div>

            <div class="logs-table-container">
                <?php if (empty($logs)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;">📭</div>
                        <p style="font-size: 18px; margin-bottom: 10px;">Aucun log trouvé</p>
                        <p>Les filtres sélectionnés ne retournent aucun résultat.</p>
                    </div>
                <?php else: ?>
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th width="150">Date/Heure</th>
                                <th width="80">Niveau</th>
                                <th width="120">Type</th>
                                <th width="150">Utilisateur</th>
                                <th width="120">IP</th>
                                <th>Description</th>
                                <th width="80">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <div style="font-size: 12px; color: #666;">
                                            <?= date('d/m/Y', strtotime($log['date_log'])) ?>
                                        </div>
                                        <div style="font-family: monospace; font-size: 11px;">
                                            <?= date('H:i:s', strtotime($log['date_log'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $log['niveau'] ?>">
                                            <?= strtoupper($log['niveau']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="font-size: 11px; background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">
                                            <?= htmlspecialchars($log['type_action'] ?? 'N/A') ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ($log['nom']): ?>
                                            <div class="log-user">
                                                <?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?>
                                            </div>
                                            <div style="font-size: 11px; color: #666;">
                                                <?= htmlspecialchars($log['identifiant']) ?>
                                            </div>
                                            <div style="font-size: 10px; color: #28a745;">
                                                <?= htmlspecialchars($log['statut']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #666; font-style: italic;">Système</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="log-ip">
                                            <?= htmlspecialchars($log['ip_address']) ?>
                                        </span>
                                    </td>
                                    <td class="log-details">
                                        <div style="font-weight: 500; margin-bottom: 5px;">
                                            <?= htmlspecialchars($log['description']) ?>
                                        </div>
                                        <?php if (!empty($log['details'])): ?>
                                            <div style="font-size: 11px; color: #666;">
                                                <?= substr(htmlspecialchars($log['details']), 0, 100) ?>
                                                <?php if (strlen($log['details']) > 100): ?>...<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="showLogDetails(<?= $log['id_log'] ?>)"
                                            class="btn btn-sm btn-primary"
                                            style="padding: 4px 8px; font-size: 11px;">
                                            👁️ Voir
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if (!empty($logs)): ?>
            <div class="pagination">
                <span style="color: #666; font-size: 14px;">
                    Affichage des <?= count($logs) ?> logs les plus récents
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal pour afficher les détails -->
    <div id="logModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📄 Détails du log</h3>
                <button class="close-modal" onclick="closeLogModal()">&times;</button>
            </div>
            <div class="modal-body" id="logDetails">
                Chargement...
            </div>
        </div>
    </div>

    <script>
        // Initialiser les graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique activité par jour
            const ctxActivite = document.getElementById('chartActivite').getContext('2d');
            const activiteData = {
                labels: [
                    <?php
                    $jours_labels = [];
                    foreach ($stats['par_jour'] as $jour) {
                        $jours_labels[] = "'" . date('d/m', strtotime($jour['jour'])) . "'";
                    }
                    echo implode(', ', array_reverse($jours_labels));
                    ?>
                ].reverse(),
                datasets: [{
                    label: 'Nombre de logs',
                    data: [
                        <?php
                        $jours_counts = [];
                        foreach ($stats['par_jour'] as $jour) {
                            $jours_counts[] = $jour['count'];
                        }
                        echo implode(', ', array_reverse($jours_counts));
                        ?>
                    ].reverse(),
                    backgroundColor: 'rgba(14, 31, 76, 0.2)',
                    borderColor: 'rgba(14, 31, 76, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            };

            new Chart(ctxActivite, {
                type: 'line',
                data: activiteData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Graphique types d'actions
            const ctxTypes = document.getElementById('chartTypes').getContext('2d');
            const typesData = {
                labels: [
                    <?php
                    $types_labels = [];
                    $types_counts = [];
                    foreach ($stats['par_type'] as $type) {
                        $types_labels[] = "'" . htmlspecialchars($type['type_action']) . "'";
                        $types_counts[] = $type['count'];
                    }
                    echo implode(', ', $types_labels);
                    ?>
                ],
                datasets: [{
                    data: [<?= implode(', ', $types_counts) ?>],
                    backgroundColor: [
                        '#0e1f4c', '#1a3a7a', '#28a745', '#ffc107',
                        '#dc3545', '#17a2b8', '#6f42c1', '#fd7e14',
                        '#20c997', '#e83e8c'
                    ]
                }]
            };

            new Chart(ctxTypes, {
                type: 'doughnut',
                data: typesData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // Auto-refresh toutes les 60 secondes
            setInterval(() => {
                const refreshBtn = document.querySelector('.filters-card .btn-primary');
                if (refreshBtn) {
                    refreshBtn.innerHTML = '🔄 Actualisation...';
                    refreshBtn.disabled = true;

                    setTimeout(() => {
                        document.getElementById('filterForm').submit();
                    }, 1000);
                }
            }, 60000);
        });

        // Afficher les détails d'un log
        function showLogDetails(logId) {
            fetch(`ajax_get_log_details.php?id=${logId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('logModal');
                        const detailsDiv = document.getElementById('logDetails');

                        detailsDiv.innerHTML = `
                            ID: ${data.log.id_log}
                            Date: ${data.log.date_log}
                            Niveau: ${data.log.niveau.toUpperCase()}
                            Type: ${data.log.type_action}
                            
                            === UTILISATEUR ===
                            ${data.log.nom ? data.log.prenom + ' ' + data.log.nom : 'Système'}
                            ${data.log.identifiant ? 'Identifiant: ' + data.log.identifiant : ''}
                            ${data.log.statut ? 'Statut: ' + data.log.statut : ''}
                            
                            === INFORMATIONS SYSTÈME ===
                            IP: ${data.log.ip_address}
                            User-Agent: ${data.log.user_agent}
                            
                            === DESCRIPTION ===
                            ${data.log.description}
                            
                            === DÉTAILS ===
                            ${data.log.details || 'Aucun détail supplémentaire'}
                        `;

                        modal.style.display = 'flex';
                    } else {
                        alert('Erreur: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de chargement des détails');
                });
        }

        // Fermer le modal
        function closeLogModal() {
            document.getElementById('logModal').style.display = 'none';
        }

        // Tester le système de logs
        function testLogSystem() {
            if (confirm('Générer un log de test ?')) {
                fetch('ajax_test_log.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ Log de test généré avec succès !');
                            location.reload();
                        } else {
                            alert('❌ Erreur: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur de communication');
                    });
            }
        }

        // Fermer le modal avec ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLogModal();
            }
        });

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('logModal').addEventListener('click', (e) => {
            if (e.target.id === 'logModal') {
                closeLogModal();
            }
        });

        // Export automatique en arrière-plan
        function autoExport() {
            if (confirm('Exporter les logs actuels en CSV ?')) {
                window.open('logs.php?export=1', '_blank');
            }
        }

        // Raccourcis clavier
        document.addEventListener('keydown', (e) => {
            // Ctrl+E pour exporter
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                autoExport();
            }

            // Ctrl+F pour focus sur la recherche
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
        });
    </script>
</body>

</html>