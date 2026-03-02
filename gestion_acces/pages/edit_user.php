<?php
// edit_user.php - Page de modification
session_start();

if (!isset($_SESSION['id_statut']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

// 🔥 AJOUTER ICI - Log de visite
addLog($conn, 'info', 'PAGE_VUE', $_SESSION['user_id'], 
       "Accès à la modification d'utilisateur", "Page: edit_user.php");

// Après la modification réussie
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    // 🔥 AJOUTER ICI - Log de modification
    addLog($conn, 'info', 'MODIFICATION_UTILISATEUR', $_SESSION['user_id'], 
           "Utilisateur modifié", 
           "ID utilisateur: $id_edit, Champs modifiés: ...");
}

$message = '';
$errorMessage = '';

// Récupérer l'utilisateur à modifier
$id = $_GET['id'] ?? 0;
$user = null;

if ($id) {
    $stmt = $conn->prepare("
        SELECT u.*, s.statut, c.libelle as classe_nom 
        FROM utilisateur u 
        JOIN statut s ON u.id_statut = s.id_statut 
        JOIN classe c ON u.id_classe = c.id_classe 
        WHERE u.id_utilisateur = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    header("Location: admin.php?error=user_not_found");
    exit();
}

// Récupérer les statuts et classes
$statutsStmt = $conn->prepare("SELECT id_statut, statut FROM statut WHERE id_statut != 1 ORDER BY id_statut");
$statutsStmt->execute();
$statuts = $statutsStmt->fetchAll(PDO::FETCH_ASSOC);

$classesStmt = $conn->query("SELECT id_classe, libelle FROM classe ORDER BY libelle");
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $identifiant = trim($_POST['identifiant'] ?? '');
    $id_statut = intval($_POST['id_statut'] ?? 0);
    $id_classe = intval($_POST['id_classe'] ?? 0);
    $mot_de_pass = trim($_POST['mot_de_pass'] ?? '');
    
    try {
        if (!empty($mot_de_pass)) {
            // Si nouveau mot de passe fourni
            $hashed_password = password_hash($mot_de_pass, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("
                UPDATE utilisateur 
                SET nom = ?, prenom = ?, identifiant = ?, id_statut = ?, id_classe = ?, mot_de_pass = ?
                WHERE id_utilisateur = ?
            ");
            $updateStmt->execute([$nom, $prenom, $identifiant, $id_statut, $id_classe, $hashed_password, $id]);
        } else {
            // Sans changer le mot de passe
            $updateStmt = $conn->prepare("
                UPDATE utilisateur 
                SET nom = ?, prenom = ?, identifiant = ?, id_statut = ?, id_classe = ?
                WHERE id_utilisateur = ?
            ");
            $updateStmt->execute([$nom, $prenom, $identifiant, $id_statut, $id_classe, $id]);
        }
        
        $message = "✅ Utilisateur modifié avec succès !";
        // Recharger les données
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $errorMessage = "Erreur : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier utilisateur - AGTI SAV</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { background: #0e1f4c; color: white; padding: 12px; border: none; border-radius: 5px; cursor: pointer; width: 100%; }
        .btn:hover { background: #1a3a7a; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h2>✏️ Modifier l'utilisateur</h2>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Nom</label>
                <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Prénom</label>
                <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Identifiant</label>
                <input type="text" name="identifiant" value="<?= htmlspecialchars($user['identifiant']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Rôle</label>
                <select name="id_statut" required>
                    <?php foreach ($statuts as $statut): ?>
                        <option value="<?= $statut['id_statut'] ?>" <?= $user['id_statut'] == $statut['id_statut'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statut['statut']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Classe</label>
                <select name="id_classe" required>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?= $classe['id_classe'] ?>" <?= $user['id_classe'] == $classe['id_classe'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                <input type="password" name="mot_de_pass" placeholder="••••••••">
                <small style="color: #666;">Minimum 6 caractères</small>
            </div>
            
            <button type="submit" class="btn">💾 Enregistrer les modifications</button>
            <a href="admin.php" style="display: block; text-align: center; margin-top: 10px; color: #666;">← Retour à la liste</a>
        </form>
    </div>
</body>
</html>