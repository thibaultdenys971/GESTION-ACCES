<?php
// accueil.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
session_start();

// Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?error=not_logged_in");
    exit();
}

include('../includes/db.php');

// 🔥 AJOUTER ICI - Log de visite de la page d'accueil
addLog($conn, 'info', 'PAGE_VUE', $_SESSION['user_id'], 
        "Accès à la page d'accueil", "Page: accueil.php");

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
try {
    $userStmt = $conn->prepare("
        SELECT u.*, s.statut, c.libelle as classe_nom 
        FROM utilisateur u 
        JOIN statut s ON u.id_statut = s.id_statut 
        JOIN classe c ON u.id_classe = c.id_classe 
        WHERE u.id_utilisateur = ?
    ");
    $userStmt->execute([$user_id]);
    $user_info = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user_info = [];
}

// Statistiques pour l'admin
if ($_SESSION['id_statut'] == 1) {
    try {
        // Statistiques générales
        $statsStmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM utilisateur) as total_users,
                (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 4) as total_eleves,
                (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 3) as total_profs,
                (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 2) as total_doc,
                (SELECT COUNT(*) FROM utilisateur WHERE id_statut = 5) as total_admin
        ");
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stats = [];
    }
}

// Dernières activités (exemples)
$activities = [
    ['icon' => '👤', 'text' => 'Nouvel utilisateur créé', 'time' => 'Il y a 2h'],
    ['icon' => '📚', 'text' => 'Livre "Le Petit Prince" ajouté', 'time' => 'Hier'],
    ['icon' => '✅', 'text' => 'Connexion réussie', 'time' => 'Aujourd\'hui'],
    ['icon' => '👨‍🎓', 'text' => '5 nouveaux élèves inscrits', 'time' => 'Cette semaine'],
];

