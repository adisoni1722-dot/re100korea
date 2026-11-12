<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** 컨테이너 꺼내기 */
if (!function_exists('app')) {
    function app(string $name)
    {
        if (!class_exists('Container') || !Container::has($name)) {
            throw new RuntimeException("Service '{$name}' not found in Container.");
        }
        return Container::get($name);
    }
}

// ---------------------------------------------
//  간단한 네임스페이스 지원
// ---------------------------------------------
if (!function_exists('autoload_psr4')) {
    function autoload_psr4(string $class): void
    {
        if (class_exists($class, false)) {
            return;
        }

        static $prefixes = null;
        if ($prefixes === null) {
            $prefixes = [
                'App\\'    => realpath(APPPATH) . DIRECTORY_SEPARATOR,
                'Shared\\' => realpath(BASEPATH . 'shared') . DIRECTORY_SEPARATOR,
                // 'MyVendor\Library\\' => realpath(BASEPATH . 'vendor/myvendor/library') . DIRECTORY_SEPARATOR,
            ];
            $prefixes = array_filter($prefixes, fn($path) => $path !== DIRECTORY_SEPARATOR);
        }

        foreach ($prefixes as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }

            $relativeClass = substr($class, $len);
            $file          = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (is_file($file)) {
                $realFile = realpath($file);
                if ($realFile && strpos($realFile, $baseDir) === 0) {
                    require_once $realFile;
                    return;
                }
            }
        }
    }
    spl_autoload_register('autoload_psr4');
}

/** 내부: 설정값 가져오기 (컨테이너 우선, 기본값 폴백) */
function cfg(string $key, $default = null)
{
    static $c = null;
    if ($c === null && class_exists('Container') && Container::has('config')) {
        $c = Container::get('config');
    }
    return is_array($c) && array_key_exists($key, $c) ? $c[$key] : $default;
}


if (!function_exists('session')) {

    function session($key = null, $default = null, $auto_escape = true)
    {
        if (!class_exists('Container') || !Container::has('session')) {
            return $default;
        }
        $s = Container::get('session');

        if ($key === null) {
            return $s;
        }

        $value = $s->get($key) ?? $default;

        if ($auto_escape && is_string($value)) {
            return esc($value);
        }

        return $value;
    }
}

/**
 * XSS 방지용 이스케이프 헬퍼
 */
if (!function_exists('esc')) {

    function esc($data, string $context = 'html')
    {
        return Security::esc($data, $context);
    }
}

if (!class_exists('RawString')) {
    // PHP 7.4 호환: typed property, nullable type 제거
    class RawString
    {
        private $value;
        public function __construct($value = '')
        {
            $this->value = (string)$value;
        }
        public function __toString()
        {
            return $this->value;
        }
    }
}
if (!function_exists('raw')) {
    function raw($value = null)
    {
        return new RawString($value ?? '');
    }
}


if (!function_exists('context')) {
    /**
     * Context 데이터 가져오기 (객체/배열 호환)
     */
    function context(string $key, $default = null)
    {
        // 1. 컨테이너 확인
        if (!class_exists('Container') || !Container::has('context')) {
            return $default;
        }

        $ctx = Container::get('context');

        // Context가 '객체'인 경우 (Context 클래스 사용 시)
        if (is_object($ctx)) {
            // Context 클래스에 get 메서드가 있는지 확인
            if (method_exists($ctx, 'get')) {
                return $ctx->get($key, $default);
            }
            // get 메서드가 없다면 프로퍼티 접근 시도 (혹시 모를 상황 대비)
            return $ctx->$key ?? $default;
        }

        // 3. Context가 '배열'인 경우 (이전 방식 호환)
        if (is_array($ctx)) {
            $val = $ctx[$key] ?? $default;
            // 클로저라면 실행 후 반환
            if ($val instanceof Closure) {
                return $val();
            }
            return $val;
        }

        return $default;
    }
}

/**
 * 내부 헬퍼: 디렉터리 스캔 캐시 관리자
 * 모든 파일/클래스 탐색 함수가 공유하는 단일 캐시
 *
 * @param string $baseDir 스캔할 디렉터리
 * @return array [ext][lower_basename] => RealCaseName.ext 형태의 3D 맵
 */
