<?php



if(!defined('__CLASS__EDITOR__')){
  define('__CLASS__EDITOR__', 1);


class EDITOR
{
	public function __construct()
	{
		//$this->_argument="test";
	}

	public function EDITOR(){
		$this->__construct();
	}

	//自身实例化
	public function self_init($conf){
		return new EDITOR($conf);
	}

	//对象说明
	public function self_help($conf){
		$ret=Array();
		$ret['name'] ='TB管理对象';
		$ret['intro']='用于TB对象的管理，即编辑TB对象的基本表';
		return $ret;
	}

	//返回菜单定义
	public function self_menu(){
		$xml='<result>';
		$xml.='<retcode>0/n</retcode>';
		$xml.='<type>conf</type>';
		$xml.='<title>模型管理器</title>';
		$xml.='<menulevel>1</menulevel>';
		$xml.='<data><menu>obj</menu><fathermenu></fathermenu><name>对象管理</mame></data>';
		$xml.='<data><menu>tbl</menu><fathermenu></fathermenu><name>数据表管理</mame></data>';
		$xml.='<data><menu>action</menu><fathermenu></fathermenu><name>功能配置</mame></data>';
		$xml.='<data><menu>version</menu><fathermenu></fathermenu><name>版本管理</mame></data>';
		$xml.='</result>';
		return $xml;
	}

	
}


}
?>