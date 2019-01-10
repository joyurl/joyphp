<?php

/*
	简易访问统计系统
	+ 2017-8-04 增加疑似蜘蛛OPR/11.2.3|CPU iPhone OS，以及Crawler
	+ 2017-8-02 增加_spider2和_refer判断虚假访问
	+ 2017-7-24 增加bot.+http兼容多数robot
	+ 2017-7-20 更新spider名称，增加robot|spider强兼容性名称
	+ 2017-7-13 修改来路统计SQL判断BUG；增加来路管理和搜索引擎Robot日志

  */


class _visit
{
	var $debug=0;
	var $siteID=1;
	var $tbl=Array();
	var $info=Array();
	var $_spider="";
	var $_spider2="";
	var $_refer=Array();
	function _visit($siteid=1){

		//蜘蛛
		$this->_spider="(Baiduspider|Googlebot|bingbot|Yahoo.+Slurp|msnbot|Sogou.+spider|360Spider|ia_archiver|Wget|Google Favicon|masscan|sysscan|ResearchBot|WebCrawler|Scrapy|WinHttpRequest|python-requests|Qwantify|robot|spider|Crawler|SCH-N719.+Safari|bot.+http:)";
		//疑似蜘蛛（同时来路为空）Chrome/52.0.2743.82 Safari/537.36
		$this->_spider2='(Chrome\/(39|40|45|46|50|52|53|55|58)\.0.+Safari\/537\.36|Firefox\/[2-9]\..+|Gecko\/20100101 Firefox\/[1-9][0-9]*\..+|Gecko\/20090715 Firefox\/3\.5.+|OPR\/11\.2\.3.+Safari\/537\..+|CPU iPhone OS [0-9]+_.+|MQQBrowser.+TBS.+|Mac OS X.+)$';

		//虚假referer
		$this->_refer[]='http://www.baidu.com/';
		$this->_refer[]='http://image.baidu.com/';
		$this->_refer[]='http://image.baidu.com';
		$this->_refer[]='https://www.baidu.com/';
		$this->_refer[]='https://mail.qq.com/';
		$this->_refer[]='https://www.bing.com/';
		$this->_refer[]='https://cn.bing.com/';
		$this->_refer[]='http://www.google.com/';
		$this->_refer[]='http://www.google.com/search';
		$this->_refer[]='http://www.google.com.tw/';
		$this->_refer[]='http://www.google.com.hk/';
		$this->_refer[]='http://www.google.com.my/';
		$this->_refer[]='https://google.com/';
		$this->_refer[]='https://www.google.ca/';
		$this->_refer[]='https://www.google.com/';
		$this->_refer[]='https://www.google.com.tw/';
		$this->_refer[]='https://www.google.com.my/';
		$this->_refer[]='https://www.google.com.hk/';
		$this->_refer[]='https://www.google.com.au/';
		$this->_refer[]='https://www.google.com.sg/';
		$this->_refer[]='https://www.google.com.jp/';

		$this->siteID=intval($siteid);
		$this->tbl['main']    ='visit_main';
		$this->tbl['date']    ='visit_date';
		$this->tbl['detail']  ='visit_detail';
		$this->tbl['refer']   ='visit_refer';
		$this->tbl['referset']='visit_referset';
		$this->tbl['user']    ='visit_user';
		$this->tbl['spider']  ='visit_spider';

	}

	function tbl($name){
		return $this->tbl[$name];
	}

	function vrecord($DB,$u){

		$tag='___v_cid';
		//非会员采用cookie
		if(empty($u)){
			//COOKIE中有记录则认为是以前的用户
			if(isset($_COOKIE[$tag])){
				$u=$_COOKIE[$tag];
				setcookie($tag, $u,time()+12*3600,'/');
			}
			else{
				//生成随机UUID
				$uuid=date("y").intval(substr(time(),2,8)).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);
				setcookie($tag, $uuid,time()+12*3600,'/');
				$u=$uuid;
			}
		}


		$ym=intval(date("Ym"));
		$fd="visit_ip".date("d");
		$_h=date("_H");

