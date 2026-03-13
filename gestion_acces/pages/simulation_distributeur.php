<?php
// pages/simulation_distributeur.php
session_start();

// VÉRIFICATION DE CONNEXION
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?error=not_logged_in");
    exit();
}

include '../includes/db.php';

$message = '';
$errorMessage = '';
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$selected_livre = isset($_SESSION['selected_livre']) ? $_SESSION['selected_livre'] : null;
$selected_casier = isset($_SESSION['selected_casier']) ? $_SESSION['selected_casier'] : null;

// TRAITEMENT DU SCAN BADGE (simulation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ÉTAPE 2: SCAN DU BADGE
    if (isset($_POST['simuler_scan'])) {
        $id_badge = trim($_POST['id_badge']);
        
        try {
            // Vérifier le badge
            $badgeStmt = $conn->prepare("
                SELECT b.*, u.id_utilisateur, u.nom, u.prenom, u.identifiant, s.statut
                FROM badge b
                JOIN utilisateur u ON b.id_utilisateur = u.id_utilisateur
                JOIN statut s ON u.id_statut = s.id_statut
                WHERE b.id_badge = ? AND b.etat = 'actif' AND b.date_expiration >= CURDATE()
            ");
            $badgeStmt->execute([$id_badge]);
            $badge = $badgeStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($badge) {
                $_SESSION['badge_scan'] = $badge;
                $step = 3;
                
                addLog($conn, 'info', 'SCAN_BADGE', $badge['id_utilisateur'], 
                       "Badge scanné au distributeur", "Badge ID: $id_badge");
                
            } else {
                $errorMessage = "❌ Badge invalide, expiré ou inactif !";
            }
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
    
    // ÉTAPE 3: CONFIRMATION DU PRÊT
    elseif (isset($_POST['confirmer_pret'])) {
        $id_ouvrage = intval($_POST['id_ouvrage']);
        $casier = intval($_POST['casier']);
        $badge = $_SESSION['badge_scan'];
        
        try {
            $conn->beginTransaction();
            
            // Vérifier le stock
            $checkStmt = $conn->prepare("
                SELECT id_exemplaire, stock 
                FROM exemplaire 
                WHERE id_ouvrage = ? AND stock > 0
            ");
            $checkStmt->execute([$id_ouvrage]);
            $exemplaire = $checkStmt->fetch();
            
            if ($exemplaire) {
                // Calculer la date de retour (14 jours)
                $date_retour = date('Y-m-d', strtotime('+14 days'));
                
                // Créer le prêt
                $pretStmt = $conn->prepare("
                    INSERT INTO pret (date_pret, date_retour_prevu) 
                    VALUES (CURDATE(), ?)
                ");
                $pretStmt->execute([$date_retour]);
                $pret_id = $conn->lastInsertId();
                
                // Lier l'ouvrage au prêt
                $etreStmt = $conn->prepare("INSERT INTO etre (id_ouvrage, id_pret) VALUES (?, ?)");
                $etreStmt->execute([$id_ouvrage, $pret_id]);
                
                // Lier le badge au prêt
                $realiserStmt = $conn->prepare("INSERT INTO realiser (id_pret, id_badge) VALUES (?, ?)");
                $realiserStmt->execute([$pret_id, $badge['id_badge']]);
                
                // Mettre à jour le stock
                $updateStmt = $conn->prepare("UPDATE exemplaire SET stock = stock - 1 WHERE id_exemplaire = ?");
                $updateStmt->execute([$exemplaire['id_exemplaire']]);
                
                // Marquer comme réservé
                $reserverStmt = $conn->prepare("UPDATE exemplaire SET reserver = reserver + 1 WHERE id_ouvrage = ?");
                $reserverStmt->execute([$id_ouvrage]);
                
                $conn->commit();
                
                // Nettoyer la session
                unset($_SESSION['selected_livre']);
                unset($_SESSION['selected_casier']);
                unset($_SESSION['badge_scan']);
                
                addLog($conn, 'info', 'PRET_DISTRIBUTEUR', $badge['id_utilisateur'], 
                       "Livre emprunté via distributeur", "Livre ID: $id_ouvrage, Casier: $casier");
                
                $message = "✅ Prêt confirmé ! Le livre est tombé dans le bac. Bonne lecture !";
                $step = 1;
                
            } else {
                $errorMessage = "❌ Ce livre n'est plus disponible !";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
    
    // ANNULER ET RECOMMENCER
    elseif (isset($_POST['annuler'])) {
        unset($_SESSION['selected_livre']);
        unset($_SESSION['selected_casier']);
        unset($_SESSION['badge_scan']);
        $step = 1;
        $message = "🔄 Opération annulée. Vous pouvez recommencer.";
    }
}

// RÉCUPÉRATION DES LIVRES DISPONIBLES
$livresStmt = $conn->prepare("
    SELECT o.*, 
           COALESCE(SUM(e.stock), 0) as stock_disponible,
           COALESCE(SUM(e.reserver), 0) as nb_reservations
    FROM ouvrage o 
    LEFT JOIN exemplaire e ON o.id_ouvrage = e.id_ouvrage 
    GROUP BY o.id_ouvrage
    HAVING stock_disponible > 0
    ORDER BY o.titre
");
$livresStmt->execute();
$livres = $livresStmt->fetchAll(PDO::FETCH_ASSOC);

// Assigner des numéros de casier aux livres (1-20)
$casiers = [];
foreach ($livres as $index => $livre) {
    $casiers[$livre['id_ouvrage']] = ($index % 20) + 1;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distributeur de livres - GESTION ACCÈS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
        }

        /* HEADER */
        .header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(14, 31, 76, 0.2);
        }

        .header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        /* INDICATEUR D'ÉTAPE */
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 4px;
            background: #ddd;
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            background: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 4px solid #ddd;
            background: white;
        }

        .step.active {
            border-color: #0e1f4c;
            background: #0e1f4c;
            color: white;
        }

        .step.completed {
            border-color: #28a745;
            background: #28a745;
            color: white;
        }

        .step-label {
            position: absolute;
            top: 70px;
            white-space: nowrap;
            font-size: 14px;
            color: #666;
        }

        /* CARTES */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
        }

        .card-header h2 {
            color: #0e1f4c;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* GRILLE DES LIVRES */
        .livres-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .livre-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            border: 3px solid transparent;
            position: relative;
        }

        .livre-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .livre-card.selected {
            border-color: #0e1f4c;
            background: #e8f0fe;
        }

        .casier-number {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 50px;
            height: 50px;
            background: #0e1f4c;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .livre-titre {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .livre-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .stock-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* SCANNEUR BADGE */
        .scanner-area {
            background: #f8f9fa;
            border: 3px dashed #0e1f4c;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
        }

        .badge-input {
            width: 100%;
            max-width: 400px;
            padding: 15px 20px;
            font-size: 18px;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 20px auto;
            text-align: center;
            letter-spacing: 2px;
        }

        .badge-input:focus {
            outline: none;
            border-color: #0e1f4c;
        }

        .badge-info {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .badge-info p {
            margin: 10px 0;
            font-size: 16px;
        }

        /* BOUTONS */
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 5px;
        }

        .btn-primary {
            background: #0e1f4c;
            color: white;
        }

        .btn-primary:hover {
            background: #1a3a7a;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-large {
            padding: 15px 30px;
            font-size: 18px;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* MESSAGES */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        /* CONFIRMATION PRÊT */
        .pret-confirmation {
            text-align: center;
            padding: 30px;
        }

        .livre-tombe {
            font-size: 60px;
            margin: 20px 0;
            animation: tomber 1s ease-out;
        }

        @keyframes tomber {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 0; }
            100% { transform: translateY(0) rotate(360deg); opacity: 1; }
        }

        .casier-indicateur {
            background: #0e1f4c;
            color: white;
            font-size: 24px;
            font-weight: bold;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .livres-grid {
                grid-template-columns: 1fr;
            }
            
            .steps::before {
                display: none;
            }
            
            .step {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- HEADER -->
        <div class="header">
            <h1>
                <i class="fas fa-robot"></i>
                Distributeur automatique de livres
            </h1>
            <p>Choisissez un livre, scannez votre badge et récupérez votre emprunt</p>
        </div>

        <!-- MESSAGES -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- INDICATEUR D'ÉTAPES -->
        <div class="steps">
            <div class="step <?= $step >= 1 ? 'completed' : '' ?> <?= $step == 1 ? 'active' : '' ?>">
                <span>1</span>
                <span class="step-label">Choix du livre</span>
            </div>
            <div class="step <?= $step >= 2 ? 'completed' : '' ?> <?= $step == 2 ? 'active' : '' ?>">
                <span>2</span>
                <span class="step-label">Scan badge</span>
            </div>
            <div class="step <?= $step >= 3 ? 'completed' : '' ?> <?= $step == 3 ? 'active' : '' ?>">
                <span>3</span>
                <span class="step-label">Confirmation</span>
            </div>
        </div>

        <?php if ($step == 1): ?>
            <!-- ÉTAPE 1: CHOIX DU LIVRE -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-book"></i> Sélectionnez un livre</h2>
                </div>
                
                <?php if (empty($livres)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <p style="font-size: 18px; color: #666;">Aucun livre disponible pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="livres-grid">
                        <?php foreach ($livres as $livre): 
                            $casier_num = $casiers[$livre['id_ouvrage']];
                        ?>
                            <div class="livre-card <?= (isset($_SESSION['selected_livre']) && $_SESSION['selected_livre'] == $livre['id_ouvrage']) ? 'selected' : '' ?>" 
                                 onclick="selectionnerLivre(<?= $livre['id_ouvrage'] ?>, <?= $casier_num ?>)">
                                <div class="casier-number"><?= $casier_num ?></div>
                                <div class="livre-titre"><?= htmlspecialchars($livre['titre']) ?></div>
                                <div class="livre-info">📋 Code: <?= htmlspecialchars($livre['code_barre']) ?></div>
                                <div class="livre-info">🏷️ Cote: <?= htmlspecialchars($livre['cote']) ?></div>
                                <div class="livre-info">📝 État: <?= htmlspecialchars($livre['etat']) ?></div>
                                <div style="margin-top: 15px;">
                                    <span class="stock-badge">📦 Stock: <?= $livre['stock_disponible'] ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="POST" id="formChoixLivre" style="display: none;">
                        <input type="hidden" name="choisir_livre" value="1">
                    </form>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button class="btn btn-primary btn-large" onclick="validerChoixLivre()" id="btnValiderLivre" disabled>
                            <i class="fas fa-arrow-right"></i> Étape suivante : Scanner mon badge
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($step == 2): ?>
            <!-- ÉTAPE 2: SCAN DU BADGE -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-id-card"></i> Scannez votre badge</h2>
                </div>

                <div class="scanner-area">
                    <i class="fas fa-qrcode" style="font-size: 48px; color: #0e1f4c; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 20px;">Présentez votre badge devant le lecteur</h3>
                    
                    <form method="POST">
                        <input type="text" 
                               name="id_badge" 
                               class="badge-input" 
                               placeholder="Numéro du badge"
                               autofocus
                               required>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" name="simuler_scan" class="btn btn-primary btn-large">
                                <i class="fas fa-id-card"></i> Simuler le scan
                            </button>
                            
                            <a href="?step=1" class="btn btn-warning btn-large">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </form>
                    
                    <p style="margin-top: 20px; color: #666; font-size: 14px;">
                        💡 Pour la simulation, entrez un numéro de badge (ex: 1, 5, 8) 
                    </p>
                </div>

                <?php if (isset($_SESSION['selected_livre'])): 
                    $livre_id = $_SESSION['selected_livre'];
                    $casier = $_SESSION['selected_casier'];
                    foreach ($livres as $livre) {
                        if ($livre['id_ouvrage'] == $livre_id) {
                            $livre_info = $livre;
                            break;
                        }
                    }
                ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Livre sélectionné : <strong><?= htmlspecialchars($livre_info['titre']) ?></strong> 
                        (Casier n°<?= $casier ?>)
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($step == 3 && isset($_SESSION['badge_scan'])): 
            $badge = $_SESSION['badge_scan'];
            $livre_id = $_SESSION['selected_livre'];
            $casier = $_SESSION['selected_casier'];
            foreach ($livres as $livre) {
                if ($livre['id_ouvrage'] == $livre_id) {
                    $livre_info = $livre;
                    break;
                }
            }
        ?>
            <!-- ÉTAPE 3: CONFIRMATION DU PRÊT -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-check-circle"></i> Confirmation du prêt</h2>
                </div>

                <div class="pret-confirmation">
                    <!-- Animation livre qui tombe -->
                    <div class="livre-tombe">
                        <i class="fas fa-book" style="color: #0e1f4c;"></i>
                        ⬇️
                    </div>

                    <div class="casier-indicateur">
                        Casier n°<?= $casier ?> ouvert - Le livre est tombé dans le bac !
                    </div>

                    <!-- Récapitulatif -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left;">
                        <h3 style="color: #0e1f4c; margin-bottom: 15px;">📋 Récapitulatif</h3>
                        
                        <p><strong>👤 Emprunteur :</strong> <?= htmlspecialchars($badge['prenom'] . ' ' . $badge['nom']) ?></p>
                        <p><strong>🆔 Identifiant :</strong> <?= htmlspecialchars($badge['identifiant']) ?></p>
                        <p><strong>🎓 Statut :</strong> <?= htmlspecialchars($badge['statut']) ?></p>
                        <p><strong>📖 Livre :</strong> <?= htmlspecialchars($livre_info['titre']) ?></p>
                        <p><strong>📋 Code barre :</strong> <?= htmlspecialchars($livre_info['code_barre']) ?></p>
                        <p><strong>📍 Casier :</strong> N°<?= $casier ?></p>
                        <p><strong>📅 Date de retour :</strong> <?= date('d/m/Y', strtotime('+14 days')) ?></p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="id_ouvrage" value="<?= $livre_id ?>">
                        <input type="hidden" name="casier" value="<?= $casier ?>">
                        
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <button type="submit" name="confirmer_pret" class="btn btn-success btn-large">
                                <i class="fas fa-check"></i> Confirmer le prêt
                            </button>
                            
                            <button type="submit" name="annuler" class="btn btn-danger btn-large">
                                <i class="fas fa-times"></i> Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- BOUTON D'ANNULATION GLOBAL (visible à toutes les étapes) -->
        <?php if ($step > 1): ?>
            <div style="text-align: center; margin-top: 20px;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="annuler" class="btn btn-warning">
                        <i class="fas fa-undo"></i> Recommencer
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- INSTRUCTIONS -->
        <div class="card" style="background: #e8f4fd;">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Comment ça marche ?</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center;">
                <div>
                    <div style="font-size: 40px; color: #0e1f4c; margin-bottom: 10px;">1️⃣</div>
                    <h3>Choisissez un livre</h3>
                    <p>Chaque livre a un numéro de casier (1-20). Notez le numéro.</p>
                </div>
                <div>
                    <div style="font-size: 40px; color: #0e1f4c; margin-bottom: 10px;">2️⃣</div>
                    <h3>Scannez votre badge</h3>
                    <p>Présentez votre badge devant le lecteur pour vous identifier.</p>
                </div>
                <div>
                    <div style="font-size: 40px; color: #0e1f4c; margin-bottom: 10px;">3️⃣</div>
                    <h3>Récupérez votre livre</h3>
                    <p>Le casier s'ouvre, le livre tombe dans le bac. Confirmez le prêt.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedLivre = <?= isset($_SESSION['selected_livre']) ? $_SESSION['selected_livre'] : 'null' ?>;
        let selectedCasier = <?= isset($_SESSION['selected_casier']) ? $_SESSION['selected_casier'] : 'null' ?>;
        const btnValider = document.getElementById('btnValiderLivre');

        function selectionnerLivre(livreId, casierNum) {
            selectedLivre = livreId;
            selectedCasier = casierNum;
            
            // Enlever la classe selected de toutes les cartes
            document.querySelectorAll('.livre-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Ajouter la classe selected à la carte cliquée
            event.currentTarget.classList.add('selected');
            
            // Activer le bouton
            if (btnValider) {
                btnValider.disabled = false;
            }
            
            // Sauvegarder dans la session via AJAX
            fetch('ajax_set_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'livre=' + livreId + '&casier=' + casierNum
            });
        }

        function validerChoixLivre() {
            if (selectedLivre) {
                window.location.href = '?step=2';
            } else {
                alert('Veuillez sélectionner un livre.');
            }
        }

        // Simuler un scan automatique (pour la démo)
        document.addEventListener('DOMContentLoaded', function() {
            const badgeInput = document.querySelector('.badge-input');
            if (badgeInput) {
                badgeInput.focus();
            }
        });

        // Désactiver le bouton si aucun livre sélectionné
        if (btnValider && !selectedLivre) {
            btnValider.disabled = true;
        }
    </script>
</body>
</html>