if (!function_exists('_get_dir_cache')) {
    function _get_dir_cache(string $baseDir): array
    {
        static $unifiedDirCache = [];

        // [1] 절대경로 정규화 (realpath 실패시에도 일관성 유지)
        $path = realpath($baseDir) ?: rtrim($baseDir, DIRECTORY_SEPARATOR);

        // [2] 캐시 히트 시 즉시 반환
        if (isset($unifiedDirCache[$path])) {
            return $unifiedDirCache[$path];
        }

        $map = [];

        // [3] 안전한 디렉터리 스캔
        if (is_dir($path) && ($handle = @opendir($path))) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') continue;

                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (!$ext) continue; // 확장자 없는 경우 무시

                $basename = strtolower(pathinfo($entry, PATHINFO_FILENAME));
                if (!isset($map[$ext])) {
                    $map[$ext] = [];
                }

                // 파일명 충돌 방지를 위해 기존 존재 여부 검사
                if (!isset($map[$ext][$basename])) {
                    $map[$ext][$basename] = $entry;
                }
            }
            closedir($handle);
        }

        // [4] 정렬 (디버깅 및 일관성 보장)
        if (!empty($map)) {
            ksort($map);
            foreach ($map as &$sub) {
                ksort($sub);
            }
            unset($sub);
        }
        // [5] 캐시에 저장 후 반환
        return $unifiedDirCache[$path] = $map;
    }
}


if (!function_exists('find_class')) {
    function find_class(string $name, array $dirs = []): ?string
    {
        static $classCache = [];

        if (isset($classCache[$name])) {
            return $classCache[$name];
        }

        $lowerName = strtolower($name);
        $ext       = 'php'; // 클래스 파일 확장자

        foreach ($dirs as $dir) {
            $path = realpath($dir) ?: rtrim($dir, DIRECTORY_SEPARATOR);
            if (!is_dir($path)) continue;

            // 중앙 디렉터리 캐시 호출
            $dirMap = _get_dir_cache($path);

            // 1) 캐시 구조에서 확장자별 검사
            if (isset($dirMap[$ext][$lowerName])) {
                $entry    = $dirMap[$ext][$lowerName];
                $file     = $path . DIRECTORY_SEPARATOR . $entry;
                $basename = pathinfo($entry, PATHINFO_FILENAME);

                require_once $file;

                // 2) 클래스 검증 (정확히 매칭되는지 확인)
                if (class_exists($basename, false)) {
                    return $classCache[$name] = $basename;
                }

                // 3) fallback — 새로 로드된 클래스 중 마지막 등록된 것
                $declared = get_declared_classes();
                $loaded   = end($declared);
                return $classCache[$name] = $loaded;
            }
        }

        return $classCache[$name] = null;
    }
}

if (!function_exists('find_file')) {
    function find_file(string $dir, string $filename, array $extensions = ['php']): ?string
    {
        static $fileCache = [];

        $key = sha1($dir . '|' . $filename . '|' . implode(',', $extensions));
        if (isset($fileCache[$key])) {
            return $fileCache[$key];
        }

        $baseDir = realpath($dir) ?: rtrim($dir, DIRECTORY_SEPARATOR);
        if (!is_dir($baseDir)) {
            return $fileCache[$key] = null;
        }

        $lowerFilename = strtolower(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim($filename, '/\\')));

        // 1) 빠른 경로 (디스크 직접 확인)
        foreach ($extensions as $ext) {
            $file = $baseDir . DIRECTORY_SEPARATOR . $lowerFilename . '.' . $ext;
            if (is_file($file)) {
                $real = realpath($file);
                return $fileCache[$key] = ($real && strpos($real, $baseDir) === 0) ? $real : $file;
            }
        }

        // 2) fallback: 캐시 기반 탐색
        $dirMap = _get_dir_cache($baseDir);

        foreach ($extensions as $ext) {
            if (isset($dirMap[$ext][$lowerFilename])) {
                $entry = $dirMap[$ext][$lowerFilename];
                $file  = $baseDir . DIRECTORY_SEPARATOR . $entry;
                $real  = realpath($file);
                return $fileCache[$key] = ($real && strpos($real, $baseDir) === 0) ? $real : $file;
            }
        }

        return $fileCache[$key] = null;
    }
}



