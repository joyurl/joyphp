<?PHP

/*
	逻辑控制对象
	=========================
	作用：
	+ 定义M与V之间的数据交换规则
	+ 把M返回的数据按规则转换为可供V显示的代码
	+ 按规则处理显示权限
	+ 按规则处理流程或逻辑，用于将多个不同M方法组合成一个大的处理流程

*/


if(!defined('__CLASS__DB__')){
  define('__CLASS__DB__', 1);

class DB
{

	public function __construct()
	{
		//$this->_argument="test";
		//根据类型加载对象
		$path=dirname(dirname(__FILE__));
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
		$obj=new _DB($conf);
		//print_r($obj);
		return $obj;

	}

	public function DB(){
		$this->__construct();
	}

	//自身实例化
	public function self_init(){
		return new DB();
	}


} //END class

}//END __CLASS__DB__
?>