<?php
$host = "localhost";
$user = "root";
$password = "Ciel97122!";
$dbname = "projet_bts";


try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $user,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fonction pour ajouter des logs
function addLog($conn, $niveau, $type_action, $id_utilisateur, $description, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Si id_utilisateur est null ou vide, le passer comme null
        if (empty($id_utilisateur)) {
            $id_utilisateur = null;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO logs_systeme 
            (niveau, type_action, id_utilisateur, ip_address, user_agent, description, details)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$niveau, $type_action, $id_utilisateur, $ip, $user_agent, $description, $details]);
    } catch (PDOException $e) {
        // Silencieux en production
        error_log("Erreur logging: " . $e->getMessage());
        return false;
    }
}
?>
