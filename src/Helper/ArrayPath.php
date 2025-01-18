<?php

namespace Wtsergo\Misc\Helper;

trait ArrayPath
{
    private static function __splitPath(string $path, string $separator = '/'): array
    {
        $__inPath = explode($separator, $path);
        $__outPath = array_filter(array_map('trim', $__inPath));
        if (count($__outPath) != count($__inPath) || empty($__outPath)) {
            throw new \InvalidArgumentException('Invalid config path');
        }
        return $__outPath;
    }
    private static function __fetchByPath(array $data, string $path, mixed $default=null, string $separator = '/'): mixed
    {
        static $null;
        $null ??= new \stdClass;
        $path = static::__splitPath($path);
        $value = $data;
        while ($value!==$null && !empty($path) && is_array($value)) {
            $name = array_shift($path);
            if ($name === null) break;
            $value = array_key_exists($name, $value) ? $value[$name] : $null;
        }
        if (!empty($path)) {
            $value = $null;
        }
        return $value === $null ? $default : $value;
    }

    private static function __insertByPath(array $data, string $path, mixed $value, string $separator = '/'): array
    {
        static $null;
        $null ??= new \stdClass;
        $__path = static::__splitPath($path);
        $name = array_shift($__path);
        $oldValue = $data[$name] ?? [];
        if (!is_array($oldValue)) {
            $oldValue = [];
        }
        while (!empty($__path)) {
            $__k = array_pop($__path);
            $value = [$__k => $value];
        }
        $value = array_replace_recursive($oldValue, $value);
        $data[$name] = $value;
        return $data;
    }
}
