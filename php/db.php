<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '1521';
$service = $_ENV['DB_SERVICE'] ?? 'XE';
$username = $_ENV['DB_USER'] ?? '';
$password = $_ENV['DB_PASS'] ?? '';

if (!$username || !$password) {
    throw new RuntimeException('Database credentials are not configured.');
}

$tns = "
(DESCRIPTION=
    (ADDRESS_LIST=
        (ADDRESS=(PROTOCOL=TCP)(HOST={$host})(PORT={$port}))
    )
    (CONNECT_DATA=
        (SERVICE_NAME={$service})
    )
)";

$dsn = "oci:dbname={$tns};charset=utf8";

$pdo = new PDO($dsn, $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

return $pdo;