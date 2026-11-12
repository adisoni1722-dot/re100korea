<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Security Class (Minici v2025.11 - Final)
 * -------------------------------------------------------------
 * 1. XSS 방어 (Output Escaping) - esc()
 * - Context: html(기본), _raw(통과), _unesc(복원)
 * 2. IP 검증 (Trusted Proxy) - getIpAddress()
 * 3. 보안 유틸리티 - getRandomString(), hashEquals()
 * 4. HTML Purifier - purify()
 * -------------------------------------------------------------
 * ※ CSRF 기능은 'crfs.php'에서 별도 처리함
 * -------------------------------------------------------------
 */
class Security
{
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /* ============================================================
     * 1. Output Escaping (XSS 방어)
     * ============================================================ */

    /**
     * 출력 보안 (XSS 방어)
     */
    public static function esc($data, string $context = 'html')
    {

        if (is_array($data)) {
            return array_map(function ($v) use ($context) {
                return self::esc($v, $context);
            }, $data);
        }

        if (empty($data) || !is_string($data)) {
            return $data;
        }

        return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * [External] HTML Purifier (XSS 정제기)
     * 위지윅 에디터 내용을 저장하기 전에 사용
     */
    public function purify($dirty_html)
    {
        // 1. 라이브러리 로드
        if (!class_exists('HTMLPurifier')) {
            $libPath = APPPATH . 'third_party/htmlpurifier/library/HTMLPurifier.auto.php';
            if (file_exists($libPath)) {
                require_once $libPath;
            } else {
                return $dirty_html;
            }
        }

        // 2. 기본 설정 생성
        $config = HTMLPurifier_Config::createDefault();

        // 캐시 경로 설정
        $cache_path = APPPATH . 'cache/htmlpurifier';
        if (!is_dir($cache_path)) {
            mkdir($cache_path, 0777, true);
        }
        $config->set('Cache.SerializerPath', $cache_path);

        // 3. [필수] 커스텀 설정 ID 지정
        $config->set('HTML.DefinitionID', 'custom-iframe-setting');
        $config->set('HTML.DefinitionRev', 1);

        // =================================================================
        // 모든 $config->set()은 여기서 다 끝내야 합니다!
        // =================================================================

        // 4. 허용 태그 목록
        $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,a[href|title|target],ul,ol,li,span[style],img[src|alt|width|height],table,thead,tbody,tr,th,td,h1,h2,h3,h4,blockquote,iframe[src|width|height|allowfullscreen],div[class]');

        // 5. Iframe 보안 설정
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

        // 6. CSS 클래스 허용 (순서 이동됨: 위로 올라옴)
        $config->set('Attr.AllowedClasses', array(
            'video-container' => true,
            'table' => true
        ));

        // 7. 기타 설정 (순서 이동됨: 위로 올라옴)
        $config->set('Attr.EnableID', false);


        // =================================================================
        // [마지막] 여기서 설정을 확정짓고 커스텀 속성을 추가합니다.
        // 이 코드 아래에는 $config->set()을 쓸 수 없습니다.
        // =================================================================
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
        }

        // 8. 정제 실행
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($dirty_html);
    }
    /**
     * 파일명에서 위험한 문자 제거
     */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[\x00-\x1F\x7F\\\\\/]/', '', $filename);
        return trim(str_replace(['..', './'], '', $filename));
    }

    /* ============================================================
     * 2. Secure Utils (보안 유틸리티)
     * ============================================================ */

    public function getRandomString(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    public function hashEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /* ============================================================
     * 3. IP Address (IP 검증)
     * ============================================================ */

    /**
     * Input 클래스와의 호환성을 위해 메서드명 변경 (ip_address -> getIpAddress)
     */
    public function ip_address(): string
    {
        $server  = $_SERVER;
        $trusted = $this->config['trusted_proxies'] ?? [];
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_CLUSTER_CLIENT_IP'
        ];

        $ip = $server['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!empty($trusted) && $this->_isTrustedProxy($ip, $trusted)) {
            foreach ($headers as $header) {
                if (!empty($server[$header])) {
                    foreach (explode(',', $server[$header]) as $spoof) {
                        $spoof = trim($spoof);
                        if ($this->isValidIp($spoof)) return $spoof;
                    }
                }
            }
        }
        return $ip;
    }

    public function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    protected function _isTrustedProxy(string $ip, array $trusted): bool
    {
        foreach ($trusted as $range) {
            if (strpos($range, '/') !== false) {
                if ($this->_checkCidr($ip, $range)) return true;
            } else {
                if ($ip === $range) return true;
            }
        }
        return false;
    }

    protected function _checkCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int)$mask;

        if (strpos($ip, ':') !== false && strpos($subnet, ':') !== false) {
            $ipBin  = @inet_pton($ip);
            $subBin = @inet_pton($subnet);
            if ($ipBin === false || $subBin === false) return false;

            $bytes = $mask >> 3;
            $bits  = $mask % 8;

            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) return false;
            if ($bits === 0) return true;

            $maskByte = 0xFF << (8 - $bits);
            return (ord($ipBin[$bytes]) & $maskByte) === (ord($subBin[$bytes]) & $maskByte);
        } elseif (strpos($ip, '.') !== false && strpos($subnet, '.') !== false) {
            $ipLong   = ip2long($ip);
            $subLong  = ip2long($subnet);
            $maskLong = -1 << (32 - $mask);
            return ($ipLong & $maskLong) === ($subLong & $maskLong);
        }
        return false;
    }
}