// Date et heure
$current_date = date('d/m/Y');
$current_day = date('l');
$french_days = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche'
];
$current_day_fr = $french_days[$current_day] ?? $current_day;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - AGTI SAV</title>
    <link rel="icon" type="image/x-icon" href="../assets/css/img/LOGO-LONG.png">
    <style>
        /* Styles spécifiques à l'accueil */
        .welcome-header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 20px rgba(14, 31, 76, 0.2);
        }

        .welcome-text h1 {
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.9;
            max-width: 600px;
        }

        .date-info {
            text-align: right;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 25px;
            border-radius: 10px;
            border-left: 4px solid #ffc107;
        }

        .date-info .day {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .date-info .date {
            font-size: 16px;
            opacity: 0.9;
        }

        /* Grille de dashboard */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Cartes de statistiques */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card.users { border-color: #0e1f4c; }
        .stat-card.eleves { border-color: #28a745; }
        .stat-card.profs { border-color: #fd7e14; }
        .stat-card.doc { border-color: #17a2b8; }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #0e1f4c;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Cartes d'action */
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }

        .action-card:hover {
            transform: translateY(-3px);
        }

        .action-icon {
            font-size: 32px;
            margin-bottom: 15px;
            display: inline-block;
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
        }

        .action-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .action-desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .action-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0e1f4c;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: 0.3s;
        }

        .action-btn:hover {
            background: #1a3a7a;
            transform: translateY(-2px);
        }

        /* Activités récentes */
        .activities-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            grid-column: 1 / -1;
        }

        .activities-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .activities-header h3 {
            color: #0e1f4c;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f8f9fa;
            transition: background 0.3s;
        }

        .activity-item:hover {
            background: #f8f9fa;
            border-radius: 8px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            font-size: 20px;
            margin-right: 15px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 50%;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 12px;
            color: #888;
        }

        /* Informations utilisateur */
        .user-card {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 20px rgba(108, 92, 231, 0.2);
        }

        .user-avatar {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .user-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .user-role {
            display: inline-block;
            padding: 5px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .user-info {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .welcome-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .date-info {
                text-align: center;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        .card {
            animation: fadeIn 0.5s ease-out;
            animation-fill-mode: both;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content fade-in">
        <!-- En-tête de bienvenue -->
        <div class="welcome-header">
            <div class="welcome-text">
                <h1>👋 Bienvenue, <?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?> !</h1>
                <p>Heureux de vous revoir sur votre tableau de bord.</p>
            </div>
            <div class="date-info">
                <div class="day"><?= $current_day_fr ?></div>
                <div class="date"><?= $current_date ?></div>
            </div>
        </div>

        <!-- Grille du tableau de bord -->
        <div class="dashboard-grid">
            <?php if ($_SESSION['id_statut'] == 1): // Admin ?>
                <!-- Cartes de statistiques pour l'admin -->
                <div class="stat-card users">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?= $stats['total_users'] ?? 0 ?></div>
                    <div class="stat-label">Utilisateurs totaux</div>
                </div>

                <div class="stat-card eleves">
                    <div class="stat-icon">👨‍🎓</div>
                    <div class="stat-number"><?= $stats['total_eleves'] ?? 0 ?></div>
                    <div class="stat-label">Élèves</div>
                </div>

                <div class="stat-card profs">
                    <div class="stat-icon">👨‍🏫</div>
                    <div class="stat-number"><?= $stats['total_profs'] ?? 0 ?></div>
                    <div class="stat-label">Professeurs</div>
                </div>

                <div class="stat-card doc">
                    <div class="stat-icon">📚</div>
                    <div class="stat-number"><?= $stats['total_doc'] ?? 0 ?></div>
                    <div class="stat-label">Documentalistes</div>
                </div>

                <!-- Carte d'action rapide pour admin -->
                <div class="action-card">
                    <div class="action-icon">👤</div>
                    <div class="action-title">Créer un nouvel utilisateur</div>
                    <div class="action-desc">Ajoutez rapidement un nouvel élève, professeur ou membre du personnel.</div>
                    <a href="create_user.php" class="action-btn">Créer maintenant</a>
                </div>

                <div class="action-card">
                    <div class="action-icon">🔍</div>
                    <div class="action-title">Rechercher un utilisateur</div>
                    <div class="action-desc">Trouvez rapidement un utilisateur par nom, prénom ou identifiant.</div>
                    <a href="admin.php" class="action-btn">Rechercher</a>
                </div>

            <?php else: // Autres rôles ?>
                <!-- Contenu pour les non-admins -->
                <div class="user-card">
                    <div class="user-avatar">👤</div>
                    <div class="user-name"><?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></div>
                    <div class="user-role"><?= htmlspecialchars($user_info['statut']) ?></div>
                    <div class="user-info">Classe : <?= htmlspecialchars($user_info['classe_nom']) ?></div>
                    <div class="user-info">Identifiant : <?= htmlspecialchars($user_info['identifiant']) ?></div>
                </div>

                <div class="action-card">
                    <div class="action-icon">📚</div>
                    <div class="action-title">Bibliothèque</div>
                    <div class="action-desc">Consultez les livres disponibles et vos emprunts en cours.</div>
                    <a href="bibliotheque.php" class="action-btn">Accéder</a>
                </div>

                <div class="action-card">
                    <div class="action-icon">📅</div>
                    <div class="action-title">Emploi du temps</div>
                    <div class="action-desc">Consultez votre emploi du temps hebdomadaire.</div>
                    <a href="edt.php" class="action-btn">Voir EDT</a>
                </div>

                <div class="action-card">
                    <div class="action-icon">👤</div>
                    <div class="action-title">Mon profil</div>
                    <div class="action-desc">Modifiez vos informations personnelles et votre mot de passe.</div>
                    <a href="profile.php" class="action-btn">Modifier</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Activités récentes -->
        <div class="activities-card">
            <div class="activities-header">
                <h3>📋 Activités récentes</h3>
                <span style="color: #666; font-size: 14px;">Dernières 24h</span>
            </div>
            
            <?php foreach ($activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon"><?= $activity['icon'] ?></div>
                    <div class="activity-content">
                        <div class="activity-text"><?= $activity['text'] ?></div>
                        <div class="activity-time"><?= $activity['time'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Section informations pratiques -->
        <div class="dashboard-grid" style="margin-top: 30px;">
            <div class="action-card">
                <div class="action-icon">📞</div>
                <div class="action-title">Support technique</div>
                <div class="action-desc">Besoin d'aide ? Contactez notre équipe de support.</div>
                <a href="support.php" class="action-btn">Contacter</a>
            </div>

            <div class="action-card">
                <div class="action-icon">📄</div>
                <div class="action-title">Documentation</div>
                <div class="action-desc">Consultez les guides d'utilisation du système.</div>
                <a href="docs.php" class="action-btn">Lire</a>
            </div>

            <?php if ($_SESSION['id_statut'] != 1): ?>
                <div class="action-card">
                    <div class="action-icon">🏆</div>
                    <div class="action-title">Mes badges</div>
                    <div class="action-desc">Consultez vos badges et récompenses.</div>
                    <a href="badges.php" class="action-btn">Voir</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Message du jour (peut être configuré depuis la base) -->
        <?php if ($_SESSION['id_statut'] == 1): ?>
            <div class="action-card" style="margin-top: 30px; background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%); color: white;">
                <div class="action-icon" style="background: rgba(255,255,255,0.2);">💡</div>
                <div class="action-title" style="color: white;">Astuce du jour</div>
                <div class="action-desc" style="color: rgba(255,255,255,0.9);">
                    Utilisez la fonction de recherche avancée dans la page d'administration pour filtrer les utilisateurs par classe, statut ou date d'inscription.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Mettre à jour l'heure en temps réel
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const timeString = now.toLocaleDateString('fr-FR', options);
            const dayElement = document.querySelector('.date-info .day');
            const dateElement = document.querySelector('.date-info .date');
            
            if (dayElement && dateElement) {
                const days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                const dayName = days[now.getDay()];
                const date = now.toLocaleDateString('fr-FR');
                const time = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                
                dayElement.textContent = dayName;
                dateElement.textContent = `${date} - ${time}`;
            }
        }

        // Mettre à jour toutes les minutes
        setInterval(updateTime, 60000);
        updateTime(); // Appel initial

        // Animation au scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observer les cartes
        document.querySelectorAll('.card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s, transform 0.5s';
            observer.observe(card);
        });
    </script>
</body>
</html>
