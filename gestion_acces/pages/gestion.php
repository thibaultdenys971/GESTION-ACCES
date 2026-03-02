<?php
// pages/gestion_bibliotheque.php (ou distributeur.livre.php)
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['id_statut'] != 2 && $_SESSION['id_statut'] != 1)) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

$message = '';
$errorMessage = '';
$search = $_GET['search'] ?? '';

// TRAITEMENT SIMPLIFIÉ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $exemplaireStmt = $conn->prepare("INSERT INTO exemplaire (stock, reserver, id_ouvrage) VALUES (?, 'non', ?)");
            $exemplaireStmt->execute([$stock, $ouvrage_id]);
            
            $message = "✅ Ouvrage ajouté avec succès !";
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
    
    elseif (isset($_POST['enregistrer_pret'])) {
        $id_ouvrage = intval($_POST['id_ouvrage']);
        $date_retour = $_POST['date_retour'];
        
        try {
            // Vérifier le stock
            $checkStmt = $conn->prepare("SELECT id_exemplaire, stock FROM exemplaire WHERE id_ouvrage = ? AND stock > 0");
            $checkStmt->execute([$id_ouvrage]);
            $exemplaire = $checkStmt->fetch();
            
            if ($exemplaire) {
                // Créer le prêt
                $pretStmt = $conn->prepare("INSERT INTO pret (date_pret, date_retour_prevu) VALUES (CURDATE(), ?)");
                $pretStmt->execute([$date_retour]);
                $pret_id = $conn->lastInsertId();
                
                // Lier l'ouvrage
                $etreStmt = $conn->prepare("INSERT INTO etre (id_ouvrage, id_pret) VALUES (?, ?)");
                $etreStmt->execute([$id_ouvrage, $pret_id]);
                
                // Mettre à jour le stock
                $updateStmt = $conn->prepare("UPDATE exemplaire SET stock = stock - 1 WHERE id_exemplaire = ?");
                $updateStmt->execute([$exemplaire['id_exemplaire']]);
                
                $message = "✅ Prêt enregistré avec succès !";
            } else {
                $errorMessage = "❌ Cet ouvrage n'est plus disponible.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
}

// REQUÊTE CORRIGÉE (sans JOIN problématique)
$ouvrageQuery = "SELECT o.*, e.stock, e.reserver FROM ouvrage o LEFT JOIN exemplaire e ON o.id_ouvrage = e.id_ouvrage";

if (!empty($search)) {
    $searchTerm = "%$search%";
    $ouvrageQuery .= " WHERE o.titre LIKE ? OR o.code_barre LIKE ?";
    $ouvrageStmt = $conn->prepare($ouvrageQuery);
    $ouvrageStmt->execute([$searchTerm, $searchTerm]);
} else {
    $ouvrageStmt = $conn->prepare($ouvrageQuery);
    $ouvrageStmt->execute();
}

$ouvrages = $ouvrageStmt->fetchAll(PDO::FETCH_ASSOC);

// Prêts en cours
$pretStmt = $conn->prepare("
    SELECT p.*, o.titre, o.code_barre, DATEDIFF(p.date_retour_prevu, CURDATE()) as jours_restants
    FROM pret p
    JOIN etre et ON p.id_pret = et.id_pret
    JOIN ouvrage o ON et.id_ouvrage = o.id_ouvrage
    WHERE p.date_retour_effectif IS NULL
    ORDER BY p.date_retour_prevu ASC
");
$pretStmt->execute();
$prets = $pretStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Bibliothèque - GESTION ACCES</title>
    <style>
        .stats-mini { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-mini-card { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 3px solid; }
        .stat-mini-card.ouvrages { border-color: #28a745; }
        .stat-mini-card.prets { border-color: #17a2b8; }
        .tab { flex: 1; padding: 12px; text-align: center; background: none; border: none; cursor: pointer; font-weight: 600; color: #666; border-radius: 6px; }
        .tab.active { background: #0e1f4c; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .badge-disponible { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .badge-indisponible { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .ouvrage-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 15px; border-left: 4px solid #0e1f4c; }
        .header { background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .search-input { flex: 1; padding: 12px 15px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; }
        .search-btn { background: #0e1f4c; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; }
        .btn-edit { background: #ffc107; color: #212529; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>📚 Gestion de la Bibliothèque</h1>
        </div>

        <?php if ($message): ?><div class="success"><?= $message ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="error"><?= $errorMessage ?></div><?php endif; ?>

        <div class="tabs">
            <button class="tab active" onclick="showTab('ouvrages')">📖 Ouvrages</button>
            <button class="tab" onclick="showTab('prets')">🔄 Prêts</button>
            <button class="tab" onclick="showTab('ajout')">➕ Ajouter</button>
        </div>

        <div id="tab-ouvrages" class="tab-content active">
            <div class="card">
                <div class="card-body">
                    <form method="GET" style="display:flex;gap:10px;">
                        <input type="text" name="search" class="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher...">
                        <button type="submit" class="search-btn">🔍</button>
                    </form>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach ($ouvrages as $ouvrage): ?>
                    <?php $disponible = ($ouvrage['stock'] > 0); ?>
                    <div class="ouvrage-card">
                        <h3><?= htmlspecialchars($ouvrage['titre']) ?></h3>
                        <span class="<?= $disponible ? 'badge-disponible' : 'badge-indisponible' ?>">
                            <?= $disponible ? 'Disponible' : 'Indisponible' ?>
                        </span>
                        <p><strong>Code:</strong> <?= $ouvrage['code_barre'] ?></p>
                        <p><strong>Stock:</strong> <?= $ouvrage['stock'] ?></p>
                        <button onclick="emprunter(<?= $ouvrage['id_ouvrage'] ?>)" class="btn-edit" <?= !$disponible ? 'disabled' : '' ?>>Emprunter</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="tab-prets" class="tab-content">
            <table class="users-table">
                <thead>
                    <tr><th>Livre</th><th>Date retour</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($prets as $pret): ?>
                        <tr>
                            <td><?= $pret['titre'] ?></td>
                            <td><?= $pret['date_retour_prevu'] ?></td>
                            <td><?= $pret['jours_restants'] < 0 ? 'Retard' : 'En cours' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-ajout" class="tab-content">
            <form method="POST">
                <input type="text" name="titre" placeholder="Titre" required>
                <input type="text" name="code_barre" placeholder="Code barre" required>
                <input type="text" name="cote" placeholder="Cote" required>
                <input type="number" name="stock" value="1" min="1" required>
                <button type="submit" name="ajouter_ouvrage">Ajouter</button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.getElementById('tab-' + tabName).style.display = 'block';
            event.target.classList.add('active');
        }
        
        function emprunter(id) {
            alert('Emprunt du livre ID: ' + id);
        }
    </script>
</body>
</html>