		$v_date=date("Y-m-d");
		$v_fd="visit_ip".date("H");

		$ip=$this->getip();
		//echo "u=$u<br>";
		$User=$DB->query("select * from ".$this->tbl['user']." where visit_user_id=".$DB->getsql($u,1),1);
		if($User){
			if($this->debug) $this->debug_log($DB,"$u is exists");
		}

		//记录
		if(empty($User)){

			//相同的IP认为是同一个用户
			$iprs=$DB->query("select * from ".$this->tbl['user']." where visit_ip=".$DB->getsql($ip),1);
			if(!empty($iprs)){
				if($this->debug) $this->debug_log($DB,"$ip is exists");
				return false;
			}

			//登录之前有cookie，则检查cookie
			if($u<1000000000 and isset($_COOKIE[$tag])){
				usleep(2000);
				$uuid=$_COOKIE[$tag];
				$iprs=$DB->query("select * from ".$this->tbl['user']." where visit_user_id=".$DB->getsql($uuid,1),1);
				if(!empty($iprs)){
					if($this->debug) $this->debug_log($DB,"$u=$uuid exists");
					setcookie($tag, $uuid,time()+12*3600,'/');
					return false;
				}
			}

			if($this->debug) $this->debug_log($DB,"$u $ip isnew");
			$URI=substr($_SERVER['REQUEST_URI'],0,100);
			$tmp=explode('/',$_SERVER['HTTP_REFERER']);
			$REF="".substr($_SERVER['HTTP_REFERER'],0,199);
			$AGT="".substr($_SERVER['HTTP_USER_AGENT'],0,199);
			$info='';
			$this->_spider=str_replace("/","\\/",$this->_spider);

			//$_r=preg_replace("/^(http|https):\/\/[-.a-zA-Z0-9]+\//i","/",$_SERVER['HTTP_REFERER']);
			//虚假访问或搜索引擎
			//or $_SERVER['REQUEST_URI']==$_r
			if(empty($_SERVER['HTTP_USER_AGENT'])
				or strtolower($tmp[2])==strtolower($_SERVER['SERVER_NAME'])
				or strlen($AGT)<52
				or preg_match('/www\.baidu\.com\/s\?/i',$_SERVER['HTTP_REFERER'])
				or !empty($_SERVER['HTTP_REFERER'])  and  !preg_match('/^(http:|https:)/i',$_SERVER['HTTP_REFERER'])
				or $_SERVER['REQUEST_URI']==$_SERVER['HTTP_REFERER']
				or empty($REF) and preg_match("/".$this->_spider2."/i",$_SERVER['HTTP_USER_AGENT'])
				or in_array($REF,$this->_refer)
				or preg_match("/".$this->_spider."/i",$_SERVER['HTTP_USER_AGENT'],$dets)){
					//$DB->query("insert into ".$this->tbl['main']." (visit_clear,visit_name,visit_intro) values (CURDATE(),".$DB->getsql($ip).",".$DB->getsql("[".date("H:i:s")."] ".$AGT).")");
					$DB->query("insert into ".$this->tbl['spider']." (visit_ip,visit_datetime,visit_page,visit_refer,visit_agent) value (".$DB->getsql($ip).",".$DB->getsql(date('Y-m-d H:i:s')."").",".$DB->getsql($URI).",".$DB->getsql($REF).",".$DB->getsql($AGT).")");
					if($this->debug) $this->debug_log($DB,"is spider");
					return false;
			}

			//记录来源
			if(!empty($_SERVER['HTTP_REFERER'])){
				$dm=strtolower($tmp[2]);
				if(!empty($dm)){
					$DS=$DB->query("select * from ".$this->tbl['referset']." where (refer_name=".$DB->getsql($dm)." or LENGTH(refer_names)>0 and ".$DB->getsql($dm)." RLIKE refer_names ) and refer_stat=0 order by refer_order desc,refer_id limit 1",1);
					if($DS){
						$refid=intval($DS['refer_id']);
						$res=$DB->query("select * from ".$this->tbl['refer']." where refer_id=".$refid." and refer_date=".$DB->getsql($v_date),1);
						if($res){
							$DB->query("update ".$this->tbl['refer']."  set {$v_fd}={$v_fd}+1,visit_ips=visit_ips+1 where refer_id=".$refid." and refer_date=".$DB->getsql($v_date));
						}
						else {
							$DB->query("insert into ".$this->tbl['refer']." (refer_id,refer_date,{$v_fd},visit_ips) values (".$refid.",".$DB->getsql($v_date).",1,1)");
						}
					}
				}
			}

			$DB->query("insert into ".$this->tbl['user']." (visit_user_id,visit_ip,visit_date) value (".$DB->getsql($u,1).",".$DB->getsql($ip).",".$DB->getsql(date('Y-m-d H:i:s')).") ");
			usleep(2000);
			$DB->query("insert into ".$this->tbl['detail']." (visit_user_id,visit_ip,visit_datetime,visit_page,visit_refer,visit_agent) value (".$DB->getsql($u,1).",".$DB->getsql($ip).",".$DB->getsql(date('Y-m-d H:i:s')."").",".$DB->getsql($URI).",".$DB->getsql($REF).",".$DB->getsql($AGT).")");
			usleep(2000);
			$res=$DB->query("select * from ".$this->tbl['date']." where visit_date=".$DB->getsql($v_date),1);
			if(!empty($res)){
				$DB->query("update ".$this->tbl['date']."  set visit_update=NOW(),{$v_fd}={$v_fd}+1,visit_ips=visit_ips+1 where visit_date=".$DB->getsql($v_date));
			}
			else{
				$DB->query("insert into ".$this->tbl['date']."  (visit_date,visit_startdate,visit_update,{$v_fd},visit_ips) values (".$DB->getsql($v_date).",NOW(),NOW(),1,1)");

			}

		}
		//清除过期数据
		else if($_h<"_08" and $_h>="_01"){
			$RS=$DB->query("select * from ".$this->tbl['main']."  where visit_id=".intval($this->siteID),1);
			if(empty($RS) or $RS['visit_clear']!=date('Y-m-d')){
				usleep(10000);
				$DB->query("delete from ".$this->tbl['user']."  where visit_date<".$DB->getsql(date("Y-m-d 00:00:00")));
				usleep(20000);
				$DB->query("Optimize table  ".$this->tbl['user']."");
				usleep(20000);
				$deltime=date("Y-m-d 00:00:00",time()-7*24*3600);
				$DB->query("delete from ".$this->tbl['detail']."  where visit_datetime<".$DB->getsql($deltime)." limit 10000");
				usleep(20000);
				$DB->query("Optimize table  ".$this->tbl['detail']."");
				usleep(20000);
				$DB->query("delete from ".$this->tbl['spider']."  where visit_datetime<".$DB->getsql($deltime)." limit 10000");
				usleep(20000);
				$DB->query("Optimize table  ".$this->tbl['spider']."");
				usleep(20000);


				$deltime=date("Y-m-d",time()-10*24*3600);
				$DB->query("delete from ".$this->tbl['main']."  where visit_id>1000 and visit_clear<".$DB->getsql($deltime));
				usleep(20000);
				$DB->query("Optimize table  ".$this->tbl['main']."");
				usleep(10000);
				if(empty($RS)){
					$DB->query("insert into ".$this->tbl['main']." (visit_id,visit_clear) values (".intval($this->siteID).",".$DB->getsql(date('Y-m-d')).") ");
				}
				else{
					$DB->query("update ".$this->tbl['main']." set visit_clear=".$DB->getsql(date('Y-m-d'))." where visit_id=".intval($this->siteID));
				}
			}
		}
		
	}


	function getip()
	{
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

	function debug_log($DB,$remark){
		//$DB->query("insert into ".$this->tbl['main']." (visit_clear,visit_name,visit_intro) value (CURDATE(),'debug',".$DB->getsql("[".date("H:i:s")."]".$remark).")");
	}

	function get_vhour($DB){
		$t=time();
		$_date0=date('Y-m-d');
		$_date1=date('Y-m-d',$t-24*3600);
		$_date2=date('Y-m-d',$t-48*3600);
		$_date=date('Y-m-d 00:00:00',$t-48*3600);
		
		$result=$DB->query("select left(visit_datetime,13) as v_date,count(*) as v_num  from ".$this->tbl['detail']." where visit_datetime>=".$DB->getsql($_date)." group by left(visit_datetime,13) order by v_date desc",2);
		$ret=Array();
		$ret['data']=Array();
		$ret['date0']=$_date0;
		$ret['date1']=$_date1;
		$ret['date2']=$_date2;
		$ret['max']=0;
		foreach($result as $arr){
			if($arr['v_num']>$ret['max']) $ret['max']=$arr['v_num'];
			$ret['data'][$arr['v_date']]=$arr['v_num'];
		}
		return $ret;
	}

	function get_vdate($DB,$data,&$page,$sql=''){
		$_date=trim($data['date']);
		if(empty($_date)){
			$_date=date('Y');
		}

		$data['table']=preg_replace("/[^_a-z0-9]/i","",$data['table']);
		$data['key_date']=preg_replace("/[^_a-z0-9]/i","",$data['key_date']);
		$data['key_start']=preg_replace("/[^_a-z0-9]/i","",$data['key_start']);
		$data['key_end']=preg_replace("/[^_a-z0-9]/i","",$data['key_end']);
		$data['key_visit']=preg_replace("/[^_a-z0-9]/i","",$data['key_visit']);

		$ret=Array();
		$ret['date']=$_date;
		//显示月数据
		if(preg_match('/^2[0-9]{3}$/',$_date)){

			//所有
			$res=$DB->query("select min(".$data['key_start'].") as mindate,max(".$data['key_end'].") as maxdate,sum(".$data['key_visit'].") as visits from ".$data['table']." where 1 ".$sql,1);
			//print_r($res);
			$ret['prev']=$_date;
			$ret['next']=$_date;
			if(substr($res['maxdate'],0,4)>$_date) $ret['next']=0+$_date+1; //下一年
			if(substr($res['mindate'],0,4)<$_date) $ret['prev']=0+$_date-1; //上一年

			//当前年
			$res=$DB->query("select min(".$data['key_start'].") as mindate,max(".$data['key_end'].") as maxdate,sum(".$data['key_visit'].") as visits from ".$data['table']." where left(".$data['key_date'].",4)=".$DB->getsql($_date)."".$sql,1);
			if($res['maxdate']==date('Y-m-d')) $res['maxdate']=date('Y-m-d H:i:s');
			else if(strlen($res['maxdate'])==10) $res['maxdate']=$res['maxdate']." 23:59:59";
			$_daynum=round((strtotime($res['maxdate'])-strtotime($res['mindate']))/24/3600,3);
			if($_daynum<1) $_daynum=1;
			//$_dayarg=round($res['visits']/$_daynum,1);
			$ret['startdate']=$res['mindate'];
			$ret['allnum']=$res['visits'];
			$ret['daynum']=round($_daynum,1);
			$result=$DB->query("select left(".$data['key_date'].",7) as v_date,sum(".$data['key_visit'].") as v_num  from ".$data['table']." where left(".$data['key_date'].",4)=".$DB->getsql($_date).$sql." group by left(".$data['key_date'].",7) order by v_date desc",2);
			$month=count($result);
			if($month<1) $month=1;
			$ret['dayarg']=round($res['visits']/$month);
		}
		//显示每天的数据
		else if(preg_match('/^2[0-9]{3}-[0-9]{2}$/',$_date)){
			$res=$DB->query("select min(".$data['key_start'].") as mindate,max(".$data['key_end'].") as maxdate,sum(".$data['key_visit'].") as visits from ".$data['table']." where left(".$data['key_date'].",7)=".$DB->getsql($_date)."".$sql,1);
			if($res['maxdate']==date('Y-m-d')) $res['maxdate']=date('Y-m-d H:i:s');
			else if(strlen($res['maxdate'])==10) $res['maxdate']=$res['maxdate']." 23:59:59";
			$_daynum=round((strtotime($res['maxdate'])-strtotime($res['mindate']))/24/3600,3);
			if($_daynum<1) $_daynum=1;
			$_dayarg=round($res['visits']/$_daynum);
			$ret['startdate']=$res['mindate'];
			$ret['allnum']=$res['visits'];
			$ret['dayarg']=$_dayarg;
			$ret['daynum']=round($_daynum,1);

			$result=$DB->query("select left(".$data['key_date'].",10) as v_date,sum(".$data['key_visit'].") as v_num  from ".$data['table']." where left(".$data['key_date'].",7)=".$DB->getsql($_date).$sql." group by left(".$data['key_date'].",10) order by v_date desc",2);
			
		}

		$tmp=Array();
		$max=10;
		foreach($result as $arr){
			if($arr['v_num']>$max) $max=$arr['v_num'];
		}
		foreach($result as $arr){
			$_day='-';
			if(strlen($arr['v_date'])==10){
				$_day=date("w",strtotime($arr['v_date']));
			}
			$tmp[]=Array("v_date"=>$arr['v_date'],'v_day'=>$_day,'v_num'=>$arr['v_num'],'v_percent'=>intval(100*$arr['v_num']/$max));
		}
		$ret['data']=$tmp;
		return $ret;
	}

	function get_refer($DB,&$page,$_key=''){
		$DB->pageSize=30;
		$where ='';
		if(!empty($_key)){
			$where=$where." where (refer_name like ".$DB->getsql('%'.$_key.'%')." or REPLACE(refer_names,'\\','') like ".$DB->getsql('%'.$_key.'%').") ";
		}
		$_date0=date('Y-m-d');
		$_date1=date('Y-m-d',time()-24*3600);
		$results=$DB->query("select refer_id,refer_date,visit_ips from ".$this->tbl['refer']." where refer_date=".$DB->getsql($_date0)." or refer_date=".$DB->getsql($_date1)."",2);
		$visitData=Array();
		foreach($results as $arr){
			$_id=$arr['refer_id'];
			$visitData[$_id][$arr['refer_date']]=intval($arr['visit_ips']);
		}
		$result=$DB->query_page($page,"*",$this->tbl['referset'],$where,' order by refer_order desc,refer_id ','');
		$this->info['pagenum']=$DB->pageinfo['pagenum'];
		$this->info['rowsnum']=$DB->pageinfo['rowsnum'];
		$ret=Array();
		foreach($result as $arr){
			$_id=$arr['refer_id'];
			$arr['_visit0']=isset($visitData[$_id][$_date0]) ? intval($visitData[$_id][$_date0]):0;
			$arr['_visit1']=isset($visitData[$_id][$_date1]) ? intval($visitData[$_id][$_date1]):0;
			$ret[]=$arr;
		}
		return $ret;
	}

	function get_detail($DB,&$page,$_key=''){
		$DB->pageSize=30;
		$where ='';
		if(!empty($_key)){
			$where=$where." where (visit_refer like ".$DB->getsql('%'.$_key.'%')." or visit_agent like ".$DB->getsql('%'.$_key.'%').") ";
		}
		$result=$DB->query_page($page,"*",$this->tbl['detail'],$where,' order by visit_id desc','');
		$this->info['pagenum']=$DB->pageinfo['pagenum'];
		$this->info['rowsnum']=$DB->pageinfo['rowsnum'];
		return $result;
	}

	function get_spider($DB,&$page,$_key=''){
		$DB->pageSize=30;
		$where ='';
		if(!empty($_key)){
			$where=$where." where (visit_agent like ".$DB->getsql('%'.$_key.'%').") ";
		}
		$result=$DB->query_page($page,"*",$this->tbl['spider'],$where,' order by visit_id desc','');
		$this->info['pagenum']=$DB->pageinfo['pagenum'];
		$this->info['rowsnum']=$DB->pageinfo['rowsnum'];
		return $result;
	}

	
}


?>