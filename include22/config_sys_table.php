<?PHP

function config_sys_table($key='')
{

	$Tbl=Array();
	//需要修改的部分
	//@array : [key]='程序中使用的别名'; name='真实表名';title=中文名称,bak=是否写修改日志和备份
	$Tbl['set_config'] =Array('name'=>'set_config','title'=>'系统配置','bak'=>1);

	if(!empty($key)){
		$key=preg_replace("/[^_a-z0-9A-Z]/","",$key);
		if(!isset($Tbl[$key])) return '';
		return $Tbl[$key];
	}

	return $Tbl;
}


?>