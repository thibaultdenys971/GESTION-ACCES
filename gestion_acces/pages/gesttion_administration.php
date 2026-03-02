<?php
// pages/gestion_administration.php
session_start();

// VÉRIFICATION ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

$message = '';
$errorMessage = '';
$administration = [];
$search = '';

// RECHERCHE DE L'ADMINISTRATION
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $stmt = $conn->prepare("
            SELECT u.*, s.statut, c.libelle as classe_nom
            FROM utilisateur u
            JOIN statut s ON u.id_statut = s.id_statut
            JOIN classe c ON u.id_classe = c.id_classe
            WHERE s.statut = 'Administration' 
            AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.identifiant LIKE ?)
            ORDER BY u.nom, u.prenom
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $administration = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // TOUTE L'ADMINISTRATION
    $stmt = $conn->prepare("
        SELECT u.*, s.statut, c.libelle as classe_nom
        FROM utilisateur u
        JOIN statut s ON u.id_statut = s.id_statut
        JOIN classe c ON u.id_classe = c.id_classe
        WHERE s.statut = 'Administration'
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute();
    $administration = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// SUPPRIMER UN MEMBRE DE L'ADMINISTRATION
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];

    if ($id_to_delete == $_SESSION['user_id']) {
        $errorMessage = "Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        try {
            $checkStmt = $conn->prepare("
                SELECT u.id_utilisateur, s.statut 
                FROM utilisateur u 
                JOIN statut s ON u.id_statut = s.id_statut 
                WHERE u.id_utilisateur = ? AND s.statut = 'Administration'
            ");
            $checkStmt->execute([$id_to_delete]);
            $user = $checkStmt->fetch();

            if ($user) {
                $deleteStmt = $conn->prepare("DELETE FROM utilisateur WHERE id_utilisateur = ?");
                $deleteStmt->execute([$id_to_delete]);
                $message = "✅ Membre de l'administration supprimé avec succès.";
                header("Location: gestion_administration.php?message=deleted");
                exit();
            } else {
                $errorMessage = "Cet utilisateur n'est pas un membre de l'administration ou n'existe pas.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'administration - GESTION ACCES</title>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>🏢 Gestion de l'administration</h1>
            <div>
                <a href="create_user.php?type=administration" class="back-btn">➕ Ajouter un membre</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Description du rôle -->
        <div class="card" style="margin-bottom: 20px; background: #f0f0f0;">
            <div class="card-body">
                <h3 style="color: #0e1f4c; margin-bottom: 10px;">Rôle de l'administration</h3>
                <p style="color: #333; line-height: 1.6;">
                    Les membres de l'administration ont accès aux fonctionnalités administratives
                    mais ne peuvent pas modifier les paramètres système comme les administrateurs.
                    Ils peuvent gérer les utilisateurs, mais avec des limitations.
                </p>
            </div>
        </div>

        <!-- Liste de l'administration -->
        <div class="card">
            <div class="card-header">
                Membres de l'administration (<?= count($administration) ?>)
            </div>
            <div class="card-body">
                <?php if (empty($administration)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p style="font-size: 18px;">Aucun membre de l'administration trouvé.</p>
                        <p>Ces membres peuvent aider à la gestion quotidienne du système.</p>
                        <a href="create_user.php?type=administration" class="btn" style="display: inline-block; margin-top: 15px;">
                            ➕ Ajouter un membre
                        </a>
                    </div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Identifiant</th>
                                <th>Département</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($administration as $admin): ?>
                                <tr>
                                    <td>#<?= $admin['id_utilisateur'] ?></td>
                                    <td><strong><?= htmlspecialchars($admin['nom']) ?></strong></td>
                                    <td><?= htmlspecialchars($admin['prenom']) ?></td>
                                    <td><code><?= htmlspecialchars($admin['identifiant']) ?></code></td>
                                    <td>
                                        <?php if ($admin['classe_nom']): ?>
                                            <span class="badge" style="background: #6f42c1; color: white;">
                                                <?= htmlspecialchars($admin['classe_nom']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">Administration</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="edit_user.php?id=<?= $admin['id_utilisateur'] ?>" class="btn-edit">✏️ Modifier</a>
                                        <a href="gestion_administration.php?delete=<?= $admin['id_utilisateur'] ?>"
                                            class="btn-delete"
                                            onclick="return confirm('Supprimer le membre <?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?> ?')">
                                            🗑️ Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 20px; color: #666; font-size: 14px;">
                        <p><?= count($administration) ?> membre(s) de l'administration trouvé(s)</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>