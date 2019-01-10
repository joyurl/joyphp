<?php


class ClassAutoLoader {

	public function __construct() {
		//自 PHP 5.3.0 起可以使用一个匿名函数
		spl_autoload_register(array($this, 'loader'));
	}
	private function loader($className) {
		$className=strtolower($className);
		$path=dirname(__FILE__);
		$class_file=$path.'/include/class_'.$className . '.php';
		if(!file_exists($class_file)){
			throw new Exception("Unable to load $className.");
			return false;
		}
		include_once($class_file);
	}
}
new ClassAutoLoader();


function DB(){
	//根据类型加载对象
	$path=dirname(__FILE__);
	$conf=Array();
	if(function_exists("db_config")){
		$conf=db_config();
	}
	else{
		$config_file=$path.'/config/db_config.php';
		if(file_exists($config_file)){
			include($config_file);
			$conf=db_config();
		}
	}
	
	if(empty($conf) or empty($conf['datafile'])){
		die('<CENTER>database config error</CENTER>');

	}
	$class_file=$path.'/include/'.$conf['datafile'];
	if(!file_exists($class_file)){
		echo $class_file;
		die('<CENTER>Don\'t found datafile('.$conf['datafile'].')! </CENTER>');
	}
	//echo "$class_file";
	include_once($class_file);
	$obj=new DB($conf);
	return $obj;
}



$DB=DB();
$obj = new B();
echo '<pre>';print_r($obj);
//$obj = new Class2();


?>