<?php

include("./include/db_mysql.php");
include("./include/config_table.php");
$arr=get_class_methods("DB");
echo '<pre>';
print_r($arr);

$conf=Array("db_type"=>"mysql","db_host"=>"localhost");
$saveField=Array();
$saveField['conf_name']=Array(0,50,0,"");
$saveField['conf_value']=Array(0,250,0,"");
$saveField['conf_visits']=Array(1,999999999,0,"");
$savedata=Array();
$savedata['conf_name']="site_name";
$savedata['conf_value']="ÖÐ»ªÍø";
$savedata['conf_visits']=2;

$M=Array();

$M['DB']=call_user_func(array("DB","self_init"),$conf);
$M['DB']->setTable('set_config');
$M['DB']->setField($saveField);
$M['DB']->setData($savedata);
//$ret=call_user_func(array($M['DB'],"del"));
$ret=$M['DB']->find(Array("conf_name"=>"site_name"));
echo $M['DB']->_errmsg;

//$DB=new DB($conf);
?>