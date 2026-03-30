<?php
session_start();
$mensaje = '';
if (isset($_SESSION['session_user'])) {
    header('Location: main.php');
} else {
    header('Location: login.php');
}
