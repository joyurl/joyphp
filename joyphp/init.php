<?php
/*
 *  ��ʼ���ļ�
 */ 


define('EXT', '.php');
define('DS', DIRECTORY_SEPARATOR);

defined('CORE_PATH') or define('CORE_PATH', __DIR__ . DS);
defined('ROOT_PATH') or define('ROOT_PATH', dirname(realpath(CORE_PATH)) . DS);
define('LIB_PATH',   CORE_PATH . 'lib' . DS);
define('TRAIT_PATH', CORE_PATH . 'trait' . DS);
defined('EXTEND_PATH') or define('EXTEND_PATH', CORE_PATH . 'extend' . DS);
defined('CONF_PATH') or define('CONF_PATH', ROOT_PATH  . 'config' . DS); // �����ļ�Ŀ¼
defined('RUNTIME_PATH') or define('RUNTIME_PATH', ROOT_PATH . 'runtime' . DS);

defined('CONF_EXT') or define('CONF_EXT', EXT); // �����ļ���׺


// ��������
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);


require LIB_PATH . 'Loader.php';

//ע���Զ�����
\joyphp\Loader::register();

//ע����������

