<?php
session_start();
include 'C:\wamp64\www\exophp\gestion_acces\includes\db.php';

// LOGGER LA VISITE DE LA PAGE DE CONNEXION (uniquement si pas déjà fait dans la session)
if (!isset($_SESSION['log_page_vue'])) {
    addLog($conn, 'info', 'PAGE_VUE', null, "Accès à la page de connexion", "Page: index.php");
    $_SESSION['log_page_vue'] = true;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identifiant = trim($_POST['identifiant'] ?? '');
    $mot_de_pass = trim($_POST['mot_de_pass'] ?? '');

    if (!empty($identifiant) && !empty($mot_de_pass)) {

        try {
            $stmt = $conn->prepare("
                SELECT id_utilisateur, identifiant, mot_de_pass, id_statut, nom, prenom 
                FROM utilisateur 
                WHERE identifiant = :identifiant
            ");
            $stmt->execute(['identifiant' => $identifiant]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                if (password_verify($mot_de_pass, $user['mot_de_pass'])) {

                    $_SESSION['user_id'] = $user['id_utilisateur'];
                    $_SESSION['identifiant'] = $user['identifiant'];
                    $_SESSION['id_statut'] = $user['id_statut'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];

                    // 🔥 LOGGER LA CONNEXION RÉUSSIE
                    addLog(
                        $conn,
                        'info',
                        'CONNEXION',
                        $user['id_utilisateur'],
                        "Connexion réussie - {$user['prenom']} {$user['nom']}",
                        "Statut ID: {$user['id_statut']}, Identifiant: {$user['identifiant']}"
                    );

                    // 🔀 Redirection selon le rôle
                    switch ($user['id_statut']) {
                        case 1:
                            header("Location: pages/accueil.php");
                            break;
                        case 2:
                            header("Location: pages/technicien.php");
                            break;
                        default:
                            header("Location: pages/edt.php");
                            break;
                    }
                    exit();
                } else {
                    // 🔥 LOGGER LE MOT DE PASSE INCORRECT
                    addLog(
                        $conn,
                        'warning',
                        'CONNEXION_ECHOUEE',
                        null,
                        "Tentative de connexion échouée - Mot de passe incorrect",
                        "Identifiant: $identifiant"
                    );

                    $errorMessage = "Mot de passe incorrect.";
                }
            } else {
                // 🔥 LOGGER L'UTILISATEUR NON TROUVÉ
                addLog(
                    $conn,
                    'warning',
                    'CONNEXION_ECHOUEE',
                    null,
                    "Tentative de connexion échouée - Utilisateur non trouvé",
                    "Identifiant: $identifiant"
                );

                $errorMessage = "Utilisateur non trouvé.";
            }
        } catch (PDOException $e) {
            // 🔥 LOGGER L'ERREUR SQL
            addLog(
                $conn,
                'error',
                'ERREUR_SQL',
                null,
                "Erreur SQL lors de la connexion",
                $e->getMessage()
            );

            $errorMessage = "Erreur technique. Veuillez réessayer.";
        }
    } else {
        // 🔥 LOGGER CHAMPS VIDES
        addLog(
            $conn,
            'info',
            'CONNEXION_INCOMPLETE',
            null,
            "Tentative de connexion avec champs vides",
            "Identifiant: " . ($identifiant ?: 'vide')
        );

        $errorMessage = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Connexion - GESTION ACCÈS</title>

    <style>
        body.login-page {
            background: url("/exophp/gestion_acces/assets/css/img/fdc.jpg") no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            position: relative;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body.login-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(14, 31, 76, 0.85);
            z-index: 0;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 40px 35px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-logo {
            font-size: 28px;
            font-weight: bold;
            color: #0e1f4c;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-logo span {
            font-size: 32px;
        }

        .login-subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .input-field {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .input-field:focus {
            border-color: #0e1f4c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(14, 31, 76, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: #0e1f4c;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .login-btn:hover {
            background: #1a3a7a;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(14, 31, 76, 0.3);
        }

        .login-error {
            background: #fef2f2;
            color: #b91c1c;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #b91c1c;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        .login-error::before {
            content: "⚠️";
            font-size: 16px;
        }

        .login-footer {
            margin-top: 25px;
            color: #999;
            font-size: 12px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .login-footer a {
            color: #0e1f4c;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .info-message {
            background: #e3f2fd;
            color: #0c5460;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-top: 15px;
            border-left: 4px solid #0e1f4c;
        }
    </style>
</head>

<body class="login-page">

    <div class="login-container">
        <div class="login-card">

            <div class="login-logo">
                <span>🔐</span> GESTION ACCÈS
            </div>
            <div class="login-subtitle">Plateforme de gestion des accès et des présences</div>

            <?php if (isset($errorMessage) && $errorMessage): ?>
                <div class="login-error">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['inscription']) && $_GET['inscription'] == 'success'): ?>
                <div class="login-error" style="background: #d4edda; color: #155724; border-left-color: #28a745;">
                    ✅ Inscription réussie ! Vous pouvez vous connecter.
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="form-group">
                    <label>👤 Identifiant</label>
                    <input type="text"
                        name="identifiant"
                        class="input-field"
                        placeholder="Votre identifiant"
                        value="<?= htmlspecialchars($_POST['identifiant'] ?? '') ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>🔑 Mot de passe</label>
                    <input type="password"
                        name="mot_de_pass"
                        class="input-field"
                        placeholder="Votre mot de passe"
                        required>
                </div>

                <button type="submit" class="login-btn">
                    Se connecter →
                </button>

            </form>

            <div class="info-message">
                💡 Utilisez vos identifiants fournis par l'administration.
            </div>

            <div class="login-footer">
                © 2026 Gestion Accès - Lycée Bel Air<br>
                <small>Version 2.0 - Système de logs actif</small>
            </div>

        </div>
    </div>

</body>

</html>