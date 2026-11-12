<?php

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * -------------------------------------------------------------
 * Session (MiniCI v2025.11)
 * -------------------------------------------------------------
 * - DB / 파일 기반 세션 관리
 * - libsodium 암호화 지원
 * - CI3 호환 메서드 유지
 * - DI 기반 구성 (db, config, security 주입)
 * -------------------------------------------------------------
 */
class Session
{
    protected $db;
    protected array $config;
    protected ?Security $security = null;

    protected static bool $initialized = false;
    protected static bool $handler_registered = false;

    public function __construct($db_instance, array $config, ?Security $security = null)
    {
        $this->db       = $db_instance;
        $this->config   = $config;
        $this->security = $security;

        if (self::$initialized) return;

        $driver = $this->config['session_driver'] ?? 'file';

        // DB 세션 핸들러 등록 (1회만)
        if ($driver === 'database' && !self::$handler_registered) {
            if (session_status() === PHP_SESSION_NONE) {
                session_set_save_handler(
                    [$this, '_open'],
                    [$this, '_close'],
                    [$this, '_read'],
                    [$this, '_write'],
                    [$this, '_destroy'],
                    [$this, '_gc']
                );
                self::$handler_registered = true;
            }
        }

        // 세션 시작
        if (session_status() === PHP_SESSION_NONE) {
            if (headers_sent()) {
                trigger_error('Session cannot start after headers have been sent.', E_USER_WARNING);
                return;
            }

            $lifetime = (int) ($this->config['session_expiration'] ?? 0);
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            ini_set('session.cookie_lifetime', (string) $lifetime);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            $cookie_params = [
                'lifetime' => $lifetime,
                'path'     => $this->config['cookie_path'] ?? '/',
                'domain'   => $this->config['cookie_domain'] ?? '',
                'secure'   => $this->config['cookie_secure'] ?? false,
                'httponly' => $this->config['cookie_httponly'] ?? true,
                'samesite' => $this->config['cookie_samesite'] ?? 'Lax',
            ];

            if (($cookie_params['samesite'] ?? '') === 'None') {
                $cookie_params['secure'] = true;
            }

            session_set_cookie_params($cookie_params);
            session_name($this->config['session_cookie_name'] ?? 'ci_session');

            try {
                session_start();
            } catch (Throwable $e) {
                log_message('error', 'Session start failed: ' . $e->getMessage());
                return;
            }

            // ID 주기적 재생성
            if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
                $update = (int) ($this->config['session_update'] ?? 300);
                if (!isset($_SESSION['last_regenerate']) || time() - $_SESSION['last_regenerate'] > $update) {
                    if (!headers_sent()) {
                        @session_regenerate_id(true);
                        $_SESSION['last_regenerate'] = time();
                    }
                }
            }
        }

