<?php
/**
 * Created by PhpStorm.
 * User: linyang
 * Date: 16/8/26
 * Time: 上午10:42
 */

namespace cube;

use cube\engine\AngularEngine;
use cube\engine\ViewEngine;
use log\Log;
use utils\Utils;

/**
 * Class App.
 * Cube HTTP Framework Facade Core Class.
 * Copyright(c) 2016 Linyang.
 * MIT Licensed
 * @package cube
 */
final class App
{
    /**
     * facade Router.
     * @var Router
     */
    private static $router = null;
    /**
     * cube/Request.
     * @var Request
     */
    private static $req = null;
    /**
     * cube/Response.
     * @var Response
     */
    private static $res = null;

    /**
     * Application GarbageCollection.
     */
    public static function gc()
    {
        if (self::$router) {
            self::$router->gc();
            self::$router = null;
        }
        self::$req = null;
        self::$res = null;
        self::$router = null;

        Log::flush();
    }

    /**
     * initialize the app.
     *
     * options:[
     *      'base_dir'=>'project dir',
     *      'time_zone'=>'zone',
     *      'time_limit'=>'set_time_limit',
     *      'error_report'=>'0/1',
     *      'debug'=>1
     * ]
     * @param $options array
     */
    public static function init($options)
    {
        if (self::$router) {
            throw new \Exception('App has been initialized!');
        }

        //load libs & modules.
        Config::init($options);

        //check php version.
        if (!Utils::is_legal_php_version('5.4.0')) {
            throw new \Exception('PHP VERSION IS LOW!');
        }

        self::$req = new Request();

        self::$res = new Response();

        self::$router = new Router(self::$req, self::$res);

        //load the logic.
        import(Config::get('core', 'app'));

        //app start.
        self::$router->next();

        //debug stack.
        if ($options && $options['debug']) {
            Log::log(Stack::value(self::$router->stack()));
        }

        //gc.
        self::gc();
    }


    /**
     * return the facade router.
     *
     * $app = App::app();
     * $app->on('/test',function($req,$res,$next){
     *      $next();
     * });
     *
     * @return Router
     */
    public static function app()
    {
        return self::$router;
    }

    /**
     * create a child router.
     *
     * RIGHT USAGE OF the App::Router():
     *
     * [app.php]
     * $app = App::app();
     * $app->on('/test',function($req,$res,$next){
     *      $next();
     * });
     * $app->on('/my','router/my.php');
     *
     * [my.php]
     * $router = App:Router();
     * $router->on('/',function($req,$res,$next){
     *      $next();
     * });
     * $router->on('/say',function($req,$res,$next){
     *      $next();
     * });
     * --------------------------------------------------
     * WRONG USAGE OF the App::Router():
     *
     * [app.php]
     * $app = App::app();
     * $app->on('/test',function($req,$res,$next){
     *      $next();
     * });
     * $app->on('/my','router/my.php');
     *
     * $router = App:Router();
     * $router->on('/',function($req,$res,$next){
     *      $next();
     * });
     * $router->on('/say',function($req,$res,$next){
     *      $next();
     * });
     *
     * Router at this time will not be put into the queue, and the child Router of the parent Router is lost!
     *
     * @return Router
     */
    public static function Router()
    {
        return Router::createFactory(self::$req, self::$res);
    }

    /**
     * redirect the request path.
     *
     * @param $value string
     * @throws \Exception
     */
    public static function redirect($value)
    {
        if (!self::$router) {
            throw new \Exception('App has been initialized!');
        }
        if ($value) {
            self::$req->redirected = true;
            self::$req->path = Utils::pathFilter($value);
            self::$router->next(true);
        }
    }

    /**
     * global render the view engine.
     *
     * @param $engine ViewEngine
     * @param $name string
     * @param $value object
     */
    public static function globalRender($name, $value = null)
    {
        if (self::$res) {
            self::$res->angular($name, $value);
        } else {
            $engine = new AngularEngine();
            $engine->render($name, $value);
        }

        App::gc();
    }

    private function __construct()
    {
        //private
    }
}

/**
 * Class Stack.
 * @package cube
 */
final class Stack
{
    private static $str = '<b>( / , Router )</b><br>';
    private static $rightStr = '';

