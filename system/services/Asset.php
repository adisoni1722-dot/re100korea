<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Asset {
    protected ?Router $router;

    public function __construct(?Router $router = null) {
        $this->router = $router ?? (Container::has('router') ? Container::get('router') : null);
    }

    public function load(string $path): string {
        if ($path === '') {
            return '';
        }

        $is_absolute = (strpos($path, '/') === 0);
        $clean_path  = trim($path, '/');

        // /auto 접미사 확인 및 제거
        $auto_detect = false;
        if (substr($clean_path, -5) === '/auto') {
            $auto_detect = true;
            $clean_path = substr($clean_path, 0, -5);
        }

        if ($is_absolute) {
            $base_path = BASEPATH . $clean_path;
            $base_url  = '/' . $clean_path;
        } else {
            $controller = $this->router->segments[0] ?? 'main';
            $base_path  = BASEPATH . "views/{$controller}/{$clean_path}";
            $base_url   = "/views/{$controller}/{$clean_path}";
        }

        $final_url = $this->resolveAsset($base_path, $base_url, $auto_detect);
        if (!$final_url) {
            return "\n";
        }

        return $this->buildTag($final_url);
    }

    protected function resolveAsset(string $base_path, string $base_url, bool $auto_detect = false): ?string {
        if (is_file($base_path)) {
            return $base_url;
        }

        if ($auto_detect && is_dir($base_path)) {
            $page_name  = $this->router->segments[1] ?? null;
            $candidates = !empty($page_name) ? [$page_name] : [];
            if (!empty($this->router->method) && strtolower($this->router->method) === 'index') {
                $candidates[] = 'home';
            }

            foreach (array_unique($candidates) as $base_name) {
                foreach (['.css', '.min.css', '.js', '.min.js'] as $ext) {
                    $file = "{$base_path}/{$base_name}{$ext}";
                    if (is_file($file)) {
                        return rtrim($base_url, '/') . '/' . $base_name . $ext;
                    }
                }
            }
        }

        return null;
    }

    protected function buildTag(string $final_url): string {
        $local_path = BASEPATH . ltrim($final_url, '/');
        $version    = file_exists($local_path)
        ? filemtime($local_path)
        : (function_exists('ver') ? ver() : time());

        return (substr($final_url, -3) === '.js')
        ? '<script src="' . $final_url . '?v=' . $version . '"></script>' . "\n"
        : '<link rel="stylesheet" href="' . $final_url . '?v=' . $version . '">' . "\n";
    }
}
