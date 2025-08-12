<?php
session_start();
// Détruire uniquement la session du confirmateur
unset($_SESSION['confirmateur_id']);
// Si besoin, détruire toute la session :
// session_unset();
// session_destroy();
header('Location: ../login.php');
exit(); 