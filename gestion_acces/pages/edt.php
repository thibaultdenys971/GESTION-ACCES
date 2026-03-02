<?php
// pages/edt.php
session_start();

// Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?error=not_logged_in");
    exit();
}

include '../includes/db.php';

// 🔥 AJOUTER ICI - Log de visite de l'emploi du temps
addLog(
    $conn,
    'info',
    'PAGE_VUE',
    $_SESSION['user_id'],
    "Consultation de l'emploi du temps",
    "Page: edt.php"
);

$user_id = $_SESSION['user_id'];
$id_statut = $_SESSION['id_statut'];

// Récupérer les informations de l'utilisateur
$userStmt = $conn->prepare("
    SELECT u.*, s.statut, c.libelle as classe_nom, c.id_classe
    FROM utilisateur u 
    JOIN statut s ON u.id_statut = s.id_statut 
    LEFT JOIN classe c ON u.id_classe = c.id_classe 
    WHERE u.id_utilisateur = ?
");
$userStmt->execute([$user_id]);
$user_info = $userStmt->fetch(PDO::FETCH_ASSOC);

// Initialiser les variables
$edt_data = [];
$current_week = date('W');
$selected_week = isset($_GET['week']) ? intval($_GET['week']) : $current_week;
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Récupérer l'emploi du temps selon le statut
if ($id_statut == 4) { // ÉLÈVE - basé sur sa classe
    if (!empty($user_info['id_classe'])) {
        $edtStmt = $conn->prepare("
            SELECT edt.*, j.Jours, c.cours, cl.libelle as classe_nom,
                   (SELECT GROUP_CONCAT(DISTINCT m.matiere SEPARATOR ', ') 
                    FROM edt_mat em2 
                    JOIN matiere m ON em2.`id-matiere` = m.id_matiere 
                    WHERE em2.id_cours = c.id_cours) as matieres,
                   (SELECT GROUP_CONCAT(DISTINCT u2.prenom, ' ', u2.nom SEPARATOR ', ') 
                    FROM utilisateur_matiere um2 
                    JOIN utilisateur u2 ON um2.id_utilisateur = u2.id_utilisateur
                    WHERE um2.id_matiere IN (
                        SELECT em3.`id-matiere`
                        FROM edt_mat em3
                        WHERE em3.id_cours = c.id_cours
                    )
                    AND u2.id_statut = 3) as professeurs
            FROM emploie_du_temps edt
            JOIN jours j ON edt.id_jours = j.id_jours
            JOIN cours c ON edt.id_cours = c.id_cours
            JOIN classe cl ON edt.id_classe = cl.id_classe
            WHERE edt.id_classe = ?
            ORDER BY j.id_jours, edt.heure_deb
        ");
        $edtStmt->execute([$user_info['id_classe']]);
        $edt_data = $edtStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($id_statut == 3) { // PROFESSEUR - basé sur ses matières
    $edtStmt = $conn->prepare("
        SELECT edt.*, j.Jours, c.cours, cl.libelle as classe_nom,
               GROUP_CONCAT(DISTINCT m.matiere SEPARATOR ', ') as matieres
        FROM emploie_du_temps edt
        JOIN jours j ON edt.id_jours = j.id_jours
        JOIN cours c ON edt.id_cours = c.id_cours
        JOIN classe cl ON edt.id_classe = cl.id_classe
        JOIN edt_mat em ON c.id_cours = em.id_cours
        JOIN matiere m ON em.`id-matiere` = m.id_matiere
        JOIN utilisateur_matiere um ON m.id_matiere = um.id_matiere
        WHERE um.id_utilisateur = ?
        GROUP BY edt.id_edt, j.Jours, c.cours, cl.libelle, edt.heure_deb, edt.heure_fin
        ORDER BY j.id_jours, edt.heure_deb
    ");
    $edtStmt->execute([$user_id]);
    $edt_data = $edtStmt->fetchAll(PDO::FETCH_ASSOC);
} else { // ADMIN ou DOCUMENTALISTE
    $edtStmt = $conn->prepare("
        SELECT edt.*, j.Jours, c.cours, cl.libelle as classe_nom,
               (SELECT GROUP_CONCAT(DISTINCT m.matiere SEPARATOR ', ') 
                FROM edt_mat em2 
                JOIN matiere m ON em2.`id-matiere` = m.id_matiere 
                WHERE em2.id_cours = c.id_cours) as matieres,
               (SELECT GROUP_CONCAT(DISTINCT u2.prenom, ' ', u2.nom SEPARATOR ', ') 
                FROM utilisateur_matiere um2 
                JOIN utilisateur u2 ON um2.id_utilisateur = u2.id_utilisateur
                WHERE um2.id_matiere IN (
                    SELECT em3.`id-matiere`
                    FROM edt_mat em3
                    WHERE em3.id_cours = c.id_cours
                )
                AND u2.id_statut = 3) as professeurs
        FROM emploie_du_temps edt
        JOIN jours j ON edt.id_jours = j.id_jours
        JOIN cours c ON edt.id_cours = c.id_cours
        JOIN classe cl ON edt.id_classe = cl.id_classe
        ORDER BY cl.libelle, j.id_jours, edt.heure_deb
    ");
    $edtStmt->execute();
    $edt_data = $edtStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Organiser les données par jour
$edt_by_day = [];
$jours_order = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];

foreach ($jours_order as $jour) {
    $edt_by_day[$jour] = [];
}

foreach ($edt_data as $cours) {
    $jour = strtolower($cours['Jours']);
    if (isset($edt_by_day[$jour])) {
        $edt_by_day[$jour][] = $cours;
    }
}

// Trier les cours par heure pour chaque jour
foreach ($edt_by_day as $jour => $cours) {
    usort($edt_by_day[$jour], function ($a, $b) {
        return strcmp($a['heure_deb'], $b['heure_deb']);
    });
}

// Récupérer le cours actuel
$current_time = date('H:i:s');
$current_day = date('l');
$french_days = [
    'Monday' => 'lundi',
    'Tuesday' => 'mardi',
    'Wednesday' => 'mercredi',
    'Thursday' => 'jeudi',
    'Friday' => 'vendredi'
];
$current_day_fr = $french_days[$current_day] ?? '';

$cours_actuel = null;
foreach ($edt_data as $cours) {
    if (strtolower($cours['Jours']) == $current_day_fr) {
        $heure_deb = strtotime($cours['heure_deb']);
        $heure_fin = strtotime($cours['heure_fin']);
        $now = time();

        if ($now >= $heure_deb - 900 && $now <= $heure_fin + 900) {
            $cours_actuel = $cours;
            break;
        }
    }
}

// Récupérer le prochain cours
$prochain_cours = null;
foreach ($edt_data as $cours) {
    $jour_cours = strtolower($cours['Jours']);
    $jour_index = array_search($jour_cours, $jours_order);
    $current_day_index = array_search($current_day_fr, $jours_order);

    $heure_deb = strtotime($cours['heure_deb']);
    $now = time();

    if (
        $jour_index > $current_day_index ||
        ($jour_index == $current_day_index && $heure_deb > $now + 900)
    ) {
        $prochain_cours = $cours;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emploi du temps - AGTI SAV</title>
    <style>
        /* Mêmes styles CSS que précédemment */
        .edt-header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .edt-header h1 {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .user-info-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
        }

        .user-info-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: 600;
            opacity: 0.9;
        }

        .info-value {
            font-weight: bold;
        }

        .week-navigation {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 25px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .week-btn {
            background: #0e1f4c;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .week-btn:hover {
            background: #1a3a7a;
            transform: scale(1.1);
        }

        .week-display {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }

        .current-next-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .current-card,
        .next-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .current-card {
            border-top: 4px solid #28a745;
            background: linear-gradient(135deg, #ffffff 0%, #e8f5e9 100%);
        }

        .next-card {
            border-top: 4px solid #17a2b8;
            background: linear-gradient(135deg, #ffffff 0%, #e3f2fd 100%);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .card-header h3 {
            color: #2c3e50;
            font-size: 18px;
        }

        .course-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 14px;
        }

        .detail-value {
            color: #2c3e50;
        }

        .edt-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .edt-table th {
            background: #2c3e50;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }

        .edt-table td {
            padding: 12px;
            border: 1px solid #eee;
            vertical-align: top;
        }

        .time-header {
            background: #34495e;
            color: white;
            font-weight: bold;
            text-align: center;
            width: 80px;
        }

        .cours-cell {
            background: white;
            transition: transform 0.2s;
        }

        .cours-cell:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1;
            position: relative;
        }

        .cours-item {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 4px solid #3498db;
        }

        .cours-item.current {
            background: #d4edda;
            border-left-color: #28a745;
        }

        .cours-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .cours-details {
            font-size: 12px;
            color: #666;
        }

        .cours-time {
            background: rgba(52, 152, 219, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            display: inline-block;
            margin-top: 5px;
        }

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
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-top: 4px solid;
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

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-eleve {
            background: #3498db;
            color: white;
        }

        .status-prof {
            background: #e74c3c;
            color: white;
        }

        .status-admin {
            background: #2ecc71;
            color: white;
        }

        .status-doc {
            background: #9b59b6;
            color: white;
        }

        @media (max-width: 768px) {
            .edt-table {
                font-size: 14px;
            }

            .edt-table th,
            .edt-table td {
                padding: 8px;
            }

            .time-header {
                width: 60px;
            }

            .current-next-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="edt-header">
            <h1>📅 Emploi du temps</h1>

            <div class="user-info-card">
                <div class="user-info-row">
                    <div class="info-item">
                        <span class="info-label">👤 Nom :</span>
                        <span class="info-value"><?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🎓 Statut :</span>
                        <span class="status-badge status-<?= strtolower($user_info['statut']) ?>">
                            <?= htmlspecialchars($user_info['statut']) ?>
                        </span>
                    </div>
                    <?php if ($id_statut == 4 && !empty($user_info['classe_nom'])): ?>
                        <div class="info-item">
                            <span class="info-label">🏫 Classe :</span>
                            <span class="info-value"><?= htmlspecialchars($user_info['classe_nom']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">📅 Semaine :</span>
                        <span class="info-value"><?= $selected_week ?> - <?= $selected_year ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation des semaines -->
        <div class="week-navigation">
            <button class="week-btn" onclick="changeWeek(-1)">◀</button>
            <div class="week-display">
                Semaine <?= $selected_week ?> - <?= $selected_year ?>
                <div style="font-size: 14px; font-weight: normal; color: #6c757d;">
                    Du <?= date('d/m', strtotime("{$selected_year}-W{$selected_week}-1")) ?>
                    au <?= date('d/m', strtotime("{$selected_year}-W{$selected_week}-5")) ?>
                </div>
            </div>
            <button class="week-btn" onclick="changeWeek(1)">▶</button>
            <button class="week-btn" onclick="resetWeek()" style="background: #6c757d;">⟳</button>

            <button class="export-btn" onclick="window.print()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: bold;">
                🖨️ Imprimer
            </button>
        </div>

        <!-- Cours actuel et prochain -->
        <div class="current-next-section">
            <div class="current-card">
                <div class="card-header">
                    <div style="font-size: 24px;">⏰</div>
                    <h3>Cours en ce moment</h3>
                </div>

                <?php if ($cours_actuel): ?>
                    <div class="course-details">
                        <div class="detail-item">
                            <span class="detail-label">📚 Cours :</span>
                            <span class="detail-value"><?= htmlspecialchars($cours_actuel['cours']) ?></span>
                        </div>
                        <?php if ($id_statut == 3): ?>
                            <div class="detail-item">
                                <span class="detail-label">🏫 Classe :</span>
                                <span class="detail-value"><?= htmlspecialchars($cours_actuel['classe_nom']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <span class="detail-label">🕐 Horaire :</span>
                            <span class="detail-value">
                                <?= date('H:i', strtotime($cours_actuel['heure_deb'])) ?> -
                                <?= date('H:i', strtotime($cours_actuel['heure_fin'])) ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">📅 Jour :</span>
                            <span class="detail-value"><?= ucfirst($cours_actuel['Jours']) ?></span>
                        </div>
                        <?php if (!empty($cours_actuel['matieres'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">📖 Matière(s) :</span>
                                <span class="detail-value"><?= htmlspecialchars($cours_actuel['matieres']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($id_statut == 4 && !empty($cours_actuel['professeurs'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">👨‍🏫 Professeur(s) :</span>
                                <span class="detail-value"><?= htmlspecialchars($cours_actuel['professeurs']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($id_statut == 3): ?>
                        <div style="margin-top: 15px;">
                            <a href="preappel.php"
                                style="display: inline-block; padding: 10px 20px; background: #0e1f4c; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                                📋 Faire l'appel
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">
                        Aucun cours en ce moment.
                    </p>
                <?php endif; ?>
            </div>

            <div class="next-card">
                <div class="card-header">
                    <div style="font-size: 24px;">📅</div>
                    <h3>Prochain cours</h3>
                </div>

                <?php if ($prochain_cours): ?>
                    <div class="course-details">
                        <div class="detail-item">
                            <span class="detail-label">📚 Cours :</span>
                            <span class="detail-value"><?= htmlspecialchars($prochain_cours['cours']) ?></span>
                        </div>
                        <?php if ($id_statut == 3): ?>
                            <div class="detail-item">
                                <span class="detail-label">🏫 Classe :</span>
                                <span class="detail-value"><?= htmlspecialchars($prochain_cours['classe_nom']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <span class="detail-label">🕐 Horaire :</span>
                            <span class="detail-value">
                                <?= date('H:i', strtotime($prochain_cours['heure_deb'])) ?> -
                                <?= date('H:i', strtotime($prochain_cours['heure_fin'])) ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">📅 Jour :</span>
                            <span class="detail-value"><?= ucfirst($prochain_cours['Jours']) ?></span>
                        </div>
                        <?php if (!empty($prochain_cours['matieres'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">📖 Matière(s) :</span>
                                <span class="detail-value"><?= htmlspecialchars($prochain_cours['matieres']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($id_statut == 4 && !empty($prochain_cours['professeurs'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">👨‍🏫 Professeur(s) :</span>
                                <span class="detail-value"><?= htmlspecialchars($prochain_cours['professeurs']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php
                    if ($current_day_fr == strtolower($prochain_cours['Jours'])) {
                        $heure_deb = strtotime($prochain_cours['heure_deb']);
                        $now = time();
                        $diff = $heure_deb - $now;

                        if ($diff > 0) {
                            $hours = floor($diff / 3600);
                            $minutes = floor(($diff % 3600) / 60);
                            echo "<div style='margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px; text-align: center;'>
                                    <strong>Dans :</strong> ";
                            if ($hours > 0) echo "$hours h ";
                            echo "$minutes min
                                  </div>";
                        }
                    }
                    ?>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">
                        Aucun prochain cours programmé.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <?php if (!empty($edt_data)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?= count($edt_data) ?></div>
                    <div class="label">Cours hebdomadaires</div>
                </div>

                <?php
                $total_heures = 0;
                foreach ($edt_data as $cours) {
                    $debut = strtotime($cours['heure_deb']);
                    $fin = strtotime($cours['heure_fin']);
                    $total_heures += ($fin - $debut) / 3600;
                }
                ?>
                <div class="stat-card">
                    <div class="number"><?= number_format($total_heures, 1) ?></div>
                    <div class="label">Heures par semaine</div>
                </div>

                <?php
                $matieres_uniques = [];
                foreach ($edt_data as $cours) {
                    if (!empty($cours['matieres'])) {
                        $matieres = explode(', ', $cours['matieres']);
                        foreach ($matieres as $matiere) {
                            $matiere = trim($matiere);
                            if (!empty($matiere) && !in_array($matiere, $matieres_uniques)) {
                                $matieres_uniques[] = $matiere;
                            }
                        }
                    }
                }
                ?>
                <div class="stat-card">
                    <div class="number"><?= count($matieres_uniques) ?></div>
                    <div class="label">Matières différentes</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tableau emploi du temps -->
        <?php if (!empty($edt_data)): ?>
            <h2 style="color: #0e1f4c; margin-bottom: 20px;">📋 Emploi du temps de <?= $id_statut == 4 ? 'ma classe' : ($id_statut == 3 ? 'mes cours' : 'toutes les classes') ?></h2>

            <div style="overflow-x: auto;">
                <table class="edt-table">
                    <thead>
                        <tr>
                            <th class="time-header">Heures</th>
                            <?php foreach ($jours_order as $jour): ?>
                                <th><?= ucfirst($jour) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $time_slots = [];
                        for ($h = 8; $h < 18; $h++) {
                            $time_slots[] = sprintf('%02d:00', $h);
                            $time_slots[] = sprintf('%02d:30', $h);
                        }

                        foreach ($time_slots as $index => $time_slot):
                            if ($index % 2 == 0) continue; // Afficher seulement les heures pleines
                            $next_slot = date('H:i', strtotime($time_slot . ' +1 hour'));
                        ?>
                            <tr>
                                <td class="time-header">
                                    <?= $time_slot ?><br>
                                    <small><?= $next_slot ?></small>
                                </td>

                                <?php foreach ($jours_order as $jour): ?>
                                    <td class="cours-cell">
                                        <?php
                                        foreach ($edt_by_day[$jour] as $cours):
                                            $heure_deb = date('H:i', strtotime($cours['heure_deb']));
                                            $heure_fin = date('H:i', strtotime($cours['heure_fin']));

                                            if ($heure_deb >= $time_slot && $heure_deb < $next_slot):
                                                $is_current = ($jour == $current_day_fr &&
                                                    $time_slot <= $current_time &&
                                                    $next_slot > $current_time);
                                        ?>
                                                <div class="cours-item <?= $is_current ? 'current' : '' ?>">
                                                    <div class="cours-title">
                                                        <?= htmlspecialchars($cours['cours']) ?>
                                                        <?php if ($id_statut == 1): ?>
                                                            <br><small>(<?= htmlspecialchars($cours['classe_nom']) ?>)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cours-details">
                                                        <?php if ($id_statut == 3 && !empty($cours['classe_nom'])): ?>
                                                            <div>🏫 <?= htmlspecialchars($cours['classe_nom']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($cours['matieres'])): ?>
                                                            <div>📖 <?= htmlspecialchars($cours['matieres']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($id_statut == 4 && !empty($cours['professeurs'])): ?>
                                                            <div>👨‍🏫 <?= htmlspecialchars($cours['professeurs']) ?></div>
                                                        <?php endif; ?>
                                                        <div class="cours-time">
                                                            <?= $heure_deb ?> - <?= $heure_fin ?>
                                                        </div>
                                                    </div>
                                                </div>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="background: white; padding: 40px; text-align: center; border-radius: 10px; margin-top: 20px;">
                <p style="font-size: 18px; color: #666; margin-bottom: 15px;">
                    <?php if ($id_statut == 4): ?>
                        Votre classe "<?= htmlspecialchars($user_info['classe_nom'] ?? '') ?>" n'a pas d'emploi du temps configuré.
                    <?php elseif ($id_statut == 3): ?>
                        Vous n'avez pas d'emploi du temps assigné.
                    <?php else: ?>
                        Aucun emploi du temps disponible.
                    <?php endif; ?>
                </p>
                <p style="color: #888;">
                    Contactez l'administration pour plus d'informations.
                </p>
            </div>
        <?php endif; ?>

        <!-- Information pour les élèves -->
        <?php if ($id_statut == 4): ?>
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #3498db;">
                <h4 style="color: #0e1f4c; margin-bottom: 10px;">ℹ️ Information pour les élèves</h4>
                <p style="color: #2c3e50; margin-bottom: 5px;">
                    <strong>Votre emploi du temps est celui de votre classe :</strong> <?= htmlspecialchars($user_info['classe_nom']) ?>
                </p>
                <p style="color: #666; font-size: 14px;">
                    Tous les élèves de votre classe ont le même emploi du temps.
                    En cas d'absence, informez votre professeur et l'administration.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Navigation des semaines
        function changeWeek(delta) {
            const urlParams = new URLSearchParams(window.location.search);
            let week = parseInt(urlParams.get('week')) || <?= $current_week ?>;
            let year = parseInt(urlParams.get('year')) || <?= date('Y') ?>;

            week += delta;

            if (week < 1) {
                week = 52;
                year--;
            } else if (week > 52) {
                week = 1;
                year++;
            }

            window.location.href = `edt.php?week=${week}&year=${year}`;
        }

        function resetWeek() {
            window.location.href = 'edt.php';
        }

        // Mettre à jour l'heure en temps réel
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR');
            const dayString = now.toLocaleDateString('fr-FR', {
                weekday: 'long'
            });

            // Met à jour les cours actuels si nécessaire
            const currentCards = document.querySelectorAll('.cours-item.current');
            currentCards.forEach(card => {
                card.classList.remove('current');
            });

            // Marquer le cours actuel
            const currentDayCell = document.querySelector(`td:contains("${dayString}")`);
            if (currentDayCell) {
                // Logique pour marquer le cours actuel
            }
        }

        // Mettre à jour toutes les minutes
        setInterval(updateCurrentTime, 60000);

        // Export PDF (simplifié)
        function exportPDF() {
            alert("Export PDF en cours de développement...");
            // window.print() est déjà disponible
        }
    </script>
</body>

</html>