<?php
// create_user.php
session_start();

// VÉRIFICATION ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['id_statut'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../includes/db.php';

// 🔥 AJOUTER ICI - Log de visite
addLog($conn, 'info', 'PAGE_VUE', $_SESSION['user_id'], 
       "Accès à la création d'utilisateur", "Page: create_user.php");

// Après la création réussie d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    // 🔥 AJOUTER ICI - Log de création
    addLog($conn, 'info', 'CREATION_UTILISATEUR', $_SESSION['user_id'], 
           "Nouvel utilisateur créé", 
           "Nom: $nom $prenom, Statut: $id_statut");
}

$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mot_de_pass = trim($_POST['mot_de_pass'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $id_statut = intval($_POST['id_statut'] ?? 4); // Élève par défaut
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $id_classe = intval($_POST['id_classe'] ?? 1);

    // Validation
    if (empty($identifiant) || empty($mot_de_pass) || empty($confirm_password) || empty($nom) || empty($prenom)) {
        $errorMessage = "Tous les champs sont obligatoires.";
    } elseif ($mot_de_pass !== $confirm_password) {
        $errorMessage = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($mot_de_pass) < 6) {
        $errorMessage = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        try {
            // Vérifier si l'utilisateur existe déjà
            $checkStmt = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE identifiant = ?");
            $checkStmt->execute([$identifiant]);
            
            if ($checkStmt->fetch()) {
                $errorMessage = "Cet identifiant est déjà utilisé.";
            } else {
                // Hasher le mot de passe
                $hashed_password = password_hash($mot_de_pass, PASSWORD_DEFAULT);
                
                // Insérer le nouvel utilisateur
                $insertStmt = $conn->prepare("
                    INSERT INTO utilisateur (identifiant, mot_de_pass, id_statut, nom, prenom, id_classe) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $insertStmt->execute([
                    $identifiant, 
                    $hashed_password, 
                    $id_statut, 
                    $nom, 
                    $prenom, 
                    $id_classe
                ]);
                
                $message = "✅ Utilisateur créé avec succès !";
                
                // Réinitialiser le formulaire
                $_POST = array();
            }
            
        } catch (PDOException $e) {
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
}

// Récupérer la liste des utilisateurs existants
try {
    $usersStmt = $conn->prepare("
        SELECT u.id_utilisateur, u.identifiant, u.nom, u.prenom, s.statut, c.libelle as classe
        FROM utilisateur u 
        JOIN statut s ON u.id_statut = s.id_statut 
        JOIN classe c ON u.id_classe = c.id_classe
        WHERE u.id_statut != 1  -- Ne pas afficher les admins
        ORDER BY u.id_utilisateur DESC
        LIMIT 10
    ");
    $usersStmt->execute();
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// Récupérer la liste des classes
try {
    $classesStmt = $conn->prepare("SELECT * FROM classe ORDER BY libelle");
    $classesStmt->execute();
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $classes = [];
}

// Récupérer la liste des statuts
try {
    $statutsStmt = $conn->prepare("SELECT * FROM statut ORDER BY id_statut");
    $statutsStmt->execute();
    $statuts = $statutsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $statuts = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un utilisateur - AGTI SAV</title>
    <style>
        /* Styles spécifiques à create_user.php */
        .header {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
        }

        .back-btn {
            background: white;
            color: #0e1f4c;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .back-btn:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 1100px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0e1f4c;
            box-shadow: 0 0 0 3px rgba(14, 31, 76, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .password-strength {
            height: 5px;
            background: #eee;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }

        .btn {
            background: linear-gradient(135deg, #0e1f4c 0%, #1a3a7a 100%);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            transition: 0.3s;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover {
            background: linear-gradient(135deg, #1a3a7a 0%, #0e1f4c 100%);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .message {
            padding: 15px;
            border-radius: 6px;
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

        .users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .users-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }

        .users-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-admin { background: #dc3545; color: white; }
        .badge-doc { background: #17a2b8; color: white; }
        .badge-prof { background: #fd7e14; color: white; }
        .badge-eleve { background: #28a745; color: white; }
        .badge-administration { background: #6f42c1; color: white; }

        .password-toggle {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 16px;
        }

        .password-rules {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .user-count {
            margin-top: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
            color: #495057;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <!-- Contenu principal -->
    <div class="main-content" id="mainContent">
        <div class="header">
            <h1>👥 Créer un nouvel utilisateur</h1>
            <a href="admin.php" class="back-btn">← Retour au tableau de bord</a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="content-wrapper">
            <!-- Formulaire de création -->
            <div class="card">
                <div class="card-header">
                    Formulaire de création
                </div>
                <div class="card-body">
                    <form method="POST" id="createUserForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nom">Nom *</label>
                                <input type="text" 
                                       id="nom" 
                                       name="nom" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                                       required
                                       placeholder="Dupont">
                            </div>

                            <div class="form-group">
                                <label for="prenom">Prénom *</label>
                                <input type="text" 
                                       id="prenom" 
                                       name="prenom" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>"
                                       required
                                       placeholder="Jean">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="identifiant">Identifiant *</label>
                            <input type="text" 
                                   id="identifiant" 
                                   name="identifiant" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($_POST['identifiant'] ?? '') ?>"
                                   required
                                   placeholder="jean.dupont">
                            <small style="color: #666; font-size: 12px;">Utilisé pour la connexion</small>
                        </div>

                        <div class="form-group">
                            <label for="mot_de_pass">Mot de passe *</label>
                            <div class="password-toggle">
                                <input type="password" 
                                       id="mot_de_pass" 
                                       name="mot_de_pass" 
                                       class="form-control" 
                                       required
                                       oninput="checkPasswordStrength(this.value)"
                                       placeholder="Minimum 6 caractères">
                                <button type="button" class="toggle-password" onclick="togglePassword('mot_de_pass')">👁️</button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="password-rules" id="passwordRules">
                                ❌ Doit contenir au moins 6 caractères
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe *</label>
                            <div class="password-toggle">
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       required
                                       placeholder="Retapez le mot de passe">
                                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                            </div>
                            <div id="passwordMatch" style="font-size:12px; margin-top:5px;"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="id_statut">Rôle *</label>
                                <select id="id_statut" name="id_statut" class="form-control" required>
                                    <?php foreach ($statuts as $statut): ?>
                                        <option value="<?= $statut['id_statut'] ?>" 
                                            <?= ($_POST['id_statut'] ?? '') == $statut['id_statut'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($statut['statut']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="id_classe">Classe *</label>
                                <select id="id_classe" name="id_classe" class="form-control" required>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?= $classe['id_classe'] ?>" 
                                            <?= ($_POST['id_classe'] ?? '') == $classe['id_classe'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($classe['libelle']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn">
                            📝 Créer l'utilisateur
                        </button>
                    </form>
                </div>
            </div>

            <!-- Liste des utilisateurs récents -->
            <div class="card">
                <div class="card-header">
                    Derniers utilisateurs créés (<?= count($users) ?>)
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">
                            Aucun utilisateur trouvé.
                        </p>
                    <?php else: ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Rôle</th>
                                    <th>Classe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>#<?= $user['id_utilisateur'] ?></td>
                                        <td><strong><?= htmlspecialchars($user['nom']) ?></strong></td>
                                        <td><?= htmlspecialchars($user['prenom']) ?></td>
                                        <td>
                                            <?php 
                                            $badgeClass = 'badge-eleve';
                                            if ($user['statut'] == 'Admin') $badgeClass = 'badge-admin';
                                            elseif ($user['statut'] == 'Documentaliste') $badgeClass = 'badge-doc';
                                            elseif ($user['statut'] == 'Professeur') $badgeClass = 'badge-prof';
                                            elseif ($user['statut'] == 'Administration') $badgeClass = 'badge-administration';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($user['statut']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($user['classe']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="user-count">
                            Derniers 10 utilisateurs créés
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gestion des mots de passe
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleBtn = event.target;
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            const rulesText = document.getElementById('passwordRules');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 25) {
                strengthBar.style.background = '#dc3545';
                rulesText.innerHTML = '❌ Faible';
                rulesText.style.color = '#dc3545';
            } else if (strength < 50) {
                strengthBar.style.background = '#fd7e14';
                rulesText.innerHTML = '⚠️ Moyen';
                rulesText.style.color = '#fd7e14';
            } else if (strength < 75) {
                strengthBar.style.background = '#ffc107';
                rulesText.innerHTML = '✅ Bon';
                rulesText.style.color = '#ffc107';
            } else {
                strengthBar.style.background = '#28a745';
                rulesText.innerHTML = '✅ Excellent';
                rulesText.style.color = '#28a745';
            }
            
            // Vérifier la correspondance des mots de passe
            const confirmPassword = document.getElementById('confirm_password').value;
            checkPasswordMatch(password, confirmPassword);
        }

        function checkPasswordMatch(password, confirmPassword) {
            const matchText = document.getElementById('passwordMatch');
            
            if (!confirmPassword) {
                matchText.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.innerHTML = '✅ Les mots de passe correspondent';
                matchText.style.color = '#28a745';
            } else {
                matchText.innerHTML = '❌ Les mots de passe ne correspondent pas';
                matchText.style.color = '#dc3545';
            }
        }

        // Écouter les changements sur le champ de confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('mot_de_pass').value;
            checkPasswordMatch(password, this.value);
        });

        // Empêcher la soumission si les mots de passe ne correspondent pas
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('mot_de_pass').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas !');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères !');
                return false;
            }
        });
    </script>
</body>
</html>