/**
 * -------------------------------------------------------------
 * Global Helper Wrappers — delegate to service components
 * -------------------------------------------------------------
 */

/* ----------------- helper() ----------------- */
if (!function_exists('helper')) {
    function helper(string $name): void
    {
        if (class_exists('Container') && Container::has('loader')) {
            Container::get('loader')->helper($name);
            return;
        }
        throw new RuntimeException('loader service not initialized: cannot load helper.');
    }
}

/* ----------------- model() ------------------ */
if (!function_exists('model')) {
    function model($name, array $opt = [])
    {
        if (class_exists('Container') && Container::has('loader')) {
            return Container::get('loader')->model($name, $opt);
        }
        throw new RuntimeException('loader service not initialized: cannot load model.');
    }
}

/* ----------------- library() ---------------- */
if (!function_exists('library')) {
    function library($name, array $opt = [])
    {
        if (class_exists('Container') && Container::has('loader')) {
            return Container::get('loader')->library($name, $opt);
        }
        throw new RuntimeException('loader service not initialized: cannot load library.');
    }
}

/* ----------------- view() ------------------- */
if (!function_exists('view')) {
    function view(string $view, array $data = [], bool $useLayout = true): void
    {
        if (class_exists('Container') && Container::has('view')) {
            Container::get('view')->render($view, $data, $useLayout);
            return;
        }
        throw new RuntimeException('View service not initialized: cannot render view.');
    }
}

/* ----------------- redirect() ------------------- */
if (!function_exists('redirect')) {
    function redirect(string $uri = '', string $method = 'auto', ?int $code = null): void
    {
        if (class_exists('Container') && Container::has('response')) {
            Container::get('response')->redirect($uri, $method, $code);
            return;
        }
        throw new RuntimeException('Response service not initialized.');
    }
}

if (!function_exists('input')) {
    function input(?string $type = null, ?string $key = null, $default = null, bool $auto_escape = true)
    {

        // 컨테이너 로드
        $ins = (class_exists('Container') && Container::has('input'))
            ? Container::get('input')
            : new Input(new Security());

        if ($type === null) {
            return $ins;
        }

        $type = strtolower($type);
        $map  = [
            'get'    => 'get',
            'post'   => 'post',
            'var'    => 'var',
            'cookie' => 'cookie',
            'server' => 'server',
            'header' => 'header',
            'method' => 'method',
            'ip'     => 'ip_address',
            'json'   => 'json',
            'raw'    => 'raw_input_stream',
        ];

        if (!isset($map[$type]) || !method_exists($ins, $map[$type])) {
            return $default;
        }

        $m = $map[$type];

        if ($key === null) {
            return $ins->{$m}();
        }

        $val = $ins->{$m}($key);
        $value = $val !== null ? $val : $default;

        if ($auto_escape && is_string($value)) {
            return esc($value);
        }

        return $value;
    }
}

if (!function_exists('uri')) {
    /**
     * uri()                        → Uri 인스턴스
     * uri('segment', 1, $def)      → n번째 세그먼트
     * uri('segments')              → 세그먼트 배열
     * uri('path')                  → 경로 문자열 (/없이)
     * uri('query')                 → 쿼리스트링 (예: foo=1&bar=2)
     */
    function uri(?string $what = null, $arg1 = null, $default = null)
    {
        $u = (class_exists('Container') && Container::has('uri'))
            ? Container::get('uri')
            : new Uri(app('router'));

        if ($what === null) {
            return $u; // 인스턴스 그대로
        }

        $what = strtolower($what);

        // 메서드 유무에 따라 CI3/CI4 이름을 모두 시도 (호환 어댑터)
        $call = function (array $candidates, ...$args) use ($u) {
            foreach ($candidates as $m) {
                if (method_exists($u, $m)) {
                    return $u->{$m}(...$args);
                }
            }
            return null;
        };

        switch ($what) {
            case 'segment':
                $n   = (int) $arg1;
                $val = $call(['getSegment', 'segment'], $n);
                return $val !== null ? $val : $default;

            case 'segments':
                return $call(['getSegments', 'segments']) ?? [];

            case 'path':
                // getPath() 또는 path() 지원
                return $call(['getPath', 'path']) ?? '';

            case 'query':
                // getQuery() 또는 query() 지원
                return $call(['getQuery', 'query']) ?? '';

            case 'string':
            case 'uri_string':
                // CI3의 uri_string 유사
                $path = $call(['getPath', 'path']) ?? '';
                return ltrim($path, '/');
        }

        return $default;
    }
}

