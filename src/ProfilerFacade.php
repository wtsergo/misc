<?php

namespace Wtsergo\Misc;

class ProfilerFacade
{
    public static function start($timerId): void
    {
        if (self::isEnabled()) {
            self::stat()->start($timerId, microtime(true), memory_get_usage(true), memory_get_usage());
        }
    }
    public static function stop($timerId): void
    {
        if (self::isEnabled()) {
            self::stat()->stop($timerId, microtime(true), memory_get_usage(true), memory_get_usage());
        }
    }
    public static function getFilteredTimerIds(array $thresholds = null, $filterPattern = null): array
    {
        if (self::isEnabled()) {
            return self::stat()->getFilteredTimerIds($thresholds, $filterPattern);
        }
        return [];
    }
    public static function getAllStat(): array
    {
        $result = [];
        if (self::isEnabled()) {
            foreach (self::getFilteredTimerIds() as $timerId) {
                $time = self::fetch($timerId, 'sum');
                $realmem = self::fetch($timerId, 'realmem');
                $emalloc = self::fetch($timerId, 'emalloc');
                $msg = sprintf(
                    'PROFILER STAT [%s]: %s, %s, %s',
                    $timerId,
                    number_format($time*1000, 2).' ms',
                    number_format($realmem / 1024.0, 2).' Kb',
                    number_format($emalloc / 1024.0, 2).' Kb'
                );
                $result[] = $msg;
            }
        }
        return $result;
    }
    public static function get($timerId): array
    {
        if (self::isEnabled()) {
            return self::stat()->get($timerId);
        }
        return [];
    }
    public static function fetch($timerId, $key): string|bool|int|float|null
    {
        if (self::isEnabled()) {
            return self::stat()->fetch($timerId, $key);
        }
        return null;
    }
    public static function clear($timerId = null): void
    {
        if (self::isEnabled()) {
            self::stat()->clear($timerId);
        }
    }

    private static bool $enabled=false;
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }
    public static function enable(): void
    {
        self::setEnabled(true);
    }
    public static function disable(): void
    {
        self::setEnabled(false);
    }

    private static function stat(): ProfilerStat
    {
        static $stat = null;
        return $stat ??= new ProfilerStat();
    }
}
