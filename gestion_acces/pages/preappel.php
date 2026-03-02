<?php
// pages/preappel.php
session_start();

// Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?error=not_logged_in");
    exit();
}

include '../includes/db.php';

// 🔥 AJOUTER ICI - Log de visite de la page préappel
addLog($conn, 'info', 'PAGE_VUE', $_SESSION['user_id'], 
       "Accès à la page Préappel", "Page: preappel.php");

// ... dans la validation de l'appel (après le traitement)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['valider_appel'])) {
    // 🔥 AJOUTER ICI - Log de validation d'appel
    addLog($conn, 'info', 'APPEL_VALIDE', $_SESSION['user_id'], 
           "Appel validé pour la classe $classe_nom", 
           "Cours: $cours_nom, Date: $date_cours"); }

// Vérification des droits (professeur ou admin)
$allowed_status = [1, 3]; // Admin et Professeur
if (!in_array($_SESSION['id_statut'], $allowed_status)) {
    header("Location: accueil.php?error=access_denied");
    exit();
}

$message = '';
$errorMessage = '';

// Récupérer l'utilisateur actuel
$user_id = $_SESSION['user_id'];

// Récupérer les matières du professeur
$matieres = [];
$matiereStmt = $conn->prepare("
    SELECT m.id_matiere, m.matiere 
    FROM utilisateur_matiere um
    JOIN matiere m ON um.id_matiere = m.id_matiere
    WHERE um.id_utilisateur = ?
");
$matiereStmt->execute([$user_id]);
$matieres = $matiereStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les cours du jour
$current_date = date('Y-m-d');
$current_day = date('l');
$french_days = [
    'Monday' => 'lundi',
    'Tuesday' => 'mardi',
    'Wednesday' => 'mercredi',
    'Thursday' => 'jeudi',
    'Friday' => 'vendredi'
];
$current_day_fr = $french_days[$current_day];

// Heure actuelle
$current_time = date('H:i:s');

// Récupérer les cours du professeur pour aujourd'hui
// CORRECTION ICI : Utilisez uniquement les colonnes existantes
$coursStmt = $conn->prepare("
    SELECT edt.*, j.Jours, c.cours, cl.libelle as classe_nom,
           m.matiere,
           (SELECT COUNT(*) FROM utilisateur u2 WHERE u2.id_classe = edt.id_classe AND u2.id_statut = 4) as nb_eleves
    FROM emploie_du_temps edt
    JOIN jours j ON edt.id_jours = j.id_jours
    JOIN cours c ON edt.id_cours = c.id_cours
    JOIN classe cl ON edt.id_classe = cl.id_classe
    JOIN edt_mat em ON c.id_cours = em.id_cours
    JOIN matiere m ON em.`id-matiere` = m.id_matiere
    JOIN utilisateur_matiere um ON m.id_matiere = um.id_matiere
    WHERE j.Jours = ? 
    AND um.id_utilisateur = ?
    ORDER BY edt.heure_deb
");
$coursStmt->execute([$current_day_fr, $user_id]);
$cours_du_jour = $coursStmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire d'appel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['valider_appel'])) {
    $id_edt = $_POST['id_edt'];
    $date_cours = $_POST['date_cours'];
    
    try {
        $conn->beginTransaction();
        
        // Vérifier si la table 'appel' existe, sinon la créer
        $tableCheck = $conn->query("SHOW TABLES LIKE 'appel'");
        if ($tableCheck->rowCount() == 0) {
            // Créer la table appel si elle n'existe pas
            $conn->exec("
                CREATE TABLE IF NOT EXISTS appel (
                    id_appel INT AUTO_INCREMENT PRIMARY KEY,
                    id_edt INT NOT NULL,
                    date_cours DATE NOT NULL,
                    heure_validation DATETIME DEFAULT CURRENT_TIMESTAMP,
                    valide_par INT,
                    UNIQUE KEY unique_appel (id_edt, date_cours)
                )
            ");
        }
        
        // Vérifier si la table 'presence' existe, sinon la créer
        $tableCheck2 = $conn->query("SHOW TABLES LIKE 'presence'");
        if ($tableCheck2->rowCount() == 0) {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS presence (
                    id_presence INT AUTO_INCREMENT PRIMARY KEY,
                    id_utilisateur INT NOT NULL,
                    id_edt INT NOT NULL,
                    date_cours DATE NOT NULL,
                    statut ENUM('present', 'absent', 'retard') DEFAULT 'absent',
                    heure_enregistrement DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_presence (id_utilisateur, id_edt, date_cours)
                )
            ");
        }
        
        // 1. Marquer l'appel comme validé
        $appelStmt = $conn->prepare("
            INSERT INTO appel (id_edt, date_cours, heure_validation, valide_par)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE heure_validation = NOW(), valide_par = ?
        ");
        $appelStmt->execute([$id_edt, $date_cours, $user_id, $user_id]);
        
        // 2. Enregistrer les présences des élèves
        if (isset($_POST['presences'])) {
            foreach ($_POST['presences'] as $id_utilisateur => $statut) {
                $presenceStmt = $conn->prepare("
                    INSERT INTO presence (id_utilisateur, id_edt, date_cours, statut, heure_enregistrement)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE statut = ?, heure_enregistrement = NOW()
                ");
                $presenceStmt->execute([$id_utilisateur, $id_edt, $date_cours, $statut, $statut]);
            }
        }
        
        // 3. Enregistrer dans le journal existant
        $journalStmt = $conn->prepare("
            INSERT INTO journal_entrer (date, heure, type_action, details)
            VALUES (CURDATE(), NOW(), 'APPEL_VALIDE', ?)
        ");
        $details = "Appel validé pour le cours ID: $id_edt - Date: $date_cours";
        $journalStmt->execute([$details]);
        
        $conn->commit();
        $message = "✅ Appel validé avec succès !";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $errorMessage = "Erreur lors de la validation : " . $e->getMessage();
    }
}

// Récupérer les élèves d'une classe spécifique
function getElevesByClasse($conn, $id_classe) {
    $stmt = $conn->prepare("
        SELECT u.id_utilisateur, u.nom, u.prenom, u.identifiant, b.id_badge
        FROM utilisateur u
        LEFT JOIN badge b ON u.id_utilisateur = b.id_utilisateur
        WHERE u.id_classe = ? AND u.id_statut = 4
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$id_classe]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les badges détectés récemment (simulation Arduino)
function getBadgesRecents($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT b.id_badge, u.nom, u.prenom, u.identifiant, 
                   u.id_classe, c.libelle as classe_nom,
                   je.date, je.heure
            FROM entrer_badge eb
            JOIN badge b ON eb.id_badge = b.id_badge
            JOIN utilisateur u ON b.id_utilisateur = u.id_utilisateur
            LEFT JOIN classe c ON u.id_classe = c.id_classe
            JOIN journal_entrer je ON eb.id_journal = je.id_journal
            WHERE je.date = CURDATE()
            ORDER BY je.heure DESC
            LIMIT 20
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Si la jointure échoue, retourner un tableau vide
        return [];
    }
}

$badges_recents = getBadgesRecents($conn);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préappel Automatisé - AGTI SAV</title>
    <style>
        /* Styles spécifiques au Préappel */
        :root {
            --present: #28a745;
            --absent: #dc3545;
            --retard: #ffc107;
            --encours: #17a2b8;
        }

        .header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .header h1 {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 28px;
        }

        .time-info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        .time-info .current-time {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .time-info .current-date {
            font-size: 16px;
            opacity: 0.9;
        }

        /* Cartes de cours */
        .cours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .cours-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-top: 4px solid;
            transition: transform 0.3s;
        }

        .cours-card:hover {
            transform: translateY(-5px);
        }

        .cours-card.encours {
            border-color: var(--encours);
            background: linear-gradient(135deg, #ffffff 0%, #e3f2fd 100%);
        }

        .cours-card.futur {
            border-color: #6c757d;
        }

        .cours-card.termine {
            border-color: #28a745;
            opacity: 0.8;
        }

        .cours-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        .cours-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }

        .cours-time {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            color: #495057;
        }

        .cours-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item .label {
            font-weight: 600;
            color: #6c757d;
            font-size: 14px;
        }

        .info-item .value {
            color: #2c3e50;
        }

        /* Badges détectés */
        .badges-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .badge-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--present);
            transition: transform 0.2s;
        }

        .badge-card:hover {
            transform: translateY(-2px);
            background: #e9ecef;
        }

        .badge-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .badge-time {
            font-size: 12px;
            color: #6c757d;
        }

        /* Tableau d'appel */
        .appel-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .appel-table th {
            background: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .appel-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .appel-table tr:hover {
            background: #f8f9fa;
        }

        /* Boutons de statut */
        .statut-btn {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
        }

        .btn-present {
            background: var(--present);
            color: white;
        }

        .btn-absent {
            background: var(--absent);
            color: white;
        }

        .btn-retard {
            background: var(--retard);
            color: #212529;
        }

        .statut-btn:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }

        .statut-btn.active {
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }

        /* Validation d'appel */
        .validation-section {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
            text-align: center;
        }

        .validation-timer {
            font-size: 48px;
            font-weight: bold;
            margin: 20px 0;
            font-family: monospace;
        }

        .validation-btn {
            background: white;
            color: #00b09b;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .validation-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
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

        /* Responsive */
        @media (max-width: 768px) {
            .cours-grid {
                grid-template-columns: 1fr;
            }
            
            .appel-table {
                font-size: 14px;
            }
            
            .validation-timer {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>📋 Préappel Automatisé</h1>
            <div class="time-info">
                <div class="current-day"><?= $current_day_fr ?> <?= date('d/m/Y') ?></div>
                <div class="current-time" id="live-clock"><?= date('H:i:s') ?></div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error"><?= $errorMessage ?></div>
        <?php endif; ?>

        <!-- Badges détectés récemment -->
        <div class="badges-section">
            <h3 style="color: #0e1f4c; margin-bottom: 15px;">🔄 Badges détectés aujourd'hui</h3>
            
            <?php if (empty($badges_recents)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">
                    Aucun badge détecté aujourd'hui. Les badges apparaîtront ici lorsqu'ils seront scannés.
                </p>
            <?php else: ?>
                <div class="badges-grid">
                    <?php foreach ($badges_recents as $badge): ?>
                        <div class="badge-card">
                            <div class="badge-name"><?= htmlspecialchars($badge['prenom'] . ' ' . $badge['nom']) ?></div>
                            <div style="font-size: 13px; color: #495057;">
                                <?php if (!empty($badge['classe_nom'])): ?>
                                    Classe : <?= htmlspecialchars($badge['classe_nom']) ?>
                                <?php else: ?>
                                    Classe : Non définie
                                <?php endif; ?>
                            </div>
                            <div class="badge-time">
                                ⏰ <?= date('H:i', strtotime($badge['heure'])) ?> - <?= $badge['date'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cours du jour -->
        <h2 style="color: #0e1f4c; margin-bottom: 20px;">📅 Mes cours aujourd'hui (<?= $current_day_fr ?>)</h2>
        
        <?php if (empty($cours_du_jour)): ?>
            <div style="background: white; padding: 40px; text-align: center; border-radius: 10px; color: #666;">
                <p style="font-size: 18px;">Aucun cours prévu pour <?= $current_day_fr ?>.</p>
                <p style="font-size: 14px; margin-top: 10px;">Vérifiez votre emploi du temps ou contactez l'administration.</p>
            </div>
        <?php else: ?>
            <div class="cours-grid">
                <?php foreach ($cours_du_jour as $cours): 
                    // Déterminer l'état du cours
                    $heure_deb = strtotime($cours['heure_deb']);
                    $heure_fin = strtotime($cours['heure_fin']);
                    $now = time();
                    
                    // Convertir les heures en timestamp du jour actuel
                    $today = strtotime(date('Y-m-d'));
                    $debut_timestamp = $today + $heure_deb;
                    $fin_timestamp = $today + $heure_fin;
                    
                    $cours_state = '';
                    
                    if ($now < $debut_timestamp - 900) { // 15 minutes avant
                        $cours_state = 'futur';
                    } elseif ($now >= $debut_timestamp - 900 && $now <= $fin_timestamp + 900) { // Pendant ±15min
                        $cours_state = 'encours';
                    } else {
                        $cours_state = 'termine';
                    }
                    
                    // Récupérer les élèves de cette classe
                    $eleves = getElevesByClasse($conn, $cours['id_classe']);
                ?>
                    <div class="cours-card <?= $cours_state ?>">
                        <div class="cours-header">
                            <div class="cours-title"><?= htmlspecialchars($cours['cours']) ?> - <?= htmlspecialchars($cours['matiere']) ?></div>
                            <div class="cours-time"><?= date('H:i', $heure_deb) ?> - <?= date('H:i', $heure_fin) ?></div>
                        </div>
                        
                        <div class="cours-info">
                            <div class="info-item">
                                <span class="label">👥 Classe :</span>
                                <span class="value"><?= htmlspecialchars($cours['classe_nom']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">👨‍🎓 Élèves :</span>
                                <span class="value"><?= $cours['nb_eleves'] ?> inscrits</span>
                            </div>
                            <div class="info-item">
                                <span class="label">📅 Jour :</span>
                                <span class="value"><?= htmlspecialchars($cours['Jours']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">🔄 État :</span>
                                <span class="value">
                                    <?php 
                                        switch($cours_state) {
                                            case 'futur': echo '🕒 À venir'; break;
                                            case 'encours': echo '✅ En cours'; break;
                                            case 'termine': echo '📋 Terminé'; break;
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Bouton pour ouvrir l'appel -->
                        <?php if ($cours_state == 'encours'): ?>
                            <button onclick="openAppelModal(<?= $cours['id_edt'] ?>, '<?= htmlspecialchars($cours['classe_nom']) ?>', '<?= htmlspecialchars($cours['cours']) ?>', <?= $cours['id_classe'] ?>)"
                                    style="width: 100%; padding: 12px; background: #0e1f4c; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                📝 Faire l'appel
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Section simulation Arduino -->
        <div class="validation-section">
            <h3>🔄 Simulation Arduino</h3>
            <p>Après 10 minutes de cours, le système automatique envoie un signal pour validation</p>
            
            <div class="validation-timer" id="arduino-timer">02:00</div>
            
            <p>Le professeur a 2 minutes pour valider l'appel automatisé</p>
            
            <button class="validation-btn" onclick="validerAppelArduino()">
                ✅ Valider l'appel automatisé
            </button>
        </div>
    </div>

    <!-- Modal pour l'appel -->
    <div id="appelModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalTitle" style="color: #0e1f4c;">Appel de la classe</h3>
                <button onclick="closeAppelModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <div id="appelContent">
                <!-- Contenu dynamique -->
            </div>
        </div>
    </div>

    <script>
        // Horloge en temps réel
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR');
            const clockElement = document.getElementById('live-clock');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
            
            // Mettre à jour le timer Arduino
            updateArduinoTimer();
        }
        
        setInterval(updateClock, 1000);
        
        // Timer Arduino
        let arduinoTime = 120; // 2 minutes en secondes
        function updateArduinoTimer() {
            const timerElement = document.getElementById('arduino-timer');
            if (timerElement) {
                const minutes = Math.floor(arduinoTime / 60);
                const seconds = arduinoTime % 60;
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (arduinoTime > 0) {
                    arduinoTime--;
                } else {
                    // Sonner l'alarme (simulation)
                    timerElement.style.color = '#dc3545';
                    timerElement.textContent = "00:00 - VALIDATION REQUISE";
                }
            }
        }
        
        // Modal d'appel
        function openAppelModal(id_edt, classe_nom, cours_nom, id_classe) {
            document.getElementById('modalTitle').textContent = `Appel - ${classe_nom} - ${cours_nom}`;
            
            // Charger les élèves dynamiquement
            fetch(`ajax_get_eleves.php?id_classe=${id_classe}&id_edt=${id_edt}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('appelContent').innerHTML = html;
                    document.getElementById('appelModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('appelContent').innerHTML = '<p>Erreur lors du chargement des élèves.</p>';
                    document.getElementById('appelModal').style.display = 'flex';
                });
        }
        
        function closeAppelModal() {
            document.getElementById('appelModal').style.display = 'none';
        }
        
        // Validation Arduino
        function validerAppelArduino() {
            if (confirm("Valider l'appel automatisé ? Cette action sera enregistrée dans le journal.")) {
                // Simuler l'envoi au serveur
                fetch('ajax_valider_appel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        type: 'arduino',
                        timestamp: new Date().toISOString()
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("✅ Appel validé ! Enregistré dans le journal.");
                        document.getElementById('arduino-timer').textContent = "✅ VALIDÉ";
                        document.getElementById('arduino-timer').style.color = "#28a745";
                    } else {
                        alert("Erreur: " + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert("Erreur de communication avec le serveur.");
                });
            }
        }
        
        // Gestion des statuts dans l'appel
        function setPresence(id_eleve, statut) {
            const buttons = document.querySelectorAll(`[data-eleve="${id_eleve}"]`);
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.statut === statut) {
                    btn.classList.add('active');
                }
            });
            
            // Mettre à jour le champ caché
            const hiddenInput = document.getElementById(`presence_${id_eleve}`);
            if (hiddenInput) {
                hiddenInput.value = statut;
            }
        }
        
        // Initialiser le timer
        setInterval(updateArduinoTimer, 1000);
        updateClock();
        
        // Fermer la modal avec ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAppelModal();
            }
        });
        
        // Fonction globale accessible depuis les iframes/ajax
        window.setPresence = setPresence;
        window.closeAppelModal = closeAppelModal;
    </script>
</body>
</html>