if (!function_exists('load_asset')) {
    function load_asset(string $path): string
    {
        return Container::get('asset')->load($path);
    }
}

if (!function_exists('log_message')) {
    /**
     * MiniCI log_message v2 + log rotation (PHP 7.4 호환)
     * -------------------------------------------------------------
     * - dev : error/debug/info 전체 출력
     * - prod: error만 기록
     * - fwrite 기반 안전 기록
     * - 허용 IP 디버그 출력
     * - JSON context 지원
     * - 7일 이상 지난 로그 자동 삭제
     * -------------------------------------------------------------
     */
    function log_message($level = 'error', $message = '', array $context = [])
    {
        static $env, $base_path, $ip_whitelist, $log_errors;

        // 1) 캐시된 설정
        if (!isset($env)) {
            $env = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';
        }
        if (!isset($base_path)) {
            $path      = cfg('log_path', APPPATH . 'logs/');
            $base_path = rtrim((string) $path, '/') . '/';
        }
        if (!isset($log_errors)) {
            $log_errors = (bool) cfg('log_errors', true);
        }
        if (!isset($ip_whitelist)) {
            $ip_whitelist = defined('ALLOWED_IP') ? (array) ALLOWED_IP : [];
        }

        // 2) 환경별 허용 로그 레벨
        switch ($env) {
            case 'development':
                $allowed = ['error', 'debug', 'info'];
                break;
            case 'testing':
                $allowed = ['error', 'info'];
                break;
            default:
                $allowed = ['error'];
        }

        if (!in_array($level, $allowed, true) || !$log_errors) {
            return false;
        }

        // 3) 로그 디렉터리 확인
        if (!is_dir($base_path) && !@mkdir($base_path, 0755, true)) {
            return false;
        }

        // 4) 7일 이상 지난 로그 자동 삭제
        $expire_days = 7;
        $expire_time = time() - ($expire_days * 86400);
        foreach (glob($base_path . 'log-*.php') as $logfile) {
            if (filemtime($logfile) < $expire_time) {
                @unlink($logfile);
            }
        }

        // 5) 파일 경로 (환경 구분)
        $filename = sprintf('log-%s-%s.php', strtolower($env), date('Y-m-d'));
        $file     = $base_path . $filename;
        $date     = date('Y-m-d H:i:s');

        // 6) 메시지 포맷
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line = strtoupper($level) . " - {$date} --> {$message}{$contextStr}" . PHP_EOL;

        // 7) 신규 파일 헤더 추가
        if (!file_exists($file)) {
            $header = "<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>" . PHP_EOL . PHP_EOL;
            @file_put_contents($file, $header, LOCK_EX);
        }

        // 8) 안전한 fwrite
        $fp = @fopen($file, 'ab');
        if ($fp) {
            fwrite($fp, $line);
            fclose($fp);
        } else {
            return false;
        }

        // 9) 개발환경 + 허용 IP면 브라우저 출력
        if ($env === 'development') {
            $client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            if (in_array($client_ip, $ip_whitelist, true)) {
                echo '<pre style="color:#999;font-size:12px;margin:0">[' .
                    strtoupper($level) . '] ' .
                    htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
                    ($contextStr ? ' ' . htmlspecialchars($contextStr, ENT_QUOTES, 'UTF-8') : '') .
                    '</pre>';
            }
        }

        return true;
    }
}

/** --------------------------------------------------------------
 * show_404 / show_error
 * -------------------------------------------------------------- */
