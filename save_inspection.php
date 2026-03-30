<?php
// Conectar a la base de datos (modifica los datos según tu configuración)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pedro";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Preparar la consulta para insertar los elementos seleccionados
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["inspection_items"])) {
    $items = $_POST["inspection_items"];

    // Limpiar los datos para evitar SQL injection
    foreach ($items as &$item) {
        $item = $conn->real_escape_string($item);
    }

    // Insertar los elementos seleccionados en la tabla 'inspection_items'
    foreach ($items as $item) {
        $sql = "INSERT INTO inspection_items (item_name, checked) VALUES ('$item', 1)";
        if ($conn->query($sql) !== TRUE) {
            echo "Error al guardar los datos: " . $conn->error;
            exit();
        }
    }

    // Redirigir con mensaje de éxito
    $conn->close();
    header("Location: check-list.php?success=true");
    exit();
} else {
    // Si no hay datos enviados, redirigir a la página principal
    header("Location: check-list.php");
    exit();
}
?>
