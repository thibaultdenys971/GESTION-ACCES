
<?php
// logout.php - Version garantie
session_start();

// 1. D'abord, afficher ce qu'il y a dans la session AVANT destruction
if (isset($_SESSION['identifiant'])) {
    $user_before = $_SESSION['identifiant'];
    $statut_before = $_SESSION['id_statut'] ?? 'non défini';
} else {
    $user_before = "Aucun utilisateur connecté";
}

// 2. Vider TOUTES les variables de session
$_SESSION = array();

// 3. Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 4. Détruire la session sur le serveur
session_destroy();

// 5. Vérifier que la session est vraiment détruite
session_start(); // Redémarrer pour vérifier
if (empty($_SESSION)) {
    $session_destroyed = true;
} else {
    $session_destroyed = false;
}
session_write_close(); // Fermer à nouveau

// 6. Rediriger vers login avec message
header("Location: ../index.php");
exit();
?>