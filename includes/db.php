<?php
require_once __DIR__ . '/config.php';
function cs_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    try { $pdo = new PDO('mysql:host=' . CS_DB_HOST . ';dbname=' . CS_DB_NAME . ';charset=utf8mb4', CS_DB_USER, CS_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
    catch (PDOException $e) { throw new RuntimeException('Database unavailable. Import database/climasense.sql in phpMyAdmin and verify includes/config.php.'); }
    return $pdo;
}
