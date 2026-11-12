<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Controller
{
    /** @var Controller|null CI3 호환 get_instance()용 싱글톤 레퍼런스 */
    private static ?Controller $instance = null;

    /** @var array<string,string> __get()에서 사용할 서비스 별칭 */
    private static array $propertyAliases = [
        'driver'   => 'database',
        'database' => 'db',
    ];

    // Core Components
    protected $load            = null;
    public ?Security $security = null;
    public ?Router $router     = null;

    public array $config = [];

    // 동적 로드된 속성 (모델/라이브러리 등)
    private array $_loaded_properties = [];

    /**
     * Controller 생성자
     * Router가 컨트롤러를 인스턴스화한 뒤, _bootCore()에서 코어 주입
     */
    public function __construct()
    {
        $this->config   = Container::get('config');
        $this->security = Container::get('security');
        $this->router   = Container::get('router');
        // no-op (Router가 _bootCore() 호출)
    }

    /**
     * Core 초기화 (Router에서 호출)
     */
    final public function _bootCore(): void
    {
        self::$instance = $this;

        Container::set('controller', $this);

        // Loader 호환용 Stub 등록
        if (!isset($this->load)) {
            $this->load = new LegacyLoader();
        }

        // 하위 클래스의 __base() 메서드 호출
        if (method_exists($this, '__base')) {
            $this->__base();
        }
    }

    protected function __base()
    {
        // 훅(Hook) 메서드: 필요 시 자식 클래스에서 구현
    }

    /**
     * CI3 호환 get_instance()
     */
    public static function &get_instance(bool $allowNull = false)
    {
        if (!self::$instance) {
            if ($allowNull) {
                $null = null;
                return $null;
            }
            throw new RuntimeException('Controller 인스턴스가 초기화되지 않았습니다.');
        }
        return self::$instance;
    }


    /* ---------------------------------------------------------
     * 매직 메서드 (__get, __set)
     * --------------------------------------------------------- */
    public function __set(string $name, $value): void
    {
        if (property_exists($this, $name)) {
            throw new LogicException("Cannot override core property '{$name}' in Controller.");
        }
        $this->_loaded_properties[$name] = $value;
    }

    public function __get(string $name)
    {
        // 1. 캐시 확인
        if (isset($this->_loaded_properties[$name])) {
            return $this->_loaded_properties[$name];
        }

        // 2. load 최우선 처리
        if ($name === 'load' && isset($this->load)) {
            return $this->load;
        }

        // 3. 멤버 변수 확인
        if (property_exists($this, $name) && isset($this->$name)) {
            return $this->$name;
        }

        // 4. Container 연동 (최적화됨)
        if (class_exists('Container')) {
            $id = self::$propertyAliases[$name] ?? $name;

            // 정규식 검사 제거 (Container::has가 유효성 체크 역할도 겸함)
            if (Container::has($id)) {
                try {
                    $instance = Container::get($id);
                    $this->_loaded_properties[$name] = $instance;
                    return $instance;
                } catch (\Throwable $e) {
                    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                        $msg = "Container get('{$id}') failed in " . static::class . ': ' . $e->getMessage();
                        function_exists('log_message') ? log_message('error', $msg) : error_log($msg);
                    }
                }
            }
        }

        // 5. 디버그 모드 경고
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $msg = "Dynamic property unresolved: " . static::class . "::$" . $name;
            function_exists('log_message') ? log_message('warning', $msg) : error_log($msg);
        }

        return null;
    }
}

/** CI3 호환 */
if (!class_exists('CI_Controller', false)) {
    class_alias('Controller', 'CI_Controller');
}
if (!class_exists('CI_Model', false)) {
    class_alias('Model', 'CI_Model');
}
function &get_instance()
{
    return Controller::get_instance();
}
