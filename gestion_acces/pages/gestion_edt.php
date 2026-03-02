<?php
// pages/gestion_edt.php
session_start();

// VÉRIFICATION ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';


// 🔥 AJOUTER ICI - Log de visite de la gestion EDT
addLog($conn, 'info', 'PAGE_VUE', $_SESSION['user_id'], 
       "Accès à la gestion EDT", "Page: gestion_edt.php");

// Dans l'ajout d'un cours
if (isset($_POST['add_cours'])) {
    // 🔥 AJOUTER ICI - Log d'ajout de cours
    addLog($conn, 'info', 'AJOUT_COURS', $_SESSION['user_id'], 
           "Nouveau cours ajouté", 
           "Classe: $id_classe, Cours: $id_cours, Jour: $id_jours");
}

// Dans l'assignation d'une salle
if (isset($_POST['assign_salle_edt'])) {
    // 🔥 AJOUTER ICI - Log d'assignation de salle
    addLog($conn, 'info', 'ASSIGNATION_SALLE', $_SESSION['user_id'], 
           "Salle assignée à un cours", 
           "ID EDT: $id_edt, ID Salle: $id_salle"); }

$message = '';
$errorMessage = '';

// ==================== GESTION DES SALLES ====================
// Ajouter une salle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_salle'])) {
    $nom_salle = trim($_POST['nom_salle']);
    $capacite = intval($_POST['capacite']);
    $type_salle = $_POST['type_salle'];
    $batiment = trim($_POST['batiment']);
    $equipements = trim($_POST['equipements']);
    
    if (!empty($nom_salle)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO salle (salle, capacite, type_salle, batiment, equipements)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom_salle, $capacite, $type_salle, $batiment, $equipements]);
            $message = "✅ Salle ajoutée avec succès !";
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
}

// Supprimer une salle
if (isset($_GET['delete_salle'])) {
    $id_salle = $_GET['delete_salle'];
    
    try {
        // Vérifier si la salle est utilisée dans l'emploi du temps
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM salle_edt WHERE id_salle = ?");
        $checkStmt->execute([$id_salle]);
        $count = $checkStmt->fetchColumn();
        
        if ($count == 0) {
            $stmt = $conn->prepare("DELETE FROM salle WHERE id_salle = ?");
            $stmt->execute([$id_salle]);
            $message = "✅ Salle supprimée avec succès !";
        } else {
            $errorMessage = "❌ Impossible de supprimer : cette salle est utilisée dans l'emploi du temps.";
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur : " . $e->getMessage();
    }
}

// ==================== GESTION DES EDT ====================
// Ajouter un cours
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_cours'])) {
    $id_classe = $_POST['id_classe'];
    $id_cours = $_POST['id_cours'];
    $id_jours = $_POST['id_jours'];
    $heure_deb = $_POST['heure_deb'];
    $heure_fin = $_POST['heure_fin'];
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO emploie_du_temps (id_classe, id_cours, id_jours, heure_deb, heure_fin)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_classe, $id_cours, $id_jours, $heure_deb, $heure_fin]);
        $message = "✅ Cours ajouté avec succès !";
    } catch (PDOException $e) {
        $errorMessage = "Erreur : " . $e->getMessage();
    }
}

