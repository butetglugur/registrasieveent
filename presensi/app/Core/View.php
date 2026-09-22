<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Template engine PHP murni dengan layout & section (mirip Blade @extends/@section).
 *
 * Di dalam view:
 *   <?php $this->extend('layouts.admin') ?>
 *   <?php $this->section('content') ?> ... <?php $this->end() ?>
 * Di layout:
 *   <?= $this->yield('content') ?>
 */
final class View
{
    /** Variabel view yang otomatis tersedia di layout. */
    private const EXPORTS = ['title', 'description', 'themeKey', 'crumb'];

    /** @var array<string,mixed> */
    private static array $shared = [];

    /** @var array<string,string> */
    private array $sections = [];
    /** @var string[] */
    private array $stack = [];
    private ?string $layout = null;
    /** @var array<string,mixed> */
    private array $data = [];

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function make(string $name, array $data = []): string
    {
        $view = new View();
        return $view->render($name, array_merge(self::$shared, $data));
    }

    public static function path(string $name): string
    {
        if (!preg_match('/^[a-z0-9_.\-]+$/i', $name)) {
            throw new \InvalidArgumentException('Nama view tidak valid');
        }
        return base_path('resources/views/' . str_replace('.', '/', $name) . '.php');
    }

    public function render(string $name, array $data): string
    {
        $this->data = $data;
        $content = $this->capture(self::path($name), $this->data);
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $content;
            }
            $content = $this->capture(self::path($layout), $this->data);
        }
        return $content;
    }

    private function capture(string $__file, array $__data): string
    {
        if (!is_file($__file)) {
            throw new \RuntimeException('View tidak ditemukan: ' . $__file);
        }
        extract($__data, EXTR_SKIP);
        $__level = ob_get_level();
        ob_start();
        try {
            include $__file;
            // Variabel "meta" yang di-set view diteruskan ke layout.
            foreach (self::EXPORTS as $__k) {
                if (isset($$__k)) {
                    $this->data[$__k] = $$__k;
                }
            }
        } catch (\Throwable $e) {
            while (ob_get_level() > $__level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->stack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->stack);
        if ($name === null) {
            throw new \LogicException('end() tanpa section()');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    public function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    /** Sisipkan partial. */
    public function partial(string $name, array $data = []): string
    {
        $v = new View();
        return $v->render($name, array_merge($this->data, $data));
    }
}
