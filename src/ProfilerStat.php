<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See MAGENTO-COPYING.txt for license details.
 */

namespace Wtsergo\Misc;

class ProfilerStat
{
    public const NESTING_SEPARATOR = '->';
    /**
     * #@+ Timer statistics data keys
     */
    public const ID = 'id';
    public const START = 'start';
    public const TIME = 'sum';
    public const COUNT = 'count';
    public const AVG = 'avg';
    public const REALMEM = 'realmem';
    public const REALMEM_START = 'realmem_start';
    public const EMALLOC = 'emalloc';
    public const EMALLOC_START = 'emalloc_start';
    /**#@-*/

    /**
     * @var array
     */
    protected $_timers = [];

    /**
     * Starts timer
     *
     * @param string $timerId
     * @param int $time
     * @param int $realMemory Real size of memory allocated from system
     * @param int $emallocMemory Memory used by emalloc()
     * @return void
     */
    public function start($timerId, $time, $realMemory, $emallocMemory): void
    {
        if (empty($this->_timers[$timerId])) {
            $this->_timers[$timerId] = [
                self::START => false,
                self::TIME => 0,
                self::COUNT => 0,
                self::REALMEM => 0,
                self::EMALLOC => 0,
            ];
        }

        $this->_timers[$timerId][self::REALMEM_START] = $realMemory;
        $this->_timers[$timerId][self::EMALLOC_START] = $emallocMemory;
        $this->_timers[$timerId][self::START] = $time;
        $this->_timers[$timerId][self::COUNT]++;
    }

    /**
     * Stops timer
     *
     * @param string $timerId
     * @param int $time
     * @param int $realMemory Real size of memory allocated from system
     * @param int $emallocMemory Memory used by emalloc()
     * @return void
     * @throws \InvalidArgumentException if timer doesn't exist
     */
    public function stop($timerId, $time, $realMemory, $emallocMemory): void
    {
        if (empty($this->_timers[$timerId])) {
            throw new \InvalidArgumentException(sprintf('Timer "%s" doesn\'t exist.', $timerId));
        }

        $this->_timers[$timerId][self::TIME] += $time - $this->_timers[$timerId]['start'];
        $this->_timers[$timerId][self::START] = false;
        $this->_timers[$timerId][self::REALMEM] += $realMemory;
        $this->_timers[$timerId][self::REALMEM] -= $this->_timers[$timerId][self::REALMEM_START];
        $this->_timers[$timerId][self::EMALLOC] += $emallocMemory;
        $this->_timers[$timerId][self::EMALLOC] -= $this->_timers[$timerId][self::EMALLOC_START];
    }

    /**
     * Get timer statistics data by timer id
     *
     * @param string $timerId
     * @return array
     * @throws \InvalidArgumentException if timer doesn't exist
     */
    public function get($timerId): array
    {
        if (empty($this->_timers[$timerId])) {
            throw new \InvalidArgumentException(sprintf('Timer "%s" doesn\'t exist.', $timerId));
        }
        return $this->_timers[$timerId];
    }

    /**
     * Retrieve statistics on specified timer
     *
     * @param string $timerId
     * @param string $key Information to return
     * @return string|bool|int|float
     * @throws \InvalidArgumentException
     */
    public function fetch($timerId, $key): string|bool|int|float
    {
        if ($key === self::ID) {
            return $timerId;
        }
        if (empty($this->_timers[$timerId])) {
            throw new \InvalidArgumentException(sprintf('Timer "%s" doesn\'t exist.', $timerId));
        }
        /* AVG = TIME / COUNT */
        $isAvg = $key == self::AVG;
        if ($isAvg) {
            $key = self::TIME;
        }
        if (!isset($this->_timers[$timerId][$key])) {
            throw new \InvalidArgumentException(sprintf('Timer "%s" doesn\'t have value for "%s".', $timerId, $key));
        }
        $result = $this->_timers[$timerId][$key];
        if ($key == self::TIME && $this->_timers[$timerId][self::START] !== false) {
            $result += microtime(true) - $this->_timers[$timerId][self::START];
        }
        if ($isAvg) {
            $count = $this->_timers[$timerId][self::COUNT];
            if ($count) {
                $result = $result / $count;
            }
        }
        return $result;
    }

    /**
     * Clear collected statistics for specified timer or for all timers if timer id is omitted
     *
     * @param string|null $timerId
     * @return void
     */
    public function clear($timerId = null): void
    {
        if ($timerId) {
            unset($this->_timers[$timerId]);
        } else {
            $this->_timers = [];
        }
    }

    /**
     * Get ordered list of timer ids filtered by thresholds and pcre pattern
     *
     * @param array|null $thresholds
     * @param string|null $filterPattern
     * @return array
     */
    public function getFilteredTimerIds(array $thresholds = null, $filterPattern = null): array
    {
        $timerIds = $this->_getOrderedTimerIds();
        if (!$thresholds && !$filterPattern) {
            return $timerIds;
        }
        $thresholds = (array)$thresholds;
        $result = [];
        foreach ($timerIds as $timerId) {
            /* Filter by pattern */
            if ($filterPattern && !preg_match($filterPattern, $timerId)) {
                continue;
            }
            /* Filter by thresholds */
            $match = true;
            foreach ($thresholds as $fetchKey => $minMatchValue) {
                $match = $this->fetch($timerId, $fetchKey) >= $minMatchValue;
                if ($match) {
                    break;
                }
            }
            if ($match) {
                $result[] = $timerId;
            }
        }
        return $result;
    }

    /**
     * Get ordered list of timer ids
     *
     * @return array
     */
    protected function _getOrderedTimerIds(): array
    {
        $timerIds = array_keys($this->_timers);
        if (count($timerIds) <= 2) {
            /* No sorting needed */
            return $timerIds;
        }

        /* Prepare PCRE once to use it inside the loop body */
        $nestingSep = preg_quote(self::NESTING_SEPARATOR, '/');
        $patternLastTimerId = '/' . $nestingSep . '(?:.(?!' . $nestingSep . '))+$/';

        $prevTimerId = $timerIds[0];
        $result = [$prevTimerId];
        $numberTimerIds = count($timerIds);
        for ($i = 1; $i < $numberTimerIds; $i++) {
            $timerId = $timerIds[$i];
            /* Skip already added timer */
            if (!$timerId) {
                continue;
            }
            /* Loop over all timers that need to be closed under previous timer */
            while (strpos($timerId, $prevTimerId . self::NESTING_SEPARATOR) !== 0) {
                /* Add to result all timers nested in the previous timer */
                for ($j = $i + 1; $j < $numberTimerIds; $j++) {
                    if (!$timerIds[$j]) {
                        continue;
                    }
                    if (strpos($timerIds[$j], $prevTimerId . self::NESTING_SEPARATOR) === 0) {
                        $result[] = $timerIds[$j];
                        /* Mark timer as already added */
                        $timerIds[$j] = null;
                    }
                }
                /* Go to upper level timer */
                $count = 0;
                $prevTimerId = preg_replace($patternLastTimerId, '', $prevTimerId, -1, $count);
                /* Break the loop if no replacements was done. It is possible when we are */
                /* working with top level (root) item */
                if (!$count) {
                    break;
                }
            }
            /* Add current timer to the result */
            $result[] = $timerId;
            $prevTimerId = $timerId;
        }
        return $result;
    }
}