    /**
     * analyze the stack of the facade router.
     * @param $stack array
     */
    private static function show($stack)
    {
        if ($stack) {
            self::$rightStr .= '   ';
            foreach ($stack as $item) {
                if (is_array($item)) {
                    if (is_string($item[1])) {
                        self::$str .= self::$rightStr . '( ' . $item[0] . ' , ' . $item[1] . " )\r\n";
                    } else if (get_class($item[1]) == 'Closure') {
                        self::$str .= self::$rightStr . '( ' . $item[0] . ' , function($req,$res,$next' . ") )\r\n";
                    } else {
                        self::$str .= self::$rightStr . '( ' . $item[0] . ' , Router , ' . $item[2] . " )\r\n";
                        self::show($item[1]->stack());
                    }
                } else {
                    self::$str .= self::$rightStr . '( function($req,$res,$next' . ") )\r\n";
                }
            }
            self::$rightStr = substr(self::$rightStr, 0, -3);
        }
    }

    /**
     * get the stack result.
     * @return string
     */
    public static function value($value)
    {
        self::$str = "\r\n";
        self::$str .= "------------------------ stack ------------------------\r\n";
        self::$str .= "( / , Router )\r\n";
        self::$rightStr = '';
        self::show($value);
        self::$str .= "------------------------ end ------------------------\r\n";

        return self::$str;
    }
}


/**
 * Class Config.
 * save the Application package.json object.
 * save the global values.
 */
final class Config
{
    /**
     * cube global config object.
     * @var array
     */
    private static $VALUE = null;


    /**
     * append the package.json object info.
     * all constant value.
     *options:[
     *      'base_dir'=>'project dir',
     *      'time_zone'=>'zone',
     *      'time_limit'=>'set_time_limit',
     *      'error_report'=>'0/1'
     * ]
     * @param $json array
     * @throws \Exception
     */
    public static function init($options)
    {
        if (!$options) {
            $options = [];
        }

        $options['base_dir'] = $options['base_dir'] ? ($options['base_dir'] . '/') : (__DIR__ . '/../');

        set_time_limit($options['time_limit'] ? $options['time_limit'] : 0);
        error_reporting($options['error_report'] ? $options['error_report'] : 0);
        date_default_timezone_set($options['time_zone'] ? $options['time_zone'] : 'Asia/Shanghai');

        define('BASE_DIR', $options['base_dir']);
        define('START_TIME', microtime(true));

        if ($json = json_decode(file_get_contents($options['base_dir'] . 'package.json'), true)) {
            self::$VALUE = $json;

            define('VIEW_DIR', $options['base_dir'] . $json['dir']['view'] . '/');
            define('TMP_DIR', $options['base_dir'] . $json['dir']['tmp'] . '/');
            define('LOG_PATH', $options['base_dir'] . $json['log']['log']);
            define('LOG_SQL_PATH', $options['base_dir'] . $json['log']['sql']);
            $GLOBALS['CONFIG'] = $json;

            ini_set('upload_tmp_dir', $options['base_dir'] . $json['dir']['tmp'] . '/');

            import([
                'cube/fs/autoload.php',
                'cube/engine/autoload.php',
                'cube/Request.php',
                'cube/Response.php',
                'cube/Router.php'
            ]);

            import($json['modules']);
        } else {
            throw new \Exception('config is error or null');
        }
    }

    /**
     * Get the package.json object children value.
     *
     * Config::get('dir','view');
     *
     * @param $arg1 string
     * @param string $arg2
     * @return object | null
     */
    public static function get($arg1, $arg2 = '')
    {
        if ($arg2) {
            return self::$VALUE[$arg1][$arg2];
        } else {
            return self::$VALUE[$arg1];
        }
    }
}

function onErrorHandler()
{
    if ($e = error_get_last()) {
        switch ($e['type']) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                displayError(['msg' => $e['message'], 'level' => $e['type'], 'line' => $e['line'], 'file' => $e['file']]);
                break;
        }
    }
}

/**
 * Global Exception Handler.
 * @param Exception $e
 */
function onExceptionHandler(\Exception $e)
{
    displayError(['msg' => $e->getMessage(), 'level' => $e->getCode(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}

function displayError($errors)
{
    if ($GLOBALS['cube-error']) return;
    $GLOBALS['cube-error'] = true;

    import('modules/log/autoload.php');

    Log::log('Exception/Error: ' . $errors['message']);
    App::globalRender('500', $errors);
}

$GLOBALS['cube-error'] = false;
set_error_handler('cube\onErrorHandler');
set_exception_handler('cube\onExceptionHandler');
register_shutdown_function('cube\onErrorHandler');