<?php
defined('BASEPATH') or exit('No direct script access allowed');
class DB_Pdo {
    private PDO $pdo;

    public function __construct(array $config) {
        $default_opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $options = ($config['options'] ?? []) + $default_opts;
        $this->pdo = new PDO($config['dsn'], $config['username'], $config['password'], $options);
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch_all(PDOStatement $stmt): array {
        return $stmt->fetchAll();
    }
    
    public function fetch_column(PDOStatement $stmt) {
        return $stmt->fetchColumn();
    }
    
    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}