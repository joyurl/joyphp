<?php
/*
	include/phpuu_functions.php
	-----------------------------

	+ 非英文字符的截取

*/



/* ------------ DB config  -------------- */



if(!defined('__PHP__FUN__')){
	define('__PHP__FUN__', 1);

	#------------------
	#	NO 1 refer
	#------------------

	# PHP 1.1
	function chk_url(){

		$url=explode('?',$_SERVER["HTTP_REFERER"]);
		if(empty($url[0])){
			return false;
		}
		else{
			return @preg_match('/'.$_SERVER["HTTP_HOST"].'/i',$url[0]) or @preg_match('/'.$_SERVER["SERVER_ADDR"].'/i',$url[0]);
		}
	}

	# PHP 1.2 2008-2-8
	function stripVar(&$val)
	{
		if(is_array($val))
		{
			foreach($val as $key => $v)
			{
				stripVar($val[$key]);
			}
		}
		else
		{
			$val=StripSlashes($val);
		}
	}

	# PHP 1.3
	//@varType: 0 string, 1 int 2 double
	function getpost($fields='',$varType=0)
	{
		$rval=array_merge($_GET,$_POST);
		if(strlen($fields)>0)
		{
			if (isset($rval[$fields]))
			{
				$rval=$rval[$fields];
			}
			else
			{
				$rval='';
			}
			if ($varType>0) $rval=trim($rval);

			if (strlen($rval)>0 and $varType>=1 and $varType<3)
			{
				return ($varType==2 ? doubleval($rval) : getint($rval));
			}
		}
		if (get_magic_quotes_gpc()) stripVar($rval);

		return $rval;
	}

	function str_hidden($str)
	{
		$html='';
		$tmp=explode("&",$str);
		foreach($tmp as $items)
		{
			if (strlen($items)<3 or !preg_match('/=/',$items))
			{
				continue;
			}
			$row=explode("=",$items);
			if (strlen($row[0])<1 or strlen($row[1])<1)
			{
				continue;
			}
			$html=$html.'
		<input type="hidden" name="'.$row[0].'" value="'.$row[1].'">';
		}
		return $html;
	}

	/* getint 2010-5-24 */
	function getint($num='')
	{
		$positive=1;
		if (substr($num,0,1)=='-') $positive=0;
		$num=preg_replace('/\D/','',$num);
		//if(strlen($num)==0) $num=0;
		return $positive==1 ? $num : -$num;
	}

	// PHP 1.4 (2001.11-2004.09.10) ################
	function getstr(&$rval,$fdName='',$maxlen=0,$minlen=0){
		$maxlen=intval($maxlen);
		$minlen=intval($minlen);
		$thelen=strlen($rval);

		$error='';
		if(!empty($fdName)){
			if($minlen>0 and $thelen==0)
				$error='['.$fdName.']不能为空！';
			else if($minlen>$thelen)
				$error='['.$fdName.']不能小于'.$minlen.'字节！';
			else if($maxlen>0 and $thelen>$maxlen)
				$error='['.$fdName.']('.$thelen.'字节)不能大于'.$maxlen.'字节！';
		}
		return $error;
	}

	// PHP 1.5 检测数字 (2003.12.28-2004.09) ################
	function getnum(&$rval,$fdName='',$max=1,$min=0){
		$max=doubleval($max);
		$min=doubleval($min);
		$rval=doubleval($rval);

		$error='';
		if(!empty($fdName)){
			if($rval<$min)
				$error=$error.'['.$fdName.']不能小于'.$min.'！';
			else if($rval>$max)
				$error=$error.'['.$fdName.']不能大于'.$max.'！';
		}
		if($rval>$max) $rval=$max;
		else if($rval<$min) $rval=$min;
		return $error;
	}


	function show_form($name,$form,$RS=Array()){
		if(is_array($form) and isset($form[$name])){
			return get_html($form[$name]);
		}
		else if(is_array($RS) and isset($RS[$name])){
			return get_html($RS[$name]);
		}
		return '';
	}

	// PHP 1.6 传文件处理 (2003.9-2004.09) ################
	function getfile($fd,$fdName='',$maxKB=0,$minB=0,&$error,$tp=''){
		//size
		$upfile=Array();
		if(isset($_FILES[$fd])) $upfile=$_FILES[$fd];
		else return $upfile;
		if(empty($upfile['tmp_name']) or $upfile['tmp_name']=="none"){
			$upfile['tmp_name']='';//change 'none' In Windows to ''
			return $upfile;
		}

		$upfile['ext']=strtolower(strrchr($upfile['name'],"."));
		$upfile['name']=$upfile['name'];
		if(preg_match('/^\./',$upfile['name'])){
			$error=$error.'['.$fdName.']不能是以(.)开始的文件！ ';
		}
		else if(strtolower($upfile['ext'])=='.php'){
			$error=$error.'['.$fdName.']不能是PHP格式！ ';
		}
		else if(!empty($fdName) and !empty($tp))
		{
			$type=preg_replace('/\./i','\\.',$tp);
			$type=preg_replace('/\//i',"|",$type);
			if (!preg_match('/^('.$type.')$/i',$upfile['ext']))
			{
				$error=$error.'['.$fdName.']不是允许的('.$tp.')格式！ ';
			}

		}
		else if(!empty($fdName) and $upfile['size'] > $maxKB*1024 and $maxKB>0){
			$error=$error.'['.$fdName.']不能大于'.$maxKB.'KB！ ';
		}
		return $upfile;
	}

	


	#---------------------
	#	NO 2 display
	#---------------------

	# PHP 2.1
	function goURL($url)
	{
		header('location: '.$url);
		exit;
	}

	#  PHP 2.2 格式化特殊字符为HTML代码
	function get_html($str,$htmlsc=0)
	{
		if($htmlsc==0 or $htmlsc==2){
			$str=htmlspecialchars($str);
		}
		if($htmlsc==1 or $htmlsc==2){
			$str=str_replace("  ","&nbsp; ",nl2br($str));
		}
		return $str;
	}



	# PHP 2.4 (2005-12-16 )
	# $entag: 0 zh,en都当作一个 ;1 两个en当一个字; 2 1.5个en当一个字，适合于中英文混排
	function sub_string($string,$zhlen,$addStr='',$entag=0)
	{
		$string=strval($string);
		if(strlen($string)<$length)
		{
			return $string;
		}

		$newstr='';
		$len=0;
		$enchar=0;
		$tmplen=0;
		$adds='';
		for ($i=0; $i<$zhlen+1; $i++)
		{
			if ($len+$tmplen>=$zhlen)
			{
				break;
			}

			$newstr=$newstr.$string[$i];

			if (ord($string[$i])>0x7E or $string[$i]>'~')//zh char
			{
				$newstr=$newstr.$string[$i+1].$string[$i+2];$i=$i+2; //For UTF-8 (2008-9-3)
				//$newstr=$newstr.$string[$i+1];$i=$i+1; //For GBK
				$len=$len+1;
			}
			else if ($entag==0)
			{
				$len=$len+1;
			}
			else if ($entag==1 or $entag==2)
			{
				$tmplen=1;
				if ($enchar==$entag)
				{
					$len=$len+1;
					$enchar=0;
					$tmplen=0;
				}
				else if ($entag==2 and $enchar==1)
				{
					$len=$len+1;
					$enchar=$enchar+1;
				}
				else $enchar=$enchar+1;
			}
		}
		if (strlen($string)>strlen($newstr))
		{
			return $newstr.$addStr;
		}

		return $newstr;

	}


	#------------------------------------
	#	NO 3 other
	#------------------------------------

	# PHP 3.1
	function writeFile($file,$wtag,$cont)
	{
		$fp=@fopen($file,$wtag);
		@fwrite($fp,$cont);
		@fclose($fp);
	}

	// PHP 3.2 文件拷贝与删除 (2003.9-2004.1.1)
	function upload_file($from_file="",$to_file="",$del_file=""){
		$res=0;
		if(!empty($from_file) and !empty($to_file) and file_exists($from_file)){
			$res=@move_uploaded_file($from_file,$to_file);
		}
		if(!empty($del_file) and $del_file!=$to_file and file_exists($del_file)){
			@unlink($del_file);
		}
		return $res;
	}


	# PHP 3.3
	function getip($ipstr='')
	{
		if (!empty($ipstr))
		{
			$ips = explode('.', $onlineip);
			return sprintf('%02x%02x%02x%02x', $ips[0], $ips[1], $ips[2], $ips[3]);
		}
		if(getenv('HTTP_CLIENT_IP'))
			$onlineip = getenv('HTTP_CLIENT_IP');
		else if(getenv('HTTP_X_FORWARDED_FOR'))
			$onlineip = getenv('HTTP_X_FORWARDED_FOR');
		else if(getenv('REMOTE_ADDR'))
			$onlineip = getenv('REMOTE_ADDR');
		else
			$onlineip = $_SERVER['REMOTE_ADDR'];

		if ($pos=strrpos($onlineip,','))
		{
			$onlineip=substr($onlineip,$pos+1);
		}

		return $onlineip;
	}


	//$pageurl e.g. 'show.php?kind=1&page='
	function page_list($pagenow,$pagenum,$pageurl='?page=') {

		$pagenow=intval($pagenow);
		$pagenum=intval($pagenum);
		$listsize=4;
		$string='';

		if($pagenum>1)
		{
			//last
			$pageno=$pagenow-1;
			if($pageno<1) $pageno=1;
			$string=$string.($pagenow>1 ? ' <a href="'.(eregi('\*',$pageurl) ? str_replace('*',$pageno,$pageurl) : $pageurl.$pageno).'" title="'.$pageno.'"><font face="Time new roman"><B>上一页</B></font></a>' : '<font face="Time new roman" disabled> <B>上一页</B></font>');
	
			//AUTO
			$j=$pagenow-$listsize>0 ? $pagenow-$listsize : 1;
			$j=$pagenum-$pagenow<$listsize ? $j+$pagenum-$pagenow-$listsize : $j;
			$j=$j<1 ? 1 : $j;
			for($i=$j;$i<$j+$listsize*2+1 and $i<$pagenum+1;$i++){
				$string=$string.($i==$pagenow ? " <B><font  color=\"#cc0000\">$i</font></B> " : ' <a href="'.(strpos($pageurl,'*') ? str_replace('*',$i,$pageurl) : $pageurl.$i).'"><B>'.$i.'</B></a> ');
			}

			//next
			$pageno=$pagenow+1;
			if($pageno>$pagenum) $pageno=$pagenum;
			$string=$string.($pagenow<$pagenum ? ' <a href="'.(strpos($pageurl,'*') ? str_replace('*',$pageno,$pageurl) : $pageurl.$pageno).'" title="'.$pageno.'"><font face="Time new roman"><B>下一页</B></font></a>' : ' <font face="Time new roman" disabled><B>下一页</B></font>');
		}
		return $string;
	}
	
	function page_list_blue($pagenow,$pagenum,$pageurl='?page=') {
	
		$pagenow=intval($pagenow);
		$pagenum=intval($pagenum);
		$listsize=4;
		$string='<ul class="am-pagination">';
	
		if($pagenum>1)
		{
			//last
			$pageno=$pagenow-1;
			if($pageno<1) $pageno=1;
			$string=$string.($pagenow>1 ? ' <li><a href=""'.(eregi('\*',$pageurl) ? str_replace('*',$pageno,$pageurl) : $pageurl.$pageno).'"">上一页</a></li>' : '<li class="am-active"><a href="javascript:;">上一页</a></li>');
	
			//AUTO
			$j=$pagenow-$listsize>0 ? $pagenow-$listsize : 1;
			$j=$pagenum-$pagenow<$listsize ? $j+$pagenum-$pagenow-$listsize : $j;
			$j=$j<1 ? 1 : $j;
			for($i=$j;$i<$j+$listsize*2+1 and $i<$pagenum+1;$i++){
				$string=$string.($i==$pagenow ? "<li class='am-active'><a href='javascript:;'>{$i}</a></li>" : '<li><a href="'.(strpos($pageurl,'*') ? str_replace('*',$i,$pageurl) : $pageurl.$i).'">'.$i.'</a></li>');
			}
	
			//next
			$pageno=$pagenow+1;
			if($pageno>$pagenum) $pageno=$pagenum;
			$string=$string.($pagenow<$pagenum ? '<li><a href="'.(strpos($pageurl,'*') ? str_replace('*',$pageno,$pageurl) : $pageurl.$pageno).'">下一页</a></li>' : '<li class="am-active"><a href="javascript:;">下一页</a></li>');
		}
		$string=$string.'</ul>';
		return $string;
	}
	
	function page_list_simple($pagenow,$pagenum,$pageurl='?page=') {
	
		$pagenow=intval($pagenow);
		$pagenum=intval($pagenum);
		$listsize=4;
		$string='';
	
		if($pagenum>1)
		{
			//last
			$pageno=$pagenow-1;
			if($pageno<1) $pageno=1;
			$string=$string."&nbsp;";
			//$string=$string.($pagenow>1 ? ' <a href="'.(eregi('\*',$pageurl) ? str_replace('*',1,$pageurl) : $pageurl.'1').'" title="1"><font face="Time new roman"><B>[首页]</B></font></a>' : '<font style="color:#999" face="Time new roman" disabled> <B>[首页]</B></font>');
			$string=$string.($pagenow>1 ? ' <a href="'.(eregi('\*',$pageurl) ? str_replace('*',$pageno,$pageurl) : $pageurl.$pageno).'" title="'.$pageno.'"><font face="Time new roman"><B>[上一页]</B></font></a>' : '<font style="color:#999" face="Time new roman" disabled> <B>[上一页]</B></font>');
			$string=$string."&nbsp;&nbsp;";
			//next
			$pageno=$pagenow+1;
			if($pageno>$pagenum) $pageno=$pagenum;
			$string=$string.($pagenow<$pagenum ? ' <a href="'.(strpos($pageurl,'*') ? str_replace('*',$pageno,$pageurl) : $pageurl.$pageno).'" title="'.$pageno.'"><font face="Time new roman"><B>[下一页]</B></font></a>' : ' <font style="color:#999" face="Time new roman" disabled><B>[下一页]</B></font>');
			//$string=$string.($pagenow<$pagenum ? ' <a href="'.(strpos($pageurl,'*') ? str_replace('*',$pagenum,$pageurl) : $pageurl.$pagenum).'" title="'.$pagenum.'"><font face="Time new roman"><B>[尾页]</B></font></a>' : ' <font style="color:#999" face="Time new roman" disabled><B>[尾页]</B></font>');
		}
		return $string;
	}
	function checkDomain(&$str,$en=0)
	{
		$str=strtolower($str);
		if (strlen($str)>80)
			return 0;

		if (preg_match('/^(gov|net|com|org)\.[a-z]{2}$/',$str))
		{
			return 0;
		}

		if (preg_match('/^([0-9a-z]([0-9a-z\-]*[0-9a-z])*\.){1,4}[a-z]{2,4}$/',$str))
			return 1;

		if ($en==0 and preg_match('/^(.+)\.(cn|hk|name|tv|biz|cc|com|net|中国|网络|公司)$/',$str,$ret))
		{
			if (preg_match('/^[\x{4e00}-\x{9fa5}a-z0-9\-]+$/u',$ret[1]))
			{
				return 1;
			}
		}
		else if ($en==1)
		{
			if (preg_match('/^([0-9a-z]([0-9a-z\-]*[0-9a-z])*\.){1,4}xn-[a-z]{2,10}$/',$str))
				return 1;
		}
		return 0;
	}

	function getDBConfig($filename='',$str='')
	{
		$ret=array();
		if (strlen($filename)>0)
		{
			$string=@file_get_contents($filename);
		}
		else
		{
			$string=$str;
		}

		if (strlen($string)>0)
		{
			$temps=explode('<dbconfig>',$string);
			$string=$temps[1];
			if (empty($string))
			{
				return $ret;
			}
			$temps=explode('</dbconfig>',$string);
			$string=$temps[0];
			if (empty($string))
			{
				return $ret;
			}

			preg_match_all("/<([a-z]+)>(.*)<\/\\1>/i", $string, $result);
			foreach ($result[1] as $k => $v)
			{
				$ret[$v]=$result[2][$k];
			}
		}
		return $ret;

	}
	
	function get_ext_icon($ext){
		$extArr =array(
				'7z' =>'7z',
				'ace' =>'ace',
				'as' =>'as',
				'css' =>'css',
				'bmp' =>'bmp',
				'default' =>'default',
				'dir' =>'dir',
				'dir_up' =>'dir_up',
				'doc' =>'doc',
				'docx' =>'docx',
				'exe' =>'exe',
				'flv' =>'flv',
				'gif' =>'gif',
				'gz' =>'gz',
				'html' =>'html',
				'htm' =>'html',
				'ini' =>'ini',
				'iso' =>'iso',
				'jpeg' =>'jpeg',
				'jpg' =>'jpg',
				'js' =>'js',
				'lnk' =>'lnk',
				'mp3' =>'mp3',
				'mp4' =>'mp4',
				'mpeg' =>'mpeg',
				'mpg' =>'mpg',
				'odp' =>'odp',
				'ods' =>'ods',
				'odt' =>'odt',
				'pdf' =>'pdf',
				'php' =>'php',
				'png' =>'png',
				'ppt' =>'ppt',
				'rar' =>'rar',
				'rb' =>'rb',
				'sql' =>'sql',
				'swf' =>'swf',
				'tar' =>'tar',
				'txt' =>'txt',
				'wmv' =>'wmv',
				'xls' =>'xls',
				'xlsx' =>'xlsx',
				'xml' =>'xml',
				'zip' =>'zip'
		);
		if(empty($extArr[$ext])) return 'default';
		return $extArr[$ext];
	}
	//
	function get_current_dir_file($dir,&$fileArr=array()){
		
		if ( !is_dir( $dir ) ) return $fileArr;
		$dir  = substr($dir,strlen($dir)-1,1)=='/' ? $dir : $dir.'/';
		
		$handle = opendir( $dir );
		while ( false !== ( $filename = readdir( $handle ) ) ) {
			if ( $filename == '.'  or $filename == '..' )  continue;
			
			$path = $dir.$filename;
			if ( is_dir( $path ) ) {
				
				$fileArr[] = array(
						"filename"=>$filename,
						"filemtime"=>filemtime($path),
						"filetype" =>"dir"
				);
			} else {
				$ext=strtolower(strrchr($filename,"."));
				$ext = substr($ext, 1,strlen($ext));
				$filetype=get_ext_icon($ext);
				$fileArr[] = array(
						"filename"=>$filename,
						"filemtime"=>filemtime($path),
						"filetype" =>$filetype
				);
			}
		}
	}
	
	function enCodeSign($str)
	{
		$str=base64_encode($str);
		$str=strrev($str);
		$str=base64_encode($str);
		$str=strrev($str);
		$str=bin2hex($str);
		return $str;
	}

	function deCodeSign($str)
	{
		$len=strlen($str);
		$c='';
		for ($i=0; $i<$len; $i=$i+2)
		{
			$t=HexDec($str[$i].$str[$i+1]);
			$c=$c.Chr($t);
		}
		$str=$c;
		$str=strrev($str);
		$str=base64_decode($str);
		$str=strrev($str);
		$str=base64_decode($str);
		return $str;
	}
	
	
	function pid_manager($filename,$maxtime){
	
		if(posix_getuid()>0){
			echo "Failed to root permission. ".posix_getuid();
			exit;
		}
	
		$filename_path="/var/run/{$filename}.pid";
	
		if(file_exists($filename_path)){
				
			$cont=file_get_contents($filename_path);
				
			if(!empty($cont)){
	
				$getArr = json_decode($cont , true);
	
				$getStarttime = $getArr['starttime'];
				$getPid = intval($getArr['pid']);
	
				//查看进程ID是否存在
				if($getPid>0 and $getStarttime>0 and file_exists("/proc/".$getPid)){
					//时间超时KILL
					if($getStarttime+$maxtime+3600>time()){
						exit;
					}
						
					$b=posix_kill($getPid, SIGKILL);
					if(!$b){
						echo "Failed to kill. {$getPid}";
						exit;
					}
						
				}
			}
		}
	
		//得到当前进程ID
		$pid = getmypid();
	
		$arr=array("pid"=>$pid,"starttime"=>time());
	
		$cont=json_encode ( $arr );
	
		$b=file_put_contents($filename_path,$cont);
		if(!$b){
			echo "Failed to write. {$filename_path} {$cont}";
			exit;
		}
	}
	
	function date_str_add($dateEnd,$val=0,$type=2)
	{
	
		$tmp1=explode(" ",$dateEnd);
		$t=explode('-',$tmp1[0]);
		if(!checkdate($t[1],$t[2],$t[0])) return '';
	
		$t0=@strtotime($dateEnd);
		if($t0<800022222) return ''; //格式不对
		$h=explode(':',$tmp1[1]);
	
		if($type==0) $t[0]=0+$t[0]+$val;  //year
		elseif($type==1) $t[1]=0+$t[1]+$val; //month
		elseif($type==2) $t[2]=0+$t[2]+$val; //day
		elseif($type==3) $h[0]=0+$h[0]+$val; //hour
		elseif($type==4) $h[1]=0+$h[1]+$val; //min
		else { return $dateEnd;}
		return date("Y-m-d H:i:s", mktime(intval($h[0]),intval($h[1]),intval($h[2]),$t[1],$t[2],$t[0]));
	}
	function android_logout($message=''){
		$html= '
		<script>
		var message="'.$message.'";
		function logOut(message){
		if(message!=null && message!=""){
			alert(message);
			setTimeout(logOut,1000);
			return;
		}		
		Android.logOut();
		return;
		}
		logOut(message);
		</script>
		';
		exit($html);
	}
	
	
	//记录管理员或会员登陆的客户端和服务端的IP和端口
	function getClientAndServerInfo(){
	
		$retArr = array();
	
		$client_ip='';
		if(!empty($_SERVER['HTTP_REMOTE_HOST'])){
			$client_ip=$_SERVER['HTTP_REMOTE_HOST'];
		}
		else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
			$client_ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		}
	
	
		//通过CDN访问 （需要CDN提供HTTP_REMOTE_PORT和HTTP_SERVER_PORT）
		if(!empty($client_ip) and !preg_match('/^10\./i',$client_ip)){
			$client_port=(isset($_SERVER['HTTP_REMOTE_PORT'])?$_SERVER['HTTP_REMOTE_PORT']:$_SERVER['SERVER_PORT']);
			$server_ip  =$_SERVER['REMOTE_ADDR'];
			$server_port=(isset($_SERVER['HTTP_SERVER_PORT'])?$_SERVER['HTTP_SERVER_PORT']:$_SERVER['SERVER_PORT']);
		}
		//直接访问
		else {
			$client_ip=  $_SERVER['REMOTE_ADDR'];
			$client_port=$_SERVER['REMOTE_PORT'];
			$server_ip  =$_SERVER['SERVER_ADDR'];
			$server_port=$_SERVER['SERVER_PORT'];
		}
	
		$retArr['client_ip'] 		= $client_ip;
		$retArr['client_port'] 	= $client_port;
		$retArr['server_ip'] 		= $server_ip;
		$retArr['server_port'] 	= $server_port;
	
		return $retArr;
	}
	
}//END __PHP__FUN__

?>