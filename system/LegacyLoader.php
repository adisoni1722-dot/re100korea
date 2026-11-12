<?php
defined('BASEPATH') or exit('No direct script access allowed');

class LegacyLoader {
    protected static ?Controller $controller = null;

    public function __call(string $method, array $params) {
        self::$controller ??= Container::has('controller') ? Container::get('controller') : null;

        switch ($method) {
            case 'view':
                return view($params[0] ?? '', $params[1] ?? [], $params[2] ?? true);

            case 'model':
                $name  = $params[0] ?? '';
                $alias = $params[1] ?? null;
                return $this->loadModel($name, $alias);

            case 'helper':
                return $this->safeLoad($params[0] ?? '', 'helper');

            case 'library':
                return $this->safeLoad($params[0] ?? '', 'library');

            default:
                throw new BadMethodCallException("Undefined method Loader::{$method}()");
        }
    }

    public function database() {
        // CI3 호환용: $this->CI->load->database()
        if (class_exists('Container') && Container::has('db')) {
            $db   = Container::get('db');
            $ctrl = Container::has('controller') ? Container::get('controller') : null;

            // 컨트롤러에 $db 프로퍼티 자동 주입
            if ($ctrl && !isset($ctrl->db)) {
                $ctrl->db = $db;
            }

            return $db;
        }

        throw new RuntimeException('Database service not initialized in Container.');
    }

    private function loadModel($arg, ?string $alias = null) {
        $controller = self::$controller;

        // 배열: ['member' => 'mem', 'order' => 'orderModel']
        if (is_array($arg)) {
            foreach ($arg as $k => $v) {
                is_string($k) ? $this->loadModel($k, (string) $v)
                : $this->loadModel($v, null);
            }
            return;
        }

        $instance = model((string) $arg);
        if ($controller && is_object($controller)) {
            $prop = $alias ?: $this->normalizeModelProperty($arg);
            if (!isset($controller->$prop)) {
                $controller->$prop = $instance;
            }
        }
        return $instance;
    }

    private function safeLoad($arg, string $type) {
        if (is_array($arg)) {
            foreach ($arg as $item) {
                $this->safeLoad($item, $type);
            }
            return;
        }

        try {
            if ($type === 'helper') {
                helper((string) $arg);
            } elseif ($type === 'library') {
                library((string) $arg);
            }
        } catch (Throwable $e) {
            // 운영환경 보호: 로그만 남기고 중단하지 않음
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                throw $e;
            }
            if (function_exists('log_message')) {
                log_message('error', "Failed to load {$type}: {$arg} ({$e->getMessage()})");
            }
        }
    }

    private function normalizeModelProperty(string $name): string {
        $base = basename(str_replace('\\', '/', $name));
        return strtolower(preg_replace('/_model$/i', '', $base)) . '_model';
    }
}
