[![Latest Stable Version](https://poser.pugx.org/epilog/epilog/v/stable)](https://packagist.org/packages/epilog/epilog) [![Total Downloads](https://poser.pugx.org/epilog/epilog/downloads)](https://packagist.org/packages/epilog/epilog) [![Latest Unstable Version](https://poser.pugx.org/epilog/epilog/v/unstable)](https://packagist.org/packages/epilog/epilog) [![License](https://poser.pugx.org/epilog/epilog/license)](https://packagist.org/packages/epilog/epilog)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/fe2a151f-ac72-4735-9f69-dd996ec5cd45/big.png)](https://insight.sensiolabs.com/projects/fe2a151f-ac72-4735-9f69-dd996ec5cd45)

Epilog - Logger for PHP 5.4+
============================

This is simple to use logger with great syntax and level of customization. Easy start for new application and serious tune for grown application. Look at examples and feature list below. Enjoy!

Usage
-----
```php
include "Epilog.php";

$log = new Epilog();
$log('By default php://stdout is output stream and info is selected severity');
```
Will output
```
[2014-01-20 11:36:36.43] Info: By default php://stdout is output stream and info is selected severity
```

Install
-------
Just add epilog/epilog to require section in composer.json and install it. For example:
```
    "require": {
        "epilog/epilog": "1.1.0"
    }
```

Main goal
---------
Epilog was made as tool for developers by developers. You can make hot start and then change everything by accessing public properties of object. Main goal is to make debug as simple as we can! Many years of debug expirience are here in Epilog. What about other loggers? They are too big and uncomfortable. With Epilog you can end your application while your friend is setting up another logger.

Advanced features
-----------------
- PSR-3 support
- Different severity levels
- Add your own severity levels
- Select strict severity level (handle only selected level)
- Timers
- Handle strict severity levels by different channels
- Custom handlers
- Different channels support
- Context support
- Setup custom formatter
- Setup custom filter set
- Possibility to log raw data
- Turn off logger
- Buffer with custom size
- Date format change

PSR-3 support
-------------
Epilog is [PSR-3 standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) compatible. Use Pepilog class that implemets Psr\Log\LoggerInterface. All psr-3 tests are passed perfectly. You can use Pepilog with Silex, Symfony2 and others.

Silex example:
```php
require_once __DIR__.'/../vendor/autoload.php';
$app = new Silex\Application();

$app['logger'] = $app->share(function(){
	return new Pepilog('/tmp/temp.log', 'debug');
});
```

Different severity levels
-------------------------
Supports 8 standard severity levels. You can easly add your own.  
  
__debug__ - debug-level messages  
__info__ - informational messages
__notice__ - normal but significant condition
__warning__ - warning conditions
__error__ - error conditions
__critical__ - critical conditions
__alert__ - action must be taken immediately
__emergency__ - system is unusable

How to write to different levels?
```php
$log['debug']("Application start.");
$log['error']("Application error.");
$log['alert']("Can't write to cache. Access denied");
```
How to select output level? Just specify it as second parameter
```php
$log = new Epilog('php://stdout', "error");
```
How to add severity levels?
```php
$log->levels['customLevel'] = 450;
$log['customLevel']("Custom severity level log");
```
What is "strict severity level"? In this case only errors will be handled by logger.
```php
$log = new Epilog('php://stdout', "=error");
```

Timers
------
You can setup and use different timers in your application
```php
$log->timerStart('timer1');
$log['info']("Aplication start");

sleep(1);

$log['info:timer1']("Application end");
$log[':timer1']("Write to default level with timer value");
```
Will output
```
[2014-01-21 11:30:45.22] Info: Aplication start 
[2014-01-21 11:30:46.22] 1.0007s Info: Application end 
[2014-01-21 11:30:46.22] 1.0010s Info: Write to default level with timer value 
```
You can reset timer
```php
$log->timerReset('timer1');
```
Or stop it
```php
$log->timerStop('timer1');
```
Custom handlers
---------------
You can add your custom handler to channel. For example MySQL handler:
```php
$customHandler = function($logString, $params) use ($config) {
  static $connection = null;
  if (!$connection) {
      $connection = new PDO('mysql:host=localhost;dbname=test', $config['dbuser'], $config['dbpass']);
  }

  $stmt = $dbh->prepare(
    "INSERT INTO log_table(date, timer, level, context, text, formatted_text)
    VALUES (:date, :timer, :level, :context, :text, :formatted_text)"
  );
  $stmt->bindParam(':date', $params['date']);
  $stmt->bindParam(':timer', $params['timer']);
  $stmt->bindParam(':level', $params['level']);
  $stmt->bindParam(':context', $params['context'];
  $stmt->bindParam(':text', $params['text']);
  $stmt->bindParam(':formatted_text', $logString);
  $stmt->execute();
}
$log = new Epilog($customHandler);
```
Different channels support with different level
-------------------------------------------------------
There is simple way to setup different channels. You can use severity standard and strict notation.
```php
$channels = [
  'info'  => ["php://stdout", function($logString, $params){file_put_contents('filename', $text);}],
  'error' => ["php://stderr", "/var/log/application_error.log"], // These handlers will process error+ levels
  '=debug' => ["/tmp/application_debug.log"]  // These handlers will process only debug level
];
$log = new Epilog($channels);
```
Context support
---------------
You can use global and local context. It accepts only arrays of data. 
```php
$log->context = ['user_name'=>'Bob']; // Setting up global context
$log("User {user_name}({user_id}) is logged in", ['user_id'=>33]); // Adding local context
$log("User {user_name}({user_id}) is logged out"); // Without local context
```
Will output
```
[2014-01-21 11:17:45.56] info: User Bob(33) is logged in {"user_name":"Bob","user_id":33}
[2014-01-21 11:17:45.56] info: User Bob({user_id}) is logged out {"user_name":"Bob"}
```
Setup custom formatter
----------------------
You can use $get helper function that can prepend and append symbols to parameter if it isn't empty.
```php
$get('date', '[', ']'); // Will return [2014-01-21 11:17:45] if not empty (depends on format)
$params['date']; // Contains date value
```
Example:
```php
$customFormatter = function($params, $get){
    return "{$get('date')}{$get('timer', ' (', 's)')} !{$get('level')}!: {$get('text')} \n";
};
$log = new Epilog('php://stdout', 'info', $customFormatter);

$log['timer'] = 'start';
$log("Message without timer");
$log[':timer']("Message with timer");
```
Will return
```
2014-01-21 11:31:37 !Info!: Message without timer
2014-01-21 11:31:37 (0.0003s) !Info!: Message with timer
```
Setup custom filters set
------------------------
You can add more filters or remove default(filter milliseconds and timer format)
```php
unset($log->filter['default']);  // Remove default filters

$log->filter[] = function($params){
  $params['text'] = trim($params['text']); // Text trim filter
  return $params;
};
```
Available params:
- date
- ms
- timer
- level
- context
- text
Log raw data
------------
Just concatenate Epilog::RAW or add null byte before log string:
```php
$log(Epilog::RAW."Raw log string\n");
$log("\0Raw log string 2\n");
```
```
Raw log string
Raw log string 2
```
Turn on/off logger
---------------
Simply setup log level to "off" or use Epilog::TURN_OFF constant
```php
$log = new Epilog();
$log->turnOff();

echo $log->status(); // off
$log->turnOn();
echo $log->status(); // on

```
Buffer with custom size
-----------------------
By default size is 0 (unlimited)
```php
$log = new Epilog("logger://buffer"); // You can use Epilog::BUFFER_ADDRESS constant instead of "logger://buffer"
$log->bufferSize = 2; // Store 2 last messages
$log("Foo");
$log("Bar");
$log("Baz");

echo $log; // __toString() will output buffer
```
```
[2014-01-21 11:55:49.80] Info: Bar
[2014-01-21 11:55:49.80] Info: Baz
```
Change date format
------------------
```php
$log->dateFormat = "d.m.Y H:i:s"; // By default is "Y-m-d H:i:s"
```
