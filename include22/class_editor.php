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

	//����ʵ����
	public function self_init($conf){
		return new EDITOR($conf);
	}

	//����˵��
	public function self_help($conf){
		$ret=Array();
		$ret['name'] ='TB�������';
		$ret['intro']='����TB����Ĺ������༭TB����Ļ�����';
		return $ret;
	}

	//���ز˵�����
	public function self_menu(){
		$xml='<result>';
		$xml.='<retcode>0/n</retcode>';
		$xml.='<type>conf</type>';
		$xml.='<title>ģ�͹�����</title>';
		$xml.='<menulevel>1</menulevel>';
		$xml.='<data><menu>obj</menu><fathermenu></fathermenu><name>�������</mame></data>';
		$xml.='<data><menu>tbl</menu><fathermenu></fathermenu><name>���ݱ����</mame></data>';
		$xml.='<data><menu>action</menu><fathermenu></fathermenu><name>��������</mame></data>';
		$xml.='<data><menu>version</menu><fathermenu></fathermenu><name>�汾����</mame></data>';
		$xml.='</result>';
		return $xml;
	}

	
}


}
?>