<?PHP

function config_sys_table($key='')
{

	$Tbl=Array();
	//��Ҫ�޸ĵĲ���
	//@array : [key]='������ʹ�õı���'; name='��ʵ����';title=��������,bak=�Ƿ�д�޸���־�ͱ���
	$Tbl['set_config'] =Array('name'=>'set_config','title'=>'ϵͳ����','bak'=>1);

	if(!empty($key)){
		$key=preg_replace("/[^_a-z0-9A-Z]/","",$key);
		if(!isset($Tbl[$key])) return '';
		return $Tbl[$key];
	}

	return $Tbl;
}


?>