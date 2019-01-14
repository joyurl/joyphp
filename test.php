<?php 

echo date('W-w',strtotime('2015-01-01')),'<br>';
echo date('W-w',strtotime('2015-01-02')),'<br>';
echo date('W-w',strtotime('2015-12-28')),'<br>';
echo date('W-w',strtotime('2015-12-31')),'<br>';
echo date('W-w',strtotime('2026-01-02')),'<br>';
exit;

echo md5('admin123');exit;
	
	class user_api
	{
		/*
		 $__input_para[$method][$name]=Array('name'=>'参数名(英文)','title'=>'参数简称(中文)','type'=>'int/float/arr/str','null'=>'是否允许空','remark'=>'备注');
		 $__output_para[$method]['result']='0/1';
		 $__output_para[$method]['err_code']='错误编号';
		 $__output_para[$method]['err_msg']='错误信息';
		 $__output_para[$method]['return']=Array();//返回数据相关说明;
		*/
		public   $_argument     ="dddd<br>";
		private  $__method_info=Array();//方法说明
		private  $__para_input =Array();//输入参数说明
		private  $__para_output=Array();//输出参数说明

		public function __construct($conf)
		{
			echo "init:";print_r($conf);
			//$this->_argument="test";
		}

		public function user_api($conf){
			$this->__construct($conf);
		}

		//自身实例化
		public function self_init($conf){
			$a=new user_api($conf);
			return $a;
		}

		//读取方法说明
		public function get_method_info($method){
			if(!empty($method) and isset($this->__method_info[$method])){
				return $this->__method_info[$method];
			}
			return '';
		}

		//读取方法输入参数
		public function get_method_input($method){
			if(!empty($method) and isset($this->__input_para[$method])){
				return $this->__input_para[$method];
			}
			return Array();
		}

		//读取方法输出参数
		public function get_method_output($method){
			if(!empty($method) and isset($this->__output_para[$method])){
				return $this->__output_para[$method];
			}
			return Array();
		}







		private function method1(){
			//echo $this->_argument;
			echo "method1!<br>";
		}

		public function method2($form){
			$this->__method_info['method2']="测试方法：用于测试的";
			$this->__input_para['method2']=
			Array(
				"name"=>Array('name'=>'参数名(英文)','title'=>'参数简称(中文)','type'=>'int/float/arr/str','null'=>'是否允许空','preg'=>'正则规则','remark'=>'备注')
			);
			//echo date("Y-m-d H:i:s");
			//echo '<br>';
			//print_r($form);
			return $form;
		}

	}

//$arr=get_class_methods($C);

echo '<pre>';



$data=array("id"=>2,"name"=>"ccc","date"=>date("Y-m-d"));

//$C=new user_api($data);

//$ret=call_user_func_array(array("user_api","method2"),$data);
//print_r($ret);exit;


$M=Array();
$M['user_api']=call_user_func(array("user_api","self_init"),$data);

$m="abc";
$ret=call_user_func(array($M['user_api'],"method2"),$data);
echo "<br>INFO=".$M['user_api']->get_method_info("method2")."<br>m=$m<br>";

print_r($ret);

//$arr=get_class_methods("user_api");
//print_r($arr);
//class_exists
$isok=method_exists("user_api","method1");
echo "<br>method_exists=$isok<pre>";
$xml="
<?xml version=\"1.0\" encoding=\"GBK\"?>
<!-- 注释1 -->
<result>
 <retcode>0/n</retcode>
 <errmsg>错误信息，result>0时显示的提示信息，有错时可能无boday</errmsg>
 <type>list</type>
 <title>新闻管理</title>
 <pageinfo>
  <pagenow>1</pagenow>
  <pagenum>2</pagenum>
  <rowsnum>15</rowsnum>
  <pagesize>10</pagesize>
 </pageinfo>
 <search>
  <keyword>请输入IP</keyword>
  <data><tag>title</tag><name>news_title</name><title>标题</title><mode>==|?=|%=|&lt;</mode></data>
  <data><tag>date</tag><name>news_adddate</name><title>添加时间</title><mode>==|?=|%=|&lt;</mode></data>
 </search>
 <sort>
  <_s>
  <data><value></value><title>--状态--</title></data>
  <data><value>0</value><title>正常</title></data>
  <data><value>1</value><title>正常</title></data>
  </_s>
  <_kd>
  <data><value>   </value><title>--分类--</title></data>
  <data><value>11</value><title>公司动态</title></data>
  <data><value>12</value><title>行业新闻</title></data>
  </_kd>
 </sort>
 <action>
  <data><title>添加文章</title><type>link/button</type><menu>newsadd</menu></data>
 </action>
 <head>
  <id>自编号</id><title>标题</title><adddate>添加时间</adddate><edit>编辑</edit>
 </head>
 <data><id>10001</id><title>特大喜讯，网站正式上线</title><adddate>2017-1-1</adddate><edit>编辑</edit></data>
 <data><id>10002</id><title>网站日访问量突破1万</title><adddate>2017-4-1</adddate><edit>编辑</edit></data>
 <data><id>10003</id><title>网站日访问量突破2万</title><adddate>2017-5-1</adddate><edit>编辑</edit></data>
 <batchedit>
  <data><title>暂停</title><name>stop</name></data>
  <data><title>恢复</title><name>resume</name></data>
  <data><title>删除</title><name>del</name></data>
 </batchedit>
 <remark>使用说明：本值为使用说明&lt;&gt;</remark>
</result>";


//$ret=XML2Array($xml);
//$ret=simplexml_load_string($xml,"SimpleXMLElement",2);
$ret=xml_to_array($xml);
print_r($ret);

function xml_to_array($xml,$tidy=0){
	if(!$tidy){
		if(function_exists("libxml_disable_entity_loader")){
			libxml_disable_entity_loader(false);
		}
		//$arr=json_decode(json_encode((array)simplexml_load_string($xml,"SimpleXMLElement",0)),true);
		$arr=@(array)simplexml_load_string($xml,"SimpleXMLElement",1);
	}
	else{
		$arr=$xml;
	}
	/*
	foreach ( $arr as $key => $value )
	{
		//$value = ( array ) $value ;
		if($key==="data" and !isset($value[0])){
			//if($key==="data") echo '['.$key.']<br>';
			//echo "$key = > |$value[0]|";print_r($value);exit;
			unset($arr[$key]);
			$arr[$key][0]=$value;
			//
		}
	}
	*/

	$retArr=Array();
	foreach ( $arr as $key => $value )
	{

		//echo "<br>$key - ".is_string($value);continue;
		if ( is_string ( $value) )
		{
			$retArr [ $key ] =  $value;
			continue;
		}

		$value=(array) $value;
		//值为纯空格标签
		if(isset($value [0]) and count($value)==1 and trim($value[0])==''){
			//$tmp=trim($value[0]);
			//echo "$key = > |$value[0]|";print_r($value);exit;
			
			$retArr [ $key ] = trim( $value[0]) ;
			unset($arr[$key]);
		}
		//空标签
		else if(count($value)==0){
			//echo "$key = > |$value[0]|";print_r($value);exit;
			
			$retArr [ $key ] = '' ;
			unset($arr[$key]);
		}
		else
		{
			$retArr [ $key ] = xml_to_array ( $value , 1 ) ;
		}
		/*
		  if ( isset ( $value [ 0 ] ) )
        {
            $newArray [ $key ] = trim ( $value [ 0 ] ) ;
        }
        else
        {
            $newArray [ $key ] = XML2Array ( $value , true ) ;
        }*/
	}
	return $retArr;
}

$curl_error=Array();
$curl_error[1]='URL地址错误';
$curl_error[2]='URL地址连接失败';
$curl_error[3]='URL地址无法访问，请稍后再试';
$curl_error[4]='URL地址请求超时，请稍后再试';
$curl_error[5]='URL地址请求发生错误';
$curl_error[11]='URL地址返回数据为空';
$curl_error[12]='URL地址返回数据格式不正确';

	function curl_get($url,$poststr="",$timeOut=10)
	{
		$ch=@curl_init();
		@curl_setopt($ch,CURLOPT_URL,$url);
		@curl_setopt($ch,CURLOPT_POST,1);
		@curl_setopt($ch,CURLOPT_POSTFIELDS,$poststr); 
		@curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		@curl_setopt($ch,CURLOPT_COOKIESESSION,false);
		@curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
		@curl_setopt($ch,CURLOPT_HEADER,false);
		@curl_setopt($ch,CURLOPT_TIMEOUT,$timeOut);
		@curl_setopt($ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_0);//squid post 1.1 不能超过1k
		if(preg_match("/^https:/i",$url)){
			@curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
			@curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
		}
		$ret=Array();
		$ret['return']=@curl_exec($ch);
		$ret['result']=0+@curl_errno($ch);
		$ret['errmsg']='';
		//$curlerr=curl_errno($ch);
		if($ret['result']!==0){
			//28是超时
			if($ret['result']==28) $ret['result']=4; //Time out 
			else if($ret['result']==7) $ret['result']=3;//connect fail
			else if($ret['result']==6) $ret['result']=2;//connect deny
			else if($ret['result']==1 or $ret['result']===3) $ret['result']=1; //URL error
			else {
				$ret['result']=5;
			}
			$ret['errmsg']=@curl_error($ch);
		}
		return $ret;
	}


?>