        self::$initialized = true;
    }

    /* -------------------------------------------------------------
     * IP 보안 헬퍼
     * ------------------------------------------------------------- */
    private function getClientIp(): string
    {
        $safe = (bool) ($this->config['session_match_ip_safe'] ?? false);

        if ($this->security && method_exists($this->security, 'ip_address')) {
            $ip = (string) $this->security->ip_address($safe);
            if ($ip !== '') return $ip;
        }

        $raw = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip  = filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        return $ip ?: '0.0.0.0';
    }

    /* -------------------------------------------------------------
     * libsodium 암호화
     * ------------------------------------------------------------- */
    private function deriveKeyFromConfig(): string
    {
        $raw = $this->config['encryption_key'] ?? '';
        if ($raw === '' || !is_string($raw)) {
            throw new RuntimeException('Session encryption_key is not set or invalid');
        }
        return hash('sha256', $raw, true);
    }

    private function encrypt(string $plaintext, string $key32): string
    {
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key32);
        return base64_encode($nonce . $cipher);
    }

    private function decrypt(string $b64, string $key32): string
    {
        $bin = base64_decode($b64, true);
        if ($bin === false || strlen($bin) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 16) return '';
        $nonce  = substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $key32);
        return $plain === false ? '' : $plain;
    }

    public function set($data, $value = null)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $_SESSION[$k] = $v;
            }
            return;
        }

        $_SESSION[$data] = $value;
    }

    /**
     * 데이터 조회 (CI4 Base)
     * $session->get('item');
     * $session->get(); // 전체 반환
     */
    public function get(?string $key = null)
    {
        if ($key === null) {
            return $_SESSION;
        }
        return $_SESSION[$key] ?? null;
    }

    /**
     * 데이터 삭제 (CI4 Base)
     * $session->remove('item');
     */
    public function remove($key)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                unset($_SESSION[$k]);
            }
            return;
        }
        unset($_SESSION[$key]);
    }

    /**
     * 키 존재 여부 확인 (CI4 Base)
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }


    /**
     * 세션 파괴 (CI4 Base)
     * 사용법: $session->destroy();
     */
    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$initialized = false;
    }

    /**
     * 세션 ID 재생성 (CI4 Base)
     * 사용법: $session->regenerate(); 또는 $session->regenerate(true);
     */
    public function regenerate(bool $destroy = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroy);
            $_SESSION['last_regenerate'] = time();
        }
    }

    /**
     * -------------------------------------------------------------
     * [Alias] CI3 Compatibility (호환성 지원)
     * -------------------------------------------------------------
     */

    public function set_userdata($key, $value = null)
    {
        $this->set($key, $value);
    }

    public function userdata($key = null)
    {
        return $this->get($key);
    }

    public function unset_userdata($key)
    {
        $this->remove($key);
    }

    public function all_userdata()
    {
        return $this->get();
    }

    public function has_userdata(string $key): bool
    {
        return $this->has($key);
    }

    public function sess_destroy(): void
    {
        $this->destroy();
    }

    public function sess_regenerate(bool $destroy = false): void
    {
        $this->regenerate($destroy);
    }

    /* -------------------------------------------------------------
     * 세션 핸들러 (DB)
     * ------------------------------------------------------------- */
    public function _open(): bool
    {
        return true;
    }
    public function _close(): bool
    {
        return true;
    }

    public function _read(string $id): string
    {
        $this->db->where('id', $id);
        if (($this->config['session_match_ip'] ?? false) === true) {
            $this->db->where('ip_address', $this->getClientIp());
        }

        $query = $this->db->limit(1)->get($this->config['session_table_name'] ?? 'sessions');
        $row = $query->row_array();

        if (!$row) return '';

        $timestamp = (int) $row['timestamp'];
        $expire    = (int) ($this->config['session_expiration'] ?? 7200);
        if ($expire > 0 && (time() - $timestamp) > $expire) {
            $this->_destroy($id);
            return '';
        }

        if (empty($row['data'])) return '';

        $key32 = $this->deriveKeyFromConfig();
        return $this->decrypt($row['data'], $key32);
    }

    public function _write(string $id, string $data): bool
    {
        try {
            $table = $this->config['session_table_name'] ?? 'sessions';
            $key32 = $this->deriveKeyFromConfig();
            $enc   = $this->encrypt($data, $key32);

            $sql = sprintf(
                "INSERT INTO %s (id, ip_address, timestamp, data)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE ip_address=VALUES(ip_address),
                 timestamp=VALUES(timestamp), data=VALUES(data)",
                Database::protect_identifier($table)
            );

            return $this->db->driver->execute($sql, [
                (string)$id,
                (string)$this->getClientIp(),
                (string)time(),
                (string)$enc,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Session write error: ' . $e->getMessage());
            return false;
        }
    }

    public function _destroy(string $id): bool
    {
        return $this->db
            ->where('id', $id)
            ->delete($this->config['session_table_name'] ?? 'sessions');
    }

    public function _gc(int $maxlifetime): bool
    {
        $expire_time = time() - (int)($this->config['session_expiration'] ?? 7200);
        return $this->db
            ->where('timestamp <', $expire_time)
            ->delete($this->config['session_table_name'] ?? 'sessions');
    }

    public function __destruct()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }
}
