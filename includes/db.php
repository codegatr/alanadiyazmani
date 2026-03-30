<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    public $pdo = null;
    private static $connectError = null;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (Exception $e) {
            self::$connectError = $e->getMessage();
            $this->pdo = null;
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function isConnected(): bool {
        return self::getInstance()->pdo !== null;
    }

    public static function getError(): ?string {
        return self::$connectError;
    }

    public function query(string $sql, array $params = []): \PDOStatement|false {
        if (!$this->pdo) return false;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (Exception $e) {
            return false;
        }
    }
}

function getDB(): Database {
    return Database::getInstance();
}
