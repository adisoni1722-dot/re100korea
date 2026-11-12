<?php

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

class DB_Mysqli
{
    private mysqli $mysqli;
    private string $last_query  = '';
    private bool $trans_enabled = false;
    private int $affected_rows  = 0;

    public function __construct(array $config)
    {

        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        } else {
            error_log('[MiniCI] Warning: mysqli extension not loaded. Check CLI PHP configuration.');
        }

        try {
            $this->mysqli = new mysqli(
                $config['hostname'],
                $config['username'],
                $config['password'],
                $config['database'],
                isset($config['port']) ? (int) $config['port'] : 3306
            );
            $this->mysqli->set_charset($config['charset'] ?? 'utf8mb4');
        } catch (mysqli_sql_exception $e) {
            throw new mysqli_sql_exception('❌ DB 연결 실패: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }


    public function query(string $sql, array $params = [])
    {
        $this->_log_query($sql);
        $this->last_query = $this->interpolate_query($sql, $params);

        try {
            // 항상 prepared statement 실행
            return $this->execute_prepared_statement($sql, $params, true);
        } catch (mysqli_sql_exception $e) {

            $this->formatError($e, $sql);
            throw $e;
        }
    }

    public function execute(string $sql, array $params = []): bool
    {
        $this->_log_query($sql);
        $this->last_query = $this->interpolate_query($sql, $params);

        try {
            return $this->execute_prepared_statement($sql, $params, false);
        } catch (mysqli_sql_exception $e) {

            $this->formatError($e, $sql);

            throw $e;
        }
    }

    // --------------------------------------------------------------------
    // Prepared Statement 실행 (리팩토링 및 안정성 강화)
    // --------------------------------------------------------------------

    private function execute_prepared_statement(string $sql, array $params, bool $get_result)
    {
        // [안정성 강화] prepare 실패는 SQL 문법 오류이므로 즉시 예외를 발생시킴
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt === false) {
            throw new mysqli_sql_exception($this->mysqli->error, $this->mysqli->errno);
        }

        if (!empty($params)) {
            $types        = '';
            $bound_params = [];

            foreach ($params as $key => $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_bool($param)) {
                    $types .= 'i';
                    $params[$key] = (int) $param;
                } else {
                    $types .= 's';
                }
                $bound_params[] = &$params[$key];
            }

            if ($types) {
                $stmt->bind_param($types, ...$bound_params);
            }
        }

        $stmt->execute();

        $this->affected_rows = $stmt->affected_rows;

        if ($get_result) {
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }

        $success = $this->affected_rows > -1;
        $stmt->close();
        return $success;
    }

    // --------------------------------------------------------------------
    // 에러 메시지 포맷
    // --------------------------------------------------------------------
    private function formatError(mysqli_sql_exception $e, string $sql): string
    {
        // [!] 예외 객체에서 직접 파일/라인/코드/메시지 추출
        $error_file = $e->getFile();
        $error_line = $e->getLine();
        $error_code = $e->getCode();
        $error_msg  = $e->getMessage();


        $last_query_for_log = $this->last_query ?: $this->interpolate_query($sql, []);

        $msg = "❌ DB ERROR [{$error_code}]: {$error_msg}\n"
            . "📍 File: {$error_file}\n"
            . "📏 Line: {$error_line}\n"
            . "🧩 SQL: {$last_query_for_log}";

        if (function_exists('log_message')) {
            log_message('error', $msg);
        } else {
            error_log($msg);
        }

        return $msg;
    }

    // --------------------------------------------------------------------
    // DB 정보 메서드
    // --------------------------------------------------------------------
    public function last_query(): string
    {
        return $this->last_query;
    }

    public function insert_id(): int
    {
        return $this->mysqli->insert_id;
    }

    public function affected_rows(): int
    {
        // ⭐️ [개선 4] $mysqli 객체 대신 클래스 속성에 저장된 값을 반환
        return $this->affected_rows;
    }

    public function error(): array
    {
        if ($this->mysqli->connect_errno) {
            return ['code' => $this->mysqli->connect_errno, 'message' => $this->mysqli->connect_error];
        }
        return ['code' => $this->mysqli->errno, 'message' => $this->mysqli->error];
    }

    // --------------------------------------------------------------------
    // 트랜잭션 메서드
    // --------------------------------------------------------------------
    public function beginTransaction(): bool
    {
        if ($this->trans_enabled) {
            return true;
        }
        $this->trans_enabled = true;
        return $this->mysqli->begin_transaction();
    }

    public function commit(): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }
        $this->trans_enabled = false;
        return $this->mysqli->commit();
    }

    public function rollBack(): bool
    {
        if (!$this->trans_enabled) {
            return false;
        }
        $this->trans_enabled = false;
        return $this->mysqli->rollback();
    }

    // --------------------------------------------------------------------
    // Helper
    // --------------------------------------------------------------------
    private function _log_query(string $sql): void
    {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            if (class_exists('Database')) {
                Database::$queries[] = $sql;
            }
        }
    }

    private function interpolate_query(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }

        foreach ($params as $p) {
            if (is_string($p)) {
                $value = "'" . $this->mysqli->real_escape_string($p) . "'";
            } elseif (is_int($p) || is_float($p)) {
                $value = (string) $p;
            } elseif (is_bool($p)) {
                $value = $p ? '1' : '0';
            } elseif (is_null($p)) {
                $value = 'NULL';
            } else {
                // object, array 등 기타 타입은 안전하게 문자열화
                $value = "'" . $this->mysqli->real_escape_string((string) $p) . "'";
            }

            // 첫 번째 ? 만 치환
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }
}
