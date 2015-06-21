<?php
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
    public $channels, $formatter, $context, $filter, $strictlvl, $contextParser;
    public $timers = [];
    public $buffer = [];
    public $toStringFilters = [];
    public $bufferSize = 0;

    protected $turnedOff = false;
    protected $level;

    const RAW = "\0";
    const BUFFER_ADDRESS = "logger://buffer";

    public function __construct($channel = "php://stdout", $level = 'info', $formatter = null)
    {
        $this->setLevel($level);

        $this->channels = is_array($channel) ? $channel : [$level => [$channel]];

        if (is_callable($formatter)) {
            $this->formatter = $formatter;
        }

        $this->initDefaultFilter();
        // PSR-3 compatible context parser
        $this->initContextParset();
        $this->initToStringFilters();

    }

    public function initDefaultFilter(){
        $this->filter['default'] = function (array $p) {
            $p['ms'] = substr($p['ms'], 0, 2);
            $p['timer'] = is_float($p['timer']) ? number_format($p['timer'], 4) : $p['timer'];
            return $p;
        };
    }

    public function initContextParset() {
        $this->contextParser = function($message, array $context = []) {
            if (false === strpos($message, '{')) {
                return $message;
            }
            $replace = [];
            $allowed = [".", "-", "_"];
            foreach ($context as $key => $val) {
                if ( ctype_alnum( str_replace($allowed, '', $key ) ) ) {
                    $replace['{' . $key . '}'] = $val;
                }
            }
            return strtr($message, $replace);
        };
    }

    public function initToStringFilters() {
        $this->toStringFilters['array'] = function($value) {
            if(is_array($value)) return str_replace(["\r","\n"],"",var_export($value,true));
            return $value;
        };
        $this->toStringFilters['object'] = function($value) {
            if(is_object($value)){
                if(method_exists($value , '__toString')) {
                    return (string)$value;
                }else{
                    return str_replace(["\r","\n"],"",var_export($value,true));
                }
            }
            return $value;
        };
        $this->toStringFilters['bool'] = function($value) {
            if(is_bool($value)) return $value?"true":"false";
            return $value;
        };
        $this->toStringFilters['resource'] = function($value) {
            if(is_resource($value)) return get_resource_type($value);
            return $value;
        };
        $this->toStringFilters['exception'] = function($value, $extra) {
            if($value instanceof \Exception) {
                if(isset($extra['key']) && $extra['key'] != "exception") {
                    return null;
                }else{
                    return $value->getMessage();
                }
            }
            return $value;
        };
    }

    public function setLevel($level) {
        if ($level[0] == "=") {
            $this->strictlvl = true;
            $level = substr($level, 1);
        }
        if (!array_key_exists($level, $this->levels)) {
            $this->level = 'info';
            trigger_error("Undefined log level \"{$level}\"", E_USER_NOTICE);
        }else{
            $this->level = $level;
        }
    }

    public function getLevel() {
        return ($this->strictlvl?'=':'').$this->level;
    }

    public function toString($value, $extra = []) {
        foreach($this->toStringFilters as $filter) {
            $value = $filter($value, $extra);
        }
        return $value;
    }

    public function put($text, array $context = [], $level = 'info', $timer = null)
    {
        if ($this->turnedOff || (isset($this->levels[$level]) && $this->levels[$this->level] > $this->levels[$level])) return;

        if(!is_string($text)) {
            $text = $this->toString($text);
        }
        $text = (string)$text;
        $logString = "";
        if(strlen($text)) {
            $logString = $text[0] == "\0" ? substr($text, 1) : null;
        }

        $timerError = null !== $timer ? "[timer_{$timer}_not_found]" : null;
        $context = is_array($this->context) ? array_replace_recursive($this->context, $context) : $context;
        
        foreach($context as $contextKey=>&$contextValue) {
            $contextValue = $this->toString($contextValue, ['key'=>$contextKey]);
        }

        $contextString=$context?json_encode($context):"";
        $p = [
            'date' => date($this->dateFormat),
            'ms' => substr((string)microtime(), 2, 6),
            'timer' => isset($this->timers[$timer]) ? microtime(true) - $this->timers[$timer] : $timerError,
            'level' => $level,
            'context' => $contextString,
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
                $p['level'] = ucfirst($p['level']);
                return "[{$get('date')}{$get('ms', '.')}] {$get('timer', '', 's ')}{$get('level')}: {$get('text')} {$get('context')}" . PHP_EOL;
            };
        }
        $logString = $logString ? : call_user_func($this->formatter, $p, $get);
        if(is_callable($this->contextParser)) {
            $logString = call_user_func($this->contextParser, $logString, $context);
        }
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

    public function __invoke($text, array $context = [], $level = "info", $timer = null)
    {
        return call_user_func_array([$this, "put"], func_get_args());
    }

    public function offsetSet($offset, $value)
    {

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
        return function ($string, array $context = []) use ($level, $timer) {
            $this->put($string, $context, $level, $timer);
        };
    }

    public function __toString()
    {
        return implode('', $this->buffer);
    }

    public function turnOff() {
        $this->turnedOff = true;
    }

    public function turnOn() {
        $this->turnedOff = false;
    }

    public function status() {
        return $this->turnedOff?"off":"on";
    }

    public function timerStart($timer) {
        $this->timers[$timer] = microtime(true);
    }

    public function timerReset($timer) {
        $this->timerStart($timer);
    }

    public function timerStop($timer) {
        if(isset($this->timers[$timer])) {
            unset($this->timers[$timer]);
        }
    }
}
