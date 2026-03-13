<?php
// pages/ajax_set_session.php
session_start();

if (isset($_POST['livre']) && isset($_POST['casier'])) {
    $_SESSION['selected_livre'] = intval($_POST['livre']);
    $_SESSION['selected_casier'] = intval($_POST['casier']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>