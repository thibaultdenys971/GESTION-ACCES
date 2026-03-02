<?php
// pages/ajax_get_eleves.php
session_start();
include '../includes/db.php';

if (!isset($_GET['id_classe']) || !is_numeric($_GET['id_classe'])) {
    die('Classe invalide');
}

$id_classe = $_GET['id_classe'];

// Récupérer les élèves de la classe
$stmt = $conn->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom, u.identifiant, 
           b.id_badge, b.date_emission, b.date_expiration,
           (SELECT COUNT(*) FROM entrer_badge eb 
            JOIN journal_entrer je ON eb.id_journal = je.id_journal 
            WHERE eb.id_badge = b.id_badge 
            AND je.date = CURDATE()
            AND TIME(je.heure) BETWEEN TIME_SUB(NOW(), INTERVAL 30 MINUTE) AND NOW()) as badge_detecte
    FROM utilisateur u
    LEFT JOIN badge b ON u.id_utilisateur = b.id_utilisateur
    WHERE u.id_classe = ? AND u.id_statut = 4
    ORDER BY u.nom, u.prenom
");
$stmt->execute([$id_classe]);
$eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($eleves)) {
    echo '<p>Aucun élève dans cette classe.</p>';
    exit;
}

// Date du cours (aujourd'hui)
$date_cours = date('Y-m-d');
?>

<form id="formAppel" method="POST" action="preappel.php">
    <input type="hidden" name="id_edt" value="<?= htmlspecialchars($_GET['id_classe']) ?>">
    <input type="hidden" name="date_cours" value="<?= $date_cours ?>">

    <table class="appel-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Badge</th>
                <th>Statut badge</th>
                <th>Présent</th>
                <th>Absent</th>
                <th>Retard</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($eleves as $eleve):
                $has_badge = !empty($eleve['id_badge']);
                $badge_valide = $has_badge && strtotime($eleve['date_expiration']) > time();
                $badge_detecte = $eleve['badge_detecte'] > 0;
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($eleve['nom']) ?></strong></td>
                    <td><?= htmlspecialchars($eleve['prenom']) ?></td>
                    <td>
                        <?php if ($has_badge): ?>
                            <span style="color: #28a745;">✅ Actif</span>
                            <div style="font-size: 11px; color: #666;">
                                Exp: <?= date('d/m/Y', strtotime($eleve['date_expiration'])) ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #dc3545;">❌ Aucun</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($badge_detecte): ?>
                            <span style="color: #28a745; font-weight: bold;">✅ Détecté</span>
                        <?php else: ?>
                            <span style="color: #dc3545;">❌ Non détecté</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="statut-btn btn-present"
                            data-eleve="<?= $eleve['id_utilisateur'] ?>"
                            data-statut="present"
                            onclick="setPresence(<?= $eleve['id_utilisateur'] ?>, 'present')">
                            ✅
                        </button>
                    </td>
                    <td>
                        <button type="button" class="statut-btn btn-absent"
                            data-eleve="<?= $eleve['id_utilisateur'] ?>"
                            data-statut="absent"
                            onclick="setPresence(<?= $eleve['id_utilisateur'] ?>, 'absent')">
                            ❌
                        </button>
                    </td>
                    <td>
                        <button type="button" class="statut-btn btn-retard"
                            data-eleve="<?= $eleve['id_utilisateur'] ?>"
                            data-statut="retard"
                            onclick="setPresence(<?= $eleve['id_utilisateur'] ?>, 'retard')">
                            ⏰
                        </button>
                    </td>
                </tr>
                <input type="hidden" name="presences[<?= $eleve['id_utilisateur'] ?>]"
                    id="presence_<?= $eleve['id_utilisateur'] ?>" value="absent">
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
        <button type="button" onclick="closeAppelModal()"
            style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px;">
            Annuler
        </button>
        <button type="submit" name="valider_appel"
            style="padding: 10px 20px; background: #0e1f4c; color: white; border: none; border-radius: 5px; font-weight: bold;">
            ✅ Valider l'appel
        </button>
    </div>
</form>

<script>
    // Initialiser les statuts basés sur les badges détectés
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($eleves as $eleve): ?>
            <?php if ($eleve['badge_detecte'] > 0): ?>
                setPresence(<?= $eleve['id_utilisateur'] ?>, 'present');
            <?php endif; ?>
        <?php endforeach; ?>
    });
</script>