<?php
// admin.php - AU TRÈS DÉBUT
session_start();

// VÉRIFICATION DE CONNEXION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_statut'])) {
    // Non connecté, rediriger vers index.php
    header("Location: ../index.php?error=not_logged_in");
    exit();
}

// VÉRIFICATION DES DROITS (seulement pour admin.php)
if ($_SESSION['id_statut'] != 1) {
    // Pas administrateur, rediriger vers page appropriée
    header("Location: ../index.php?error=access_denied");
    exit();
}

// ... le reste de votre code admin.php
?>