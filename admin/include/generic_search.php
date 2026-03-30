<?php
// Validación cuando no existen resultados
$c = count($arr);
if ($c === 0) {
    ?>
      <script type='text/javascript'>
        md.showNotificationAdmin(
          "top",
          "right",
          "info",
          "<b>Sin resultados de búsqueda.<b>",
          "info",
          2000
        );
      </script>
    <?php
}
?>