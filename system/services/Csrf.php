<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * -------------------------------------------------------------
 * Csrf (세션 기반 CSRF 보호)
 * -------------------------------------------------------------
 * - 네이티브 세션 또는 MiniCI 세션 서비스 사용
 * - DI 구성요소로 등록 (config/app.php 에서 Container::singleton('csrf', fn() => new Csrf(...)))
 * -------------------------------------------------------------
 */
class Csrf
{
    protected array $config = [];
    protected $session = null;

    public function __construct(array $config = [], $session = null)
    {
        $defaults = [
            'csrf_token_name' => 'csrf_token',
            'csrf_expire'     => 7200,
            'csrf_regenerate' => true,
        ];
        $this->config  = $config + $defaults;
        $this->session = $session;
    }

    /* ----------------------------------------------------------
     * 기본 유틸
     * --------------------------------------------------------*/
    protected static function ensureNativeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!@session_start()) {
                throw new RuntimeException('Failed to start session for CSRF');
            }
        }
    }

    protected static function randomToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            return hash('sha256', uniqid(mt_rand(), true));
        }
    }

    protected function getSessionValue(string $key)
    {
        if ($this->session && method_exists($this->session, 'get')) {
            return $this->session->get($key);
        }
        self::ensureNativeSession();
        return $_SESSION[$key] ?? null;
    }

    protected function setSessionValue(string $key, $value): void
    {
        if ($this->session && method_exists($this->session, 'set')) {
            $this->session->set($key, $value);
        } else {
            self::ensureNativeSession();
            $_SESSION[$key] = $value;
        }
    }

    protected function unsetSessionKeys(array $keys): void
    {
        if ($this->session && method_exists($this->session, 'unset')) {
            $this->session->unset($keys);
        } else {
            self::ensureNativeSession();
            foreach ($keys as $k) {
                unset($_SESSION[$k]);
            }
        }
    }

    /* ----------------------------------------------------------
     * 토큰 관리
     * --------------------------------------------------------*/

    public function get_csrf_token_name(): string
    {
        return $this->config['csrf_token_name'] ?? 'csrf_token';
    }

    public function get_csrf_hash(): string
    {
        $tokenName = $this->get_csrf_token_name();
        $timeName  = $tokenName . '_time';

        $token = (string) $this->getSessionValue($tokenName);

        // 만료 체크
        if ($token && !empty($this->config['csrf_expire']) && empty($this->config['csrf_regenerate'])) {
            $created = (int) ($this->getSessionValue($timeName) ?? 0);
            $expire  = (int) $this->config['csrf_expire'];
            if ($expire > 0 && $created > 0 && (time() - $created) >= $expire) {
                $token = '';
            }
        }

        if (!$token) {
            $token = self::randomToken();
            $this->setSessionValue($tokenName, $token);
            $this->setSessionValue($timeName, time());
        }

        return $token;
    }

    public function verify_csrf(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $tokenName = $this->get_csrf_token_name();
        $timeName  = $tokenName . '_time';

        $expect = (string) $this->getSessionValue($tokenName);

        if (!$expect) {
            return false;
        }

        // 만료 체크
        if (empty($this->config['csrf_regenerate']) && !empty($this->config['csrf_expire'])) {
            $created = (int) ($this->getSessionValue($timeName) ?? 0);
            $expire  = (int) $this->config['csrf_expire'];
            if ($expire > 0 && $created > 0 && (time() - $created) >= $expire) {
                $this->unsetSessionKeys([$tokenName, $timeName]);
                return false;
            }
        }

        $valid = hash_equals($expect, $token);

        if ($valid) {
            // 재사용 방지
            $this->unsetSessionKeys([$tokenName, $timeName]);

            // 재발급
            if (!empty($this->config['csrf_regenerate'])) {
                $this->setSessionValue($tokenName, self::randomToken());
                $this->setSessionValue($timeName, time());
            }
        }

        return $valid;
    }


    public function csrf_field(): string
    {
        $name  = $this->get_csrf_token_name();
        $value = $this->get_csrf_hash();

        return sprintf(
            '<input type="hidden" name="%s" id="%s" value="%s">',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        );
    }

    public function regenerate(): string
    {
        $tokenName = $this->get_csrf_token_name();
        $timeName  = $tokenName . '_time';
        $newToken  = self::randomToken();

        $this->setSessionValue($tokenName, $newToken);
        $this->setSessionValue($timeName, time());

        return $newToken;
    }
}
