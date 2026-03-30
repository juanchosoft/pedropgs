<?php
date_default_timezone_set('America/Bogota');

class DbConection
{
  private $host, $user, $pass, $pdo, $dbName, $server_date;

  public function __construct()
  {
    /** Local */
    $this->host   = "localhost";
    $this->user   = "root";
    $this->pass   = "";
    $this->dbName = "pgs";

    // Timestamp (según tu lógica actual)
    $this->server_date = 'DATE_ADD(NOW(),INTERVAL 1 HOUR)';

    $this->pdo = null;
  }

  public function openConect()
  {
    try {
      // ✅ IMPORTANTE: dbname y charset para evitar "No database selected" y problemas de acentos
      $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";

      $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
      ];

      $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);

      // ✅ Compatibilidad (por si tu app depende de utf8)
      $this->pdo->exec("SET NAMES utf8mb4");

      return $this->pdo;

    } catch (PDOException $e) {
      throw new Exception("<p>Error: No puede conectarse con la base de datos.</p><p>" . $e->getMessage() . "</p>\n");
    }
  }

  public function closeConect()
  {
    $this->pdo = null;
  }

  public function getServerDate()
  {
    return $this->server_date;
  }

  public function getDbName()
  {
    return $this->dbName;
  }

  public function getTable($table)
  {
    return $this->dbName . "." . $table;
  }
}
