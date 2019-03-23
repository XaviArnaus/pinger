<?php

class Timer {
    private $timers = [];

    public static function quickStart($timer_name) {
        $quick = new self();
        $quick->start($timer_name);
        return $quick;
    }

    public function start($timer_name) {
        if (!isset($this->timers[$timer_name])) {
            $this->timers[$timer_name] = microtime(true);
        }
    }

    public function stop($timer_name) {
        if (isset($this->timers[$timer_name])) {
            $elapsed = microtime(true) - $this->timers[$timer_name];
            unset($this->timers[$timer_name]);

            // Already in seconds, as a float.
            return $elapsed;
        }
        return null;
    }
}