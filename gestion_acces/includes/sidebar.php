<?php
// includes/sidebar.php
// Vérifie si l'utilisateur est connecté avant d'afficher la sidebar
if (!isset($_SESSION['user_id'])) {
    return; // Ne pas afficher la sidebar si non connecté
}

// Récupérer le statut de l'utilisateur pour afficher les liens appropriés
$is_admin = ($_SESSION['id_statut'] == 1);
$is_professeur = ($_SESSION['id_statut'] == 3);
$is_documentaliste = ($_SESSION['id_statut'] == 2);
$is_eleve = ($_SESSION['id_statut'] == 4);

// Récupérer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <style>
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1001;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar .logo {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
            color: white;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-btn {
            font-size: 24px;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: 0.3s;
        }

        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 50px;
            left: 0;
            height: calc(100% - 50px);
            width: 220px;
            background: linear-gradient(180deg, #0e1f4c 0%, #142a5e 100%);
            padding-top: 20px;
            transition: width 0.3s ease;
            overflow-x: hidden;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #ffc107;
        }

        .sidebar a.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: #28a745;
        }

        .sidebar i {
            margin-right: 12px;
            min-width: 24px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar.collapsed .link-text {
            display: none;
        }

        .sidebar-section {
            margin-top: 10px;
            margin-bottom: 10px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 10px;
        }

        .sidebar-section-title {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            padding: 10px 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar.collapsed .sidebar-section-title {
            display: none;
        }

        /* Contenu principal */
        .main-content {
            margin-left: 220px;
            margin-top: 50px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 50px);
        }

        .sidebar.collapsed~.main-content {
            margin-left: 60px;
        }

        /* Déconnexion en bas */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>

<body>
    <!-- Barre du haut -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="toggle-btn" id="toggleBtn">☰</button>
            <div class="logo">GESTION ACCES</div>
        </div>

        <div class="user-info">
            <span>👤 <?= htmlspecialchars($_SESSION['identifiant'] ?? 'Utilisateur') ?></span>
            <span style="font-size: 12px; opacity: 0.8;">
                <?php
                if ($_SESSION['id_statut'] == 1) echo '(Admin)';
                elseif ($_SESSION['id_statut'] == 2) echo '(Documentaliste)';
                elseif ($_SESSION['id_statut'] == 3) echo '(Professeur)';
                elseif ($_SESSION['id_statut'] == 4) echo '(Élève)';
                elseif ($_SESSION['id_statut'] == 5) echo '(Administration)';
                ?>
            </span>
        </div>
    </div>

    <!-- Barre latérale -->
    <div class="sidebar" id="sidebar">
        <!-- LIENS COMMUNS À TOUS -->
        <a href="accueil.php" class="<?= $current_page == 'accueil.php' ? 'active' : '' ?>">
            <i>🏠</i> <span class="link-text">Accueil</span>
        </a>

        <!-- SECTION ADMIN UNIQUEMENT -->
        <?php if ($is_admin): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Administration</div>
                <a href="admin_global.php" class="<?= $current_page == 'admin_global.php' ? 'active' : '' ?>">
                    <i>👑</i> <span class="link-text">Administration Globale</span>
                </a>
                <a href="gestion_utilisateurs.php" class="<?= $current_page == 'gestion_utilisateurs.php' ? 'active' : '' ?>">
                    <i>👥</i> <span class="link-text">Gestion Utilisateurs</span>
                </a>
                <a href="gestion_edt.php" class="<?= $current_page == 'gestion_edt.php' ? 'active' : '' ?>">
                    <i>⚙️</i> <span class="link-text">Gestion EDT</span>
                </a>
                <a href="logs.php" class="<?= $current_page == 'logs.php' ? 'active' : '' ?>">
                    <i>📃</i> <span class="link-text">Historisation</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- SECTION PROFESSEURS -->
        <?php if ($is_professeur || $is_admin): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Espace Professeur</div>
                <a href="edt.php" class="<?= $current_page == 'edt.php' ? 'active' : '' ?>">
                    <i>📅</i> <span class="link-text">Emploi du temps</span>
                </a>
                <a href="preappel.php" class="<?= $current_page == 'preappel.php' ? 'active' : '' ?>">
                    <i>📋</i> <span class="link-text">Préappel</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- SECTION DOCUMENTALISTES -->
        <?php if ($is_documentaliste || $is_admin): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Espace Documentaliste</div>
                <a href="gestion_bibliotheque.php" class="<?= $current_page == 'gestion_bibliotheque.php' ? 'active' : '' ?>">
                    <i>📚</i> <span class="link-text">Gestion distributeur</span>
                </a>
                <a href="simulation_distributeur.php" class="<?= $current_page == 'simulation_distributeur.php' ? 'active' : '' ?>">
                    <i>🤖</i> <span class="link-text">Simulation Distributeur</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- SECTION ÉLÈVES -->
        <?php if ($is_eleve): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Espace Élève</div>
                <a href="edt.php" class="<?= $current_page == 'edt.php' ? 'active' : '' ?>">
                    <i>📅</i> <span class="link-text">Emploi du temps</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- DÉCONNEXION (TOUT LE MONDE) -->
        <a href="../index.php?logout=1" class="logout-link">
            <i>🚪</i> <span class="link-text">Déconnexion</span>
        </a>
    </div>

    <script>
        // Toggle sidebar
        const toggleBtn = document.getElementById('toggleBtn');
        const sidebar = document.getElementById('sidebar');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            // Sauvegarder l'état dans localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

        // Restaurer l'état de la sidebar au chargement
        document.addEventListener('DOMContentLoaded', () => {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
        });
    </script>
</body>

</html>