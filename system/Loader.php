<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * -------------------------------------------------------------
 * LoaderComponent (Renamed to FactoryComponent in original)
 * -------------------------------------------------------------
 */
class Loader {
    private array $helperLoaded = [];

    // ---------------------- 의존성 헬퍼 ----------------------

    private function getController() {
        return (class_exists('Container') && Container::has('controller'))
        ? Container::get('controller')
        : null;
    }

    private function getDbDriver() {
        if (!class_exists('Container') || !Container::has('database')) {
            throw new RuntimeException("DB driver service 'database' not bound in Container.");
        }
        return Container::get('database');
    }

    private function getDbWrapper() {
        return (class_exists('Container') && Container::has('db'))
        ? Container::get('db')
        : null;
    }

    // ---------------------- Helper ----------------------
    public function helper($name): void {
        if (is_array($name)) {
            foreach ($name as $one) {
                $this->helper($one);
            }
            return;
        }

        $safe = strtolower(preg_replace(['/\.php$/i', '/_helper$/i'], '', basename(trim($name))));
        if (isset($this->helperLoaded[$safe])) {
            return;
        }

        // [!] 1. application/helpers 경로
        $app_dir = APPPATH . 'helpers' . DIRECTORY_SEPARATOR;
        $file    = find_file($app_dir, $safe . '_helper', ['php']);

        // [!] 2. app에 없으면 system/helpers 경로 검색
        if (!$file || !is_file($file)) {
            $sys_dir = SYSPATH . 'helpers' . DIRECTORY_SEPARATOR; // SYSPATH 사용
            $file    = find_file($sys_dir, $safe . '_helper', ['php']);
        }

        // [!] 3. 두 경로 모두에 파일이 없는 경우
        if (!$file || !is_file($file)) {
            throw new RuntimeException("Helper 파일을 찾을 수 없습니다: {$safe}_helper.php (APPPATH, SYSPATH)");
        }

        require_once $file;
        $this->helperLoaded[$safe] = true;
    }

    // ---------------------- Model ----------------------

    public function model($name, array $opt = []): ?object {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->model(is_string($k) ? $k : $v, is_string($k) ? array_merge($opt, ['alias' => $v]) : $opt);
            }
            return null;
        }

        $alias = $opt['alias'] ?? null;
        $cache = $opt['cache'] ?? true;
        $safe  = preg_replace('/\.php$/i', '', basename(trim($name)));

        // 후보군: closing → closing_model → closing
        $candidates = [];
        if (!preg_match('/_model$/i', $safe)) {
            $candidates[] = $safe . '_model';
        }
        $candidates[] = $safe;

        $cls = null;
        foreach ($candidates as $cand) {
            $cls = find_class($cand, [APPPATH . 'models' . DIRECTORY_SEPARATOR]);
            if ($cls) {
                break;
            }
        }

        if (!$cls) {
            throw new RuntimeException("Model not found: {$name}");
        }

        // 컨트롤러와 모델 이름 충돌 감지
        $ctrl = $this->getController();
        if ($ctrl) {
            $this->checkModelControllerCollision($ctrl, $cls);
        }

        // 컨트롤러에 주입될 최종 속성 이름
        $prop = $alias ?: strtolower(preg_replace('/_model$/i', '', $cls)) . '_model';

        // 공통 로더 헬퍼 호출
        return $this->_loadAndInstantiate($cls, $prop, $ctrl, $cache);
    }

    // ---------------------- Library ----------------------

    public function library($name, array $opt = []): ?object {
        if (is_array($name)) {
            foreach ($name as $one) {
                $this->library($one, $opt);
            }
            return null;
        }

        $raw      = preg_replace('/\.php$/i', '', trim($name));
        $segments = preg_split('~[\\/]+~', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $basename = array_pop($segments);
        $dir      = $segments ? implode(DIRECTORY_SEPARATOR, array_map('strtolower', $segments)) . DIRECTORY_SEPARATOR : '';

        // ✅ application + system 두 경로 모두 탐색
        $searchPaths = [
            APPPATH . 'libraries' . DIRECTORY_SEPARATOR . $dir,
            SYSPATH . 'libraries' . DIRECTORY_SEPARATOR . $dir,
        ];

        $cls = find_class($basename, $searchPaths);

        if (!$cls) {
            throw new RuntimeException("Library not found: {$name}");
        }

        $ctrl = $this->getController();
        $prop = strtolower($basename); // 라이브러리는 별칭(alias) 옵션 없음

        // ✅ 외부 설정 전달
        return $this->_loadAndInstantiate($cls, $prop, $ctrl, true, $opt);
    }

/**
 * [REFACTORED] 중복 로직 통합
 * 클래스를 인스턴스화하고 의존성을 주입하며 컨트롤러에 할당합니다.
 */
    private function _loadAndInstantiate(
        string $className,
        string $propName,
        ?object $controller,
        bool $cacheInContainer,
        array $opt = []
    ): object {
        $driver = $this->getDbDriver();
        $db     = $this->getDbWrapper();

        $ref      = new ReflectionClass($className);
        $ctor     = $ref->getConstructor();
        $instance = null;

        try {
            if ($ctor && !empty($opt)) {
                $instance = $ref->newInstanceArgs([$opt]); // 설정 배열 우선
            } elseif ($ctor && $ctor->getNumberOfRequiredParameters() > 0) {
                $instance = $ref->newInstanceArgs([$db ?: $driver]); // DB 주입
            } else {
                $instance = $ref->newInstance(); // 기본 생성자
            }
        } catch (Throwable $e) {
            $instance = $ref->newInstance(); // fallback
        }
        // (의존성 주입, 컨테이너 캐싱, 컨트롤러 주입 동일)
        if (class_exists('Model') && $instance instanceof Model) {
            $instance->driver = $driver;
            $instance->db     = $db ?: $instance;
        } else {
            if ($db && method_exists($instance, 'setDb')) {
                $instance->setDb($db);
            }
            if ($driver && property_exists($instance, 'driver')) {
                $instance->driver = $driver;
            }
        }

        if ($cacheInContainer && class_exists('Container')) {
            Container::set($className, $instance);
        }

        if ($controller && !isset($controller->$propName)) {
            $controller->$propName = $instance;
        }

        return $instance;
    }

    /**
     * [HELPER] 모델-컨트롤러 이름 충돌 감지 로직 (model()에서 분리)
     */
    private function checkModelControllerCollision(object $ctrl, string $modelClass): void {
        $ctrlClass = get_class($ctrl);
        $modelBase = basename(str_replace('\\', '/', $modelClass)); // 네임스페이스 대비

        if (strcasecmp($ctrlClass, $modelBase) === 0) {
            $msg = "[MiniCI] 경고: 모델 클래스 '{$modelBase}' 가 컨트롤러 '{$ctrlClass}' 와 이름이 같습니다. "
                . "Fatal Error를 방지하려면 모델 이름을 '{$modelBase}_model' 로 변경하세요.";

            if (function_exists('log_message')) {
                log_message('error', $msg);
            } else {
                error_log($msg);
            }
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                echo "<pre style='color:#d33; background:#fff; border:1px solid #d33; padding:10px;'>{$msg}</pre>";
            }
        }
    }
}
