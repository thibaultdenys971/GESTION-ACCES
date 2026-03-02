<?php
// pages/admin.php
session_start();

// VÉRIFICATION ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

// 🔥 AJOUTER ICI - Log de visite de la page admin
addLog($conn, 'info', 'PAGE_VUE', $_SESSION['user_id'], 
       "Accès à la gestion des élèves", "Page: admin.php");

// ... dans la suppression d'un élève (ligne 105 environ)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    
    // 🔥 AJOUTER ICI - Log de suppression
    $nom_eleve = ""; // Récupérez le nom avant suppression
    addLog($conn, 'warning', 'SUPPRESSION_UTILISATEUR', $_SESSION['user_id'], 
           "Suppression d'un élève", 
           "ID élève: $id_to_delete"); }

$message = '';
$errorMessage = '';
$eleves = [];
$search = '';

// RECHERCHE D'ÉLÈVES
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $stmt = $conn->prepare("
            SELECT u.id_utilisateur, u.nom, u.prenom, u.identifiant, u.id_classe, 
                   s.statut, c.libelle as classe_nom
            FROM utilisateur u
            JOIN statut s ON u.id_statut = s.id_statut
            LEFT JOIN classe c ON u.id_classe = c.id_classe
            WHERE s.statut = 'Eleve'
            AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.identifiant LIKE ? 
                 OR c.libelle LIKE ? OR c.libelle IS NULL)
            ORDER BY c.libelle, u.nom, u.prenom
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // TOUS LES ÉLÈVES (y compris ceux sans classe)
    $stmt = $conn->prepare("
        SELECT u.id_utilisateur, u.nom, u.prenom, u.identifiant, u.id_classe, 
               s.statut, c.libelle as classe_nom
        FROM utilisateur u
        JOIN statut s ON u.id_statut = s.id_statut
        LEFT JOIN classe c ON u.id_classe = c.id_classe
        WHERE s.statut = 'Eleve'
        ORDER BY 
            CASE WHEN c.libelle IS NULL THEN 1 ELSE 0 END,
            c.libelle, 
            u.nom, 
            u.prenom
    ");
    $stmt->execute();
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// SUPPRIMER UN ÉLÈVE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];

    // Empêcher de se supprimer soi-même
    if ($id_to_delete == $_SESSION['user_id']) {
        $errorMessage = "Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        try {
            // Vérifier que c'est bien un élève
            $checkStmt = $conn->prepare("
                SELECT u.id_utilisateur, s.statut 
                FROM utilisateur u 
                JOIN statut s ON u.id_statut = s.id_statut 
                WHERE u.id_utilisateur = ? AND s.statut = 'Eleve'
            ");
            $checkStmt->execute([$id_to_delete]);
            $user = $checkStmt->fetch();

            if ($user) {
                // Supprimer d'abord les références dans d'autres tables
                $deleteBadgeStmt = $conn->prepare("DELETE FROM badge WHERE id_utilisateur = ?");
                $deleteBadgeStmt->execute([$id_to_delete]);
                
                $deleteUserMatiereStmt = $conn->prepare("DELETE FROM utilisateur_matiere WHERE id_utilisateur = ?");
                $deleteUserMatiereStmt->execute([$id_to_delete]);
                
                // Ensuite supprimer l'utilisateur
                $deleteStmt = $conn->prepare("DELETE FROM utilisateur WHERE id_utilisateur = ?");
                $deleteStmt->execute([$id_to_delete]);
                
                $message = "✅ Élève supprimé avec succès.";
                // Recharger la liste
                header("Location: admin.php?message=deleted");
                exit();
            } else {
                $errorMessage = "Cet utilisateur n'est pas un élève ou n'existe pas.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// ================ CORRECTION ICI : RÉCUPÉRER TOUTES LES CLASSES ================
// 1. Récupérer TOUTES les classes disponibles
$allClassesStmt = $conn->query("SELECT id_classe, libelle FROM classe ORDER BY libelle");
$all_classes = $allClassesStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Récupérer le nombre d'élèves par classe
$elevesParClasse = [];
foreach ($all_classes as $classe) {
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as nb
        FROM utilisateur u
        JOIN statut s ON u.id_statut = s.id_statut
        WHERE s.statut = 'Eleve' AND u.id_classe = ?
    ");
    $countStmt->execute([$classe['id_classe']]);
    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
    $elevesParClasse[$classe['libelle']] = $count['nb'];
}

// 3. Compter les élèves sans classe
$sansClasseStmt = $conn->prepare("
    SELECT COUNT(*) as nb
    FROM utilisateur u
    JOIN statut s ON u.id_statut = s.id_statut
    WHERE s.statut = 'Eleve' AND u.id_classe IS NULL
");
$sansClasseStmt->execute();
$sans_classe = $sansClasseStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des élèves - Admin</title>
    <style>
        /* Styles spécifiques */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-top: 4px solid #28a745;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.sans-classe {
            border-top: 4px solid #dc3545;
        }

        .stat-card.empty-class {
            border-top: 4px solid #ffc107;
            background: #fffbf0;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #0e1f4c;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .classe-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #495057;
            margin-right: 8px;
            margin-bottom: 8px;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .classe-badge:hover {
            background: #0e1f4c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .classe-badge.active {
            background: #0e1f4c;
            color: white;
            border-color: #0e1f4c;
        }

        .classe-badge.sans-classe {
            background: #dc3545;
            color: white;
        }

        .classe-badge.sans-classe:hover {
            background: #c82333;
        }

        .classe-badge.empty {
            background: #ffc107;
            color: #212529;
            border: 1px dashed #ffc107;
        }

        .classe-badge.empty:hover {
            background: #e0a800;
        }

        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
            transition: all 0.3s;
        }

        .export-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }

        /* Styles réutilisables */
        .header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(14, 31, 76, 0.2);
        }

        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .back-btn {
            background: white;
            color: #0e1f4c;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .back-btn:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
        }

        .card-body {
            padding: 25px;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .search-input:focus {
            border-color: #0e1f4c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(14, 31, 76, 0.1);
        }

        .search-btn {
            background: #0e1f4c;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }

        .search-btn:hover {
            background: #1a3a7a;
            transform: translateY(-2px);
        }

        .clear-search {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .clear-search:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .users-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
        }

        .users-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .users-table tr:nth-child(even) {
            background: #fdfdfd;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-classe {
            background: #28a745;
            color: white;
            min-width: 60px;
            text-align: center;
        }

        .badge-sans-classe {
            background: #dc3545;
            color: white;
            min-width: 80px;
            text-align: center;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-edit,
        .btn-delete,
        .btn-assign {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
        }

        .btn-edit:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .btn-assign {
            background: #17a2b8;
            color: white;
        }

        .btn-assign:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-data-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .filters-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #0e1f4c;
        }

        .classes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .search-bar {
                flex-direction: column;
            }
            
            .users-table {
                font-size: 14px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .classes-list {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>👨‍🎓  Tableau de bord</h1>
            <div class="header-actions">
                <a href="create_user.php?type=eleve" class="back-btn">➕ Ajouter un élève</a>
                <a href="javascript:void(0)" onclick="exportEleves()" class="export-btn">📊 Exporter liste</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Statistiques TOUTES les classes -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= count($eleves) ?></div>
                <div class="label">Élèves total</div>
            </div>

            <?php foreach ($all_classes as $classe): ?>
                <?php $nb_eleves = $elevesParClasse[$classe['libelle']] ?? 0; ?>
                <div class="stat-card <?= $nb_eleves == 0 ? 'empty-class' : '' ?>">
                    <div class="number"><?= $nb_eleves ?></div>
                    <div class="label"><?= htmlspecialchars($classe['libelle']) ?></div>
                </div>
            <?php endforeach; ?>

            <?php if ($sans_classe['nb'] > 0): ?>
                <div class="stat-card sans-classe">
                    <div class="number"><?= $sans_classe['nb'] ?></div>
                    <div class="label">Sans classe</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Barre de recherche et filtres -->
        <div class="card">
            <div class="card-header">
                🔍 Recherche et filtres
            </div>
            <div class="card-body">
                <form method="GET" class="search-bar">
                    <input type="text"
                        name="search"
                        class="search-input"
                        placeholder="Rechercher un élève par nom, prénom, identifiant..."
                        value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="search-btn">🔍 Rechercher</button>
                    <?php if (!empty($search)): ?>
                        <a href="admin.php" class="clear-search">❌ Annuler la recherche</a>
                    <?php endif; ?>
                </form>

                <!-- Filtres par classe - TOUTES LES CLASSES -->
                <div class="filters-section">
                    <strong>🏫 Filtrer par classe :</strong><br><br>
                    
                    <div class="classes-list">
                        <!-- Toutes les classes -->
                        <a href="admin.php" 
                           class="classe-badge <?= empty($search) ? 'active' : '' ?>">
                            Toutes les classes (<?= count($eleves) ?>)
                        </a>
                        
                        <!-- Toutes les classes disponibles -->
                        <?php foreach ($all_classes as $classe): ?>
                            <?php 
                            $nb_eleves = $elevesParClasse[$classe['libelle']] ?? 0;
                            $badge_class = '';
                            if ($nb_eleves == 0) {
                                $badge_class = 'empty';
                            }
                            ?>
                            <a href="admin.php?search=<?= urlencode($classe['libelle']) ?>"
                               class="classe-badge <?= $search == $classe['libelle'] ? 'active' : '' ?> <?= $badge_class ?>">
                                <?= htmlspecialchars($classe['libelle']) ?> (<?= $nb_eleves ?>)
                            </a>
                        <?php endforeach; ?>
                        
                        <!-- Élèves sans classe -->
                        <?php if ($sans_classe['nb'] > 0): ?>
                            <a href="admin.php?search=SANS_CLASSE"
                               class="classe-badge sans-classe <?= $search == 'SANS_CLASSE' ? 'active' : '' ?>">
                                Sans classe (<?= $sans_classe['nb'] ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des élèves -->
        <div class="card">
            <div class="card-header">
                👥 Liste des élèves (<?= count($eleves) ?>)
            </div>
            <div class="card-body">
                <?php if (empty($eleves)): ?>
                    <div class="no-data">
                        <div class="no-data-icon">👨‍🎓</div>
                        <p style="font-size: 18px; margin-bottom: 10px;">Aucun élève trouvé.</p>
                        <?php if (!empty($search)): ?>
                            <p>Essayez avec d'autres termes de recherche.</p>
                            <a href="admin.php" style="color: #0e1f4c; text-decoration: underline;">
                                Voir tous les élèves
                            </a>
                        <?php else: ?>
                            <p>Cliquez sur "Ajouter un élève" pour en créer un.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Identifiant</th>
                                    <th>Classe</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eleves as $eleve): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($eleve['nom']) ?></strong></td>
                                        <td><?= htmlspecialchars($eleve['prenom']) ?></td>
                                        <td><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;"><?= htmlspecialchars($eleve['identifiant']) ?></code></td>
                                        <td>
                                            <?php if (!empty($eleve['classe_nom'])): ?>
                                                <span class="badge badge-classe">
                                                    <?= htmlspecialchars($eleve['classe_nom']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-sans-classe">
                                                    Sans classe
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <a href="edit_user.php?id=<?= $eleve['id_utilisateur'] ?>" 
                                               class="btn-edit">✏️ Modifier</a>
                                            <?php if (empty($eleve['classe_nom'])): ?>
                                                <button onclick="assignClasse(<?= $eleve['id_utilisateur'] ?>, '<?= htmlspecialchars(addslashes($eleve['prenom'] . ' ' . $eleve['nom'])) ?>')"
                                                        class="btn-assign">
                                                    🏫 Assigner classe
                                                </button>
                                            <?php endif; ?>
                                            <a href="admin.php?delete=<?= $eleve['id_utilisateur'] ?>"
                                                class="btn-delete"
                                                onclick="return confirm('Supprimer l\'élève <?= htmlspecialchars(addslashes($eleve['prenom'] . ' ' . $eleve['nom'])) ?> ?')">
                                                🗑️ Supprimer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px; color: #666; font-size: 14px; text-align: center;">
                        <p><?= count($eleves) ?> élève(s) trouvé(s) 
                           <?php if (!empty($search)): ?>
                               pour la recherche "<?= htmlspecialchars($search) ?>"
                           <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal pour assigner une classe -->
    <div id="assignModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 90%; max-width: 500px; padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #0e1f4c;" id="modalTitle">Assigner une classe</h3>
                <button onclick="closeAssignModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>
            
            <div id="assignContent">
                <!-- Contenu dynamique -->
            </div>
        </div>
    </div>

    <script>
        function exportEleves() {
            let csvContent = "data:text/csv;charset=utf-8,";

            // En-têtes
            csvContent += "ID,Nom,Prénom,Identifiant,Classe\r\n";

            // Données
            <?php foreach ($eleves as $eleve): ?>
                csvContent += "<?= $eleve['id_utilisateur'] ?>," +
                    "\"<?= htmlspecialchars($eleve['nom']) ?>\"," +
                    "\"<?= htmlspecialchars($eleve['prenom']) ?>\"," +
                    "\"<?= htmlspecialchars($eleve['identifiant']) ?>\"," +
                    "\"<?= htmlspecialchars($eleve['classe_nom'] ?? 'Sans classe') ?>\"\r\n";
            <?php endforeach; ?>

            // Téléchargement
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "eleves_<?= date('Y-m-d') ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Tri des tables
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('.users-table');
            if (table) {
                const headers = table.querySelectorAll('th');
                headers.forEach((header, index) => {
                    header.style.cursor = 'pointer';
                    header.addEventListener('click', () => {
                        sortTable(table, index);
                    });
                });
            }
        });

        function sortTable(table, column) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                const aText = a.cells[column].textContent.trim();
                const bText = b.cells[column].textContent.trim();

                // Pour la colonne Classe, traiter "Sans classe" en dernier
                if (column === 3) {
                    if (aText === "Sans classe") return 1;
                    if (bText === "Sans classe") return -1;
                }

                // Essayer de convertir en nombre si possible
                const aNum = parseFloat(aText.replace(/[^0-9.-]+/g, ''));
                const bNum = parseFloat(bText.replace(/[^0-9.-]+/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return aNum - bNum;
                }

                return aText.localeCompare(bText, 'fr', { sensitivity: 'base' });
            });

            // Inverser si déjà trié
            if (table.dataset.sortedColumn === String(column)) {
                rows.reverse();
                table.dataset.sortedColumn = null;
            } else {
                table.dataset.sortedColumn = column;
            }

            // Réinsérer les lignes
            rows.forEach(row => tbody.appendChild(row));
        }

        // Fonction pour assigner une classe
        function assignClasse(eleveId, eleveNom) {
            document.getElementById('modalTitle').textContent = `Assigner une classe à ${eleveNom}`;
            
            // Charger les classes disponibles
            fetch(`ajax_get_classes.php`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('assignContent').innerHTML = `
                        <form id="assignForm" onsubmit="assignClasseSubmit(${eleveId}); return false;">
                            <input type="hidden" name="id_utilisateur" value="${eleveId}">
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    Sélectionnez une classe :
                                </label>
                                ${html}
                            </div>
                            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                <button type="button" onclick="closeAssignModal()" 
                                        style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px;">
                                    Annuler
                                </button>
                                <button type="submit"
                                        style="padding: 10px 20px; background: #0e1f4c; color: white; border: none; border-radius: 5px; font-weight: bold;">
                                    ✅ Assigner
                                </button>
                            </div>
                        </form>
                    `;
                    document.getElementById('assignModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('assignContent').innerHTML = '<p>Erreur lors du chargement des classes.</p>';
                    document.getElementById('assignModal').style.display = 'flex';
                });
        }

        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }

        function assignClasseSubmit(eleveId) {
            const form = document.getElementById('assignForm');
            const formData = new FormData(form);
            
            fetch('ajax_assign_classe.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeAssignModal();
                    window.location.reload();
                } else {
                    alert('Erreur: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur.');
            });
        }

        // Fermer la modal avec ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAssignModal();
            }
        });
    </script>
</body>
</html>