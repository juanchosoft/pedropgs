 <?php
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Android|iPhone|iPad|iPod|Windows Phone)/i', $_SERVER['HTTP_USER_AGENT'])) {
        include './admin/include/menu_movil.php';
    } else {
        echo '<style>.menu_movil-container { display: none !important; }</style>';
    }
?>