// Assigner une salle à un cours
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_salle_edt'])) {
    $id_edt = $_POST['id_edt_assign'];
    $id_salle = $_POST['id_salle_assign'];
    
    try {
        // Vérifier si la salle est déjà assignée à ce créneau
        $checkStmt = $conn->prepare("
            SELECT * FROM salle_edt 
            WHERE id_edt = ? AND id_salle = ?
        ");
        $checkStmt->execute([$id_edt, $id_salle]);
        
        if ($checkStmt->rowCount() == 0) {
            $assignStmt = $conn->prepare("
                INSERT INTO salle_edt (id_edt, id_salle)
                VALUES (?, ?)
            ");
            $assignStmt->execute([$id_edt, $id_salle]);
            $message = "✅ Salle assignée au cours avec succès !";
        } else {
            $message = "ℹ️ Cette salle est déjà assignée à ce cours.";
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur : " . $e->getMessage();
    }
}

// Retirer une salle d'un cours
if (isset($_GET['remove_salle_edt'])) {
    $id_edt = $_GET['id_edt'];
    $id_salle = $_GET['id_salle'];
    
    try {
        $removeStmt = $conn->prepare("
            DELETE FROM salle_edt 
            WHERE id_edt = ? AND id_salle = ?
        ");
        $removeStmt->execute([$id_edt, $id_salle]);
        $message = "✅ Salle retirée du cours avec succès !";
    } catch (PDOException $e) {
        $errorMessage = "Erreur : " . $e->getMessage();
    }
}

// Supprimer un cours
if (isset($_GET['delete_cours'])) {
    $id_edt = $_GET['delete_cours'];
    
    try {
        // Supprimer d'abord les assignations de salles
        $deleteSallesStmt = $conn->prepare("DELETE FROM salle_edt WHERE id_edt = ?");
        $deleteSallesStmt->execute([$id_edt]);
        
        // Supprimer le cours
        $stmt = $conn->prepare("DELETE FROM emploie_du_temps WHERE id_edt = ?");
        $stmt->execute([$id_edt]);
        $message = "✅ Cours supprimé avec succès !";
    } catch (PDOException $e) {
        $errorMessage = "Erreur : " . $e->getMessage();
    }
}

// ==================== RÉCUPÉRATION DES DONNÉES ====================
// Récupérer toutes les salles
$sallesStmt = $conn->query("SELECT * FROM salle ORDER BY salle");
$salles = $sallesStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les emplois du temps avec leurs salles
$edtStmt = $conn->prepare("
    SELECT edt.*, j.Jours, c.cours, cl.libelle as classe_nom,
           GROUP_CONCAT(DISTINCT sa.salle SEPARATOR ', ') as salles_assignees,
           GROUP_CONCAT(DISTINCT sa.id_salle SEPARATOR ',') as id_salles
    FROM emploie_du_temps edt
    JOIN jours j ON edt.id_jours = j.id_jours
    JOIN cours c ON edt.id_cours = c.id_cours
    JOIN classe cl ON edt.id_classe = cl.id_classe
    LEFT JOIN salle_edt se ON edt.id_edt = se.id_edt
    LEFT JOIN salle sa ON se.id_salle = sa.id_salle
    GROUP BY edt.id_edt
    ORDER BY cl.libelle, j.id_jours, edt.heure_deb
");
$edtStmt->execute();
$all_edt = $edtStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les classes
$classesStmt = $conn->query("SELECT id_classe, libelle FROM classe ORDER BY libelle");
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les cours
$coursStmt = $conn->query("SELECT id_cours, cours FROM cours ORDER BY cours");
$cours = $coursStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les jours
$joursStmt = $conn->query("SELECT id_jours, Jours FROM jours ORDER BY id_jours");
$jours = $joursStmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total_cours' => count($all_edt),
    'total_salles' => count($salles),
    'cours_avec_salle' => 0,
    'cours_sans_salle' => 0
];

foreach ($all_edt as $edt) {
    if (!empty($edt['salles_assignees'])) {
        $stats['cours_avec_salle']++;
    } else {
        $stats['cours_sans_salle']++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Emploi du temps & Salles - Admin</title>
    <style>
        /* VARIABLES */
        :root {
            --primary: #0e1f4c;
            --primary-light: #1a3a7a;
            --secondary: #6f42c1;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #dee2e6;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
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
        
        .stat-card.total { border-color: var(--primary); }
        .stat-card.salles { border-color: var(--secondary); }
        .stat-card.avec-salle { border-color: var(--success); }
        .stat-card.sans-salle { border-color: var(--danger); }
        
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
        
        /* CARTES DE GESTION */
        .management-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .card-header h2 {
            color: var(--primary);
            font-size: 20px;
        }
        
        .card-icon {
            font-size: 24px;
        }
        
        /* FORMULAIRES */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(14, 31, 76, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
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
            background: var(--secondary);
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* TABLES */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .data-table th {
            background: var(--primary);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .data-table tr:hover {
            background: var(--light);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        /* BADGES */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-salle {
            background: var(--secondary);
            color: white;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .badge-success {
            background: var(--success);
            color: white;
        }
        
        .badge-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .badge-danger {
            background: var(--danger);
            color: white;
        }
        
        /* MESSAGES */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid;
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
        
        /* ONGLETS */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin-bottom: 25px;
            overflow-x: auto;
        }
        
        .tab {
            padding: 12px 25px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: var(--primary);
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(14, 31, 76, 0.05);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                font-size: 14px;
            }
            
            .tab {
                padding: 10px 15px;
                font-size: 14px;
            }
        }
        
        /* ANIMATIONS */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Header -->
        <div class="page-header fade-in">
            <h1>⚙️ Gestion Emploi du Temps & Salles</h1>
            <p>Administration complète des emplois du temps et assignation des salles</p>
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
                <div class="stat-number"><?= $stats['total_cours'] ?></div>
                <div class="stat-label">Cours programmés</div>
            </div>
            
            <div class="stat-card salles">
                <div class="stat-number"><?= $stats['total_salles'] ?></div>
                <div class="stat-label">Salles disponibles</div>
            </div>
            
            <div class="stat-card avec-salle">
                <div class="stat-number"><?= $stats['cours_avec_salle'] ?></div>
                <div class="stat-label">Cours avec salle</div>
            </div>
            
            <div class="stat-card sans-salle">
                <div class="stat-number"><?= $stats['cours_sans_salle'] ?></div>
                <div class="stat-label">Cours sans salle</div>
            </div>
        </div>
        
        <!-- Onglets -->
        <div class="tabs">
            <button class="tab active" onclick="openTab('tab-salles')">🏫 Salles</button>
            <button class="tab" onclick="openTab('tab-edt')">📅 Emploi du temps</button>
            <button class="tab" onclick="openTab('tab-assignation')">🔗 Assignation salles</button>
            <button class="tab" onclick="openTab('tab-import')">📥 Import/Export</button>
        </div>
        
        <!-- Onglet 1: Gestion des salles -->
        <div id="tab-salles" class="tab-content active fade-in">
            <div class="management-card">
                <div class="card-header">
                    <span class="card-icon">➕</span>
                    <h2>Ajouter une nouvelle salle</h2>
                </div>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nom de la salle *</label>
                            <input type="text" name="nom_salle" class="form-control" required 
                                   placeholder="Ex: A101, Labo Info, Amphi 1">
                        </div>
                        
                        <div class="form-group">
                            <label>Capacité</label>
                            <input type="number" name="capacite" class="form-control" 
                                   value="30" min="1" max="500">
                        </div>
                        
                        <div class="form-group">
                            <label>Type de salle</label>
                            <select name="type_salle" class="form-control">
                                <option value="cours">Salle de cours</option>
                                <option value="laboratoire">Laboratoire</option>
                                <option value="salle_speciale">Salle spéciale</option>
                                <option value="amphitheatre">Amphithéâtre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Bâtiment</label>
                            <input type="text" name="batiment" class="form-control" 
                                   placeholder="Ex: A, B, Principal">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Équipements (séparés par des virgules)</label>
                        <input type="text" name="equipements" class="form-control" 
                               placeholder="Ex: Vidéoprojecteur, Ordinateurs, Tableau interactif">
                    </div>
                    
                    <button type="submit" name="add_salle" class="btn btn-primary">
                        ✅ Ajouter la salle
                    </button>
                </form>
            </div>
            
            <!-- Liste des salles -->
            <div class="management-card">
                <div class="card-header">
                    <span class="card-icon">🏫</span>
                    <h2>Liste des salles (<?= count($salles) ?>)</h2>
                </div>
                
                <?php if (empty($salles)): ?>
                    <p style="text-align: center; color: #666; padding: 30px;">
                        Aucune salle n'a été créée. Ajoutez-en une ci-dessus.
                    </p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Bâtiment</th>
                                    <th>Capacité</th>
                                    <th>Équipements</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salles as $salle): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($salle['salle']) ?></strong></td>
                                        <td>
                                            <?php 
                                            $type_labels = [
                                                'cours' => 'Salle cours',
                                                'laboratoire' => 'Laboratoire',
                                                'salle_speciale' => 'Spéciale',
                                                'amphitheatre' => 'Amphithéâtre'
                                            ];
                                            echo $type_labels[$salle['type_salle'] ?? 'cours'];
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($salle['batiment'] ?? '-') ?></td>
                                        <td><?= $salle['capacite'] ?? '30' ?> places</td>
                                        <td style="max-width: 200px;">
                                            <?= htmlspecialchars($salle['equipements'] ?? 'Aucun') ?>
                                        </td>
                                        <td>
                                            <a href="?delete_salle=<?= $salle['id_salle'] ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Supprimer la salle <?= htmlspecialchars($salle['salle']) ?> ?')">
                                                🗑️ Supprimer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Onglet 2: Gestion emploi du temps -->
        <div id="tab-edt" class="tab-content fade-in">
            <div class="management-card">
                <div class="card-header">
                    <span class="card-icon">➕</span>
                    <h2>Ajouter un cours à l'emploi du temps</h2>
                </div>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Classe *</label>
                            <select name="id_classe" class="form-control" required>
                                <option value="">Sélectionnez une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?= $classe['id_classe'] ?>">
                                        <?= htmlspecialchars($classe['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Cours *</label>
                            <select name="id_cours" class="form-control" required>
                                <option value="">Sélectionnez un cours</option>
                                <?php foreach ($cours as $c): ?>
                                    <option value="<?= $c['id_cours'] ?>">
                                        <?= htmlspecialchars($c['cours']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Jour *</label>
                            <select name="id_jours" class="form-control" required>
                                <option value="">Sélectionnez un jour</option>
                                <?php foreach ($jours as $j): ?>
                                    <option value="<?= $j['id_jours'] ?>">
                                        <?= ucfirst($j['Jours']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label>Heure de début *</label>
                            <input type="time" name="heure_deb" class="form-control" 
                                   value="08:00" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label>Heure de fin *</label>
                            <input type="time" name="heure_fin" class="form-control" 
                                   value="09:00" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_cours" class="btn btn-primary">
                        ✅ Ajouter le cours
                    </button>
                </form>
            </div>
            
            <!-- Liste des emplois du temps -->
            <div class="management-card">
                <div class="card-header">
                    <span class="card-icon">📅</span>
                    <h2>Emplois du temps (<?= count($all_edt) ?> cours)</h2>
                </div>
                
                <?php if (empty($all_edt)): ?>
                    <p style="text-align: center; color: #666; padding: 30px;">
                        Aucun cours programmé. Ajoutez-en un ci-dessus.
                    </p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Cours</th>
                                    <th>Jour</th>
                                    <th>Horaire</th>
                                    <th>Salles</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_edt as $edt): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($edt['classe_nom']) ?></strong></td>
                                        <td><?= htmlspecialchars($edt['cours']) ?></td>
                                        <td><?= ucfirst($edt['Jours']) ?></td>
                                        <td>
                                            <?= date('H:i', strtotime($edt['heure_deb'])) ?> - 
                                            <?= date('H:i', strtotime($edt['heure_fin'])) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($edt['salles_assignees'])): ?>
                                                <?php 
                                                $salles_array = explode(', ', $edt['salles_assignees']);
                                                foreach ($salles_array as $salle): ?>
                                                    <span class="badge badge-salle"><?= htmlspecialchars($salle) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="badge badge-warning">❌ Pas de salle</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?delete_cours=<?= $edt['id_edt'] ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Supprimer ce cours ?')">
                                                🗑️ Supprimer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Onglet 3: Assignation salles -->
        <div id="tab-assignation" class="tab-content fade-in">
            <div class="management-card">
                <div class="card-header">
                    <span class="card-icon">🔗</span>
                    <h2>Assigner une salle à un cours</h2>
                </div>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Sélectionnez un cours *</label>
                            <select name="id_edt_assign" class="form-control" required 
                                    onchange="showCoursDetails(this.value)">
                                <option value="">Choisissez un cours</option>
                                <?php foreach ($all_edt as $edt): ?>
                                    <option value="<?= $edt['id_edt'] ?>"
                                            data-details="<?= htmlspecialchars(json_encode([
                                                'classe' => $edt['classe_nom'],
                                                'cours' => $edt['cours'],
                                                'jour' => $edt['Jours'],
                                                'horaire' => date('H:i', strtotime($edt['heure_deb'])) . ' - ' . date('H:i', strtotime($edt['heure_fin']))
                                            ])) ?>">
                                        <?= htmlspecialchars($edt['classe_nom']) ?> - 
                                        <?= htmlspecialchars($edt['cours']) ?> - 
                                        <?= ucfirst($edt['Jours']) ?> 
                                        <?= date('H:i', strtotime($edt['heure_deb'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Sélectionnez une salle *</label>
                            <select name="id_salle_assign" class="form-control" required>
                                <option value="">Choisissez une salle</option>
                                <?php foreach ($salles as $salle): ?>
                                    <option value="<?= $salle['id_salle'] ?>">
                                        <?= htmlspecialchars($salle['salle']) ?> 
                                        (<?= $salle['capacite'] ?> places, 
                                        <?= $salle['type_salle'] ?? 'cours' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="cours-details" style="display: none; padding: 15px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px;">
                        <strong>Détails du cours :</strong>
                        <div id="cours-info"></div>
                    </div>
                    
                    <button type="submit" name="assign_salle_edt" class="btn btn-primary">
                        🔗 Assigner cette salle
                    </button>
                </form>
            </div>
            
            <!-- Liste des assignations -->
            <div class="management-card">
                <div class="card-header">
                    <span class="card-icon">📋</span>
                    <h2>Assignations actuelles</h2>
                </div>
                
                <?php if (empty($all_edt)): ?>
                    <p style="text-align: center; color: #666; padding: 30px;">
                        Aucune assignation de salle. Ajoutez d'abord des cours.
                    </p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Cours</th>
                                    <th>Salles assignées</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_edt as $edt): ?>
                                    <?php if (!empty($edt['id_salles'])): ?>
                                        <?php 
                                        $id_salles_array = explode(',', $edt['id_salles']);
                                        $salles_array = explode(', ', $edt['salles_assignees']);
                                        
                                        for ($i = 0; $i < count($salles_array); $i++):
                                            if (!empty($salles_array[$i]) && isset($id_salles_array[$i])):
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($edt['classe_nom']) ?></strong><br>
                                                    <?= htmlspecialchars($edt['cours']) ?> - 
                                                    <?= ucfirst($edt['Jours']) ?> 
                                                    <?= date('H:i', strtotime($edt['heure_deb'])) ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-salle"><?= htmlspecialchars($salles_array[$i]) ?></span>
                                                </td>
                                                <td>
                                                    <a href="?remove_salle_edt=1&id_edt=<?= $edt['id_edt'] ?>&id_salle=<?= $id_salles_array[$i] ?>" 
                                                       class="btn btn-danger btn-sm"
                                                       onclick="return confirm('Retirer cette salle du cours ?')">
                                                        ❌ Retirer
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php 
                                            endif;
                                        endfor; 
                                        ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Onglet 4: Import/Export -->
        <div id="tab-import" class="tab-content fade-in">
            <div class="management-card">
                <div class="card-header">
                    <span class="card-icon">📥</span>
                    <h2>Import/Export des données</h2>
                </div>
                
                <div class="form-grid">
                    <!-- Export -->
                    <div style="padding: 20px; border: 2px dashed #dee2e6; border-radius: 8px;">
                        <h3 style="margin-bottom: 15px; color: var(--primary);">📤 Export</h3>
                        <p style="margin-bottom: 15px; color: #666;">
                            Téléchargez les données d'emploi du temps au format CSV.
                        </p>
                        
                        <a href="javascript:void(0)" onclick="exportEDT()" class="btn btn-success">
                            📄 Exporter EDT (CSV)
                        </a>
                        
                        <a href="javascript:void(0)" onclick="exportSalles()" class="btn btn-secondary">
                            🏫 Exporter Salles (CSV)
                        </a>
                    </div>
                    
                    <!-- Import -->
                    <div style="padding: 20px; border: 2px dashed #dee2e6; border-radius: 8px;">
                        <h3 style="margin-bottom: 15px; color: var(--primary);">📥 Import</h3>
                        <p style="margin-bottom: 15px; color: #666;">
                            Importez des données à partir d'un fichier CSV.
                        </p>
                        
                        <div style="color: #dc3545; font-size: 14px; margin-bottom: 15px; padding: 10px; background: #f8d7da; border-radius: 4px;">
                            ⚠️ Fonctionnalité en développement
                        </div>
                    </div>
                </div>
                
                <!-- Aperçu des données -->
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3 style="margin-bottom: 15px; color: var(--primary);">👁️ Aperçu des données</h3>
                    
                    <div class="form-grid">
                        <div>
                            <strong>Total cours :</strong> <?= $stats['total_cours'] ?><br>
                            <strong>Total salles :</strong> <?= $stats['total_salles'] ?>
                        </div>
                        <div>
                            <strong>Cours avec salle :</strong> <?= $stats['cours_avec_salle'] ?><br>
                            <strong>Cours sans salle :</strong> <?= $stats['cours_sans_salle'] ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gestion des onglets
        function openTab(tabName) {
            // Masquer tous les onglets
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Désactiver tous les boutons d'onglets
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Afficher l'onglet sélectionné
            document.getElementById(tabName).classList.add('active');
            
            // Activer le bouton correspondant
            event.currentTarget.classList.add('active');
        }
        
        // Afficher les détails du cours sélectionné
        function showCoursDetails(edtId) {
            const select = document.querySelector('select[name="id_edt_assign"]');
            const selectedOption = select.options[select.selectedIndex];
            const detailsDiv = document.getElementById('cours-details');
            const infoDiv = document.getElementById('cours-info');
            
            if (edtId && selectedOption.dataset.details) {
                const details = JSON.parse(selectedOption.dataset.details);
                infoDiv.innerHTML = `
                    <div style="margin-top: 5px;">
                        Classe : ${details.classe}<br>
                        Cours : ${details.cours}<br>
                        Jour : ${details.jour}<br>
                        Horaire : ${details.horaire}
                    </div>
                `;
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        }
        
        // Export CSV des EDT
        function exportEDT() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Classe,Cours,Jour,Heure Début,Heure Fin,Salles\r\n";
            
            <?php foreach ($all_edt as $edt): ?>
                csvContent += "<?= $edt['id_edt'] ?>," +
                    "\"<?= htmlspecialchars($edt['classe_nom']) ?>\"," +
                    "\"<?= htmlspecialchars($edt['cours']) ?>\"," +
                    "\"<?= ucfirst($edt['Jours']) ?>\"," +
                    "\"<?= date('H:i', strtotime($edt['heure_deb'])) ?>\"," +
                    "\"<?= date('H:i', strtotime($edt['heure_fin'])) ?>\"," +
                    "\"<?= htmlspecialchars($edt['salles_assignees'] ?? '') ?>\"\r\n";
            <?php endforeach; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "edt_<?= date('Y-m-d') ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Export CSV des salles
        function exportSalles() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Salle,Type,Capacité,Bâtiment,Équipements\r\n";
            
            <?php foreach ($salles as $salle): ?>
                csvContent += "<?= $salle['id_salle'] ?>," +
                    "\"<?= htmlspecialchars($salle['salle']) ?>\"," +
                    "\"<?= htmlspecialchars($salle['type_salle'] ?? 'cours') ?>\"," +
                    "\"<?= $salle['capacite'] ?>\"," +
                    "\"<?= htmlspecialchars($salle['batiment'] ?? '') ?>\"," +
                    "\"<?= htmlspecialchars($salle['equipements'] ?? '') ?>\"\r\n";
            <?php endforeach; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "salles_<?= date('Y-m-d') ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Validation des horaires
        document.addEventListener('DOMContentLoaded', function() {
            const heureDeb = document.querySelector('input[name="heure_deb"]');
            const heureFin = document.querySelector('input[name="heure_fin"]');
            
            if (heureDeb && heureFin) {
                heureFin.addEventListener('change', function() {
                    const deb = heureDeb.value;
                    const fin = heureFin.value;
                    
                    if (deb && fin && deb >= fin) {
                        alert("L'heure de fin doit être après l'heure de début !");
                        heureFin.value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>