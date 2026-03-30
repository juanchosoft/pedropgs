<?php



//MANTENER LA SESION ABIERTA CON COOKIES
ini_set('session.cache_expire', 800000);
ini_set('session.cache_limiter', 'none');
ini_set('session.cookie_lifetime', 8000000);
ini_set('session.gc_maxlifetime', 800000); //el mas importante


session_start();
if (!isset($_SESSION['session_user'])) {
   header('Location: logout.php');
}