if (!function_exists('show_404')) {
    function show_404($page = '', $log_error = true)
    {
        $heading = '404 페이지를 찾을 수 없습니다';
        $message = '요청하신 페이지를 찾을 수 없습니다.';

        if ($log_error) {
            log_message('error', $heading . ': ' . $page);
        }

        if (!headers_sent()) {
            header('HTTP/1.1 404 Not Found');
        }

        echo "<html><head><title>{$heading}</title></head><body>";
        echo "<h1>{$heading}</h1><p>{$message}</p></body></html>";
        exit(4);
    }
}

if (!function_exists('show_error')) {
    function show_error($message, $status_code = 500, $heading = '에러가 발생했습니다')
    {
        $env = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';
        if (!headers_sent()) {
            http_response_code($status_code);
        }

        log_message('error', 'Error: ' . $message);

        if ($env !== 'development') {
            exit(1);
        }

        echo "<html><head><title>{$heading}</title></head><body>";
        echo "<h1>{$heading}</h1><p>{$message}</p></body></html>";
        exit(1);
    }
}



/** --------------------------------------------------------------
 * URL Helpers
 * -------------------------------------------------------------- */

if (!function_exists('base_url')) {
    function base_url($path = '')
    {
        if (class_exists('Container') && Container::has('uri')) {
            return Container::get('uri')->base_url($path);
        }
        return $path; // fallback
    }
}

if (!function_exists('site_url')) {
    function site_url($uri = '')
    {
        if (class_exists('Container') && Container::has('uri')) {
            return Container::get('uri')->site_url($uri);
        }
        // fallback: 단순 base_url 대체
        return base_url($uri);
    }
}

if (!function_exists('current_url')) {
    function current_url($with_query_string = true)
    {
        if (class_exists('Container') && Container::has('uri')) {
            $uri = Container::get('uri');
            return $with_query_string ? $uri->full_url() : $uri->current_url();
        }
        return '';
    }
}

if (!function_exists('full_url')) {
    function full_url(): string
    {
        if (class_exists('Container') && Container::has('uri')) {
            return Container::get('uri')->full_url();
        }

        // fallback (Container 없는 경우)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }
}

if (!function_exists('base_domain')) {
    function base_domain($path = '')
    {
        $url = base_url($path) ?? '';
        $p   = parse_url($url);
        if (!$p || !isset($p['host'])) {
            return '';
        }

        $host = $p['host'];
        if (isset($p['port'])) {
            $host .= ':' . $p['port'];
        }

        return $host;
    }
}

/** --------------------------------------------------------------
 * CSRF 헬퍼
 * -------------------------------------------------------------- */

if (!function_exists('csrf_token_name')) {
    function csrf_token_name(): string
    {
        if (class_exists('Container') && Container::has('csrf')) {
            return Container::get('csrf')->get_csrf_token_name();
        }
        return 'csrf_token_name';
    }
}

if (!function_exists('csrf_hash')) {
    function csrf_hash(): string
    {
        if (class_exists('Container') && Container::has('csrf')) {
            return Container::get('csrf')->get_csrf_hash();
        }
        return '';
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        if (!class_exists('Container') || !Container::has('csrf')) {
            return '';
        }
        return Container::get('csrf')->csrf_field();
    }
}

/* --------------------------------------------------------------
 * FormComponent Facade Helpers
 * -------------------------------------------------------------- */

if (!function_exists('set_value')) {
    function set_value($field, $default = '')
    {
        return Container::get('form')->set_value($field, $default);
    }
}
if (!function_exists('form_error')) {
    function form_error($field, $prefix = '', $suffix = '')
    {
        return Container::get('form')->error($field, $prefix, $suffix);
    }
}

if (!function_exists('set_select')) {
    function set_select($field, $value = '', $default = false)
    {
        return Container::get('form')->set_select($field, $value, $default);
    }
}

if (!function_exists('set_checkbox')) {
    function set_checkbox($field, $value = '', $default = false)
    {
        return Container::get('form')->set_checkbox($field, $value, $default);
    }
}

if (!function_exists('set_radio')) {
    function set_radio($field, $value = '', $default = false)
    {
        return Container::get('form')->set_radio($field, $value, $default);
    }
}

if (!function_exists('validation_errors')) {
    function validation_errors($prefix = '', $suffix = '')
    {
        return Container::get('form')->validation_errors($prefix, $suffix);
    }
}
