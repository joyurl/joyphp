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


if(!defined('__CLASS__P__')){
  define('__CLASS__P__', 1);

class P
{

	public function __construct()
	{
		//$this->_argument="test";
	}

	public function P(){
		$this->__construct();
	}

	//自身实例化
	public function self_init($conf){
		return new P($conf);
	}

	//数据处理主接口，此方法自动调用对应的show_方法
	public function main(){
		
	}

	//处理数据列表
	protected function show_list(){
		
	}

	//处理数据编辑或预览
	protected function show_view(){
		
	}

	//处理报表数据
	protected function show_report(){
		
	}



} //END class

}//END __CLASS__P__
?>