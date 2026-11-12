<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * -------------------------------------------------------------
 * MiniCI Router (Final Clean Version)
 * - PHP 7.4 Compatible
 * -------------------------------------------------------------
 */
class Router
{
    protected string $raw_uri;
    protected string $controllers_path;
    protected array $config = [];

    /** @var array 라우트 규칙 저장소 */
    protected array $routes = [];

    /** URI 세그먼트 (Router 내부용) */
    public array $segments  = [];
    public array $rsegments = [];

    /** 라우팅 결과 */
    public ?string $class     = null;
    public ?string $method    = null;
    public ?string $directory = null;

    protected static array $reflection_cache = [];
    protected static array $method_cache     = [];

    public function __construct(string $request_uri, array $config = [])
    {
        $this->config           = $config;
        $this->controllers_path = APPPATH . 'controllers' . DIRECTORY_SEPARATOR;

        $default_controller = 'Home';
        $default_method     = 'index';

        // ------------------------------------------------------------
        // 2. routes.php 로드 (설정 오버라이딩)
        // ------------------------------------------------------------
        $route_file = APPPATH . 'config/routes.php';
        if (file_exists($route_file)) {
            $loaded = include $route_file;

            // 배열 반환(return [])과 변수 할당($route) 방식 모두 호환
            if (!is_array($loaded) && isset($route) && is_array($route)) {
                $loaded = $route;
            }

            if (is_array($loaded)) {
                $this->routes = $loaded;

                // routes.php에 정의된 값이 있다면 기본값 교체
                if (!empty($loaded['default_controller'])) {
                    $default_controller = $loaded['default_controller'];
                }
                if (!empty($loaded['default_method'])) {
                    $default_method = $loaded['default_method'];
                }

                // 기타 설정 병합
                if (isset($loaded['404_override'])) {
                    $this->config['404_override'] = $loaded['404_override'];
                }
                if (isset($loaded['translate_uri_dashes'])) {
                    $this->config['translate_uri_dashes'] = $loaded['translate_uri_dashes'];
                }
            }
        }

        // 최종 결정된 기본값을 내부 config에 저장 (이후 로직에서 사용)
        $this->config['default_controller'] = $default_controller;
        $this->config['default_method']     = $default_method;

        // ------------------------------------------------------------
        // 3. URI 파싱
        // ------------------------------------------------------------
        $this->raw_uri   = $this->sanitize_uri($request_uri);
        $this->segments  = $this->explode_segments($this->raw_uri);
        $this->rsegments = $this->segments;

        // 첫 번째 세그먼트(컨트롤러)는 소문자로 통일
        if (!empty($this->segments[0])) {
            $this->segments[0] = strtolower($this->segments[0]);
        }
    }

    /** ----------------------------------------------------------
     * URI 정리 + 보안 필터
     * ---------------------------------------------------------- */
    protected function sanitize_uri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '';
        $base = $this->config['base_url'] ?? '';

        if ($base) {
            $basePath = parse_url($base, PHP_URL_PATH) ?: '';
            if ($basePath && strpos($path, $basePath) === 0) {
                $path = substr($path, strlen($basePath));
            }
        }

        $path = rawurldecode($path);
        $path = preg_replace('#/+#', '/', $path);

        // Null Byte Attack 방지
        if (strpos($path, "\0") !== false || stripos($path, '%00') !== false) {
            throw new RuntimeException('Invalid request', 400);
        }

