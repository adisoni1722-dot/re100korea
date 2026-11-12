<?php
defined('BASEPATH') or exit('No direct script access allowed');

// -------------------------------------------------------------
// 0. MiniCI 시스템 계층 최소 로드
// -------------------------------------------------------------
require_once SYSPATH . 'Common.php';
require_once SYSPATH . 'Config.php';
require_once SYSPATH . 'Container.php';
require_once SYSPATH . 'Database.php';
require_once SYSPATH . 'Model.php';
require_once SYSPATH . 'Controller.php';
require_once SYSPATH . 'Loader.php';
require_once SYSPATH . 'LegacyLoader.php';
require_once SYSPATH . 'components/Security.php';
require_once SYSPATH . 'Router.php';
// -------------------------------------------------------------
// 1. 필수 서비스 등록
// -------------------------------------------------------------
$configCache = require APPPATH . 'config/config.php';

Container::set('config', $configCache);
Container::singletonIf('loader', fn() => new Loader());
Container::singletonIf('security', fn() => new Security($configCache));

Container::singletonIf('router', fn() => new Router('/', []));

Container::singleton('database', fn() => Database::connect([
    'driver' => $configCache['driver'] ?? 'mysqli',
    'config' => $configCache['database'] ?? [],
]));

Container::singleton('db', fn() => new Model(Container::get('database')));



// -------------------------------------------------------------
// 2. CLI 인자 파싱
// -------------------------------------------------------------
$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$controllerInput = $argv[0] ?? 'tools';
$method          = $argv[1] ?? 'index';
$params          = array_slice($argv, 2);

$segments  = explode('/', trim($controllerInput, '/'));
$fileName  = ucfirst(array_pop($segments));
$subDir    = implode('/', $segments);

$controllerPath = APPPATH . 'controllers/'
                . ($subDir ? $subDir . '/' : '')
                . $fileName . '.php';

echo "MiniCI CLI Mode\n";
echo "Controller Path : {$controllerPath}\n";
echo "Class           : {$fileName}\n";
echo "Method          : {$method}\n\n";

// -------------------------------------------------------------
// 3. 컨트롤러 실행
// -------------------------------------------------------------
try {
    if (!is_file($controllerPath)) {
        throw new RuntimeException("Controller file not found: {$controllerPath}");
    }

    require_once $controllerPath;

    if (!class_exists($fileName)) {
        throw new RuntimeException("Controller class '{$fileName}' not found in file.");
    }

    $instance = new $fileName();

    if (!method_exists($instance, $method)) {
        throw new RuntimeException("Method '{$method}' not found in controller '{$fileName}'.");
    }

    $result = call_user_func_array([$instance, $method], $params);

    if ($result !== null) {
        echo (is_scalar($result) ? $result : print_r($result, true)) . "\n";
    }

    echo "\n✅ Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[MiniCI CLI Fatal]: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n");
    exit(1);
}
