<?php
// pages/gestion_documentalistes.php
session_start();

// VÉRIFICATION ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

$message = '';
$errorMessage = '';
$documentalistes = [];
$search = '';

// RECHERCHE DE DOCUMENTALISTES
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $stmt = $conn->prepare("
            SELECT u.*, s.statut, c.libelle as classe_nom
            FROM utilisateur u
            JOIN statut s ON u.id_statut = s.id_statut
            JOIN classe c ON u.id_classe = c.id_classe
            WHERE s.statut = 'Documentaliste' 
            AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.identifiant LIKE ?)
            ORDER BY u.nom, u.prenom
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $documentalistes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // TOUS LES DOCUMENTALISTES
    $stmt = $conn->prepare("
        SELECT u.*, s.statut, c.libelle as classe_nom
        FROM utilisateur u
        JOIN statut s ON u.id_statut = s.id_statut
        JOIN classe c ON u.id_classe = c.id_classe
        WHERE s.statut = 'Documentaliste'
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute();
    $documentalistes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// SUPPRIMER UN DOCUMENTALISTE
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
                WHERE u.id_utilisateur = ? AND s.statut = 'Documentaliste'
            ");
            $checkStmt->execute([$id_to_delete]);
            $user = $checkStmt->fetch();

            if ($user) {
                $deleteStmt = $conn->prepare("DELETE FROM utilisateur WHERE id_utilisateur = ?");
                $deleteStmt->execute([$id_to_delete]);
                $message = "✅ Documentaliste supprimé avec succès.";
                header("Location: gestion_documentalistes.php?message=deleted");
                exit();
            } else {
                $errorMessage = "Cet utilisateur n'est pas un documentaliste ou n'existe pas.";
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
    <title>Gestion des documentalistes - GESTION ACCES</title>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>📚 Gestion des documentalistes</h1>
            <div>
                <a href="create_user.php?type=documentaliste" class="back-btn">➕ Ajouter un documentaliste</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Description du rôle -->
        <div class="card" style="margin-bottom: 20px; background: #e7f3ff;">
            <div class="card-body">
                <h3 style="color: #0e1f4c; margin-bottom: 10px;">Rôle du documentaliste</h3>
                <p style="color: #333; line-height: 1.6;">
                    Les documentalistes sont responsables de la gestion de la bibliothèque.
                    Ils peuvent : gérer les livres, superviser les prêts, gérer les retours,
                    et assurer le bon fonctionnement du système de bibliothèque.
                </p>
            </div>
        </div>

        <!-- Liste des documentalistes -->
        <div class="card">
            <div class="card-header">
                Liste des documentalistes (<?= count($documentalistes) ?>)
            </div>
            <div class="card-body">
                <?php if (empty($documentalistes)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p style="font-size: 18px;">Aucun documentaliste trouvé.</p>
                        <p>Les documentalistes gèrent la bibliothèque et les prêts de livres.</p>
                        <a href="create_user.php?type=documentaliste" class="btn" style="display: inline-block; margin-top: 15px;">
                            ➕ Ajouter le premier documentaliste
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
                                <th>Classe assignée</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentalistes as $doc): ?>
                                <tr>
                                    <td>#<?= $doc['id_utilisateur'] ?></td>
                                    <td><strong><?= htmlspecialchars($doc['nom']) ?></strong></td>
                                    <td><?= htmlspecialchars($doc['prenom']) ?></td>
                                    <td><code><?= htmlspecialchars($doc['identifiant']) ?></code></td>
                                    <td>
                                        <?php if ($doc['classe_nom']): ?>
                                            <span class="badge" style="background: #17a2b8; color: white;">
                                                <?= htmlspecialchars($doc['classe_nom']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">Bibliothèque</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="edit_user.php?id=<?= $doc['id_utilisateur'] ?>" class="btn-edit">✏️ Modifier</a>
                                        <a href="gestion_documentalistes.php?delete=<?= $doc['id_utilisateur'] ?>"
                                            class="btn-delete"
                                            onclick="return confirm('Supprimer le documentaliste <?= htmlspecialchars($doc['prenom'] . ' ' . $doc['nom']) ?> ?')">
                                            🗑️ Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 20px; color: #666; font-size: 14px;">
                        <p><?= count($documentalistes) ?> documentaliste(s) trouvé(s)</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>