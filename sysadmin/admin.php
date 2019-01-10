<?php
/*
 * 控制台
 */

header("Content-type: text/html; charset=UTF-8");

$config=Array(
    'type'       =>'mysql',
    'hostname'        => 'localhost',
    'hostport'        =>3306,
    // 数据库名
    'database'        => 'joyurl',
    // 用户名
    'username'        => 'root',
    // 密码
    'password'        => 'rootroot',
    'charset'         => 'utf8' //utf8
);


include("../joyphp/init.php");
//include("../joyphp/lib/Db.php");
//include("../joyphp/lib/db/Database.php");
//include("../joyphp/lib/db/Db_mysql.php");
$filename=CONF_PATH . 'database' . EXT;
$conf=\joyphp\Config::load($filename,'database');

print_r(\joyphp\Config::get());

use joyphp\db;

// 定义应用目录
define('APP_PATH', dirname(__DIR__) . '/app/');
echo '<br>App_path =',APP_PATH;
echo '<br>root_path=',ROOT_PATH;
echo '<br>Core_path=',CORE_PATH;
echo '<br>Lib_path=', LIB_PATH;
echo '<br>extend_path=',EXTEND_PATH;
echo '<br>Conf_path=',CONF_PATH;
echo '<br>Run_path=',RUNTIME_PATH;

echo '<br><pre>';

//print_r($config);

if(1){
    //$result=Db::init($config)->query("update user3 set user_name='管理员' where user_id=1001");

    $result=Db::query("select * from user2");
    print_r($result);
}
//echo '<br>';
//$result=Db::init($config)->query("SHOW CREATE TABLE `adm_user`");
//print_r($result);

/*
CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(30) NOT NULL DEFAULT '' COMMENT '账号',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1  COMMENT='管理员';

CREATE TABLE `user2` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf-8;

CREATE TABLE `user3` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(30) NOT NULL DEFAULT '' COMMENT '账号',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 
//24:32;    20 () :: 28 (3*9+1)
replace into user set user_name='管理员abcde12345' ,user_id=1001;
replace into user2 set user_name='管理员abcde12345',user_id=1001;
replace into user2 set user_name='abcde12345abcde12345abcde12345' ,user_id=1002;
replace into user3 set user_name='管理员abcde12345' ,user_id=1001;
replace into user3 set user_name='abcde12345abcde12345abcde12345' ,user_id=1002;


*/