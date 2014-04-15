<?php
namespace Mercy;
class Epilog implements \ArrayAccess
{
    public $levels = [
        'debug' => 100,
        'info' => 200,
        'notice' => 300,
        'warning' => 400,
        'error' => 500,
        'critical' => 600,
        'alert' => 700,
        'emergency' => 800
    ];
    public $dateFormat = 'Y-m-d H:i:s';
    public $channels, $level, $context, $formatter, $filter, $strictlvl;
    public $timers = [];
    public $buffer = [];
    public $bufferSize = 0;

    protected $turnedOff = false;

    const RAW = "\0";
    const BUFFER_ADDRESS = "logger://buffer";
    const TURN_OFF = "off";

    public function __construct($channel = "php://stdout", $level = 'info', $formatter = null)
    {
        if ($level == self::TURN_OFF) {
            $this->turnedOff = true;
            return;
        }
        if ($level[0] == "=") {
            $this->strictlvl = true;
            $level = substr($level, 1);
        }
        $this->level = $level;
        if (!array_key_exists($level, $this->levels)) {
            $this->level = 'info';
            trigger_error("Undefined log level \"{$level}\"", E_USER_NOTICE);
        }
        $this->channels = is_array($channel) ? $channel : [$level => [$channel]];
        if (is_callable($formatter)) {
            $this->formatter = $formatter;
        }
        $this->filter['default'] = function ($p) {
            $p['ms'] = substr($p['ms'], 0, 2);
            $p['timer'] = is_float($p['timer']) ? number_format($p['timer'], 4) : $p['timer'];
            return $p;
        };
    }

    public function log($text, $level = 'info', $timer = null)
    {
        if ($this->turnedOff || (isset($this->levels[$level]) && $this->levels[$this->level] > $this->levels[$level])) return;

        $logString = $text[0] == "\0" ? substr($text, 1) : null;
        $timerError = null !== $timer ? "[timer_{$timer}_not_found]" : null;
        $p = [
            'date' => date($this->dateFormat),
            'ms' => substr((string)microtime(), 2, 6),
            'timer' => isset($this->timers[$timer]) ? microtime(true) - $this->timers[$timer] : $timerError,
            'level' => ucfirst($level),
            'context' => $this->context,
            'text' => $text
        ];
        foreach ($this->filter as $filter) {
            $p = $filter($p);
        }
        $get = function ($key, $before = '', $after = '') use ($p) {
            return isset($p[$key]) && $p[$key] !== null ? $before . $p[$key] . $after : '';
        };
        if (!is_callable($this->formatter)) {
            $this->formatter = function ($p, $get) {
                return "[{$get('date')}{$get('ms', '.')}] {$get('timer', '', 's ')}{$get('level')}: {$get('text')} {$get('context', '{', '}')}" . PHP_EOL;
            };
        }
        $logString = $logString ? : call_user_func($this->formatter, $p, $get);
        foreach ($this->channels as $chlvl => $channels) {
            if (($this->strictlvl && $level == $chlvl)
                || ($chlvl == "=" . $level)
                || ($chlvl[0] != "=" && $this->levels[$level] >= $this->levels[$chlvl] && !$this->strictlvl)
            ) {
                foreach ($channels as $channel) {
                    if (is_callable($channel)) {
                        $channel($logString, $p);
                    } else {
                        if ($channel === self::BUFFER_ADDRESS) {
                            $this->buffer[] = $logString;
                            if ($this->bufferSize > 0 && count($this->buffer) > $this->bufferSize) {
                                array_shift($this->buffer);
                            }
                        } else {
                            file_put_contents($channel, $logString, FILE_APPEND);
                        }
                    }
                }

            }
        }
    }

    public function __invoke($logString, $level = 'info', $timer = "")
    {
        return call_user_func_array([$this, "log"], func_get_args());
    }

    public function offsetSet($offset, $value)
    {
        if (in_array($value, ["start", "reset"])) {
            $this->timers[$offset] = microtime(true);
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->timers[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->timers[$offset]);
    }

    public function offsetGet($key)
    {
        if ($this->turnedOff) {
            return function () {
            };
        }
        $rules = explode(':', $key);
        $timer = isset($rules[1]) ? $rules[1] : null;
        $level = isset($this->levels[$rules[0]]) ? $rules[0] : $this->level;
        return function ($string) use ($level, $timer) {
            $this->log($string, $level, $timer);
        };
    }

    public function __toString()
    {
        return implode('', $this->buffer);
    }
}