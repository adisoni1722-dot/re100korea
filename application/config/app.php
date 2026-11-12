<?php
$configCache = Container::get('config');

/* -------------------------------------------------------------
 * Core Loader
 * ------------------------------------------------------------- */
Container::singleton('loader', fn() => new Loader());

/* -------------------------------------------------------------
 * View / HTTP 응답 계층
 * ------------------------------------------------------------- */
Container::singleton('view', fn() => new View());
Container::singleton('response', fn() => new Response());

/* -------------------------------------------------------------
 * Core 서비스 (보안, 세션, CSRF, 입력, URI)
 * ------------------------------------------------------------- */
Container::singleton('security', fn() => new Security($configCache));

Container::singleton('session', fn() => new Session(
    Container::get('db'),
    $configCache,
    Container::get('security')
));

Container::singleton('csrf', fn() => new Csrf(
    $configCache,
    Container::get('session')
));

Container::singleton('input', fn() => new Input(
    Container::get('security'),
    $configCache
));

Container::singleton('uri', fn() => new Uri(
    Container::get('router'),
    Container::get('input'),
    Container::get('security')
));


/* -------------------------------------------------------------
 * Context (뷰 전역 데이터 저장소)
 * ------------------------------------------------------------- */
Container::singleton('context', fn() => new Context());

/* -------------------------------------------------------------
 * Form
 * ------------------------------------------------------------- */

Container::singleton('form', fn() => new Form(
    Container::get('input'),
    Container::get('security'),
    Container::get('csrf'),
    Container::get('config')
));


/* -------------------------------------------------------------
 * Router (CLI / 웹 요청 구분)
 * ------------------------------------------------------------- */
$requestUri = php_sapi_name() === 'cli' ? '/' : ($_SERVER['REQUEST_URI'] ?? '/');
Container::singleton('router', fn() => new Router($requestUri, $configCache));

/* -------------------------------------------------------------
 * Database 계층
 * ------------------------------------------------------------- */
Container::singleton('database', fn() => Database::connect([
    'driver' => $configCache['driver'] ?? 'mysqli',
    'config' => $configCache['database'] ?? [],
]));

Container::singleton('db', fn() => new Model(Container::get('database')));

/* -------------------------------------------------------------
 * Asset 서비스
 * ------------------------------------------------------------- */
Container::singleton('asset', fn() => new Asset(Container::get('router')));
