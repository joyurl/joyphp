<?php
/*
 *  初始化文件
 */ 


define('EXT', '.php');
define('DS', DIRECTORY_SEPARATOR);

defined('CORE_PATH') or define('CORE_PATH', __DIR__ . DS);
defined('ROOT_PATH') or define('ROOT_PATH', dirname(realpath(CORE_PATH)) . DS);
define('LIB_PATH',   CORE_PATH . 'lib' . DS);
define('TRAIT_PATH', CORE_PATH . 'trait' . DS);
defined('EXTEND_PATH') or define('EXTEND_PATH', CORE_PATH . 'extend' . DS);
defined('CONF_PATH') or define('CONF_PATH', ROOT_PATH  . 'config' . DS); // 配置文件目录
defined('RUNTIME_PATH') or define('RUNTIME_PATH', ROOT_PATH . 'runtime' . DS);

defined('CONF_EXT') or define('CONF_EXT', EXT); // 配置文件后缀


// 环境常量
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);


require LIB_PATH . 'Loader.php';

//注册自动加载
\joyphp\Loader::register();

//注册错误处理机制

