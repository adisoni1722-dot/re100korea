<?php
class Container {
    /** @var array<string,mixed> */
    private static array $instances = []; // 즉시/해결된 인스턴스
    /** @var array<string,callable> */
    private static array $factories = []; // lazy factory
    /** @var array<string,string> */
    private static array $aliases = []; // 별칭 → 실제 ID
    /** @var array<string,bool> */
    private static array $building = []; // 순환 의존 가드
    /** @var array<int, callable> */
    private static array $onMissing = []; // ← 추가: 미등록 자동 해결자

    /* ---------- 내부 유틸 ---------- */
    private static function normalize(string $id): string {
        return strtolower($id);
    }
    private static function resolveAlias(string $id): string {
        $id   = self::normalize($id);
        $seen = [];
        while (isset(self::$aliases[$id])) {
            if (isset($seen[$id])) {
                throw new RuntimeException("Alias loop detected at '{$id}'");
            }
            $seen[$id] = true;
            $id        = self::$aliases[$id];
        }
        return $id;
    }

    /* ---------- 등록/바인딩 ---------- */
    public static function set(string $id, $instance): void {
        $id                   = self::resolveAlias($id);
        self::$instances[$id] = $instance;
        unset(self::$factories[$id]);
    }

    public static function singleton(string $id, callable $factory): void {
        $id = self::resolveAlias($id);
        if (self::has($id)) {
            throw new LogicException("Service '{$id}' already registered (bound or instantiated)");
        }
        self::$factories[$id] = $factory;
    }

    public static function alias(string $alias, string $target): void {
        $alias  = self::normalize($alias);
        $target = self::normalize($target);

        // 자기 자신으로의 별칭 금지
        if ($alias === $target) {
            throw new LogicException("Alias '{$alias}' cannot point to itself");
        }

        // 순환 alias 방지
        $seen  = [];
        $check = $target;
        while (isset(self::$aliases[$check])) {
            if (isset($seen[$check])) {
                throw new LogicException("Circular alias detected between '{$alias}' and '{$target}'");
            }
            $seen[$check] = true;
            $check        = self::$aliases[$check];
            if ($check === $alias) {
                throw new LogicException("Alias loop detected: '{$alias}' ↔ '{$target}'");
            }
        }

        self::$aliases[$alias] = $target;
    }

    /* ---------- 미등록 자동 해결 훅 ---------- */
    /** 미등록 키를 요청받았을 때 시도할 해결자 등록 (resolver(string $id): mixed|null) */
    public static function onMissing(callable $resolver): void {
        self::$onMissing[] = $resolver;
    }

    private static function tryMissingResolvers(string $id) {
        foreach (self::$onMissing as $resolver) {
            try {
                $resolved = $resolver($id);
                if ($resolved !== null) {
                    // 객체/자원/스칼라 모두 허용은 하되, 디버그 메시지 명확화
                    return $resolved; // 첫 성공만 사용
                }
            } catch (\Throwable $e) {
                // 다음 resolver에게 기회
            }
        }
        return null;
    }

    /* ---------- 조회 ---------- */
    public static function has(string $id): bool {
        $id = self::resolveAlias($id);
        return array_key_exists($id, self::$instances) || array_key_exists($id, self::$factories);
    }

    public static function bound(string $id): bool {
        $id = self::resolveAlias($id);
        return array_key_exists($id, self::$factories);
    }

    public static function instantiated(string $id): bool {
        $id = self::resolveAlias($id);
        return array_key_exists($id, self::$instances);
    }

    public static function get(string $id) {
        $id = self::resolveAlias($id);

        if (array_key_exists($id, self::$instances)) {
            return self::$instances[$id];
        }
        if (!array_key_exists($id, self::$factories)) {
            $resolved = self::tryMissingResolvers($id);
            if ($resolved !== null) {
                self::$instances[$id] = $resolved; // 싱글톤처럼 캐시
                return $resolved;
            }
            throw new RuntimeException("Service '{$id}' not found");
        }

        // 순환 의존 가드
        if (isset(self::$building[$id])) {
            $stack = implode(' -> ', array_keys(self::$building)) . " -> {$id}";
            throw new RuntimeException("Circular dependency detected: {$stack}");
        }

        self::$building[$id] = true;
        try {
            $factory              = self::$factories[$id];
            $instance             = $factory();
            self::$instances[$id] = $instance;
            unset(self::$factories[$id]);
            return $instance;
        } finally {
            unset(self::$building[$id]);
        }
    }

    public static function singletonIf(string $id, callable $factory): void {
        if (!self::has($id)) {
            self::singleton($id, $factory);
        }

    }
    /* ---------- 관리/디버깅 도우미 ---------- */
    public static function forget(string $id): void {
        $id = self::resolveAlias($id);
        unset(self::$instances[$id], self::$factories[$id]);
        foreach (self::$aliases as $a => $t) {
            if ($t === $id) {
                unset(self::$aliases[$a]);
            }

        }
    }

    public static function reset(): void {
        self::$instances = [];
        self::$factories = [];
        self::$aliases   = [];
        self::$building  = [];
        self::$onMissing = [];
    }

    public static function keys(): array {
        return array_values(array_unique(array_merge(
            array_keys(self::$instances),
            array_keys(self::$factories)
        )));
    }

    public static function all(): array {
        return [
            'instances' => array_keys(self::$instances),
            'factories' => array_keys(self::$factories),
            'aliases'   => self::$aliases,
        ];
    }

    public static function stats(): array {
        return [
            'bound'        => count(self::$factories),
            'instantiated' => count(self::$instances),
            'aliases'      => count(self::$aliases),
        ];
    }
}