        return trim($path, '/');
    }

    /** ----------------------------------------------------------
     * 세그먼트 분리
     * ---------------------------------------------------------- */
    protected function explode_segments(string $uri): array
    {
        return $uri === '' ? [] : array_values(array_filter(explode('/', $uri), 'strlen'));
    }

    /** ----------------------------------------------------------
     * 컨트롤러 탐색 (Legacy find_class)
     * ---------------------------------------------------------- */
    private function findController(): ?array
    {
        $segments  = $this->segments;
        $directory = '';

        // 생성자에서 확정된 기본값 사용
        $default_controller = $this->config['default_controller'];
        $default_method     = $this->config['default_method'];

        // 하위 디렉토리 지원
        if (!empty($segments[0])) {
            $firstSegPath = $this->controllers_path . $segments[0];
            if (is_dir($firstSegPath)) {
                $directory = array_shift($segments);
            }
        }

        $controller_slug = strtolower($segments[0] ?? $default_controller);
        $method          = $segments[1] ?? $default_method;
        $params          = array_slice($segments, 2);

        $dir_path = $this->controllers_path . ($directory ? $directory . DIRECTORY_SEPARATOR : '');
        if (!is_dir($dir_path)) {
            return null;
        }

        $controller_class = find_class($controller_slug, [$dir_path]);
        if (!$controller_class) {
            return null;
        }

        return [
            'controller' => $controller_class,
            'method'     => $method,
            'params'     => $params,
            'directory'  => $directory,
        ];
    }

    /** ----------------------------------------------------------
     * 외부 진입점
     * ---------------------------------------------------------- */
    public function dispatch(): void
    {
        try {
            $this->dispatchCore();
        } catch (Throwable $e) {
            if (function_exists('respond_exception')) {
                respond_exception($e, $this);
                return;
            }
            throw $e;
        }
    }

    /** ----------------------------------------------------------
     * [REFACTORED] 핵심 라우팅 실행부
     * ---------------------------------------------------------- */
    private function dispatchCore(): void
    {
        $uri = implode('/', $this->segments);
        $matched = false;

        $searches = ['(:any)', '(:num)'];
        $replaces = ['([^/]+)', '(\d+)'];

        // 1. 커스텀 라우트 매칭
        foreach ($this->routes as $pattern => $target) {
            // 설정용 키 건너뛰기
            if (in_array($pattern, ['default_controller', 'default_method', '404_override', 'translate_uri_dashes'], true)) {
                continue;
            }

            // 정규식 변환 및 이스케이프
            $temp_pattern = str_replace($searches, ['__ANY__', '__NUM__'], $pattern);
            $regex_safe   = preg_quote($temp_pattern, '#');
            $regex_final  = str_replace(['__ANY__', '__NUM__'], $replaces, $regex_safe);

            if (preg_match('#^' . $regex_final . '$#', $uri, $matches)) {
                array_shift($matches);

                // 타겟 URL 치환 ($1, $2...)
                $target_uri = preg_replace_callback('/\$(\d+)/', function ($m) use ($matches) {
                    $index = (int)$m[1] - 1;
                    return $matches[$index] ?? '';
                }, $target);

                $route_parts = explode('/', $target_uri);

                // LFI(Directory Traversal) 방지 필터
                $route_parts = array_values(array_filter($route_parts, function($seg) {
                    return $seg !== '' && $seg !== '.' && $seg !== '..';
                }));

                // 매칭된 결과 할당 (값이 없으면 기본값 사용)
                $controller = $route_parts[0] ?? $this->config['default_controller'];
                $method     = $route_parts[1] ?? $this->config['default_method'];
                $params     = array_slice($route_parts, 2);

                $matched = true;
                break;
            }
        }

        // 2. 라우트 결과 적용
        if ($matched) {
            $this->segments = array_merge([$controller, $method], $params);
            $route = $this->findController();

            if ($route) {
                $route['method'] = $method;
                $route['params'] = $params;
            } else {
                // 라우팅은 됐는데 파일이 없는 경우
                throw new RuntimeException("Routed Controller not found: {$controller}", 404);
            }
        } else {
            // 3. 기본(레거시) 탐색
            $route = $this->findController();

            // 4. 네임스페이스 탐색 (PSR-4 Fallback)
            if (!$route) {
                $controller = $this->segments[0] ?? $this->config['default_controller'];
                $method     = $this->segments[1] ?? $this->config['default_method'];
                $nsClass    = "App\\Controllers\\" . ucfirst($controller);

                if (class_exists($nsClass)) {
                    $route = [
                        'controller' => $nsClass,
                        'method'     => $method,
                        'params'     => array_slice($this->segments, 2),
                        'directory'  => '',
                    ];
                } else {
                    throw new RuntimeException("Controller not found: {$controller}", 404);
                }
            }
        }

        // -------------------------------------------------------
        // 3. 컨트롤러 실행 (Reflection)
        // -------------------------------------------------------
        $this->class     = $route['controller'];
        $this->method    = $route['method'];
        $this->directory = $route['directory'] ?? '';

        if (empty($this->class)) {
            throw new RuntimeException("Invalid controller name (null).", 500);
        }

        if (!empty($this->config['translate_uri_dashes'])) {
            $this->method = str_replace('-', '_', $this->method);
        }

        $ref = self::$reflection_cache[$this->class] ??= new ReflectionClass($this->class);

        if (!$ref->isSubclassOf('Controller')) {
            throw new RuntimeException("Invalid controller: {$this->class} (extends Controller missing?)", 500);
        }

        $ctrl = $ref->newInstanceWithoutConstructor();

        if (!method_exists($ctrl, '_bootCore')) {
            throw new RuntimeException('Bootstrap method missing (_bootCore)', 500);
        }

        $ctrl->_bootCore();

        $ctor = $ref->getConstructor();
        if ($ctor && $ctor->getNumberOfRequiredParameters() === 0) {
            $ctor->invoke($ctrl);
        }

        static $blocked = ['_bootCore', '__construct', '__destruct', '__base'];
        if (in_array($this->method, $blocked, true)) {
            throw new RuntimeException('Forbidden method', 403);
        }

        if (method_exists($ctrl, '_remap')) {
            (new ReflectionMethod($ctrl, '_remap'))->invoke($ctrl, $this->method, $route['params']);
            return;
        }

        if (!method_exists($ctrl, $this->method)) {
            throw new RuntimeException("Method not found: {$this->method}", 404);
        }

        $cacheKey = $this->class . '::' . $this->method;
        $mRef     = self::$method_cache[$cacheKey] ??= new ReflectionMethod($ctrl, $this->method);

        if (!$mRef->isPublic() || $mRef->isConstructor() || $this->method[0] === '_') {
            throw new RuntimeException('Forbidden access', 403);
        }

        if (count($route['params']) < $mRef->getNumberOfRequiredParameters()) {
            throw new RuntimeException('Bad request: missing parameters', 400);
        }

        $mRef->invokeArgs($ctrl, $route['params']);
    }
}
