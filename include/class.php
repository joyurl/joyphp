<?php
/*
	对象管理及自动加载程序

	+ 2017-9-12 完善并定型

*/


class ClassAutoLoader {

	public function __construct() {
		//自 PHP 5.3.0 起可以使用一个匿名函数，但之前的版本不能，因此使用对象来处理
		spl_autoload_register(array($this, 'loadfile'));
	}
	private function loadfile($className) {
		$path=dirname(__FILE__);
		if($className=='DB') return false;
		if(class_exists($className)) return false;
		if(substr($className,0,1)=='_'){
			$class_file=$path.'/'.substr(strtolower($className),1) . '.php';
		}
		else{
			$class_file=$path.'/class/class_'.strtolower($className) . '.php';
		}
		if(!file_exists($class_file)){
			throw new Exception("Unable to load file class_".strtolower($className).".php!");
			return false;
		}
		include_once($class_file);
	}
}
new ClassAutoLoader();


//@$dbconfig数据库配置文件及函数名(两个名称需要相同)
function newDB($dbconfig='db_config'){
	
	$path=dirname(dirname(__FILE__));
	$conf=Array();
	//默认配置
	if($dbconfig=='db_config' and function_exists("db_config")){
		$conf=call_user_func("db_config");
	}
	//自定义配置
	else if(!empty($dbconfig)){

		$config_file=$path.'/config/'.$dbconfig.'.php';
		if(!file_exists($config_file)){
			die('<CENTER>Unable to load file include/'.$dbconfig.'.php!</CENTER>');
		}
		include_once($config_file);
		if(!function_exists($dbconfig)){
			die('<CENTER>Undefined function '.$dbconfig.'!</CENTER>');
		}
		$conf=call_user_func($dbconfig);
	}
	else{
		die('<CENTER>Undefined database\'s config!</CENTER>');
	}
	//根据类型加载数据库对象
	if(isset($conf['datafile'])) $conf['datafile']=trim($conf['datafile']);
	if(empty($conf) or empty($conf['datafile'])){
		die('<CENTER>database config error</CENTER>');
	}
	$class_file=$path.'/include/db/'.$conf['datafile'];
	if(!file_exists($class_file)){
		die('<CENTER>Unable to load file '.$conf['datafile'].'! </CENTER>');
	}

	include_once($class_file);
	if(class_exists("DB")){
		$obj=new DB($conf);
		return $obj;
	}
	die('<CENTER>Unable to load DB in '.$conf['datafile'].'! </CENTER>');
}

?>