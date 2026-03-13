<?php
// includes/verifier_identifiant.php
session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unique' => false, 'error' => 'Non authentifié']);
    exit();
}

// Inclusion de la connexion à la base de données
require_once 'db.php';

// Récupérer les paramètres
$identifiant = isset($_GET['identifiant']) ? trim($_GET['identifiant']) : '';
$excludeUserId = isset($_GET['exclude']) && is_numeric($_GET['exclude']) ? intval($_GET['exclude']) : null;

if (empty($identifiant)) {
    echo json_encode(['unique' => false, 'error' => 'Identifiant vide']);
    exit();
}

try {
    // Préparer la requête
    $sql = "SELECT COUNT(*) as count FROM utilisateur WHERE identifiant = ?";
    $params = [$identifiant];
    
    // Si on exclut un utilisateur (pour la modification)
    if ($excludeUserId !== null) {
        $sql .= " AND id_utilisateur != ?";
        $params[] = $excludeUserId;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Retourner le résultat
    echo json_encode([
        'unique' => ($result['count'] == 0),
        'identifiant' => $identifiant,
        'count' => $result['count']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'unique' => false, 
        'error' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
?>