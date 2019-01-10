<?PHP
//E_ALL & ~E_NOTICE
error_reporting(E_ALL & ~E_NOTICE);

$__free_open__ 					= 1; //0关闭免费，1开启免费
$__login_api__					= 1; //是否使用外部帐号登录
$__prod_name__  				= '云主机';

$__mobile_format				= '/^(13[0-9]|14[0-9]|15[0-9]|17[0-9]|18[0-9])[0-9]{8}$/';
$__tpl_name							='templates';//模板目录
$__version_num__				= "1.00.1610"; //版本号
$__service_chat_show__		=1;//售前咨询开关
$__service_show__			=1;//售后咨询开关
$__beian_show__				=0;//备案开关
$__sale_show__				=1;//优惠设置开关
$__try_open__				=1;//一键体验开关


//调整时区
if(function_exists('date_default_timezone_set')){
	date_default_timezone_set('Hongkong');
}



$tplsTypeArr 						= array();

//0 文章列表（新闻或帮助）；1 产品表列；2 图文内容（如果是此类型则可直接编辑内容）；8 详细内容（产品或文章的详细页面，此项需要设置父级页面，无内容管理）；

$tplsTypeArr[1]						= '文章列表';
$tplsTypeArr[2]						= '产品展示';
$tplsTypeArr[3]						= '图文内容';
$tplsTypeArr[9]						= '外部链接';
$tplsTypeArr[10]					= '详细内容';


$userType=Array();
$userType[0]='个人会员';
$userType[1]='企业/社会团体/政府机关';
$userType[8]='普通管理员';
$userType[9]='系统管理员';




?>