<?PHP
/*
	本地订单流程及数据处理对象
	=========================
	用于处理订单与数据库之间的交互

*/


class OrderContrl
{
	var $DB;
	var $Tbl;
	var $RS;
	var $API;
	var $orderID=0;
	var $userID=0;
	var $clientID=0;
	var $isAdmin=0;
	var $isBatch=0;
	var $ipOpen=5;

	var $logkeep=0;
	var $sitetag='';
	var $sentcmd=1;  //是否需要发命令
	var $queryType=0;
	var $queryRun=0;
	var $queryPrice=0;
	var $isAPI=0;
	var $typeArr =Array();
	var $netArr  =Array();
	var $priceSet=Array();
	var $periodSet=Array();
	var $diskArr=Array();


	var $vmArr   = Array();//批量开设时使用
	var $vmsArr   = Array();//批量开设时使用
	var $serverArr=Array();
	var $osArr =Array();
	var $osSet =Array();
	var $msg='';  //执行时的错误信息，如果无错误，则此值为空

	var $snVersion=0;
	var $setConf=Array();
	var $_osid;

	//重装和重启间隔时间设置(用户即时操作间隔3分钟，系统操作间隔6分钟(保留的服务器为4分钟)，以让用户操作优先)
	function OrderContrl(&$DB,$order_id=0,$userid=0)
	{
		global $Tbl,$__now_is_test__;
		$this->DB=$DB;
		$this->Tbl=$Tbl;
		$this->orderID=$order_id;
		$this->userID =intval($userid); //若是管理员，则传负值
		if($userid<0) $this->isAdmin=1; //
		$this->snVersion=0+intval(_VIP_VERSION_TAG);
		if(!empty($__now_is_test__)) $this->sentcmd=0;//不用发命令
		if($this->snVersion<2) $this->snVersion=21;
		
		//基本配置
		$resultConf=$DB->query("select conf_name,conf_value from ".$Tbl['set_config']." where 1");
		$this->setConf=$DB->fetch_subarr($resultConf,'conf_name','conf_value');
		$this->setConf['prod_preload'] = intval($this->setConf['prod_preload']);
		
	}

	//查询价格设置
	function query_price(){

		if(count($this->netArr)<1){

			$sql=" net_stat=0 ";
			$stat=0;
			if($this->userID>0) {
				$sql=" net_stat<2 ";
				$stat=2;
			}
			$result=$this->DB->query("select * from ".$this->Tbl['set_network']." where $sql  order by net_order asc,net_id asc",2);
			foreach ($result as $arr)
			{
				$netid=intval($arr['net_id']);
				$this->netArr[$netid]=$arr;
			}

			$this->osArr=Array();
			$result=$this->DB->query("select * from ".$this->Tbl['set_ostype']." where os_stat<2 and os_filetype=0 and os_father_id=0 order by os_ordernum desc, os_id",2);
			foreach ($result as $arr)
			{
				$osid=intval($arr['os_id']);
				$this->osArr[$osid]=$arr;
			}

		}

		$results=$this->DB->query("select * from ".$this->Tbl['prod_price']." where 1",2);
		foreach($results as $arr){
			$this->priceSet[$arr['type_id']][$arr['price_type']]=$arr;
		}

		$this->periodSet=$this->DB->query("select * from ".$this->Tbl['prod_period']." where period_stat=0 and (startdate='0000-00-00 00:00:00' OR startdate<=NOW() and enddate>=NOW()) order by period_unit desc,period,period_rate",2);

		$this->queryPrice=1;
	}

	//显示资源（用于DIY购买或DIY升级）
	//$isAdmin : 0 用户; >0 管理员; $this->isBatch=1; 批量开设
	function query_resource($isAdmin=0,$isNew=0,$queryType=0,$prodset=Array())
	{
		
		//网络
		$this->queryRun=0;
		global $__soft_free__,$__prod_ismod;
		$this->isAdmin=$isAdmin;
		$this->netArr=Array();
		$queryType=intval($queryType);
		if($queryType==1) $queryType=0;
		$this->queryType=$queryType;
		$sql=" net_stat=0 ";
		if($isAdmin==1) $sql=" net_stat<2 ";
		$result=$this->DB->query("select * from ".$this->Tbl['set_network']." where $sql  order by net_order asc,net_id asc",2);
		foreach ($result as $arr)
		{
			$netid=intval($arr['net_id']);
			$this->netArr[$netid]=$arr;
		}

		//操作系统 (最小系统盘及内存限制)
		$stat=2;
		$osstat=0;
		if($isAdmin==1) {
			$stat=3;
			$osstat=1;
		}
		else if($isNew==1) $stat=1;

		$this->osArr=Array();
		
		$sql="select * from ".$this->Tbl['set_ostype']." where os_stat<".$stat;
		if($__prod_ismod){//测试关闭，正式开启
			//$sql.=" and os_filemod=1";
		}else{
			//$sql.=" and os_filemod=0";
		}
		$sql.=" and os_filetype=0 and os_father_id=0 order by os_filemod,os_ordernum desc, os_id";
		
		$result=$this->DB->query($sql,2);
		foreach ($result as $arr)
		{
			$osid=intval($arr['os_id']);
			if($queryType>2){
				$tag="/,$queryType,/i";
				$type_str=",{$arr['type_id']},";
				if(strlen($arr['type_id'])>0 and !preg_match($tag,$type_str)) continue;//如果限制了分类，不符合则跳过
			}
			$this->osArr[$osid]=$arr;
		}

		$osSet=Array();
		$result=$this->DB->query("select * from ".$this->Tbl['set_server_os']." where os_isclose<=$osstat ",2);
		foreach ($result as $arr)
		{
			$osid=intval($arr['os_id']);
			if(isset($this->osArr[$osid])){
				$osSet[]=$arr;
			}
		}
		$this->osSet=$osSet;

		//查询可用资源 (收费300个,免费100个，未认证试用3个)
		$limit0=3;
		$limit1=intval(_FREE_PROD_LIMIT);
		$limit2=intval(_VIP_PROD_LIMIT);
		$sql="select * from ".$this->Tbl['set_server']." where length(server_key)>5 ";
		if(isset($prodset['vm_memory']) and isset($prodset['vm_cpulimit']) and isset($prodset['vm_disk'])){
			$sql=$sql." and server_memory_max>=".intval($prodset['vm_memory'])." and server_cpu_max >=".intval($prodset['vm_cpulimit'])." and server_disk_max>".intval($prodset['vm_disk'])." ";
		}
		else{
			$sql=$sql." and server_memory_max>=1024 and server_cpu_max >=1000 and server_disk_max>10 ";//有资源
		}

		$addNum=0;
		//状态
		if($isAdmin>0){
			$sql=$sql." and server_stat<2 ";
			$addNum=5;
		}
		else {
			$sql=$sql." and server_stat<1 "; 
			if($this->isBatch>0) $addNum=2;//批量时
			//server_install_stat=1为排队安装中，查询时受限，开设不受限
			if($this->isBatch<2) $sql=$sql." and server_install_stat<1";
		}

		//运行总数限制
		$sql=$sql." and (server_edition >=".intval($this->snVersion)." and server_vmnum < ".$limit2." or server_vmnum<".$limit1." and server_edition >=10 or server_vmnum < 3) and (server_vmmax=0 or server_vmmax>1 and server_vmnum < server_vmmax) "; //=1的不接受新开，包括管理员

		//分类及自定义个数限制=1时不允许新开
		if($queryType>1){
			$sql=$sql." and (server_prod_type=$queryType and (server_vmuse=0 or server_vmrun < server_vmuse+$addNum and server_vmuse >1) or server_sub_type=$queryType and (server_vmuses=0 or server_vmruns < server_vmuses+$addNum and server_vmuses >1) and (server_vmuse=0 or server_vmrun < server_vmuse+$addNum and server_vmuse>1))";
		}
		else{
			$sql=$sql." and server_prod_type!=1 and server_vmuse!=1 "; //不查询关闭的
		}

		$results=$this->DB->query($sql,2);
		$this->msg=$sql.';'.mysql_error();

		$t1=0+time()-10*60; //安装时间10分钟以前
		$t2=0+time()-30*60; //安装时间30分钟以前
		$t3=0+time()-3*60;   //安装时间3分钟以前(基本能完成安装) 

		/* 优先级说明：
		 * 默认为1(暂停的或开到90%的或3分钟内有安装的)，
		 * 3分钟未安装过的+1/10分钟内未安装的+1/10分钟内未安装订单数小于预定60%的+1/10分钟内未安装小于预定30%的+1;
		 * 20分钟内未安装(且不是1)的+1; 设置优先的+1(所有状态)
		 */

		//一键体验单独取数(同时运行的不允许超过25个)
		$vpsArr=Array();
		if($queryType==2){
			$ress=$this->DB->query("select server_id,count(*) as vpsnum from ".$Tbl['vm_try_order']." where  vm_stat=0  group by server_id",2);
			foreach($ress as $arr){
				$vid=$arr['server_id'];
				$vpsArr[$vid]=0+$arr['vpsnum'];
			}
		}


		$typeSet=Array();
		$fkey='server_id';//第1字段
		$result=Array();
		$k=0;//2015-10-28新增
		foreach ($results as  $arr)
		{
			//用第1字段来排序，然后恢复
			$arr['__tmp']=$arr[$fkey];
			$sid=$arr['server_id'];
			if($queryType==2 and isset($vpsArr[$sid]) and $vpsArr[$sid]>25) continue;

			$v=1;


			$rand1=-1;
			$rand2=-1;
			if($arr['server_vmuse']>0) $rand1=round($arr['server_vmrun']/$arr['server_vmuse'],2);
			if($arr['server_vmmax']>0) $rand2=round($arr['server_vmnum']/$arr['server_vmmax'],2);
			if($rand2>$rand1) $rand1=$rand2;//两个设置取最大值(2015-9-23之前为最小值)

			if($rand1==-1) $rand1=round($arr['server_vmrun']/64,2);

			//2016-11-1 新增server_order_time<$t1
			if($arr['sarver_stat']>0 or $rand1>=0.92) $v=1;//暂停的或满了的靠后排例(2015-9-23)
			else if($arr['server_install_time']>$t3 or $arr['server_order_time']>$t3) {//3分钟内安装过的
				$v=1;
			}
			else{
				$v=$v+1;
			}

			//10分钟内未安装过的
			if($rand1<0.86 and $v>1 and $arr['server_install_time']<$t1 and $arr['server_order_time']<$t1){
				$v=$v+1;
				if($rand1<0.6) $v=$v+1;
				if($rand1<0.3) $v=$v+1;
			}

			//20分钟无安装的+1(2016-11-3)
			if($rand1<0.86 and $v>1 and $arr['server_install_time']<$t2 and $arr['server_order_time']<$t2){
				$v=$v+1;
			}

			//设置优先的+1(有一定空闲的才加，这样单个用户可尽量开空闲的，快满了的留给批量用户)
			if($rand1<0.86 and $arr['server_order']==1) {
				$v=$v+2;
			}

			//设置值较空闲，且资源较多随机值优先
			$randval=rand(0,4);
			if($v>2 and $arr['server_order_res']>0) 
			{
				$randval=rand(5,9);
			}

			$orderval=$v*100 + $randval*10 + rand(1,9);//尾加随机，可让同等条件的乱序显示
			if($queryType==2) $orderval=rand(11,99); //以最后安装时间倒序
			$arr[$fkey]=$orderval;


			$store_os=$arr['server_os_store'];
			if(empty($store_os)) $store_os=$sid;
			$arr['min_memory']=0;
			$arr['min_disksys']=0;

			//检查最小值
			foreach($osSet as $row){
				$osid=intval($row['os_id']);
				if($store_os!=$row['server_id']) continue;
				if (!isset($this->osArr[$osid])) continue;

				$rows=$this->osArr[$osid];
				if ($arr['min_memory']==0) {
					$arr['min_memory'] =$rows['os_memory'];
					$arr['min_disksys'] =$rows['os_disk_sys'];
				}
				else if ($rows['os_memory']<$arr['min_memory'] OR $rows['os_memory']==$arr['min_memory'] and $rows['os_disk_sys']<$arr['min_disksys']){
					$arr['min_memory'] =$rows['os_memory'];
					$arr['min_disksys'] =$rows['os_disk_sys'];
				}
			}

			if ($arr['server_memory_max']<$arr['min_memory'])
			{
				$arr['server_memory_max']=0;
			}
			if ($arr['server_disk_max']<$arr['min_disksys']+5)
			{
				$arr['server_disk_max']=0;
			}

			if ($arr['server_disk_max']>0 and $arr['server_memory_max']>0 and $arr['server_cpu_max']>0)
			{
				$type=intval($arr['server_prod_type']);
				$typeSet[$type]=1;
			}

			$arr['maxnum']=0;
			if($arr['server_edition']>=$this->snVersion) {
				$arr['maxnum']=$limit2-$arr['server_vmnum'];
				$arr['topnum']=$limit2;
			}
			else if($arr['server_edition']>=10){
				$arr['maxnum']=$limit1-$arr['server_vmnum'];
				$arr['topnum']=$limit1;
			}
			else {
				$arr['maxnum']=3-$arr['server_vmnum'];
				$arr['topnum']=$limit0;
			}
			if($arr['maxnum']<1) $arr['maxnum']=0;

			//最大可开数加入运行数限制（2013-10-12）
			if($arr['server_vmuse']>0 and $arr['maxnum']>0){
				if ($isAdmin>0){
					$tmp=$arr['server_vmuse']+5-$arr['server_vmrun'];//管理员只允许超5个
				}
				else{
					$tmp=$arr['server_vmuse']-$arr['server_vmrun'];
				}
				//2013-10-28 从if()外移到里面
				if($tmp<0) $tmp=0;
				if($arr['maxnum']>$tmp){
					$arr['maxnum']=$tmp;
				}
			}
			$arr['server_cpu_cores']=floor($arr['server_cpu_cores']*2/3);
			if($arr['server_cpu_cores']<2) $arr['server_cpu_cores']=2;

			if($arr['server_sub_type']==$queryType and $arr['server_prod_type']!=$queryType and $arr['server_vmuses']>0){
				if ($isAdmin>0){
					$tmp=$arr['server_vmuses']+5-$arr['server_vmruns'];//管理员只允许超5个
				}
				else{
					$tmp=$arr['server_vmuses']-$arr['server_vmruns'];
				}
				if($tmp<0) $tmp=0;
				if($arr['maxnum']>$tmp){
					$arr['maxnum']=$tmp;
				}
			}

			//剩余资源可开数推算（未考虑硬盘）
			if($arr['server_cpu_arg']<2000) $arr['server_cpu_arg']=2000;
			if($arr['server_memory_arg']<2000) $arr['server_memory_arg']=2000;
			$tmp=floor($arr['server_cpu_max']/$arr['server_cpu_arg']);
			if($tmp<0) $tmp=0;
			if($arr['maxnum']>$tmp){
				$arr['maxnum']=$tmp;
			}
			$tmp=floor($arr['server_memory_max']/$arr['server_memory_arg']);
			if($tmp<0) $tmp=0;
			if($arr['maxnum']>$tmp){
				$arr['maxnum']=$tmp;
			}
			$arr['ipnum']=0;
			$arr['orderval']=$orderval;
			if($arr['server_install_time']<$arr['server_order_time']){
				$arr['server_install_time']=$arr['server_order_time'];
			}
			if(empty($__soft_free__)) $arr['server_type']=0;
			$result[$k]=$arr;
			$k++;
		}
		@rsort($result);

		$this->serverArr=Array();
		$serverNum=count($result);
		//echo '<pre>';print_r($osSet);exit;
		$n=0;
		//echo '<pre>';print_r($this->osArr);exit;
		foreach ($result as $arr)
		{
			$arr[$fkey]=$arr['__tmp']; //还原第1字段
			$sid=intval($arr['server_id']);
			$store_os=$arr['server_os_store'];
			if(empty($store_os)) $store_os=$sid;
			$n++;


			/*
			
			*/
			
			$arr['server_templates']=',';
			foreach($osSet as $row){
				$_osid=intval($row['os_id']);
				$rows=$this->osArr[$_osid];
				if($store_os==$row['server_id'] and (strlen($arr['server_api_version'])>0 and $rows['os_filemod']==1 or empty($arr['server_api_version']) and $rows['os_filemod']==0)){
					$arr['server_templates']=$arr['server_templates'].','.intval($row['os_id']);
				}
			}
			
			if(strlen($arr['server_templates'])>1) $arr['server_templates']=$arr['server_templates'].',';
			$this->serverArr[$sid]=$arr;

			usleep(500);

		}
		//echo '<pre>';print_r($this->serverArr);exit;


		//查询分类
		$this->typeArr=Array();
		$sql="select * from ".$this->Tbl['set_prod_type']." where type_sort=0 ";
		if($this->isAdmin>0) $sql=$sql." and type_stat<4";
		else if($this->isAPI)  $sql=$sql." and type_stat<3 and type_stat!=1";
		else $sql=$sql." and type_stat<2";

		$results=$this->DB->query($sql." order by type_order asc,type_id asc",2);

		foreach ($results as $_k => $arr)
		{
			$type=intval($arr['type_id']);

			$arr['isopen']=(isset($typeSet[$type]) ? 1 : 0);//是否有可用服务器

			if(empty($__soft_free__)) $arr['type_free_open']=0;//不支持免费的类型
			$this->typeArr[$type]=$arr;

		}

		//推荐配置及价格
		
	}


	//F1.1 查询可用推荐配置及价格，用于显示推荐配置列表(购买第一步)及推荐配置管理
	//$queryType为0是显示所有分类的推荐配置
	//如果$isPrice=1，是否需要自己查价格
	//$isAdmin值 0 用户; -1 用户批量; -2 查询; 1-8 管理员开设; 9 推荐配置管理（显示所有的）
	//需要返回的有：推荐配置设置、配置对应可用时长及最小价格(只返回30天及30天以下最小的一个时长，不显示所有价格)
	//返回格式$ret=Array("prodid"=>Array('...(prod_conf表的字段)'=>'表中设置值','_price'=>'价格','_bandwidth'=>'带宽其它值','_usable'=>'0/1是否可用','_error'=>'不可用提示'));
	function query_all_conf($isAdmin=0,$isPrice=0,$queryType=0,$queryFree=0){
		$_osid  =intval($this->_osid);
		
		$isAdmin=intval($isAdmin);
		
		if(empty($this->queryRun)){
			$this->query_resource($isAdmin,1,$queryType);
		}
		$addNum=0;
		if($isAdmin>0 and $isAdmin<9){
			$addNum=5;
		}
		else if($isAdmin==-1){
			$addNum=2;//批量创建时
		}
		else if($isAdmin==-2){
			$addNum=-2;//查询
		}
		
		$typeSet=Array();
		$result=$this->DB->query("select * from ".$this->Tbl['set_prod_type']." where type_stat<1",2);
		foreach($result as $arr){
			$tid=$arr['type_id'];
			$typeSet[$tid]=$arr;
		}

		//价格设置
		//period_unit值 0=天，1=小时；2=分钟
		$result=$this->DB->query("select * from ".$this->Tbl['prod_conf_price']." where 1 order by period_unit desc,period,price",2);
		$priceArr=Array();
		foreach($result as $arr){
			$pid=$arr['prod_id'];
			$priceArr[$pid][]=$arr;
		}
		//特殊带宽设置(用户显示)
		$result=$this->DB->query("select * from ".$this->Tbl['prod_conf_set']."",2);
		$setArr=Array();
		foreach($result as $arr){
			$pid=$arr['prod_id'];
			$setArr[$pid]=(isset($setArr[$pid])?$setArr[$pid].'<br />':'')."{$arr['vm_bandwidth_out']}Mbps/{$arr['vm_bandwidth_in']}Mbps/{$arr['vm_domain_limit']}个";
		}
		//IP地址可用数量池(开多个时需要共享)
		$result=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where ip_stat=0",2);
		$ipArr=Array();//IP列表
		foreach($result as $arr){
			$ipArr[]=$arr['ip_addr'];//IP,是否分配
		}
		unset($result);

		$interArr=Array();
		$result=$this->DB->query("select record_server_id,count(*) as num_max from ".$this->Tbl['set_internet_server']." where record_stat=0 and record_vm_id=0 group by record_server_id",2);
		
		foreach($result as $arr){
			$_num=$arr['num_max'];
			if($_num<0) $_num=0;
			$interArr[$arr['record_server_id']]=$_num;
		}
		unset($result);

		$ret=Array();
		$sql="select * from ".$this->Tbl['prod_conf']." where  1 ";
		//$isAdmin>=9 用于推荐配置管理，查询所有配置
		if($isAdmin>0 and $isAdmin<9) {
			$sql=$sql." and prod_stat<2"; //用户续费或管理员购买续费
			if($this->isAPI) $sql=$sql." and prod_api>0";
		}
		else if($isAdmin<1) {
			$sql=$sql." and prod_stat<1";//用户新购买
			if($this->isAPI) $sql=$sql." and prod_api>0";
			else  $sql=$sql." and prod_api<2";
		}

		if(!empty($queryType)) {
			$sql=$sql." and type_id=$queryType"; //指定分类
			if(!isset($this->typeArr[$queryType])){//关闭的分类
				return Array();
			}
		}

		if($queryFree>0){
			$sql=$sql." and prod_free_open>0";
		}
		$sql=$sql." order by type_id,vm_memory,vm_cpulimit,vm_disk_data,vm_bandwidth_in,vm_bandwidth_out,vm_iptype,prod_name";
		$result=$this->DB->query($sql,2);
		//print_r($this->serverArr);
		foreach($result as $arr){
			$_typeid=$arr['type_id'];
			if(!isset($this->typeArr[$_typeid])) continue;//不存在的分类跳过
			$_price='';
			$_pricem='';
			$_usable=0;
			$_error='';
			$_bandwidth='';
			$pid=$arr['prod_id'];
			if(isset($setArr[$pid])) $_bandwidth=$setArr[$pid];
			$_month=0;
			//先检查推荐配置价格
			if(isset($priceArr[$pid])){
				$tmpArr = array ();
				foreach($priceArr[$pid] as $row){
					//如果线路不存在则跳过
					$nettype=$row['net_type'];
					if(!isset($typeSet[$nettype])){
						continue;
					}
					//得到每个线路的最小价格
					if (! empty ( $tmpArr [$nettype] )) {
						if ($row ['period_unit'] > $tmpArr [$nettype] ['period_unit']) {
							$tmpArr [$nettype] = $row;
						} else if ($row ['period_unit'] == $tmpArr [$nettype] ['period_unit'] and $tmpArr [$nettype] ['period'] > $row ['period']) {
							$tmpArr [$nettype] = $row;
						}
					} else {
						$tmpArr [$nettype] = $row;
					}
					
					$_unit='天';
					if($row['period_unit']>0) $_unit='小时';
					//第一个值(可能是小时、天、月、年)
					if(empty($_price)){
						$_price=$row['price'].'元/'.$row['period'].$_unit;
						if($row['period_unit']==0 and $row['period']==30) $_pricem=";";
					}
					//第一个30天的值
					else if(empty($_pricem) and $row['period']==30 and $row['period_unit']==0){
						$_pricem=$row['price'].'元/'.$row['period'].$_unit;
					}
					if($row['period']>=30 and $row['period_unit']==0){
						$_month=1;
					}
				}
				$arr['_min_price'] = $tmpArr;
				if(strlen($_pricem)>2) $_price=$_price.';'.$_pricem;
			}
			
			//未处理DIY方式的价格
			if(empty($_price)){
				$_error='未设置价格';
			}
			//检查是否有可用资源
			if(empty($_error)){
				$server_num=0;
				$server_limit=0;
				$server_usable=0;
				//print_r($this->serverArr);exit;
				$useNetIdArr=array();
				foreach($this->serverArr as $row){
					//不同分类
					if($arr['type_id']!=$row['server_prod_type'] and $arr['type_id']!=$row['server_sub_type']) continue;
					//指定网络后不同网络
					if(strlen($arr['net_id'])>1 and strpos(",,,{$arr['net_id']},",",{$row['server_net_id']},")<1) continue;
					//指定操作系统
					//if($pid==12233) {echo $arr['os_id'],"; os=",$row['server_templates'];exit;}
					if(strlen($arr['os_id'])>1 and strpos(',,'.$row['server_templates'],",{$arr['os_id']},")<1) continue;
					if(!preg_match('/[0-9]/', $row['server_templates']))continue;
					if($_osid>0 and strlen($_osid)>1 and strpos($row['server_templates'],",{$_osid},")<1) continue;
					//服务器符合限制要求(不用判断资源是否够用)
					
					//如果是共享IP产品，独立IP服务器跳过(@2015-6-15)
					if(empty($arr['vm_iptype']) and $row['server_ipsell']==1) continue;
					$sid=intval($row['server_id']);
					if($arr['vm_mode']==1 and !isset($interArr[$sid])) continue;//拨号专用服务器


					$server_num=$server_num+1;

					####//如果不是管理员，需要判断暂停状态（包括分类、网络、操作系统）
					$_net=intval($row['server_net_id']);


					if($row['server_stat']>0 and ($isAdmin<1 or $isAdmin>8)) continue;
					//限制个数是否超限
					if($row['server_vmnum']>=$row['topnum']) continue;//授权上限
					//自定义上限
					if($row['server_vmnum']>=$row['server_vmmax'] and $row['server_vmmax']>0) continue;//设定最大数上限
					//以下处理运行数上限
					if($arr['type_id']==$row['server_prod_type']){
						if($row['server_vmuse']>0 and $row['server_vmrun']>=$row['server_vmuse']+$addNum) continue;
					}
					else if($arr['type_id']==$row['server_sub_type']){
						if($row['server_vmuse']>0 and $row['server_vmrun']>=$row['server_vmuse']+$addNum) continue;//超过总数
						if($row['server_vmuses']>0 and $row['server_vmruns']>=$row['server_vmuses']+$addNum) continue;//第二分类超过限定值
					}
					else {
						continue;
					}
					$server_limit=1;
				
					if($arr['vm_mode']==1 and empty($interArr[$sid])) continue;//拨号专用服务器开满
					
					$nettypeid = intval($this->netArr[$row['server_net_id']]['net_type_id']);
					if($nettypeid<=0) continue;
					if(empty($priceArr[$pid])) continue;
					
					$isUsePrice=false;
					foreach ($priceArr[$pid] as $v){
						if($v['net_type']==$nettypeid){
							$isUsePrice=true;
							break;
						}
					}
					
					if(!$isUsePrice)	continue;
					
					//可用资源是否够用(包括CPU、内存、IP、硬盘)
					if($row['server_cpu_cores']<$arr['vm_cpunum']) continue;
					if($row['server_cpu_max']<$arr['vm_cpulimit']+500) continue;
					if($row['server_memory_max']<$arr['vm_memory']+500) continue;//内存
					if($row['server_cpu_min']>$arr['vm_cpunum']) continue;//服务器最小核心数限制
					if($row['server_memory_min']>$arr['vm_memory']) continue;//服务器最小内存限制
					if($row['server_paylimit']==1 and $_month<1) continue;//如果服务器只允许月付，而付款方式中没有时

					//以前磁盘未判断####(2016-10-26)
					if($row['server_disk_max']<15+$arr['vm_disk_data']) continue;

					//独立IP的是否有空闲IP 
					$ipnum=0;//2016-10-26
					if(!empty($arr['vm_iptype'])){
						$lanstart=intval($row['server_lan_start']);
						$lanend=intval($row['server_lan_end']);
						if($lanstart>254) $lanstart=254;
						if($lanend>254) $lanend=254;
						$iptag=substr($row['server_addr'],0,strrpos($row['server_addr'],"."));
						//$ipnum=(isset($ipArr[$iptag]) ? $ipArr[$iptag] :0);
						if(!empty($this->netArr[$_net]['net_ip_close'])) {
							$ipnum=0;
						}
						else
						{
							foreach($ipArr as $ipstr){
								$_iptag=substr($ipstr,0,strrpos($ipstr,"."));
								if($_iptag!=$iptag) continue;//不是同一段的
								$tmp=explode('.',$ipstr);
								$ipv=intval($tmp[3]);
								if($ipv<1 or $ipv>254) continue; //不允许使用的.1和.254 IP
								if($lanend>0 and $lanend>=$lanstart){
									if($ipv<$lanstart) continue;
									if($ipv>$lanend) continue;
								}
								$ipnum=1;
								break;
							}
						}
						if($ipnum<1) continue;
					}

					$server_usable=1;
				}

				if(empty($server_num)){
					$_error='无相关服务器';
				}
				else if(empty($server_limit)){
					$_error='服务器开满';
				}
				else if(empty($server_usable)){
					$_error='缺少可用资源';
				}

			}

			if(empty($_error)){
				$_usable=1;//可用情况
			}
			$arr['prod_name']=$arr['prod_name'];
			$arr=array_merge($arr,Array("_price"=>$_price));
			$arr=array_merge($arr,Array("_usable"=>$_usable));
			$arr=array_merge($arr,Array("_error"=>$_error));
			$arr=array_merge($arr,Array("_bandwidth"=>$_bandwidth));
			$ret[$pid]=$arr;
		}
		return $ret;
	}

	//F1.2 查询单个配置，用于购买选择网络(购买第二步)
	//用户确定并提交后，网页同时需要query_conf()来计算价格，扣费后使用order_add直接创建订单
	//$prodid不能为空
	//返回可用操作系统(包括分类)、可用网络及对应服务器(含网络线路类型及IP类型定价)、可用时长及价格（不同网络可能不同）
	function query_conf($isAdmin=0,$isPrice=0,$prodid=0,$isSkip=0){
		//返回值定义
		$ret=Array(
				'_error'=>''    //是否有错误(为空是则无错误)
				,'_opennum'=>0
				,'_sidStr'=>""//开过的服务器
				,'_sidSet'=>""//开过>=2次的服务器
				,'_ipStr'=>"" //开过的IP段
				,'_netStr'=>"" //用户用过的机房编号
				,'_discount'=>1 //会员折扣
				,'prod'=>Array() //产品信息
				,'net'=>array() //网络设置(用于提交后判断是什么线路类型)
				,'nettype'=>Array() //网络类型设置(用于显示线路类型供选择)
				,'prodSet'=>Array() //线路特殊设置，用于提交后根据网络类型调整产品配置
				,'priceSet'=>array() //价格设置(不同线路不同价格，用于提交后根据网络类型计价)
				,'prodtype' =>array()//产品分类信息
				,'jscode'=>''    //可用资源的javascript字符串(不带头尾<script>和</script>)，
				//jscode项目包括:OS类型，OS分类，OS列表，网络列表，网络特殊设置(用于同价不同配)，服务器列表，网络类型对应的时长及价格(用于同配不同价)
				);
		
		
		if(empty($prodid)){
			$ret['_error']='推荐配置ID为空！';
			return $ret;
		}
		$prodid=intval($prodid);
		
		$sql="select * from ".$this->Tbl['prod_conf']." where prod_id=".$prodid;
		if($isAdmin>0){
			$sql=$sql." and prod_stat<2"; //用户续费或管理员购买续费
		}else {
			$sql=$sql." and prod_stat<1";//用户新购买
		}
		//管理员不受限制
		if($this->isAPI){
			$sql=$sql." and prod_api>0";
		}else if(empty($isAdmin)){
			$sql=$sql." and prod_api<2";
		}
		$prods=$this->DB->query($sql,1);
		
		if(empty($prods)){
			$ret['_error']='推荐配置ID('.$prodid.'|'.$isAdmin.')无效！';
			return $ret;
		}
		
		//允许试用(2015-5-7新增)
		if($prods['prod_test_open']>0){
			if($this->setConf['site_test_open']<=0){
				$prods['prod_test_open']=0;//系统关闭试用
			}else{
				$sql="select * from ".$this->Tbl['vm_order']." where vm_adddate>'".date('Y-m-d 00:00:00',time()-60*24*3600)."' and vm_isfree!=1";
				if($this->isAPI){
					$sql=$sql." and user_id=".$this->userID." and client_id=".intval($this->clientID);//API使用
				}else{
					$sql=$sql." and user_id=".intval($this->userID);//本地使用
				}
				$sql=$sql." limit 1";
				$res=$this->DB->query($sql,1);
				if(!empty($res)) $prods['prod_test_open']=0;//申请过
			}
		}
		
		//返回已经开过的记录
		$_used_server_arr=Array(); //开过的服务器
		$_used_server_num_arr=Array(); //开过>=2服务器
		$_used_ip_num_arr=Array();  // 开过的IP段数量
		$_used_net_num_arr=Array(); // 开过的机房
		$_discount=1;
		
		if($this->userID>=100){
			//得到用户折扣
			$rebateRes=$this->DB->query("select * from ".$this->Tbl['set_user_rebate']." where user_stat=0 and user_id=".intval($this->userID),1);
			if(!empty($rebateRes)){
				$_discount=$rebateRes['user_discount'];
				if($_discount<0.5 or $_discount>1) $_discount=1;
			}
			
			//得到用户开通的服务器，机房，IP段
			$sql="select vm_ip,server_id,net_id from ".$this->Tbl['vm_order'];
			$sql.=" where user_id=".intval($this->userID);
			$sql.=" and vm_stat<2 ";
			if($this->isAPI or $this->clientID>=100){
				$sql=$sql." and client_id=".intval($this->clientID);
			}
			$sql.=" limit 300";
			$result=$this->DB->query($sql,2);
			usleep(20000);
			
			foreach($result as $arr){
				$tag=$arr['vm_ip'];
				$iptag=substr($arr['vm_ip'],0,strrpos($arr['vm_ip'],"."));
				$sid=intval($arr['server_id']);
				$net=intval($arr['net_id']);
				
				$_used_server_arr[$sid]				= 1;
				$_used_server_num_arr[$sid]	= (isset($_used_server_num_arr[$sid])?($_used_server_num_arr[$sid]+1):1);
				$_used_ip_num_arr[$iptag]		= (isset($_used_ip_num_arr[$iptag])?($_used_ip_num_arr[$iptag]+1):1);
				$_used_net_num_arr[$net]		= 1;
			}
			
		}
		
		$queryType					= $prods['type_id'];
		$prods['prod_name']	= $prods['prod_name'];
		
		
		if(empty($this->queryRun)){
			$this->query_resource($isAdmin,1,$queryType,$prods);
		}
		if(!isset($this->typeArr[$queryType])){
			$ret['_error']='产品所属分类关闭无效！';
			return $ret;
		}
		
		//得到资源限制
		if($this->typeArr[$queryType]['limit_cpu_mode']<=0) $this->typeArr[$queryType]['limit_cpu_mode']=1;
		if($this->typeArr[$queryType]['limit_bw_mode']<=0) $this->typeArr[$queryType]['limit_bw_mode']=1;
		if($this->typeArr[$queryType]['limit_bw_out_set']<=0 or $this->typeArr[$queryType]['limit_bw_out_set']>100) $this->typeArr[$queryType]['limit_bw_out_set']=100;
		if($this->typeArr[$queryType]['limit_bw_in_set']<=0 or $this->typeArr[$queryType]['limit_bw_in_set']>100) $this->typeArr[$queryType]['limit_bw_in_set']=100;
		
		//IP地址可用数量池(开多个时需要共享)
		$result=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where ip_stat=0",2);
		$ipArr=Array();
		foreach($result as $arr){
			$ipArr[]=$arr['ip_addr'];
		}
		unset($result);
		
		//得到拨号模式下服务器可用的拨号帐号
		$interArr=Array();
		if($prods['vm_mode']){
			$sql="select record_server_id,count(*) as num_max from ".$this->Tbl['set_internet_server'];
			$sql.=" where record_stat=0";
			$sql.=" and record_vm_id=0";
			$sql.=" group by record_server_id";
			$result=$this->DB->query($sql,2);
			
			foreach($result as $arr){
				$_num=0+$arr['num_max'];
				if($_num<0) $_num=0;
				$interArr[$arr['record_server_id']]=0+$_num;
			}
			unset($result);
		}
		
		
		$use_server_arr=array();//得到可用的服务器
		$_openNum=0;
		$netArr=Array();
		$netMonthPay=Array();//线路是否没有时长限制(服务器表server_paylimit只能月付)，为0表示有限制，为1表示无限制
		$osSet=Array();
		
		foreach($this->serverArr as $row){
			//不同分类
			if($prods['type_id']!=$row['server_prod_type'] and $prods['type_id']!=$row['server_sub_type']) continue;
			//指定网络后不同网络
			if(strlen($prods['net_id'])>1 and strpos(",,,{$prods['net_id']},",",{$row['server_net_id']},")<1) continue;
			//指定操作系统
			//echo $prods['os_id'],";",$row['server_templates'];exit;
			if(strlen($prods['os_id'])>1 and strpos($row['server_templates'],",{$prods['os_id']},")<1) continue;
			//只卖独立IP
			if($row['server_ipsell']==1 and $prods['vm_iptype']==0) continue;
			$sid=intval($row['server_id']);
			if($prods['vm_mode']==1 and (empty($interArr[$sid]) or $interArr[$sid]<1)) continue;//拨号专用服务器开满
			//可用资源是否够用(包括CPU、内存、IP、硬盘)
			if($row['server_cpu_cores']<$prods['vm_cpunum']) continue;//可用核心数
			if($row['server_cpu_max']<$prods['vm_cpulimit']) continue;//可用CPU
			if($row['server_memory_max']<$prods['vm_memory']) continue;//可用内存
			if($row['server_cpu_min']>$prods['vm_cpunum']) continue;//服务器最小核心数限制
			if($row['server_memory_min']>$prods['vm_memory']) continue;//服务器最小内存限制
			$_net=intval($row['server_net_id']);
			//月付限制检查0为允许天付 1月付
			if($row['server_paylimit']==0){
				$_line=$this->netArr[$_net]['net_type_id'];
				$netMonthPay[$_line]=1;
			}
			
			//以前磁盘未判断####(2016-10-26)
			if($row['server_disk_max']<15+$prods['vm_disk_data']) continue;

			//独立IP的是否有空闲IP
			$ipnum=0;
			if(!empty($prods['vm_iptype'])){
				$lanstart=intval($row['server_lan_start']);
				$lanend=intval($row['server_lan_end']);
				if($lanstart>254) $lanstart=254;
				if($lanend>254) $lanend=254;
				$iptag=substr($row['server_addr'],0,strrpos($row['server_addr'],"."));
				//$ipnum=(isset($ipArr[$iptag]) ? $ipArr[$iptag] :0);
				if(!empty($this->SCP->netArr[$_net]['net_ip_close'])){
					$ipnum=0;
				}else{
					foreach($ipArr as $ipstr){
						$_iptag=substr($ipstr,0,strrpos($ipstr,"."));
						if($_iptag!=$iptag) continue;//不是同一段的
						$tmp=explode('.',$ipstr);
						$ipv=intval($tmp[3]);
						if($ipv<1 or $ipv>254) continue; //不允许使用的.1和.254 IP
						if($lanend>0 and $lanend>$lanstart){
							if($ipv<$lanstart) continue;
							if($ipv>$lanend) continue;
						}
						$ipnum=1;
						break;
					}
				}
				if($ipnum<1) continue;
			}
			$row['_ipnum'] = $ipnum;
			
			if($prods['vm_mode']==1 and $interArr[$sid]<$row['maxnum']) $row['maxnum']=$interArr[$sid];//可用拨号帐号数。
			if($row['maxnum']<1) continue;
			
			$_openNum=$_openNum+$row['maxnum'];
			
			$tmp=explode(',',$row['server_templates']);
			foreach($tmp as $_osid){
				if(!empty($_osid)){
					if(strlen($prods['os_id'])>1 and $prods['prod_sys_close']>0 and strpos(",,{$prods['os_id']},",",{$_osid},")<1) continue;
					$osSet[$_osid]=1;
				}
			}

			if(isset($netArr[$_net])){
				$netArr[$_net]=0+$netArr[$_net]+$row['maxnum'];
			}
			else{
				$netArr[$_net]=0+$row['maxnum'];
			}
			
			$use_server_arr[$row['server_id']]=$row;
		}

		if(empty($use_server_arr)){
			$ret['_error']='暂时缺货！';
			return $ret;
		}
		
		//网络设置
		$use_net_arr=array();
		foreach($this->netArr as $arr){
			$_net=intval($arr['net_id']);
			if(empty($netArr[$_net])) continue;
			$lineType[$arr['net_type_id']]=1;
			$use_net_arr[$_net]=$arr;
		}
		
		//查询网络线路
		$use_nettype_arr=array();
		$results=$this->DB->query("select * from ".$this->Tbl['set_prod_type']." where type_stat<2 and type_sort=1",2);
		
		foreach ($results as $_k => $arr){
			$type=intval($arr['type_id']);
			if(!isset($lineType[$type])) continue;
			$use_nettype_arr[$type]=$arr;
		}
		
		//推荐配置网络参数特殊设置
		$result=$this->DB->query("select * from ".$this->Tbl['prod_conf_set']." where  prod_id=$prodid",2);
		
		$use_prodconf_net_arr=array();
		
		foreach($result as $arr){
			$_net=intval($arr['net_id']);
			if($this->netArr[$_net]['net_domain_close']>1) $arr['vm_domain_limit']=0;
			$use_prodconf_net_arr[$_net]=$arr;
		}
		
		//推荐配置价格（暂时未处理DIY方式的价格。即未设置推荐配置价格时不返回价格设置）
		
		$nameSet=Array('D'=>'天','H'=>'小时','M'=>'分钟');
		
		if(!$isSkip){
			$result=$this->DB->query("select * from ".$this->Tbl['prod_conf_price']." where  prod_id=$prodid order by price",2);
			$use_price_arr = array();
			foreach($result as $arr){
				if($arr['period_unit']==1) $tag='H';
				else if($arr['period_unit']==2) $tag='M';
				else if($arr['period_unit']==0) $tag='D';
				else{
					continue;//其它错误设置跳过
				}
				$_line=$arr['net_type'];
				//非管理员+限制了只能月付(不能天付)+时长小于30天
				if(empty($this->isAdmin) and empty($netMonthPay[$_line]) and ($arr['period_unit']>0 or $arr['period']<30)) continue;
					
				$name=$nameSet[$tag];
				$arr['_key'] = $tag.$arr['period'];
				$arr['_name']=$name;
				$use_price_arr[]=$arr;
			}
			
			if(empty($use_price_arr)){
				$ret['_error']='未设置有效的价格！';
				return $ret;
			}
		}
		//操作系统模板
		$use_os_arr = array();
		foreach($this->osArr as $arr){
			$_osid=$arr['os_id'];
			if(!isset($osSet[$_osid])) continue;//不可用的不显示
			//内存不够则跳过(2015-9-29)
			if($arr['os_memory']>$prods['vm_memory']) continue;
			$use_os_arr[$_osid] = $arr;
		}
		if(empty($use_os_arr)){
			$ret['_error']='未设置正确的操作系统！';
			return $ret;
		}
		
		
		//根据价格反查线路，网络
		//******************************
		if(!$isSkip){
			$_use_nettype_arr =array();
			foreach ($use_price_arr as $arr){
				$_use_nettype_arr[$arr['net_type']]=1;
			}
			
			foreach ($use_nettype_arr as $type=>$arr){
				if(empty($_use_nettype_arr[$type])) unset($use_nettype_arr[$type]);
			}
			
			foreach ($use_net_arr as $_net => $arr){
				if(empty($use_nettype_arr[$arr['net_type_id']])) unset($use_net_arr[$_net]);
			}
			
			$_use_os_arr_tmp = array();
			//得到机房下可用的操作系统
			$_use_net_os_tmp = array();
			
			foreach ($use_server_arr as $serverid=>$row){
				
				$netid = intval($row['server_net_id']);
				
				if(empty($use_net_arr[$row['server_net_id']])) {
					unset($use_server_arr[$serverid]);
					continue;
				}
				
				$_osArrTmp = explode(",", $row['server_templates']);
				foreach ($_osArrTmp as $v){
					$v = trim($v);
					if(empty($v) or strlen($v)<1) continue;
					$_use_os_arr_tmp[$v] =$v;
					$_use_net_os_tmp[$netid][$v] = $v;
				}
				
			}
			
			$appID='0';
			foreach ($use_os_arr as $_osid =>$arr){
				if(empty($_use_os_arr_tmp[$_osid])) {
					unset($use_os_arr[$_osid]);
					continue;
				}
				$appID=$appID.','.intval(intval($arr['os_app_id']));
			}
		}
		//******************************
		
		//操作系统应用分类(可于快速选择分类)
		$result=$this->DB->query("select * from ".$this->Tbl['set_os_app']." where os_app_stat=0 and os_app_id IN($appID) order by os_app_order",2);
		$use_osapp_arr = array();
		foreach($result as $arr){
			$use_osapp_arr[]=$arr;
		}
		
		
		//得到可用网络
		$new_net_arr=array();
		foreach ($use_net_arr as $_net => $arr){
			$osstr='';
			if(!empty($_use_net_os_tmp[$_net])){
				$osstr = implode(",", $_use_net_os_tmp[$_net]);
			}
			$new_net_arr[$_net]=Array(
					"name"=>get_html($arr['net_name']),
					'net_type_id'=>intval($arr['net_type_id']),
					"net_domain_close"=>intval($arr['net_domain_close']),
					"net_ip_close"=>intval($arr['net_ip_close']),
					"net_port_close"=>intval($arr['net_port_close']),
					"os"=>$osstr
					);
		}
		
		//得到可用线路
		$new_nettype_arr=array();
		foreach ($use_nettype_arr as $type => $arr){
			$new_nettype_arr[$type]=get_html($arr['type_name']).''.(empty($netMonthPay[$type])?'[M]':'');
		}
		
		//推荐配置带宽和域名个数特殊设置
		$new_prodconf_net_arr=array();
		foreach ($use_prodconf_net_arr as $_net =>$arr){
			$new_prodconf_net_arr[$_net]=Array(
					"vm_bandwidth_out"=>intval($arr['vm_bandwidth_out']),
					"vm_bandwidth_in"=>intval($arr['vm_bandwidth_in']),
					"vm_domain_limit"=>intval($arr['vm_domain_limit'])
					);
		}
		
		//可用价格
		$new_price_arr=array();
		
		$n=0;
		foreach ($use_price_arr as $arr){
			$new_price_arr[$n]=Array(
				"net_type"=>intval($arr['net_type']),
				"period"=>intval($arr['period']),
				"period_unit"=>intval($arr['period_unit']),
				"key"=>$arr['_key'],
				"name"=>$arr['_name'],
				"price"=>doubleval($arr['price'])
			);
			
			$n++;
		}
		
		
		//保存
		$ret['prod']												= $prods;
		
		$ret['_discount']									= $_discount;
		
		$ret['_sidStr']											= $_used_server_arr;
		$ret['_sidSet']										= $_used_server_num_arr;
		$ret['_ipStr']											= $_used_ip_num_arr;
		$ret['_netStr']										= $_used_net_num_arr;
		
		$ret['prodtype']['limit_cpu_mode']		= $this->typeArr[$queryType]['limit_cpu_mode'];
		$ret['prodtype']['limit_bw_mode']		= $this->typeArr[$queryType]['limit_bw_mode'];
		$ret['prodtype']['limit_bw_out_set']	= $this->typeArr[$queryType]['limit_bw_out_set'];
		$ret['prodtype']['limit_bw_in_set']	= $this->typeArr[$queryType]['limit_bw_in_set'];
		
		//可开总数
		$ret['_opennum']									= $_openNum;
		
		//得到可用网络
		$ret['net'] = $new_net_arr;
		
		//得到可用线路
		$ret['nettype'] = $new_nettype_arr;
		
		//推荐配置带宽和域名个数特殊设置
		$ret['prodSet']=$new_prodconf_net_arr;
		
		//可用价格
		$ret['priceSet']=$new_price_arr;
		
		//操作系统
		$ret['os']=$use_os_arr;
		
		
		$_used_server_str=',';
		foreach ($_used_server_arr as $serverid =>$num){
			$_used_server_str.="{$serverid},";
		}
		
		$_used_server_num_str=',';
		foreach($_used_server_num_arr as $serverid =>$_num){
			if($_num>=2) $_used_server_num_str.="{$serverid},";;//开过>=2次的跳过
		}
		
		$_used_ip_num_str=',';
		foreach ($_used_ip_num_arr as $ipstr =>$num){
			$_used_ip_num_str.="{$ipstr},";
		}
		
		$_used_net_num_str=',';
		foreach ($_used_net_num_arr as $netstr =>$num){
			$_used_net_num_str.="{$netstr},";
		}
		
		$str="
		function __cloud_res_pool(){
		";
		$str=$str."\nthis._sidStr=\"$_used_server_str\";\n";
		$str=$str."\nthis._sidSet=\"$_used_server_num_str\";\n";
		$str=$str."\nthis._ipStr=\"$_used_ip_num_str\";\n";
		$str=$str."\nthis._netStr=\"$_used_net_num_str\";\n";
		//$str=$str."\n//".count($use_server_arr);
		$str=$str."\n this.serverArr={\n";
		$n=0;
		
		//$_net_sum=Array();
		foreach ($use_server_arr as $serverid=>$row){

			$str=$str.'
			'.($n>0?',':'').'"'.$serverid.'":{
				"ipaddr":"'.$this->specialstr($row['server_addr']).'"
				,"stat":0
				,"serverid":'.intval($row['server_id']).'
				,"typeid":'.intval($row['server_prod_type']).'
				,"typesub":'.intval($row['server_sub_type']).'
				,"net":'.intval($row['server_net_id']).'
				,"nettype":'.intval($this->netArr[$row['server_net_id']]['net_type_id']).'
				,"iponly":'.intval($row['server_ipsell']).'
				,"memory":'.round($row['server_memory_max']).'
				,"core":'.intval($row['server_cpu_cores']).'
				,"cpu":'.round($row['server_cpu_max']).'
				,"disk":'.intval($row['server_disk_max']).'
				,"osstr":",,'.$this->specialstr($row['server_templates']).',"
				,"group":'.intval($row['server_group']).'
				,"memory_min":'.intval($row['server_memory_min']).'
				,"cpu_min":'.intval($row['server_cpu_min']).'
				,"paylimit":'.intval($row['server_paylimit']).'
				,"ipnum":'.intval($row['_ipnum']).'
				,"opennum":'.intval($row['maxnum']).'
				,"orderval":'.intval($row['orderval']).'
				,"install":'.intval($row['server_install_time']).'
				}
			';
			$n++;
		}
		$str=$str."\n }\n";
		
		$str=$str."\n this.netArr={\n";
		$n=0;
		//$_tmp='';
		foreach ($use_net_arr as $arr){
			//$_tmp=$_tmp.','.intval($arr['net_id']);
			$_net=intval($arr['net_id']);
			$_netord=0+$netArr[$_net];
			if($arr['net_order']>99) $arr['net_order']=99;
			if($_netord>200) $_netord=200+rand(0,99);
			if($_netord<10) $arr['net_order']=$arr['net_order']-2;
			if($arr['net_order']<0) $arr['net_order']=0;
			$_order=intval($arr['net_order'])*1000+$_netord;
			$str=$str.'
			'.($n>0?',':'').'"'.intval($arr['net_id']).'":{
				"name":"'.$this->specialstr($arr['net_name']).'"
				,"stat":0
				,"netid":'.intval($arr['net_id']).'
				,"orderval":'.intval($arr['net_order']).'
				,"nettype":'.intval($arr['net_type_id']).'
				,"domainclose":'.intval($arr['net_domain_close']).'
				,"ipclose":'.intval($arr['net_ip_close']).'
				,"portclose":'.intval($arr['net_port_close']).'
				,"netid":'.intval($arr['net_id']).'
				,"orderval":'.intval($_order).'
				,"bwinmin":0
				,"bwinmax":0
				,"bwoutmin":0
				,"bwoutmax":0
				,"pricetype":0
				,"remark":"'.$this->specialstr($arr['net_remark']).'"
				}
			';
			$n++;
		}
		$str=$str."\n }\n";
		//2016-11-4 如果只有一个机房会异常###
		//##2016-12-23 此行不再需要 $str=$str."\n this.netList=new Array(-1".$_tmp.");\n ";
		$str=$str."\n this.netType={\n";
		$n=0;
		foreach ($use_nettype_arr as $arr){
			$type = intval($arr['type_id']);
			$str=$str.'
			'.($n>0?',':'').'"'.$type.'":{
				"type_id":'.intval($type).'
				,"type_name":"'.$this->specialstr($arr['type_name']).'"
				,"pay_limit":'.(empty($netMonthPay[$type])?'1':'0').'
				}
			';
			$n++;
		}
		$str=$str."\n }\n";
		
		
		$str=$str."\n this.prodSet={\n";
		$n=0;
		foreach ($use_prodconf_net_arr as $arr){
			$_net=intval($arr['net_id']);
			$str=$str.'
			'.($n>0?',':'').'"'.$_net.'":{
				"vm_bandwidth_out":'.intval($arr['vm_bandwidth_out']).'
				,"vm_bandwidth_in":'.intval($arr['vm_bandwidth_in']).'
				,"vm_domain_limit":'.intval($arr['vm_domain_limit']).'
				}
			';
			$n++;
		}
		$str=$str."\n }\n";
		
		$str=$str."\n this.price={\n";
		$n=0;
		foreach ($use_price_arr as $arr){
			$str=$str.'
			'.($n>0?',':'').'"'.$n.'":{
				"net_type":'.intval($arr['net_type']).'
				,"period":'.intval($arr['period']).'
				,"period_unit":'.intval($arr['period_unit']).'
				,"key":"'.$arr['_key'].'"
				,"name":"'.$this->specialstr($arr['_name']).'"
				,"price":'.doubleval($arr['price']).'
				}
			';
			$n++;
		}
		$str=$str."\n }\n";
		
		$str=$str."\n this.osArr={\n";
		$n=0;
		foreach ($use_os_arr as $arr){
			$str=$str.'
			'.($n>0?',':'').'"'.$arr['os_id'].'":{
				"name":"'.(($arr['os_filemod']==1)?"+ ":"@ ").$this->specialstr($arr['os_name']).'"
				,"osid":'.intval($arr['os_id']).'
				,"orderval":'.intval($arr['os_filemod']==1?$arr['os_ordernum']:1000000+$arr['os_ordernum']).'
				,"username":"'.$this->specialstr($arr['os_username']).'"
				,"stat":0
				,"memory":'.round($arr['os_memory']).'
				,"intro":"'.$this->specialstr($arr['os_intro']).'"
				,"type":'.intval($arr['os_sort_id']).'
				,"disk_sys":'.intval($arr['os_disk_sys']).'
				,"appid":'.intval($arr['os_app_id']).'
				,"userid":'.intval($arr['user_id']).'
				,"disk":1
				}
			';
			$n++;
		}
		$str=$str."\n }\n";
		
		$str=$str."\n this.osApp={\n";
		$n=0;
		foreach ($use_osapp_arr as $arr){
			$str=$str.'
			'.($n>0?',':'').'"'.$arr['os_app_id'].'":{"name":"'.$this->specialstr($arr['os_app_name']).'"}
			';
			$n++;
		}
		$str=$str."\n }\n";
		
		$str=$str."
		}
		//end __cloud_res_pool
		";
		$ret['jscode']=$str;
		
		
		return $ret;
	}


	//F1.3 保存到数据库
	//正确是返回订单号，错误时返回空
	//本方法不检查
	//返回：
	//$ret['_error']：错误信息，如果有值则有错。$ret['vm_id']返回订单号
	function order_add($form,$skipCheck=0,$remark='')
	{
		global $__prod_ismod,$__prod_res_limit;
		$DB =$this->DB;
		$Tbl=$this->Tbl;
		$this->msg='';

		$saveData=Array();
		$serverid=intval($form['server_id']);
		$osid  =intval($form['os_id']);
		$userid=intval($form['user_id']);
		$period=intval($form['period']);
		$period_unit=intval($form['period_unit']);//0=天，1=小时；2=分钟
		if($period<15 and $period_unit==2) $period=15;//最小15分钟

		//echo $period;print_r($form);exit;

		$Msg='';
		if (empty($userid))
		{
			$userid=0;
		}

		if ($period<1)
		{
			$Msg=$Msg.'未设置正确的时长！';
		}

		if ($form['vm_memory']<100){
			$Msg=$Msg.'未设置正确的内存值！';
		}

		if (empty($osid) or !isset($this->osArr[$osid]))
		{
			$Msg=$Msg.'未选择正确的[操作系统]！';
		}
		else
		{
			//根据操作系统内存及系统盘要求检查是否符合要求
			if ($this->osArr[$osid]['os_memory']>$form['vm_memory'])
			{
				$Msg=$Msg.'当前所选内存规格太小不能安装选择的[操作系统]！';
			}

		}
		$form['vm_diy_pass']=trim($form['vm_diy_pass']);

		$cpu_limit=intval($form['vm_cpulimit']);
		if ($cpu_limit<200)
		{
			$Msg=$Msg.'CPU频率数错误!';
		}
		$tmp=$this->check_cpu_limit($form['vm_cpunum'],$cpu_limit);
		if(!empty($tmp)){
			$Msg=$Msg.'CPU频率数与核心数不匹配(需要为核心核1000倍至2000倍之间)!';
		}

		if (!empty($Msg))
		{
			return $this->error($Msg);
		}
		

		$links=intval($form['vm_links']);
		if(empty($links)){
			$links=$this->get_links($form['vm_cpulimit'],$form['vm_memory']);
		}
		else if($links<20){
			$Msg=$Msg.'[连接数]不能小于20！';
		}
		else if($links>20000){
			$Msg=$Msg.'[连接数]不能大于20000！';
		}
		$typeid=intval($form['type_id']);
		$netid=intval($form['net_id']);
		//$ip2=intval(substr($serverid,2));
		
		if (empty($netid) or !isset($this->netArr[$netid]))
		{
			$Msg=$Msg.'未设置正确的[机房或网络]！';
		}

		if(empty($this->typeArr[$typeid])){
			$Msg=$Msg.'未设置正确的[分类]！';
		}

		if ($netid<1000 or $netid>9900)
		{
			$Msg=$Msg.'所选择的网络编号格式不对(值应为：1000-9999)！';
		}


		if($form['vm_mode']==1) {
			$form['vm_iptype']=0;
		}
		
		
		//分配服务器
		$buymode=intval($form['buymode']);
		
		if($serverid==-1){
			$osStr=",$osid,";

			//2016-3-29 独立IP要判断是否有可用IP
			if($form['vm_iptype'] and empty($form['vm_ip'])){
				$resultIP=$this->DB->query("select ip_addr from ".$this->Tbl['set_ippool']." where ip_stat=0 ORDER BY rand()",2);

			}
			foreach($this->serverArr as $res){

				if($res['server_prod_type']!=$typeid and $res['server_sub_type']!=$typeid){
					continue;
				}
				if($res['server_net_id']!=$netid) continue;
				//如果服务器只能开独立IP，而推荐配置不是，则退出(2014-1-3新增)
				if(isset($res['server_ipsell']) and $res['server_ipsell']==1 and $form['vm_iptype']<1){
					continue;
				}
				if(empty($this->isAdmin) and $res['server_paylimit']>0 and ($period_unit>0 or $period<30)){
					continue;//限制了只能月付的
				}
				//2015-11-4 BUG (以前为$arr)
				$iptag=substr($res['server_addr'],0,strrpos($res['server_addr'],"."));
				$tag='';
				if($buymode==1 or $buymode==2){
					$tag=$res['server_id'];
				}
				else if($buymode==5 or $buymode==6){
					$tag=$iptag;
				}
				else if($buymode==8){
					$tag=$res['server_net_id'];
				}
				if($buymode>0 and empty($tag)) continue;
				if($buymode==2 and $this->vmArr[$tag]>1) continue;//开过两个
				else if($buymode>0 and $this->vmArr[$tag]) continue;//开过的

				if($buymode==1 and $this->vmArr[$tag]>=5) continue;//同一服务器一次最多开5个
				
				//2016-3-29 独立IP处理
				if($form['vm_iptype'] and empty($form['vm_ip'])){
					$ipnew='';
					$lanstart=intval($res['server_lan_start']);
					$lanend=intval($res['server_lan_end']);
					if($lanstart>254) $lanstart=254;
					if($lanend>254) $lanend=254;
					$tmp=explode('.',$res['server_addr']);
					$tag0=intval($tmp[0]).'.'.$tmp[1].'.'.$tmp[2].'.';
					foreach($resultIP as $tmps){
						$tmp=explode('.',$tmps['ip_addr']);
						$tag1=$tmp[0].'.'.$tmp[1].'.'.$tmp[2].'.';
						if($tag0!=$tag1) continue; //不是同一个段的
						$ipv=intval($tmp[3]);
						if($ipv<1 or $ipv>254) continue;
						//设置了子网的情况
						if($lanend>0 and $lanend>$lanstart){
							if($ipv<$lanstart) continue;
							if($ipv>$lanend) continue;
						}

						if($buymode>3) {
							if(isset($this->vmArr[$tag1])) continue;//使用过的不再分配模式
						}
						$ipnew=$tmps['ip_addr'];
						break;
					}

					if(empty($ipnew)) {
						continue;//此服务器所在段无空闲IP 2016-3-29
					}
				}

				if ($res['server_memory_max']>=$form['vm_memory'] 
					and $res['server_cpu_max']>=$form['vm_cpulimit']
					and $res['server_disk_max']>=$form['vm_disk']
					and $res['server_cpu_cores']>=$form['vm_cpunum'] //判断核心数是否可用
				)
				{
					$serverid=$res['server_id'];
					if($form['vm_iptype'] and empty($form['vm_ip'])) $form['vm_ip']=$ipnew;
					break;
				}
			}

			if(empty($serverid) or $serverid==-1){
				$Msg=$Msg.'所选机房暂无可用服务器（已经开满）需重新选！';
			}
		}
		else if (empty($serverid)){
			$Msg=$Msg.'未选择正确的[物理服务器]！';
		}
		else if(!isset($this->serverArr[$serverid]))
		{
			$Msg=$Msg.'所选服务器已经开满需重新选择服务器！';
		}
		else{
			if($this->serverArr[$serverid]['server_paylimit']>0 and ($period_unit>0 or $period<30)){
				$Msg=$Msg.'所选服务器不支持购买时长于小30天！';
			}
			//2015-4-20
			else if(!strpos($this->serverArr[$serverid]['server_templates'],','.$osid.',')){
				$Msg=$Msg.'未选择正确的[操作系统]！';
			}
		}

		if (!empty($Msg))
		{
			return $this->error($Msg);
		}
		

		//print_r($this->serverArr[$serverid]);exit;
		$this->API=new ServerMOD($this->DB,$serverid,$this->serverArr[$serverid],$this->userID);
		$tmp=explode('.',$this->serverArr[$serverid]['server_gateway']);
		$ip2=intval($tmp[1]);
		if($netid!=$this->serverArr[$serverid]['server_net_id']){
			
			$Msg=$Msg.$netid.'所选[机房或网络]有异常！';
		}
		if ($ip2<1 or $ip2>254)
		{
			$Msg=$Msg.$serverid.'服务器配置错误！';
		}

		if (!empty($Msg))
		{
			return $this->error($Msg);
		}
		

		//分配了MAC段
		if(1 and !empty($this->serverArr[$serverid]['server_mac']) and preg_match('/^[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}$/i',$this->serverArr[$serverid]['server_mac'])){
			$mactag=$this->serverArr[$serverid]['server_mac'];
			if(!preg_match('/:$/',$mactag)) $mactag=$mactag.':';
		}
		//使用默认值00:服务器编号分为三段
		else{
			$m0=DecHex(floor($serverid/10000));
			$m1=DecHex(floor($serverid/100));
			$m2=DecHex($serverid%100);
			if(strlen($m0)<2) $m0='0'.$m0;
			if(strlen($m1)<2) $m1='0'.$m1;
			if(strlen($m2)<2) $m2='0'.$m2;
			$mactag='00:'.$m0.':'.$m1.':'.$m2.':';
		}
		$mactag=strtolower($mactag);


		$prod_mem=0+$form['vm_memory'];//M
		$prod_cpu=0+$form['vm_cpulimit'];
		$cpu_num=0+$form['vm_cpunum'];
		if ($cpu_num>0 and ($prod_cpu>=getConfig('_core_to_hz_min') and $prod_cpu<$cpu_num*getConfig('_core_to_hz_min') or $prod_cpu>$cpu_num*getConfig('_core_to_hz_max')))
		{
			$Msg=$Msg.'[CPU核心数]和[CPU频率]不匹配！';
		}


		if(strlen($this->sitetag)!=5){
			$Conf=$DB->query("select conf_name,conf_value from ".$Tbl['set_config']." where conf_name='site_tag'",1);
			if(!empty($Conf) and !empty($Conf['conf_value']) and strlen($Conf['conf_value'])==5){
				$this->sitetag=$Conf['conf_value'];
			}
			else{
				$Msg='无有效主控标识，暂不能添加订单';
			}
		}
		if (!empty($Msg))
		{
			return $this->error($Msg);
		}

		//检查资源是否够用，同时分配硬盘（以剩余空间最多的优先）
		if ($cpu_num<1)
		{
			$cpu_num=ceil($prod_cpu/2000);
		}
		$disk_data=0+$form['vm_disk'];
		$disk_sys =0+$this->osArr[$osid]['os_disk_sys'];


		$dir0=0;
		$dir1=0;
		//测试或批量多个时使用，并不发检查资源命令
		if($skipCheck>0)
		{
			//每个盘开的数量，数量最小的盘优先分配（解决开设后未安装时分配不均的问题） 2012-5-3
			$result=$this->DB->query("select sum(vm_memory) as vm_memory,sum(vm_cpulimit) as vm_cpulimit from ".$this->Tbl['vm_order']." where server_id=".intval($serverid)." and vm_stat<3",2);
			$dsArr=Array();
			$duArr=Array();
			$memused=0;
			$cpuused=0;
			foreach($result as $arr){
				$memused=$memused+$arr['vm_memory'];
				$cpuused=$cpuused+$arr['vm_cpulimit'];
			}

			if(empty($this->serverID))
			{
				$res=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_stat<3 and server_id=$serverid",1);
				if($res) {
					$this->API->serverID =$serverid;
					$this->API->serverSet=$res;
				}
			}
			$serverRS=$this->API->serverSet;
			$free_mem=$this->API->get_memory($serverRS['server_memory_all'],$serverRS['server_memory_free'],$memused);
			$free_cpu=$this->API->get_cpu($serverRS['server_cpu_all'],$serverRS['server_cpu_free'],$cpuused);
			
			//增加冗余1000，防止用户提交后资源不足
			if($prod_mem>$free_mem+1000) $Msg=$Msg."服务器内存不足";//
			if($prod_cpu>$free_cpu+1000) $Msg=$Msg."服务器CPU不足！";//
			//$Mas="all={$serverRS['server_id']}; {$free_cpu}-{$prod_cpu}";
			//2016-12-17 IN 中增加store_stat<1
			$result=$this->DB->query("select * from ".$this->Tbl['set_server_stores']." where store_use=0 and store_id IN (select store_id from ".$this->Tbl['set_server_disk']." where ".$this->Tbl['set_server_disk'].".server_id=$serverid and ".$this->Tbl['set_server_disk'].".store_stat<1) and store_stat<1 order by round((store_used/store_size)/2,1),store_uptime",2);

			foreach($result as $arr){
				$_sid=intval($arr['store_id']);
				if($arr['store_usable']>$disk_sys+$disk_data){
					$dir0=$dir1=$_sid;
					break;
				}
			}

		}
		//检查服务器资源状态
		else
		{
			$res=$this->API->check_new($prod_mem,$prod_cpu,$disk_sys,$disk_data);
			if (!empty($res['msg']))
			{
				return $this->error($res['msg']."");
			}
			$dir0=intval($res['dir0']);
			$dir1=intval($res['dir1']);

		}
		
		if(empty($dir0) or empty($dir1)){
			$Msg=$Msg.$serverid.'空间不足或无法分配硬盘空间！';
		}
		$ip3=0;
		$ip4=0;

		if (!empty($Msg))
		{
			return $this->error($Msg);
		}

		#VPS命名：          vip10001  规则： vip[订单号]
		#内网IP：        10.NN.10.02  规则： 10.机器标识[SS1-254].分组编号[10-59].产品序号[2-99]
		#端口映射：      1002#-5999#  SS*100 + 产品序号

		#新MAC规则 AA:BB:CC:DD:11:02   ，AA:BB:CC:DD为火山系统中的序号，后两位分别是分组及序号
		#每个服务器分配唯一一个MAC，分配过后不能再分配(不用再请求MAC分配)


		$useTime=intval(time()-15*24*3600);//3天以内使用过的端口不再使用(新方法重新删除VPS不会再删除端口)
		//2015-12-2 改成15天内使用过的不再使用
		$server_group=intval($this->serverArr[$serverid]['server_group']);
		if(empty($server_group)) $server_group = intval($this->serverArr[$serverid]['server_net_id']);//默认使用机房作为分组
		//同一分组或服务器使用过的不能再分配
		$result=$this->DB->query("select vm_id,vm_port,vm_name from ".$this->Tbl['vm_order']." where (server_id=$serverid OR server_group=$server_group ) and (vm_deltime=0 or vm_stat<3 or vm_deltime > $useTime)",2);

		$vmArr=Array();
		foreach ($result as $arr)
		{
			$vmArr[$arr['vm_port']]=1; //使用过的端口
		}

		//查找未使用过的端口(容量5000，除去暂停的为4000个左右，18台则约220个/台)
		for ($i=10; $i<=59; $i++)
		{
			for ($j=2; $j<100; $j++)
			{
				$NowPort=$i*100+$j;
				if(!isset($vmArr[$NowPort])){
					$ip3=$i;
					$ip4=$j;
					break 2;
				}
			}
		}


		if (empty($ip4))
		{
			$Msg=$Msg.'端口及编号分配满额！';
		}
		else if (empty($ip3))
		{
			$Msg=$Msg.'分组未分配！';
		}

		$saveData['vm_admin_port']=$this->osArr[$osid]['os_admin_port']; 
		$admin_user=$this->osArr[$osid]['os_username'];
		if(empty($admin_user)){
			$Msg=$Msg.'操作系统模板“远程管理账号”为空！';
		}
		if(empty($saveData['vm_admin_port'])){
			$Msg=$Msg.'操作系统模板“远程管理端口”为空！';
		}


		if (!empty($Msg))
		{
			return $this->error($Msg);
		}

		#print_r($form);exit;
		//拨号模式判断
		
		if($form['vm_mode']==1){
			if($form['prod_id']<=0){
				$Msg=$Msg.'未设置正确的[推荐配置]！';
			}
			
			if (!empty($Msg))
			{
				return $this->error($Msg);
			}
			
			$internet_server_Res=$this->DB->query("select * from ".$this->Tbl['set_internet_server']." 
					where record_stat=0 and record_server_id=".intval($serverid)." and record_vm_id=0 order by record_id asc"
					,1);
			
			usleep(2000);
			
			if(empty($internet_server_Res)){
				$Msg=$Msg.'无可用的拨号资源！';
			}
			
			if (!empty($Msg))
			{
				return $this->error($Msg);
			}
			
			$recordid= intval($internet_server_Res['record_id']);
			$internetid= intval($internet_server_Res['internet_id']);
			
			if($internetid<=0){
				$Msg=$Msg.'无可用的拨号资源！';
				return $this->error($Msg);
			}
			
			$internet_Res=$this->DB->query("select * from ".$this->Tbl['set_internet']." where internet_id=".intval($internetid),1);
			
			usleep(2000);
			if(empty($internet_Res)){
				$Msg=$Msg.'无可用的拨号资源！';
			}
			
		}
		
		
		
		//自动分配IP地址
		$bindipstr='';
		if ($form['vm_iptype'])
		{
			$serverRS=$this->serverArr[$serverid];
			$lanstart=intval($serverRS['server_lan_start']);
			$lanend=intval($serverRS['server_lan_end']);
			if($lanstart>254) $lanstart=254;
			if($lanend>254) $lanend=254;


			//已经选择
			if(!empty($form['vm_ip'])){
				$tmp=explode('.',$form['vm_ip']);//$value['ip_addr'];
				$ipv=intval($tmp[3]);
				if($ipv<1 or $ipv>254) $Msg=$Msg.'所选IP不符合要求！';
				else if($lanend>0 and $lanend>$lanstart){//指定了子网
					if($ipv<$lanstart) $Msg=$Msg.'所选IP不符合要求！';
					else if($ipv>$lanend) $Msg=$Msg.'所选IP不符合要求！';
				}
				else{
					$IPS=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where ip_stat=0 and ip_addr=".$this->DB->getsql($form['vm_ip']),1);
					if (empty($IPS))
					{
						$Msg=$Msg.'分配的[公网IP地址]已经被使用，请新选择！';
					}
				}
			}
			//未选择，自动分配
			else{
				$server_ip=$serverRS['server_addr'];
				$lanstart=intval($serverRS['server_lan_start']);
				$lanend=intval($serverRS['server_lan_end']);
				if($lanstart>254) $lanstart=254;
				if($lanend>254) $lanend=254;
				$server_ip=preg_replace("/\.[0-9]+$/",".%",$server_ip);
				$result=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where ip_stat=0 and ip_addr like ".$this->DB->getsql($server_ip)." ORDER BY rand()",2);
				if (empty($result))
				{
					$Msg=$Msg.'当前服务器可用[公网IP地址]无空闲，请新选择！';
				}
				else{
					foreach($result as $arr){
						$tmp=explode('.',$arr['ip_addr']);
						$ip=$tmp[0].'.'.$tmp[1].'.'.$tmp[2].'.';
						$ipv=intval($tmp[3]);
						if($ipv<1 or $ipv>254) continue;

						//设置了子网的情况
						if($lanend>0 and $lanend>$lanstart){
							if($ipv<$lanstart) continue;
							if($ipv>$lanend) continue;
						}

						if($form['buymode']>3) {
							$tag=$arr['ip_addr'];
							if(isset($this->vmArr[$tag])) continue;//使用过的不再分配模式
						}
						$form['vm_ip']=$arr['ip_addr'];
						break;
					}
				}
				if(empty($form['vm_ip'])){
					$Msg=$Msg.'当前服务器中的[公网IP地址]已经被抢光，请新选择！';
				}
			}
			$saveData['vm_ip']=$form['vm_ip'];
			$bindipstr=$form['vm_ip'];
			//$form['vm_port_limit']=0;
		}
		//共享IP，未处理代理不在本机的情况
		else
		{
			//服务器是内网
			if(preg_match('/^10\./i',$this->serverArr[$serverid]['server_addr'])){
				if(empty($this->serverArr[$serverid]['server_addr_agent']))
				{
					$Msg=$Msg.'服务器未设置正确的公网IP';
				}
				$tmp=explode(":",$this->serverArr[$serverid]['server_addr_agent']);
				$saveData['vm_ip']=$tmp[0];
			}
			else{
				$saveData['vm_ip']=$this->serverArr[$serverid]['server_addr'];
			}
		}


		if (!empty($Msg))
		{
			return $this->error($Msg);
		}
		

		//创建订单
		$ip2s=$ip2;
		$ip4s=$ip4;
		if(strlen($ip2s)<2) $ip2s='0'.$ip2s;
		if(strlen($ip2s)<3) $ip2s='0'.$ip2s;
		if(strlen($ip4s)<2) $ip4s='0'.$ip4s;
		$vmmac=$mactag.sprintf("%02x:%02x",$ip3,$ip4);
		$vmmac=str_replace(":","",$vmmac);
		$vmmac=str_replace("-","",$vmmac);
		//$saveData['vm_name']  ='vip'.$netid.$ip2s.''.$ip3.$ip4s;
		//$saveData['vm_name']  ='vip'.$serverid.'-'.$ip3.$ip4s;
		$saveData['vm_name']  ='vip'.$serverid.$ip3.$ip4s;
		$saveData['server_id']=$serverid;
		$saveData['os_id'] =$osid;
		//$saveData['vm_prod_id']=$prodid;
		$portstat=0;
		if($this->serverArr[$serverid]['server_portstat']==1) $portstat=1;

		$saveData['vm_port_stat']  =intval($portstat);

		$saveData['server_group']  =intval($server_group);
		$saveData['user_id']  =intval($form['user_id']);
		//$saveData['vm_lan_ip']="10.{$ip2}.{$ip3}.{$ip4}";
		$saveData['vm_lan']=intval($ip2);//新版
		$saveData['vm_mac']   =$vmmac; //2013-7-22 使用预分配的 $mactag
		$saveData['vm_port']  =intval($ip3*100 + $ip4);
		$saveData['vm_iptype']  =intval($form['vm_iptype']);

		//新增2015-3-17
		$saveData['vm_isfree']  =intval($form['vm_isfree']);
		$saveData['prod_id']  =intval($form['prod_id']);
		$saveData['order_group']  =intval($form['order_group']);
		$saveData['client_id']  =intval($form['client_id']);

		//新增2015-11-17新模式
		$saveData['vm_ismod']=0;
		if($__prod_ismod and strlen($this->serverArr[$serverid]['server_api_version'])>0)
		{
			$saveData['vm_ismod']=1;
		}
		
		
		if(0 and $form['prod_id']==11102) {
			$saveData['vm_ismod']=0;
		}
		
		$saveData['vm_mode']=$form['vm_mode']==1?1:0;
		
		$saveData['vm_memory']=intval($form['vm_memory']);
		$saveData['vm_cpulimit']  =$cpu_limit;
		$saveData['vm_cpunum']     =$cpu_num;
		$saveData['vm_links']      =intval($links);
		$saveData['vm_bandwidth_out']=intval($form['vm_bandwidth_out']);
		$saveData['vm_bandwidth_in'] =intval($form['vm_bandwidth_in']);
		$saveData['vm_disk']         =intval($form['vm_disk']);
		
		if($form['vm_iptype']==0 and $form['vm_port_limit']>5) $form['vm_port_limit']=5;
		
		$saveData['vm_port_limit']   =intval($form['vm_port_limit']);
		$saveData['vm_domain_limit'] =intval($form['vm_domain_limit']);

		$saveData['type_id']      =intval($typeid);
		$saveData['net_id']       =intval($netid);

		if($this->typeArr[$typeid]['limit_cpu_mode']<=0) $this->typeArr[$typeid]['limit_cpu_mode']=1;
		if($this->typeArr[$typeid]['limit_bw_mode']<=0) $this->typeArr[$typeid]['limit_bw_mode']=1;
		$saveData['vm_cpu_mode']=0; //默认值
		$saveData['vm_bw_mode'] =0; //默认值
		if($__prod_res_limit){
			$saveData['vm_cpu_mode']       =intval($this->typeArr[$typeid]['limit_cpu_mode']);
			$saveData['vm_bw_mode']       =intval($this->typeArr[$typeid]['limit_bw_mode']);
		}

		if($period_unit>1){
			$startdate=date('Y-m-d H:i:s',time()+180); //按分计时的增加3分钟
		}
		else{
			$startdate=date('Y-m-d H:i:s',time()+600);//增加10分钟时间用于安装系统
		}

		$enddate=$this->date_add($startdate,$period,$period_unit);

		$saveData['vm_startdate'] =$startdate;
		$saveData['vm_enddate']   =$enddate;

		//echo '<pre>';print_r($saveData);exit;
		$sql1='';
		$sql2='';
		foreach ($saveData as $_s1 =>$_s2)
		{
			if(!preg_match('/[_a-z0-9]/i',$_s1)) continue;
			$sql1=$sql1.",".$_s1;
			$sql2=$sql2.','.$this->DB->getsql($_s2);
		}

		//echo '<pre>';print_r($form);exit;
		//2012-10-24 默认为不可用订单vm_stat=2
		$sql=" insert into ".$this->Tbl['vm_order']." (vm_stat,vm_adddate,vm_deltime";
		$sql=$sql.$sql1;
		$sql=$sql.") values (2,NOW(),0";
		$sql=$sql.$sql2.")";
		#echo '<pre>'.$sql; print_r($this->RS); exit;
		
		$res=$this->DB->query($sql);
		
		if ($res)
		{
			$vmid=0+intval($this->DB->last_id());
			if ($vmid<1)
			{
				return $this->error('创建订单时写入数据库出错，请重新创建！');
				//return 0;
			}

			$newid=$vmid;
			if($vmid<100000){
				//2012-6-27 自动判断最大ID
				usleep(100);
				$vmrs=$DB->query("select max(vm_id) as vm_id from ".$this->Tbl['vm_order'],1);
				if($vmrs and $vmrs['vm_id']>200000) $newid=intval($vmrs['vm_id'])+1;
				else $newid=201001;
			}


			/*
			命名规则（v1.0新版）:
			#@vm_name值：@****_AAABBBBB(14位)
			#磁盘值：@****AAABBBBB_1.lv
			#虚拟网卡：默认同vm_name；额外的；netAAABBBBB_1;netAAABBBBB_2
			#@**** 主控识别码5位
			#    =wvabcdefghijkmnpqrstxy(210万)，w为未注册，自动生成W加四位随机码
			     ****: 18^4= 10万(6位十进制10万) 4->w
			AAA: 30^3=2.7万*100000=27亿（不足时用0补位） (替换不能用的字符lo=>xy)
			BBBBB:5位十进制，需要补位至5位
			
			$tag0=floor($newid/100000);
			if($tag0>0){
				$tag1=''.base_convert($tag0,10,30);
				$tag1=strval($tag1);
				$tag1=str_replace('l','x',$tag1);
				$tag1=str_replace('o','y',$tag1);
				if(strlen($tag1)<2) $tag1='_0'.$tag1;
				else if(strlen($tag1)<3) $tag1='_'.$tag1;
				//len==3 则无_
			}
			else{
				$tag1='_00';
			}

			$tag2=substr(strval($newid),-5);
			while(strlen($tag2)<5){
				$tag2='0'.$tag2;
				if(strlen($tag2)>=5) break;
			}
			$vmname=$this->sitetag.$tag1.$tag2;
			*/

			$vmname=$this->sitetag.'_'.$this->order_id2str($newid);
			
			//2012-10-24 更改状态为0（可用，更改前为2）
			$res=$DB->query("update ".$Tbl['vm_order']." set vm_stat=0,".($newid!=$vmid ? "vm_id=$newid,":"")."vm_name=".$DB->getsql($vmname)." where vm_id=$vmid");
			if($res){
				$saveData['vm_id']  =$newid; //更新后再覆盖
				$saveData['vm_name']=$vmname;
				if($newid!=$vmid) {
					//此操作执行时间可能会较长（比如需要几秒，而一般query只需要几十毫秒）
					$DB->query("OPTIMIZE TABLE ".$Tbl['vm_order']."");
				}
				$vmid=$newid;
				//
			}
			//由于可能连续发送请求，也许$newid会存在已经被占用情况，更新失败时，订单为不可用订单
			else {
				//2012-10-24 未成功更改时，订单不可用
				$DB->query("update ".$Tbl['vm_order']." set vm_stat=4,vm_deltime=UNIX_TIMESTAMP() where vm_id=$vmid");
				//return 0;
				return $this->error('创建订单时处理出错，请重新创建！');
			}

			$this->RS=$saveData;
			//从表
			//生成控制面板默认密码
			$passSet='abcdefghijkmnpqrstuvwxy23456789';
			$passStr=$passSet[rand(0,22)].$passSet[rand(0,30)].$passSet[rand(0,30)].$passSet[rand(0,30)];
			$passStr=$passStr.$passSet[rand(0,30)].$passSet[rand(0,30)].$passSet[rand(0,30)].$passSet[rand(0,30)];

			$cppass=md5($passStr);
			$this->DB->query("insert into ".$Tbl['vm_order_info']." (vm_id,userid,clientid,vm_diy_pass,vm_admin_user,vm_disk_sys,vm_login_stat,vm_login_password,vm_login_tmp ) values ($vmid,".intval($form['user_id']).",".intval($form['client_id']).",".$DB->getsql($form['vm_diy_pass']).",".$this->DB->getsql($admin_user).",".intval($this->osArr[$osid]['os_disk_sys']).",1,'$cppass','$passStr')");

			usleep(2000);
			//写入数据盘
			$sql1="insert into  ".$Tbl['vm_order_disks']." (vm_id,disk_num,server_id,store_id,disk_size,read_kbytes,read_iops,write_kbytes,write_iops) values ";
			
			$disk_1_read_iops=600+ floor($saveData['vm_cpulimit']/5) +floor ($saveData['vm_memory']/5);
			$disk_1_read_kbytes=floor($disk_1_read_iops/10);
			$disk_1_write_iops=floor($disk_1_read_iops*0.8);
			$disk_1_write_kbytes=floor($disk_1_read_kbytes/2);
			
			$disk_0_read_iops=$disk_1_read_iops;
			$disk_0_read_kbytes=$disk_1_read_kbytes;
			$disk_0_write_iops=floor($disk_1_write_iops*0.6);
			$disk_0_write_kbytes=floor($disk_1_write_kbytes*0.6);
			
			$sql1=$sql1." ($vmid,0,$serverid,$dir0,".intval($this->osArr[$osid]['os_disk_sys']).",".$disk_0_read_kbytes.",".$disk_0_read_iops.",".$disk_0_write_kbytes.",".$disk_0_write_iops.") ";
			$sql1=$sql1.",($vmid,1,$serverid,$dir1,".intval($saveData['vm_disk']).",".$disk_1_read_kbytes.",".$disk_1_read_iops.",".$disk_1_write_kbytes.",".$disk_1_write_iops.")";
			$DB->query($sql1);
			usleep(2000);
			
			//拨号模式写入相应信息
			if($form['vm_mode']==1){
				$this->DB->query("update ".$this->Tbl['set_internet_server']." set record_vm_id=".intval($this->RS['vm_id']).",record_use_date=NOW() where record_id=".intval($recordid));
				usleep(2000);
			}
			//IP状态
			if ($saveData['vm_iptype']){
				$this->DB->query("update ".$this->Tbl['set_ippool']." set ip_stat=3,vm_id=".intval($vmid)." where ip_addr=".$this->DB->getsql($form['vm_ip']));
				usleep(2000);
			}

			//统计服务器安装个数
			if($buymode==1 and isset($this->vmArr[$serverid])){
				$this->vmArr[$serverid]=$this->vmArr[$serverid]+1;
			}

			//更新存储(2015-9-10)
			if($res){
				$newsize=$saveData['vm_disk']+$this->osArr[$osid]['os_disk_sys'];
				$DB->query("update ".$this->Tbl['set_server_stores']." set store_uptime=NOW(),store_used=store_used+".intval($newsize)." where store_id=$dir0");
			}

			//安装命令
			$this->order_cmd($saveData,10);
			usleep(2000);
			//更新资源(只更新资源分配情况)
			$this->API->load_server(1);
			usleep(20000);
			if(!empty($remark)){
				$this->logkeep=1;
				$remark=str_replace("*",$vmid,$remark);
				$this->add_log($this->RS,'创建新订单',$remark.'有效期'.$enddate.'('.$saveData['vm_ip'].')',1);
				$this->logkeep=0;
				usleep(20000);
			}
			else if(!empty($bindipstr)){
				$this->add_log($this->RS,'绑定IP','创建独立IP订单,ip='.$bindipstr,1);
				usleep(20000);
			}

			$topid=($saveData['vm_isfree']==0?-1:-2);
			//更新
			$_date=date("Y-m-d H:i:s",time()-2*3600);
			$DB->query("update ".$this->Tbl['set_user_order']." set order_update=".$DB->getsql($_date)." where top_id=".$topid." and user_id=".intval($saveData['user_id']));
			usleep(2000);
			$t=time();
			$DB->query("update ".$this->Tbl['set_server']."  SET server_order_time=$t where server_id=".intval($serverid));
			
			$ret=Array();
			$ret['_error']='';
			$ret['vm_id']=$saveData['vm_id'];
			return $ret;
		}
		else
		{
			$this->msg=$this->DB->err_msg();
			//return -1;//未写入库，可重试
			return $this->error('创建订单出错，请重新创建！');
		}
	}
	//END order_add();




	//F2.1 提交续期
	//参数格式:
	//$form['user_id']=会员编号
	//$form['client_id']=代理客户编号，为空时则不会判断，本地查询（不是API调用）则不需要
	//$form['data'][]=Array("vm_id"=>"订单号","period"=>"时长数字","period_unit"=>"单位"); //单位：“D天”或“H小时”或“M分钟”

	//返回格式
	//$ret=Array();
	//$ret['_error']='错误信息，无错时为空，有错时后两个值为空';
	//$ret['data']=Array();
	//$ret['data'][]=Array('vm_id'=>'订单号','stat'=>'0正常/1状态不正常/2更改库出错/3无授权/4记录不存在/5参数period错误/6超过限定个数','_error'=>'是否有错误，如果为空间正常，不为空不正常');
	function orders_renew($form,$client_id=0){
		$vmArr=$form['data'];
		$vmIN='';
		foreach($vmArr as $arr){
			$vmIN=$vmIN.','.intval($arr['vm_id']);
		}
		$vmIN=substr($vmIN,1);
		if(strlen($vmIN)==0){
			$this->error("未设置可操作记录！");
		}
		$result=$this->DB->query("select vm_id,vm_stat,vm_name,vm_startdate,vm_enddate,client_id,user_id,server_id,vm_stoptype from ".$this->Tbl['vm_order']." where  vm_id in ($vmIN) and vm_stat<4 and vm_stat>=0",2);
		$resArr=Array();
		$sids='';
		foreach($result as $arr){
			$resArr[$arr['vm_id']]=$arr;
			$sids=$sids.','.intval($arr['server_id']);
		}
		//检查服务器是否关闭
		$serverSet=Array();
		if(strlen($sids)>1){
			$sids=substr($sids,1);
			$results=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_stat<3 and server_id IN ($sids)",2);
			foreach($results as $arr){
				$serverSet[$arr['server_id']]=1+abs($arr['server_stat']);
			}
		}

		$n=0;
		$ret=Array();
		$daySet=Array("天","小时","分钟");
		foreach($vmArr as $arr){
			usleep(10000);
			$n++;
			$vmid=intval($arr['vm_id']);
			if($n>50) {
				$ret[]=Array("vm_id"=>$vmid,"stat"=>6,'_error'=>'提交的订单超过限定个数');
				continue;
			}
			if(!isset($resArr[$vmid])){
				$ret[]=Array("vm_id"=>$vmid,"stat"=>4,'_error'=>'提交的订单不存在');
				continue;
			}

			$period=intval($arr['period']);
			$unit=intval($arr['period_unit']);
	
			if($unit=="D") $unit=0;
			else if($unit=="H") $unit=1;
			else if($unit=="M") $unit=2;
			$pstr=$period."".$daySet[$unit];
			if(!in_array($unit,array(0,1,2))) {
				$ret[]=Array("vm_id"=>$vmid,"stat"=>5,'_error'=>'时长单位不正确');
				continue;
			}
			if(empty($period) or $period<1){
				$ret[]=Array("vm_id"=>$vmid,"stat"=>5,'_error'=>'时长不正确');
				continue;
			}
			$row=$resArr[$vmid];
			$sid=intval($row['server_id']);
			if($this->userID>0 and $row['user_id']!=$this->userID){
				$ret[]=Array("vm_id"=>$vmid,"stat"=>3,'_error'=>'无权操作当前订单'.$row['user_id'].'!='.$this->userID);
				continue;
			}
			else if($client_id>0 and $row['client_id']!=$client_id){
				$ret[]=Array("vm_id"=>$vmid,"stat"=>3,'_error'=>'无权操作当前订单'.$row['client_id'].'!='.$client_id);
				continue;
			}
			else if($row['vm_stat']>1){
				$ret[]=Array("vm_id"=>$vmid,"stat"=>1,'_error'=>'当前订单关闭状态，不能操作');
				continue;
			}else if(!empty($arr['_error'])){
				$ret[]=Array("vm_id"=>$vmid,"stat"=>1,'_error'=>$arr['_error']);
				continue;
			}
			else if(!isset($serverSet[$sid])){
				$ret[]=Array("vm_id"=>$vmid,"stat"=>1,'_error'=>'订单所在服务器暂停中，不能操作');
				continue;
			}
			else{

				$olddate=$row['vm_enddate'];
				if($olddate<date('Y-m-d H:i:s')) $olddate=date('Y-m-d H:i:s',time()+120);

				$enddate=$this->date_add($olddate,$period,$unit);

				$rets=$this->DB->query("update ".$this->Tbl['vm_order']." set vm_enddate=".$this->DB->getsql($enddate)."  where vm_id=$vmid");
				if($rets){
					//###暂停的自动恢复
					if($row['vm_stoptype']==3 and $row['vm_stat']==1 and isset($serverSet[$sid]) and $serverSet[$sid]<3){
						usleep(10000);
						$row['vm_stat']=0;
						$this->DB->query("insert into ".$this->Tbl['vm_order_cmd']." (vm_id,cmd_type,cmd_adddate,server_id) values ($vmid,0,NOW(),".intval($row['server_id']).")");
						//更新服务器订单数(2017-3-24增加)
						$this->DB->query("update ".$this->Tbl['set_server']." set server_vmrun=server_vmrun+1 where server_id=".$sid);
					}

					$ret[]=Array("vm_id"=>$vmid,"stat"=>0,'_error'=>'','enddate'=>$enddate);
					$this->logkeep=1;
					//$this->add_log($row,"订单续期",($this->isAPI?"@代理API":"")."续期订单{$pstr}，到期时间从{$row['vm_enddate']}变为{$enddate}",1);
					usleep(10000);
				}
				else{
					$ret[]=Array("vm_id"=>$vmid,"stat"=>2,'_error'=>'不能更改数据库');
					//$str=$str."<data><vm_id>$vmid</vm_id><stat>2</stat></data>";
				}
				usleep(10000);
			}
		}
		return $ret;
		//$this->response($str);
	}


	//F2.2 查询订单可绑定IP
	function order_ip_query($vm_id,$isAdmin=0){

		$vm_id=intval($vm_id);
		if(empty($vm_id) and !empty($this->orderID)) $vm_id=$this->orderID;

		$RS=Array();//返回值包括产品信息
		if(empty($vm_id)) {
			return $RS;
		}

		$RS['_error']='';//错误信息（为空则无错误）
		$RS['_netname']='';//机房或网络名称
		$RS['_gateway']='';//网关
		$RS['_mask']='';//子网掩码
		$RS['_dns']='';//DNS
		$RS['_iplist']=Array();  //绑定的额外IP
		$RS['_ipprice']=Array(); //IP单价(为空则无法显示价格)，用于显示给用户看
		$RS['_ipcost']=Array(); //单个IP的总费用(订单剩余时间内)，即用户绑定IP需要扣的费用
		$RS['_ipselect']=Array(); //可额外添加的IP(用户默认显示5个，管理员显示30个)
		$RS['_ipnum']=Array(); //IP数

		$res=$this->get_order_info($vm_id);
		if(empty($res)) {
			return Array();
		}


		$RS=array_merge($res,$RS);//返回值包括产品信息
		$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_id=".intval($res['server_id']),1);

		$_period=ceil((strtotime($res['vm_enddate'])-strtotime($res['vm_startdate']))/24/3600);//购买的时长，使用此计算价格

		$netid=intval($RS['net_id']);
		$netSet=$this->DB->query("select * from ".$this->Tbl['set_network']." where net_id =$netid ",1);
		$net_type_id=$netSet['net_type_id'];
		$RS['_netname']=get_html($netSet['net_name']);
		if ($RS['vm_iptype']<1)
		{
			$gateway=$serverRS['server_gateway'];
			$mask=$serverRS['server_mask'];
		}
		else
		{
			$gateway=$serverRS['server_gateway_wan'];
			$mask=$serverRS['server_mask_wan'];
		}
		$RS['_gateway']=$gateway;//网关
		$RS['_mask']=$mask;//掩码
		$RS['_dns']=$serverRS['server_dns'];//DNS
		$RS['_dname']=$RS['vm_ip'];

		//2017-3-3增加
		if($netSet['net_ip_close'] and empty($isAdmin)){
			$RS['_error']='当前机房不提供独立IP地址!';
			return $RS;
		}

		if($serverRS['server_stat']>1){
			$RS['_error']='所在服务器暂停管理，不能绑定IP!';
			return $RS;
		}

		if($RS['_ipclose']==1 and empty($isAdmin)){
			$RS['_error']='当前产品不支持独立IP地址绑定!';
			return $RS;
		}

		if($res['vm_stat']>1){
			$RS['_error']='订单关闭或删除，不能绑定IP!';
			return $RS;
		}
		else if($res['vm_stat']==1){
			$RS['_error']='订单暂停中，不能绑定IP!';
			return $RS;
		}
		//2016-3-30 增加判断
		else if($res['vm_install_stat']<=1){
			$RS['_error']='订单尚未完成安装，请稍后再试!';
			return $RS;
		}
		//安装完成3分钟内不允许操作(2016-3-30)
		else if($res['vm_changedate']>date('Y-m-d H:i:s',time()-180)){
			$RS['_error']='订单安装启动中，请稍后再试!';
			return $RS;
		}

		$prices=$this->DB->query("select * from ".$this->Tbl['prod_price']." where price_type=19 and type_id={$net_type_id}  ",1);
		if($prices){
			$RS['_ipprice']=$prices;
		}
		else{
			$RS['_error']='未设置IP价格!';
			return $RS;
		}


		$result=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where vm_id =$vm_id ",2);
		$ipnum=count($result);
		$RS['_ipnum']=$ipnum;
		

		if($prices['division_size']>0 and $prices['division_price']>0  and $ipnum>=$prices['division_size']){
			$ipPrice=0+$prices['division_price'];
		}
		else{
			$ipPrice=0+$prices['base_price'];
		}
		$ipmax=10;
		if($prices['size_max']>0) $ipmax=$prices['size_max'];
		//
		if($ipPrice<=0){
			$RS['_error']='无法计算IP价格！';
			return $RS;
		}

		$period=ceil((strtotime($RS['vm_enddate'])-time())/3600);//剩余时间（小时）	
		if($period<=0) {
			$RS['_error']='订单已到期，不能升级!';
		}
		else if($period<6){
			$RS['_error']='剩余有效时间小于6小时，不能绑定IP地址';
			return $RS;
		}

		if(empty($this->queryPrice)){
			$this->query_price();
		}
		//已绑定IP列表
		foreach($result as $arr){
			$tmp=explode('.',$arr['ip_addr']);//$value['ip_addr'];
			$ip=$tmp[0].'.'.$tmp[1].'.'.$tmp[2].'.';
			$ipv=intval($tmp[3]);
			if($ipv<1 or $ipv>254) continue; //.0或.255 IP不允许使用
			if($RS['vm_ip']==$arr['ip_addr']) continue;
			$RS['_iplist'][]=Array('ip'=>$arr['ip_addr'],'netname'=>$RS['_netname'],'mask'=>$mask,'gateway'=>$gateway);
		}

		//计算价格和查询可用IP列表
		$period=ceil($period/24);//天(不足一天按一天算)
		$period_unit=0;

		$prodBuy=$this->DB->query("select * from ".$this->Tbl['prod_period']." where period_stat=0 and type_id={$RS['type_id']} and (startdate='0000-00-00 00:00:00' OR startdate<=NOW() and enddate>=NOW()) order by period_unit desc,period",2);

		$ipBuy=$this->DB->query("select * from ".$this->Tbl['prod_period']." where period_stat=0 and type_id={$net_type_id} and (startdate='0000-00-00 00:00:00' OR startdate<=NOW() and enddate>=NOW()) order by period_unit desc,period",2);
		
		$_ip_cost=$this->get_ip_price($ipPrice,$period,$period_unit,$_period,$ipBuy,$prodBuy);
		$RS['_ipcost']=number_format(round($_ip_cost,2),2,'.','');
		$iptag=substr($RS['vm_ip'],0,strrpos($RS['vm_ip'],".")+1);
		$iptag1=substr($serverRS['server_addr'],0,strrpos($serverRS['server_addr'],".")+1);
		//IP可用数
		if($this->isAdmin){
			//echo "|$iptag|select * from ".$this->Tbl['set_ippool']." where ip_stat=0 and net_id=$netid  and (ip_addr like '{$iptag}%' OR ip_addr like '{$iptag1}%' OR ip_addr like '{$iptag2}%') ORDER BY ip_addr <br />";
			//2016-11-14 管理员允许添加服务器IP或第二IP相同段的IP
			$limit=250;
			$sql="select * from ".$this->Tbl['set_ippool']." where ip_stat=0 and net_id=$netid  and ( ip_addr like ".$this->DB->getsql($iptag.'%')."";
			$iptag1=substr($serverRS['server_addr'],0,strrpos($serverRS['server_addr'],".")+1);
			if($iptag!=$iptag1){
				$sql=$sql." OR ip_addr like '".$this->DB->getsql($iptag1.'%')."' ";
			}
			if(!empty($serverRS['server_addr_second'])){
				$iptag2=substr($serverRS['server_addr_second'],0,strrpos($serverRS['server_addr_second'],".")+1);
				$sql=$sql." OR ip_addr like '".$this->DB->getsql($iptag2.'%')."' ";
			}
			$sql=$sql.") ORDER BY ip_addr";
			//echo $sql;
			$result=$this->DB->query($sql,2);
		}
		else {
			$limit=10;
			if($limit<1) {
				$result=array();
			}
			else{
				$result=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where ip_stat=0 and ip_addr like '{$iptag}%' and net_id=$netid ORDER BY rand()",2);//同一个段最多250个左右，因此可以用ORDER BY rand()
			}
		}

		$n=0;
		$serverip=$serverRS['server_addr'];
		$lanstart=intval($serverRS['server_lan_start']);
		$lanend=intval($serverRS['server_lan_end']);
		if($lanstart>254) $lanstart=254;
		if($lanend>254) $lanend=254;
		foreach($result as $arr){
			$tmp=explode('.',$arr['ip_addr']);
			$ip=$tmp[0].'.'.$tmp[1].'.'.$tmp[2].'.';
			$ipv=intval($tmp[3]);
			if($ipv<1 or $ipv>254) continue; //不允许使用的.1和.254 IP
			//设置了子网的情况
			if($lanend>0 and $lanend>$lanstart){
				if($ipv<$lanstart) continue;
				if($ipv>$lanend) continue;
			}
			$RS['_ipselect'][]=Array('ip'=>$arr['ip_addr']);
			$n++;
			if($n>=$limit) break;
		}
		return $RS;
	}

	//F2.3 绑定新IP
	//管理或用户平台均可使用
	//$vmAPI为子机接口处理对象；$ipaddr为用户选择的IP地址
	//返回$ret['_error']为空则正常
	function order_ip_bind($RS,$ipaddr){
		
		$ret=Array();
		$ret['_error']='';
		$actname='绑定IP';
		$vm_id=intval($RS['vm_id']);
		$res=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where ip_addr=".$this->DB->getsql($ipaddr)." and ip_stat<2",1);
		if (empty($res))
		{
			return $this->error('IP地址不符合要求或已经被占用！');
		}
		
		if(empty($RS['vm_iptype'])){
			$sql="update ".$this->Tbl['vm_order']." set vm_iptype=1,vm_ip=".$this->DB->getsql($ipaddr)." where vm_id=".$vm_id;
			$ret=$this->DB->query($sql);
			if(!$ret){
				return $this->error('更改订单数据库出错！');
			}
		}

		$sql="update ".$this->Tbl['set_ippool']." set ip_stat=3,vm_id=".$vm_id." where ip_addr=".$this->DB->getsql($ipaddr);
		$ret=$this->DB->query($sql);
		if(!$ret){
			if(empty($RS['vm_iptype'])){
				$sql="update ".$this->Tbl['vm_order']." set vm_iptype=0,vm_ip=".$this->DB->getsql($RS['vm_ip'])." where vm_id=".$vm_id;
				$ret=$this->DB->query($sql);
			}
			return $this->error('更改IP数据库出错');
		}
		
		$tmpRS = $RS;
		
		$tmpRS['vm_ip']=$ipaddr;//主IP改值
		$tmpRS['vm_iptype']=1;
		
		if(empty($this->sentcmd)) return $ret; //供测试，不发命令到服务器

		$this->API=new ServerMOD($this->DB,$RS['server_id'],Array(),0);

		if(empty($RS['vm_iptype'])){
			$tmp=explode(':',$this->API->cmd_ip);
			$_api_host=$tmp[0];
			$_api_port=$tmpRS['vm_port'].'3';
		}
		else{
			$_api_host=$tmpRS['vm_ip'];
			$_api_port='8433';
		}
		//绑定IP后都是独立IP了(2015-8-26)
		//$RS['vm_iptype']=1;//必须放在$_api_host之后
		$result=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where ip_stat=3 and vm_id=".intval($vm_id),2);
		
		
		
		//发宿主机命令
		$rets=$this->API->vm_bindip($tmpRS,$result);
		//$rets['result']=0;
		if($rets['result']>0){
			//还原设置
			$sql="update ".$this->Tbl['set_ippool']." set ip_stat=0,vm_id=0 where vm_id=".$vm_id." and ip_addr=".$this->DB->getsql($ipaddr);
			$ret=$DB->query($sql);
			if(empty($RS['vm_iptype'])){
				$sql="update ".$this->Tbl['vm_order']." set vm_iptype=0,vm_ip=".$this->DB->getsql($RS['vm_ip'])." where vm_id=".$vm_id;
				$ret=$this->DB->query($sql);
			}
			$this->add_log($RS,$actname,'宿主机接口设置IP报错:'.$rets['msg'],1,(0-$login_userid));
			
			return $this->error('宿主机接口设置IP报错:'.$rets['msg']);
		}

		$portset='TCP-8433;TCP-21;TCP-'.$tmpRS['vm_admin_port'];
		sleep(1);
		$result=$this->DB->query("select * from ".$this->Tbl['vm_port']." WHERE port_stat<2 and vm_id=".intval($vm_id),2);
		foreach($result as $arr){
			if(!empty($arr['port_iptype']))
				$portset=$portset.';'.$arr['port_type'].'-'.$arr['port_name'];
		}
		
		$rets=$this->API->port_set($tmpRS,'reloadports',$portset);
	

		//发子机命令
		sleep(1);
		$vmAPI=new class_vm_api($_api_host,$_api_port,$tmpRS['vm_api_key']);
		$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_id=".intval($tmpRS['server_id']),1);
		$cmd_str=$vmAPI->bind_ip($serverRS,$tmpRS,$result);
		//
		$ret=$vmAPI->sent($cmd_str);
		//echo $cmd_str;echo $vmAPI->retXML;exit;
		if($ret['result']>0){
			
			//出错不用还原设置（因子机中可能无对应接口）
			$this->add_log($RS,$actname,'云主机接口报错：'.$ret['msg']."($_api_host:$_api_port)",1,(0-$login_userid));
			//return $this->error('云主机接口报错：'.$ret['msg']);
		}
		$ret=Array();
		$ret['_error']='';
		return $ret;
	}

	//F2.5 查询推荐配置升级可用产品（同时返回原价和需要补的差价）
	//返回值$ret=Array();
	//$ret[0]=Array();//是原产品的配置参数
	//$ret[新ID]=Array();//可用推荐配置参数
	function order_upconf_query($vm_id){
		
		$vm_id=intval($vm_id);
		if(empty($vm_id) and !empty($this->orderID)) $vm_id=$this->orderID;
		if(empty($vm_id)) return Array();
		$RS=Array();
		$res=$this->get_order_info($vm_id);
		
		if(!$res){
			return $res;
		}
		else if($res['vm_stat']>1){
			$res['_error']='订单关闭或删除，不能绑定IP!';
		}
		else if($res['vm_stat']==1){
			$res['_error']='订单暂停中，不能绑定IP!';
		}

		$_period=ceil((strtotime($res['vm_enddate'])-strtotime($res['vm_startdate']))/24/3600);//购买的时长，使用此计算价格
		$_day=$res['_daynum'];//剩余时长（精确到0.1天）
		if($_day<=0) {
			$res['_error']='订单已到期，不能升级!';
		}
		else if($_day<0.25) {
			$res['_error']='订单到期时间小于6小时，不能升级!';
		}



		if(!empty($res['_error'])){
			$ret=Array();
			$ret[0]=$res;
			return $ret;
		}

		$typeid=intval($res['type_id']);
		$sql='';
		//if($this->isAPI)  $sql=$sql." and type_stat<3 and type_stat!=1";
		//else $sql=$sql." and type_stat<2";
		$typeRS=$this->DB->query("select * from ".$this->Tbl['set_prod_type']." where type_sort=0 and type_id=$typeid  and type_stat<3 ",1);
		if(empty($typeRS)){
			$res['_error']='所在分类关闭，不能升级!';
			$ret[0]=$res;
			return $ret;
		}
		if($_day>365){
			$_period=365;
		}
		/*
		//有效时间不足购买时间三分之一的
		else if($_period>$_day*3) {
			if($_day>121) $_period=365;
			else if($_day>10) $_period=30;
			else $_period=$_day;
		}
		else if($_period>30){
			$_period=30;
		}
		*/
		else if($_period>365){
			$_period=365;
		}

		//价格设置
		$priceConf=$this->DB->query("select period,period_unit,price,prod_id,net_type  from ".$this->Tbl['prod_conf_price']." where 1  order by period_unit desc,period,price",2);
		//产品设置
		$sql="";
		if(!$this->isAdmin){
			if($this->isAPI) $sql=$sql." and prod_api>0";
			else $sql=$sql." and prod_api<2";
		}
		$prodSet=Array();
		$results=$this->DB->query("select * from ".$this->Tbl['prod_conf']." where type_id=".intval($typeid)." and vm_iptype=".intval($res['vm_iptype'])." and prod_stat<2 ".$sql,2);
		foreach($results as $arr){
			$prodSet[$arr['prod_id']]=$arr;
		}

		$this->diskArr=Array();
		$result=$this->DB->query("select * from ".$this->Tbl['vm_order_disks']." where vm_id=$vm_id",2);
		$disk_size=0;
		foreach($result as $arr){
			$this->diskArr[$arr['disk_num']]=$arr;
			if($arr['disk_num']>0){
				$disk_size=$disk_size+$arr['disk_size'];
			}
		}

		if(empty($this->queryPrice)){
			$this->query_price();
		}
		$RS['_price']=0;//格式
		$RS['_period']=$_period;//计费时长
		$RS['_daynum']=$_day;//剩余时长(天)
		$RS['_error']='';
		
		$RS=array_merge($res,$RS);
		
		$prodid=intval($RS['prod_id']);
		$netid=intval($RS['net_id']);
		$nettype=$this->netArr[$netid]['net_type_id'];
		$osid=intval($RS['os_id']);
		$memory_min=$this->osArr[$osid]['os_memory'];
		if($this->netArr[$netid]['net_domain_close']>1){
			$domain_min=-1;
		}
		else if($this->netArr[$netid]['net_domain_close']==1 and empty($this->isAdmin)){
			$domain_min=-1; //选择时不受限制(提交后强行将域名数改为0)
		}
		else{
			$this->get_domain($vm_id);
			$domain_min=intval($this->msg);
			$this->msg='';
		}

		//特殊带宽设置
		$result=$this->DB->query("select * from ".$this->Tbl['prod_conf_set']." where net_id=$netid",2);
		$setArr=Array();
		foreach($result as $arr){
			$pid=$arr['prod_id'];
			$setArr[$pid]=$arr;
		}
		$RS['_sys_close']=0;
		$RS['_diy_close']=0;
		
		if($prodSet[$prodid]){
			$prods=$prodSet[$prodid];
			if(!empty($setArr[$prodid])){
				$prods['vm_bandwidth_out']=$setArr[$prodid]['vm_bandwidth_out'];
				$prods['vm_bandwidth_in']=$setArr[$prodid]['vm_bandwidth_in'];
				$prods['vm_domain_limit']=$setArr[$prodid]['vm_domain_limit'];
			}
			//$RS['_confname']=get_html($prods['prod_name'])."&nbsp;";
			if($RS['vm_disk']>$prods['vm_disk_data']
				or $RS['vm_cpulimit']>$prods['vm_cpulimit']
				or $RS['vm_memory']>$prods['vm_memory']
				or $RS['vm_bandwidth_out']>$prods['vm_bandwidth_out']
				or $RS['vm_bandwidth_in']>$prods['vm_bandwidth_in']
			){
				$RS['_confname']='';
				$prodid=0;
			}
			else{
				$RS['_sys_close']=$prods['prod_sys_close'];
				$RS['_diy_close']=$prods['prod_diy_close'];
			}
		}
		else{
			$RS['_confname']='';
			$prodid=0;
		}
		
		$price0=0;
		if($prodid>0){
			$price0=$this->get_conf_price($priceConf,$prodid,$nettype,$_period,$_day);
			//echo "[EXIT]";exit;
		}
		//DIY
		else {
			$ret=$this->get_diy_price($_day,0,$RS);
			$price0=array_sum($ret);
			$price0=number_format(round($price0,2),2,'.','');
			if($price0<=0){
				$RS['_error']='无法计算价格';
			}
		}
		
		$this->API=new ServerMOD($this->DB,$RS['server_id'],Array(),$this->userID);
		
		if(empty($this->sentcmd)){
			$serverRS=$this->API->serverSet;
		}
		else if($this->API->serverSet['server_update']<date('Y-m-d H:i:s')-90){
			$this->API->load_server(2);
			$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_id=".intval($RS['server_id']),1);
		}
		else{
			$serverRS=$this->API->serverSet;
		}

		//可用推荐配置
		$prodArr=Array();
		$RS['_price']=$price0;
		$RS['_domain']=$domain_min;
		$RS['_memory']=$memory_min;
		$RS['_port']=0;
		$RS['_nettype']=$nettype;
		$prodArr[0]=$RS;
		foreach($prodSet as $arr){
			if($arr['prod_id']==$prodid) continue;
			if(empty($arr['prod_id'])) continue;
			if(empty($this->isAdmin) and $arr['prod_stat']>1) continue;//不允许使用的

			//指定了操作系统
			if($arr['os_id']>0 and $res['os_id']!=$arr['os_id']) continue;
			//指定了网络
			if($arr['net_id']>0 and $res['net_id']!=$arr['net_id']) continue;
			//硬盘不能向下降
			if($arr['vm_disk_data']<$res['vm_disk']){
				continue;
			}
			if($memory_min>$arr['vm_memory']) continue;
			if($domain_min>$arr['vm_domain_limit']) continue;
			if($domain_min==-1) $arr['vm_domain_limit']=0;

			$pid=$arr['prod_id'];
			$arr['_error']='';
			$arr['_price']=0;//需要补差价
			//检查资源是否够用（暂不处理硬盘）
			if($serverRS['server_memory_max']<$arr['vm_memory']-$RS['vm_memory']) $arr['_error']='当前服务器资源不足';
			if($serverRS['server_cpu_max']<$arr['vm_cpulimit']-$RS['vm_cpulimit']) $arr['_error']='当前服务器资源不足';
			if($serverRS['server_cpu_cores']<$arr['vm_cpunum']) $arr['_error']='当前服务器资源不足';
			//根据网络设置对应带宽
			if(isset($setArr[$pid])){
				$arr['vm_bandwidth_out']=$setArr[$pid]['vm_bandwidth_out'];
				$arr['vm_bandwidth_in'] =$setArr[$pid]['vm_bandwidth_in'];
				$arr['vm_domain_limit'] =$setArr[$pid]['vm_domain_limit'];
			}

			if(!empty($arr['_error'])){
				$prodArr[$pid]=$arr;
				continue;
			}


			//判断价格
			//推荐配置价格
			$price1=0;
			if($prodid>0){
				$price1=$this->get_conf_price($priceConf,$pid,$nettype,$_period,$_day);
				
			}
			//DIY价格
			if($price1==0){
				$arr['vm_disk']=$arr['vm_disk_data'];
				$arr['type_id']=intval($RS['type_id']);
				$arr['net_id']=intval($RS['net_id']);//必须
				$ret=$this->get_diy_price($_day,0,$arr);
				$price1=array_sum($ret);
				$price1=number_format(round($price1,2),2,'.','');
				if($price1<=0){
					$arr['_error']='无法计算价格';
				}
				#$arr['_price1']=$price1;
				#$arr['_priceSet']=$ret;
			}
			//差价
			if($price1!=0){
				//echo "$price1-$price0<br />";
				$arr['_price']=number_format(($price1-$price0),2,'.','');
				if($arr['_price']<=0 and empty($this->isAdmin)){
					continue;
				}
			}

			$prodArr[$pid]=$arr;
		}

		return $prodArr;

	}



	//F2.7 #查询订单DIY升级可用资源及价格
	//$ret[0]=Array();//是原产品的配置参数
	//$ret[硬件标识]=Array();//硬件可用配置及应被差价
	function order_updiy_query($vm_id){

		$vm_id=intval($vm_id);
		if(empty($vm_id) and !empty($this->orderID)) $vm_id=$this->orderID;
		if(empty($vm_id)) return Array();
		$RS=Array();
		$res=$this->get_order_info($vm_id);
		if(!$res){
			return $res;
		}
		else if($res['vm_stat']>1){
			$res['_error']='订单关闭或删除，不能绑定IP!';
		}
		else if($res['vm_stat']==1){
			$res['_error']='订单暂停中，不能绑定IP!';
		}


		$_period=ceil((strtotime($res['vm_enddate'])-strtotime($res['vm_startdate']))/24/3600);//购买的时长，使用此计算价格
		$_day=$res['_daynum'];
		if($_day<=0) {
			$res['_error']='订单已到期，不能升级!';
		}
		else if($_day<0.25) {
			$res['_error']='订单到期时间小于6小时，不能升级!';
		}


		if(!empty($res['_error'])){
			$ret=Array();
			$ret[0]=$res;
			return $ret;
		}

		if($_day>365){
			$_period=$_day;
		}
		//有效时间不足购买时间三分之一的
		else if($_period>$_day*3) {
			if($_day>121) $_period=365;
			else if($_day>10) $_period=30;
			else $_period=$_day;
		}
		else if($_period>365){
			$_period=365;
		}
		else if($_period>30){
			$_period=30;
		}

		$RS['_period']=$_period;//计费时长
		$RS['_rate']=1;
		$RS['_error']='';
		$RS=array_merge($res,$RS);

		$resSet=Array();
		//端口不计费，管理员可任意修改个数。用户若要增加，需要联系管理员修改。IP则单独绑定，单独计费。
		$resSet[]=Array('内存',$RS['vm_memory'],1,0,'GB');//格式Array('名称','值','对应price_type','是否允许价格为空')
		$resSet[]=Array('CPU核心',$RS['vm_cpunum'],2,0,'个');
		$resSet[]=Array('CPU频率',$RS['vm_cpulimit'],3,0,'GHz');
		$resSet[]=Array('硬盘',$RS['vm_disk'],21,0,'GB');
		$resSet[]=Array('域名白名单',$RS['vm_domain_limit'],4,1,'个');
		$resSet[]=Array('带宽出',$RS['vm_bandwidth_out'],11,0,'Mbps');
		$resSet[]=Array('带宽入',$RS['vm_bandwidth_in'],10,0,'Mbps');

		$this->diskArr=Array();
		$result=$this->DB->query("select * from ".$this->Tbl['vm_order_disks']." where vm_id=$vm_id",2);
		$disk_size=0;
		foreach($result as $arr){
			$this->diskArr[$arr['disk_num']]=$arr;
			if($arr['disk_num']>0){
				$disk_size=$disk_size+$arr['disk_size'];
			}
		}

		if(empty($this->queryPrice)){
			$this->query_price();
		}

		$RS['_sys_close']=0;
		$RS['_diy_close']=0;
		if($RS['prod_id']>0){
			$prods=$this->DB->query("select * from ".$this->Tbl['prod_conf']." where prod_id=".intval($RS['prod_id'])."  and prod_stat<3",1);
			if($prods){
				$RS['_sys_close']=$prods['prod_sys_close'];
				$RS['_diy_close']=$prods['prod_diy_close'];
			}
		}
		$osid=intval($RS['os_id']);
		$memory_min=$this->osArr[$osid]['os_memory'];
		$netid=intval($RS['net_id']);
		$nettype=$this->netArr[$netid]['net_type_id'];
		$typeid=$RS['type_id'];
		if($this->netArr[$netid]['net_domain_close']>1){
			$domain_min=-1;
		}
		else if($this->netArr[$netid]['net_domain_close']==1 and empty($this->isAdmin)){
			$domain_min=-1;
		}
		else{
			$this->get_domain($vm_id);
			$domain_min=intval($this->msg);
			$this->msg='';
		}

		$this->API=new ServerMOD($this->DB,$RS['server_id'],Array(),$this->userID);
		if(empty($this->sentcmd)){
			$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_id=".intval($RS['server_id']),1);
		}
		else if($this->API->serverSet['server_update']<date('Y-m-d H:i:s')-90){
			$this->API->load_server(2);
			$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_id=".intval($RS['server_id']),1);
		}
		else{
			$serverRS=$this->API->serverSet;
		}

		//计算每天计费倍率
		$_rate=1;//默认值
		foreach($this->periodSet as $arr){
			if($arr['type_id']!=$typeid or $arr['period_unit']!=0) continue;
			if($_period>=$arr['period']){
				$_rate=$arr['period_rate']/$arr['period'];
			}
		}
		//剩余时长的计费倍率
		$_rate=round($_rate*$_day,4);
		$RS['_rate']=$_rate;//计费率
		$RS['_domain']=$domain_min; //已经使用域名个数，-1时表示不支持
		$RS['_memory']=$memory_min; //操作系统最小内存限制，为-1时表示不支持DIY时选择域名
		$RS['_port']=0;   //已经使用端口个数，为-1时表示不支持DIY时选择端口

		//计算可有规格和补差价数
		$ret=Array();
		$ret[0]=$RS;
		foreach($resSet as $row){
			$ptype=intval($row[2]);
			if($ptype==11 or $ptype==10) $_type=$nettype;
			else $_type=$typeid;
			if(!isset($this->priceSet[$_type][$ptype])){
				$ret[$ptype]=Array();
				continue;
			}
			$res=$this->priceSet[$_type][$ptype];
			$value0=$row[1];
			$tmp=$this->get_day_price($ptype,$value0,$res);
			$price0=round($tmp*$_rate,3);//原来的价值

			//新的规格
			$sets=$this->get_res_select($res);
			$setArr=Array();
			foreach($sets as $value){
				if($value0==$value) continue;//原有值跳过

				//检查资源是否够用
				if($ptype==1 and $serverRS['server_memory_max']<$value-$RS['vm_memory']) continue;
				if($ptype==2 and $serverRS['server_cpu_cores']<$value) continue;
				if($ptype==3 and $serverRS['server_cpu_max']<$value-$RS['vm_cpulimit']) continue;

				if($ptype==1 and $value<$memory_min) continue;
				if($ptype==4 and $value<$domain_min) continue;
				if($ptype==4 and $domain_min==-1) continue;
				//硬盘（未完全处理）
				if($ptype==21 and $value<$value0) continue;

				//计算价格
				$tag=''.$value;
				$tmp=$this->get_day_price($ptype,$value,$res);
				$price1=round($tmp*$_rate,3);//新的价值
				$price2=$price1-$price0;
				$view=$value;
				if($ptype==1) $view=round($value/getConfig('_mem_unit'),2);
				if($ptype==3) $view=round($value/1000,2);
				$setArr[$tag]=Array("value"=>$value,"view"=>$view.$row[4],"price"=>$price2);
			}
			$ret[$ptype]=$setArr;
		}
		return $ret;
	}

	//F2.7 根据提交的数据，对比需要升级的项目
	//错误信息在$this->msg中
	function order_upgrade_check($RS,$form)
	{
		$serverRS=$this->API->serverSet;
		$retArr=Array();
		$memory_min=$RS['_memory'];
		$domain_min=$RS['_domain'];
		$port_min  =$RS['_port'];
		$this->msg='';
		$retArr['__api']=0;//是否需要发API命令
		$retArr['__msg']='';//升级备注
		if(empty($RS['vm_name'])){
			$this->msg='订单有错（订单名称为空）';
			return $retArr;
		}
		
		$serverid=intval($RS['server_id']);
		$result=$this->DB->query("select * from ".$this->Tbl['set_server']." where  server_stat>1  and server_id=".$serverid,1);
		if(!empty($result)){
			$this->msg='所在服务器暂停管理！';
			return $retArr;
		}
		
		$cpunum=$RS['vm_cpunum'];
		if(!empty($form['vm_cpunum']) and $form['vm_cpunum']!=$RS['vm_cpunum']){
			$form['vm_cpunum']=intval($form['vm_cpunum']);
			if($form['vm_cpunum']<1){
				$this->msg='CPU核心数不正确';
			}
			else if($form['vm_cpunum']>$serverRS['server_cpu_cores']){
				$this->msg='CPU核心数大于服务器允许值';
			}
			$retArr['vm_cpunum']=$form['vm_cpunum'];
			$retArr['__api']=1;
			$cpunum=$form['vm_cpunum'];
			$retArr['__msg']=$retArr['__msg'].";core={$RS['vm_cpunum']}->{$form['vm_cpunum']}";
		}
		//echo '<pre>';print_r($form); echo $retArr['vm_cpunum']; exit;
		
		if(!empty($form['vm_cpulimit']) and $form['vm_cpulimit']!=$RS['vm_cpulimit']){
			$form['vm_cpulimit']=intval($form['vm_cpulimit']);
			if($cpunum==1) $cpumin=200;
			else $cpumin=($cpunum-1)*1000;//最小值为核心数*1000MHz
			
			if($form['vm_cpulimit']<200){
				$this->msg='CPU的MHz值不正确';
			}
			else if($form['vm_cpulimit']-$RS['vm_cpulimit']>$serverRS['server_cpu_max']){
				$this->msg='CPU的MHz值不能大于服务器允许值';
			}
			else if($form['vm_cpulimit']>$cpunum*getConfig('_core_to_hz_max')){
				$this->msg='CPU的MHz值不能大于核心数允许最大值('.($cpunum*getConfig('_core_to_hz_max')).'MHz)';
			}
			else if($form['vm_cpulimit']<$cpumin){
				$this->msg='CPU的MHz值不能小于核心数允许最小值('.($cpumin).'MHz)';
			}
			$retArr['vm_cpulimit']=$form['vm_cpulimit'];
			$retArr['__api']=1;
			$retArr['__msg']=$retArr['__msg'].";CPU={$RS['vm_cpulimit']}->{$form['vm_cpulimit']}";
		}

		//内存
		if(!empty($form['vm_memory']) and $form['vm_memory']!=$RS['vm_memory']){
			$form['vm_memory']=intval($form['vm_memory']);

			if($form['vm_memory']<100){
				$this->msg='内存值'.$form['vm_memory'].'不正确';
			}
			else if($form['vm_memory']-$RS['vm_memory']>$serverRS['server_memory_max']){
				$this->msg='内存值不能大于服务器允许值';
			}
			else if($form['vm_memory']<$memory_min){
				$this->msg='内存值不能小操作系统允许值('.round($memory_min).'MB)';
			}
			$retArr['vm_memory']=$form['vm_memory'];
			$retArr['__api']=1;
			$retArr['__msg']=$retArr['__msg'].";memory={$RS['vm_memory']}->{$form['vm_memory']}";
		}
		
		//硬盘(不能减小)
		foreach($form as $_name =>$_value){
			if(preg_match('/^vm_disk_([0-9]+)/',$_name,$det)){
				$num=intval($det[1]);
				$storeid=$this->diskArr[$num]['store_id'];
				$tmp='vm_disk_'.$num;
				if(empty($form[$tmp])) continue;//未设置
				if($form[$tmp]>0 and $form[$tmp]==$this->diskArr[$num]['disk_size']) continue;//相同

				if($form[$tmp]<$this->diskArr[$num]['disk_size']) {
					$this->msg="硬盘{$num}不能小于原来的值！";
				}
				/*
				else if(!isset($S_RES['disk'][$storeid])) {
					$this->msg="硬盘{$num}无可用资源，不能升级！";
				}
				else if($num==0 and $form[$tmp]>DISK_SYS_MAX){
					$this->msg="硬盘{$num}不能超过".DISK_SYS_MAX."GB！";
				}
				else if($form[$tmp]-$this->diskArr[$num]['disk_size']>$S_RES['disk'][$storeid][0]){
					$this->msg="硬盘{$num}值不能大于服务器允许值！";
				}
				*/
				$retArr[$tmp]=$form[$tmp];
				$retArr['__api']=1;
				$retArr['__msg']=$retArr['__msg'].";disk=".$this->diskArr[$num]['disk_size']."->".$form[$tmp];
			}
		}

		//带宽入
		if(!empty($form['vm_bandwidth_in']) and $form['vm_bandwidth_in']!=$RS['vm_bandwidth_in']){
			$form['vm_bandwidth_in']=intval($form['vm_bandwidth_in']);

			if($form['vm_bandwidth_in']<1){
				$this->msg='入网带宽不能小于1';
			}
			$retArr['vm_bandwidth_in']=$form['vm_bandwidth_in'];
			$retArr['__api']=1;
			$retArr['__msg']=$retArr['__msg'].";bwin={$RS['vm_bandwidth_in']}->{$form['vm_bandwidth_in']}";
		}

		//带宽出
		if(!empty($form['vm_bandwidth_out']) and $form['vm_bandwidth_out']!=$RS['vm_bandwidth_out']){
			$form['vm_bandwidth_out']=intval($form['vm_bandwidth_out']);

			if($form['vm_bandwidth_out']<1){
				$this->msg='出网带宽不能小于1';
			}
			$retArr['vm_bandwidth_out']=$form['vm_bandwidth_out'];
			$retArr['__api']=1;
			$retArr['__msg']=$retArr['__msg'].";bwout={$RS['vm_bandwidth_out']}->{$form['vm_bandwidth_out']}";
		}

		//连接数
		if(!empty($form['vm_links']) and $form['vm_links']>0 and $form['vm_links']!=$RS['vm_links']){
			$form['vm_links']=intval($form['vm_links']);
			if($form['vm_links']<20){
				$this->msg='连接数值不能小于20！';
			}
			else if($form['vm_links']>20000){
				$this->msg='[连接数]不能大于20000！';
			}
			$retArr['vm_links']=$form['vm_links'];
			$retArr['__api']=1;
			$retArr['__msg']=$retArr['__msg'].";links={$RS['vm_links']}->{$form['vm_links']}";
		}

		//端口数
		if($RS['vm_iptype']==0 and isset($form['vm_port_limit']) and $form['vm_port_limit']!=$RS['vm_port_limit'])
		{
			if($form['vm_port_limit']<$port_min){
				$this->msg='白名单数不能小于当前订单已经申请白数单数量';
			}
			else if($form['vm_port_limit']<0){
				$this->msg='白名单数不正确';
			}
			$retArr['vm_port_limit']=$form['vm_port_limit'];
			$retArr['__msg']=$retArr['__msg'].";port={$RS['vm_port_limit']}->{$form['vm_port_limit']}";

		}

		//白名单数
		if(!empty($form['vm_domain_limit']) and $form['vm_domain_limit']!=$RS['vm_domain_limit']){
			$form['vm_domain_limit']=intval($form['vm_domain_limit']);
			if($form['vm_domain_limit']>1 and $domain_min==-1){
				$form['vm_domain_limit']=0;
				//$this->msg='当前机房不支持白名单';
			}
			else if($form['vm_domain_limit']<$domain_min){
				$this->msg='白名单数不能小于当前订单已经申请白数单数量';
			}
			else if($form['vm_domain_limit']<0){
				$this->msg='白名单数不正确';
			}
			$retArr['vm_domain_limit']=$form['vm_domain_limit'];
			$retArr['__msg']=$retArr['__msg'].";domain={$RS['vm_domain_limit']}->{$form['vm_domain_limit']}";
		}

		if(strlen($retArr['__msg'])>0) $retArr['__msg']=substr($retArr['__msg'],1);
		return $retArr;

	}


	//F2.8 执行升级操作（数据库处理）
	//未处理vm_order表vm_disk缓存
	function order_upgrade($RS,$upSet,$remark='')
	{
		if($upSet['__api']==1 and $this->sentcmd){
			if($RS['vm_ismod']){
				$ret=$this->API->mod_upgrade_vm($RS,$upSet);
			}else{
				$ret=$this->API->upgrade($RS,$upSet);
			}
		}
		else {
			$ret=Array('result'=>0);
		}
		//发命令不成功
		if($ret['result']>0){
			return $this->error($ret['msg']);
			//return 0;
		}
		$sql="";
		$disk_up=0;
		$disk_size=0;
		$disk_arr=Array();
		foreach($upSet as $_f =>$_v){
			if($_f=='__api' or $_f=='__msg' or $_f=='_error') continue;
			if(preg_match('/^vm_disk_([0-9]+)/',$_f,$det)){
				$num=intval($det[1]);
				$disk_arr[]=Array($num,$_v);
				$disk_up=1;
				if($num!=0) $disk_size=$disk_size+$_v;
				continue;
			}
			$sql=$sql.",$_f=".$this->DB->getsql($_v);
		}
		if($disk_size>0) $sql=$sql.",vm_disk=".$this->DB->getsql($disk_size,1);

		if(!empty($sql)){
			$sql="update ".$this->Tbl['vm_order']." set os_id=os_id".$sql;
			$sql=$sql." where vm_id=".intval($RS['vm_id']);
			$res=$this->DB->query($sql);
			if(!$res){
				return 0;
			}
		}
		
		if($disk_up==1){
			foreach($disk_arr as $arr){
				$sql="update ".$this->Tbl['vm_order_disks']." set disk_size=".intval($arr[1])."
						where disk_num=".intval($arr[0])." and vm_id=".intval($RS['vm_id']);
				$res=$this->DB->query($sql);
				if(!$res){
					return 0;
				}
			}
			$this->DB->query("update ".$this->Tbl['vm_order_info']." set vm_disk_uptime=".(0+time())." where vm_id=".intval($RS['vm_id']));
		}
		
		//CPU值、内存发送资源限制
		$iopsArr=array();
		if($upSet['vm_cpulimit']>0 or $upSet['vm_memory']>0){
			
			$cpulimit=$RS['vm_cpulimit'];
			if($upSet['vm_cpulimit']>0) $cpulimit=$upSet['vm_cpulimit'];
			
			$memory=$RS['vm_memory'];
			if($upSet['vm_memory']>0) $memory=$upSet['vm_memory'];
			
			$iopsArr[1]['read_iops']=600+ floor($cpulimit/5) +floor ($memory/5);
			$iopsArr[1]['read_kbytes']=floor($iopsArr[1]['read_iops']/10);
			$iopsArr[1]['write_iops']=floor($iopsArr[1]['read_iops']*0.8);
			$iopsArr[1]['write_kbytes']=floor($iopsArr[1]['read_kbytes']/2);
			
			$iopsArr[0]['read_iops']=$iopsArr[1]['read_iops'];
			$iopsArr[0]['read_kbytes']=$iopsArr[1]['read_kbytes'];
			$iopsArr[0]['write_iops']=floor($iopsArr[1]['write_iops']*0.6);
			$iopsArr[0]['write_kbytes']=floor($iopsArr[1]['write_kbytes']*0.6);
		}
		if(!empty($iopsArr)){
			sleep(0.5);
			foreach($iopsArr as $num=>$arr){
				$sql="update ".$this->Tbl['vm_order_disks']." set ";
				$sql.="read_iops=".intval($arr['read_iops']);
				$sql.=",read_kbytes=".intval($arr['read_kbytes']);
				$sql.=",write_iops=".intval($arr['write_iops']);
				$sql.=",write_kbytes=".intval($arr['write_kbytes']);
				$sql.=" where vm_id=".intval($RS['vm_id'])." and disk_num=".intval($num);
				$res=$this->DB->query($sql);
				if(!$res){
					return 0;
				}
			}
		}
		
		$typeRS=$this->DB->query("select * from ".$this->Tbl['set_prod_type']." where type_sort=0 and type_id=".intval($RS['type_id']),1);
		
		$limit_cpu_mode = intval($typeRS['limit_cpu_mode']);
		$limit_bw_mode = intval($typeRS['limit_bw_mode']);
		
		if(!in_array($limit_cpu_mode, array(1,2,5))){
			$limit_cpu_mode=1;
		}
		if(!in_array($limit_bw_mode, array(1,2))){
			$limit_bw_mode=1;
		}
		
		$isUpdateMod = false;
		if($limit_cpu_mode!=$RS['vm_cpu_mode'] or $limit_bw_mode!=$RS['vm_bw_mode']){

			$sql="update ".$this->Tbl['vm_order']." set ";
			$sqlstr='';
			if($limit_cpu_mode!=$RS['vm_cpu_mode']){
				$sqlstr.=',vm_cpu_mode='.intval($limit_cpu_mode);
			}
			if($limit_bw_mode!=$RS['vm_bw_mode']){
				$sqlstr.=',vm_bw_mode='.intval($limit_bw_mode);
			}

			if(!empty($sqlstr)) $sqlstr =substr($sqlstr, 1);
			if(!empty($sqlstr)){
				$sql = $sql.$sqlstr." where vm_id=".intval($RS['vm_id']);
				$res=$this->DB->query($sql);
				if($res){
					$isUpdateMod=true;
				}
			}

		}
		
		if( $isUpdateMod or $upSet['vm_cpulimit']>0 or $upSet['vm_memory']>0 or $upSet['vm_bandwidth_in']>0 or $upSet['vm_bandwidth_out']>0){
			//资源限制命令
			$ids=array();
			if($RS['vm_ismod']){
				$ids[]=$RS['vm_id'];
				$ret=$this->API->mod_add_quota($ids);
				if($ret['result']>0){
					//return $this->error($ret['msg']);
					$this->add_log($RS,'订单升级','发送资源限制命令，接口返回错误：'.$ret['msg'],0);
				}
			}
		}
		
		if(!empty($remark)){
			$this->add_log($RS,'订单升级',$remark,0);
		}
		$ret=Array();
		$ret['_error']='';
		return $ret;

	}



	//F3.1 查询订单列表
	//$client:对应client_id（用于代理商网站通过API查询用户订单）
	//$groupid：分组ID。$page,分页显示页码(为空时显示第1页内容);
	//$getinfo: 0 - 只返回订单信息， 1 - 同时返回续费时可用时长及价格； 2 同时返回重启状态 , 3 只返回运行状态的, 4只返回免费 5 同0但按vm_id倒序
	//返回$ret=Array()； vm_order表对应字段值;
	//默认分页为每页显示20条，若要定义其它值，可使用$SCP->DB->pageSize=30;然后再$SCP->get_order_list();
	function get_order_list($client_id=0,$groupid=0,$page=0,$getinfo=0,$keyword=''){
		$_ret=array();

		$client_id=intval($client_id);
		$groupid=intval($groupid);

		$osArr=Array();
		$results=$this->DB->query("select * from ".$this->Tbl['set_ostype']." where os_stat<3 and os_filetype=0 and os_father_id=0 order by os_ordernum desc, os_id",2);
		foreach ($results as $arr)
		{
			$osid=intval($arr['os_id']);
			$osArr[$osid]=$arr;
		}

		$where=" where user_id=".intval($this->userID);//user_id必须的
		if($getinfo==3) 
			$where=$where." and vm_stat<1 and vm_install_stat>0";
		else if($getinfo==4)
			$where=$where." and vm_stat<2 and vm_isfree=1";
		else if($getinfo==2)
			$where=$where." and (vm_stat<1 or vm_stat=1 and vm_stoptype=0) and vm_enddate>NOW()";//重启
		else
			$where=$where." and vm_stat<2";
		if($getinfo==1) $where=$where." and vm_isfree!=1";

		$keyword=trim($keyword);
		if(!empty($keyword) and strlen($keyword)<21 and strlen($keyword)>1){
			//全数字则搜索会员(否则搜索IP)
			if(preg_match('/^[0-9]+$/',$keyword) and empty($client_id)){
				$where=$where." and client_id=".intval($keyword);
			}
			//IP+端口
			else if(preg_match('/^[0-9]{1,3}(\.[%0-9]{1,3}){1,3}:[0-9]{1,5}$/',$keyword)){
				$tmp=explode(":",$keyword);
				$where=$where." and vm_ip like ".$this->DB->getsql(''.$tmp[0].'') ."";
				$tmp[1]=intval($tmp[1]);
				if ($tmp[1]>10)
				{
					$where=$where." and (".($tmp[1]>10000 ? "vm_port=".intval($tmp[1]/10) : "vm_port like '".$tmp[1]."%'")." and vm_iptype<1 or vm_admin_port=".intval($tmp[1])." and vm_iptype>0)";
				}
			}
			//IP
			else if(preg_match('/^[0-9]{1,3}(\.[%0-9]{1,3}){1,3}$/',$keyword)){
				$where=$where." and vm_ip like ".$this->DB->getsql($keyword.'');
			}
			//标注
			else{
				if($client_id>0){
					$ress=$this->DB->query("SELECT vm_id FROM ".$this->Tbl['vm_order_info']." WHERE userid=".intval($this->userID)." and clientid=".intval($client_id)." and vm_titles like ".$this->DB->getsql('%'.$keyword.'%')." ",2);
				}
				else {
					$ress=$this->DB->query("SELECT vm_id FROM ".$this->Tbl['vm_order_info']." WHERE userid=".intval($this->userID)." and  vm_title like ".$this->DB->getsql('%'.$keyword.'%')." ",2);
				}
				usleep(10000);
				$ID_STR='';
				$n=0;
				foreach($ress as $arr){
					$ID_STR=$ID_STR.','.intval($arr['vm_id']);
					$n++;
					if($n>120) break;
				}
				//echo "<!-- $ID_STR SELECT vm_id FROM ".$this->Tbl['vm_order_info']." WHERE userid=".intval($this->userID)." and  vm_title like ".$this->DB->getsql('%'.$keyword.'%')." -->";
				if(strlen($ID_STR)>0){
					$ID_STR=substr($ID_STR,1);
					$where=$where." and vm_id IN ($ID_STR)";
				}
				else{
					$where=$where." and 0 ";//找不到
				}
			}
		}

		
		if($client_id>0) {
			$where=$where." and client_id=$client_id";
			if($groupid>0) $where=$where." and order_groups=$groupid";
		}
		else{
			if($groupid>0) $where=$where." and order_group=$groupid";
		}
		$sqlorder=" order by vm_enddate,vm_id desc";
		if($getinfo==5) $sqlorder=" order by vm_id desc";
		#if(!in_array($this->DB->pageSize,Array(10,20,30,50))) $this->DB->pageSize=20;

		//处理分页
		$results=$this->DB->query_page($page,"*",$this->Tbl['vm_order'],$where,$sqlorder);
		//分页信息返回：$this->DB->pageinfo=Array('page'=>'当前页码','pagenum'=>'总页数','rowsnum'=>'总记录数');
		
		$result=Array();
		$runStat=getConfig('_run_stat');
		$setupStat=getConfig('_setup_stat');
		$sids='';
		//print_r($runStat);
		foreach($results as $row){
			$vmid=intval($row['vm_id']);
			$sids=$sids.','.intval($row['server_id']);
			if(strtotime($row['vm_enddate'])<=time()){
				$_daynum=0;
			}
			else{
				$_daynum=round((strtotime($row['vm_enddate'])-time())/24/3600,2);//剩余时长（精确到0.01天）
				if($_daynum>=100) $_daynum=floor($_daynum);//100天以上精确到1天
				else if($_daynum>=10) $_daynum=number_format($_daynum, 1, '.', '');//10天以上精确到0.1天
				else $_daynum=number_format($_daynum,2, '.', '');
			}
			$osid=intval($row['os_id']);
			$row['_vm_stat']=$runStat[$row['vm_stat']];
			$row['_install_stat']=$setupStat[$row['vm_install_stat']];
			if(isset($osArr[$osid])){
				$row['_os_name']=$osArr[$osid]['os_name'];
			}
			else{
				$row['_os_name']='已失效';
			}
			if(empty($row['vm_iptype'])){
				$row['vm_ip']=$row['vm_ip'].':'.$row['vm_port'].'2';
			}
			else{
				$row['vm_ip']=$row['vm_ip'].':'.$row['vm_admin_port'];
			}
			if($client_id>0) $row['order_group']=$row['order_groups'];
			
			if($row['vm_iptype']==0 and $row['vm_port_limit']>5) $row['vm_port_limit']=5;
			
			$row=array_merge($row,Array("_daynum"=>$_daynum));
			
			$result[$vmid]=$row;
			
			usleep(200);
		}

		unset($results);
		$vmIN='';
		$prodArr=Array();
		foreach($result as $arr){
			$vmIN=$vmIN.','.intval($arr['vm_id']);
			$pid=intval($arr['prod_id']);
			if($pid>0){
				$prodArr[$pid]=$pid;
			}
		}
		if(strlen($vmIN)>1) {
			$vmIN=substr($vmIN,1);
			$results=$this->DB->query("select * from ".$this->Tbl['vm_order_info']." where vm_id IN($vmIN)",2);
			foreach($results as $arr)
			{
				$vmid=intval($arr['vm_id']);
				$arr['vm_api_key']='';
				$arr['vm_diy_pass']='';
				$arr['vm_admin_pass']='';
				$arr['vm_login_password']='';
				$arr['vm_login_key']='';
				$arr['vm_vnc_pass']='';
				$arr['vm_mysql_pass']='';
				if($client_id>0) $arr['vm_title']=$arr['vm_titles'];
				//刚完成的
				if($arr['vm_changedate']>date('Y-m-d H:i:s',time()-120) and $result[$vmid]['vm_install_stat']==2) $result[$vmid]['vm_install_stat']=1;
				$result[$vmid]=array_merge($result[$vmid],$arr);
			}
		}

		//直接返回
		if(empty($getinfo) or $getinfo>=3){
			$_ret['data']=$result;
			$_ret['pageinfo']=$this->DB->pageinfo;
			return $_ret;
		}

		if(strlen($vmIN)<1) {
			$_ret['data']=$result;
			$_ret['pageinfo']=$this->DB->pageinfo;
			return $_ret;
		}

		$ret=Array();

		
		//检查服务器是否关闭
		$serverSet=Array();
		if(strlen($sids)>1){
			$sids=substr($sids,1);
			$resultss=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_stat<3 and server_id IN ($sids)",2);
			foreach($resultss as $arr){
				$serverSet[$arr['server_id']]=$arr; //1+abs($arr['server_stat']);
			}
		}
		//查询续期价格及对应时长
		if($getinfo==1){
			$prodIN=implode(',',$prodArr);
			if(empty($this->queryPrice)){
				$this->query_price();
			}

			//产品设置
			$prodSet=Array();
			$prodConfSet = array();
			if(strlen($prodIN)>0){

				$results=$this->DB->query("select * from ".$this->Tbl['prod_conf']." where prod_id IN($prodIN) and prod_stat<3",2);
				
				foreach($results as $arr){
					$prodSet[$arr['prod_id']]=$arr;
				}
				
				$results=$this->DB->query("select * from ".$this->Tbl['prod_conf_set']." where prod_id IN($prodIN)",2);
				foreach ($results as $arr){
					$prodConfSet[$arr['prod_id']][$arr['net_id']]=$arr;
				}
			}

			//IP数(计算续费价格时需要)
			$ipArr=Array();
			$results=$this->DB->query("select vm_id,count(*) as ipnum from ".$this->Tbl['set_ippool']." where ip_stat=3 and vm_id IN($vmIN)  group by vm_id",2);
			foreach($results as $arr){
				$ipArr[$arr['vm_id']]=0+$arr['ipnum'];
			}

			//推荐配置价格设置
			$priceConf=$this->DB->query("select period,period_unit,price,prod_id,net_type  from ".$this->Tbl['prod_conf_price']." where 1  order by period_unit desc,period,price",2);
			foreach($result as $row){
				$typeid=intval($row['type_id']);
				$pid=intval($row['prod_id']);
				$sid=intval($row['server_id']);
				$vmid=intval($row['vm_id']);
				$netid=intval($row['net_id']);
				$nettype=$this->netArr[$netid]['net_type_id'];
				$priceArr=Array();
				$_msg='';
				$ipPrice=0;
				$ipnum=(isset($ipArr[$vmid]) ?$ipArr[$vmid]:0);
				$ipsub=0;
				if($ipnum>0 and !empty($prodSet[$pid]['vm_iptype'])){
					$ipsub=1;//排除推荐配置自带的一个
				}

				$row=array_merge($row,Array("_ipnum"=>$ipnum-$ipsub));
				//$row=array_merge($row,Array("_iptype"=>$ip type));
				$row=array_merge($row,Array("_nettype"=>$nettype));
				$row=array_merge($row,Array("_confname"=>''));
				$row=array_merge($row,Array("_orderchange"=>0));
				
				//非自己暂停和过期暂停的情况
				if($row['vm_stat']==1 and $row['vm_stoptype']!=3 and $row['vm_stoptype']>0){
					$row=array_merge($row,Array("_price"=>Array()));
					$row=array_merge($row,Array("_msg"=>'订单锁定状态，不能续期'));
					$ret[$vmid]=$row;
					continue;
				}

				if(!isset($serverSet[$sid])){
					$row=array_merge($row,Array("_price"=>Array()));
					$row=array_merge($row,Array("_msg"=>'服务器暂停管理，不能续期'));
					$ret[$vmid]=$row;
					continue;
				}

				
				if($row['vm_stat']==1 and $this->isAdmin<1 and isset($serverSet[$sid]) and $serverSet[$sid]['server_vmrun']>$serverSet[$sid]['server_vmuse']+1){
					$row=array_merge($row,Array("_price"=>Array()));
					$row=array_merge($row,Array("_msg"=>'服务器已满,暂停的订单不能恢复'));
					$ret[$vmid]=$row;
					continue;
				}

				//推荐配置价格(使用推荐配置条件：CPU、内存、硬盘、带宽必须一致) get_order_info中有相同设置
				if(isset($prodSet[$pid])){
					$prods=$prodSet[$pid];
					if($row['vm_disk']>$prods['vm_disk_data']
						or $row['vm_cpunum']>$prods['vm_cpunum']
						or $row['vm_cpulimit']>$prods['vm_cpulimit']
						or $row['vm_memory']>$prods['vm_memory']
						//or $row['vm_iptype']>$prods['vm_iptype']
						//or $row['vm_bandwidth_out']>$prods['vm_bandwidth_out']
						//or $row['vm_bandwidth_in']>$prods['vm_bandwidth_in']
					){
						$pid=0;
					}
				}

				//不是推荐配置
				if(empty($pid)){
					$_msg='非推荐配置';
				}
				else{
					
					if(!empty($prodConfSet[$pid][$row['net_id']])){
						$prods['vm_bandwidth_out'] = $prodConfSet[$pid][$row['net_id']]['vm_bandwidth_out'];
						$prods['vm_bandwidth_in'] = $prodConfSet[$pid][$row['net_id']]['vm_bandwidth_in'];
						$prods['vm_domain_limit'] = $prodConfSet[$pid][$row['net_id']]['vm_domain_limit'];
					}
					
					$row=array_merge($row,Array("_confname"=>$prods['prod_name'].'&nbsp;'));
					$ipPrice=0;
					if($ipnum-$ipsub>0){
						if(isset($this->priceSet[$nettype][19])){
							$res=$this->priceSet[$nettype][19];
							$ipPrice=$this->get_day_price(19,$ipnum,$res,$ipsub);
						}
					}
					if($ipnum-$ipsub>0 and $ipPrice<=0){
						$_msg='IP价格未设置';
						$row=array_merge($row,Array("_price"=>$priceArr));
						$row=array_merge($row,Array("_msg"=>$_msg));
						$ret[$vmid]=$row;
						continue;
					}
					/*
					if($row['vm_id']==101041){
					echo "ipnum=".$ipnum.'<br>';
					echo "ipsub=".$ipsub.'<br>';
					echo "ipPrice=".$ipPrice.'<br>';
					print_r($priceArr);exit;
					}
					*/
					$ipBuy=Array();
					$prodBuy=Array();
					//查找对应的设置
					foreach($this->periodSet as $arr){
						if($arr['type_id']==$nettype){
							$ipBuy[]=$arr;
						}
						if($arr['type_id']==$typeid){
							$prodBuy[]=$arr;
						}
					}

					usleep(2000);
					foreach($priceConf as $arr){
						if($arr['prod_id']!=$pid) continue; //非当前配置的
						if($nettype!=$arr['net_type']) continue;//非当前网络的

						if($arr['period_unit']==1) $tag='H';
						else if($arr['period_unit']==2) $tag='M';
						else if($arr['period_unit']==0) $tag='D';
						else{
							continue;//其它错误设置跳过
						}

						$_ip_cost=0;

						if($ipnum-$ipsub>0){
							if($ipPrice<=0) continue;//无法计算价格 
							$_ip_cost=$this->get_ip_price($ipPrice,$arr['period'],$arr['period_unit'],$arr['period'],$ipBuy,$prodBuy);
							if($_ip_cost<=0) continue;
						}
						
						$diff_bandwidth_out=0;
						$diff_bandwidth_in=0;
						$diff_domain_limit=0;
							
						//订单出网带宽数大于推荐配置出网带宽数
						
						if($row['vm_bandwidth_out']>$prods['vm_bandwidth_out']
								or $row['vm_domain_limit']>$prods['vm_domain_limit']
								or $row['vm_bandwidth_in']>$prods['vm_bandwidth_in']
								){
									
									$row=array_merge($row,Array("_orderchange"=>1));
									
									$diff_order=$this->get_diy_price($arr['period'],$arr['period_unit'],$row);
						
									$prods['net_id']=$row['net_id'];
									$prods['vm_disk']=$row['vm_disk'];
									$diff_prod=$this->get_diy_price($arr['period'],$arr['period_unit'],$prods);
						
						
									if(!empty($this->msg)){
										continue;
									}
						
									if($row['vm_bandwidth_out']>$prods['vm_bandwidth_out']){
										$diff_bandwidth_out = $diff_order[11]-$diff_prod[11];
										if($diff_bandwidth_out<0){
											continue;
										}
									}
						
									if($row['vm_bandwidth_in']>$prods['vm_bandwidth_in']){
										$diff_bandwidth_in = $diff_order[10]-$diff_prod[10];
										if($diff_bandwidth_in<0){
											continue;
										}
									}
						
									if($row['vm_domain_limit']>$prods['vm_domain_limit']){
										$diff_domain_limit = $diff_order[4]-$diff_prod[4];
										if($diff_domain_limit<0){
											continue;
										}
									}
						
						}
						
						
						$arr['price']=$arr['price']+$_ip_cost;//加上IP的费用
						$arr['price']=$arr['price']+$diff_bandwidth_out+$diff_bandwidth_in+$diff_domain_limit;
						$arr['price']=number_format($arr['price'],2,'.','');

						$tag=$tag.$arr['period'];
						if(!isset($priceArr[$tag])){ //相同时长下只使用最优惠的价格
							$priceArr[$tag]=$arr;
						}
						usleep(500);
					}
				}
				//调用DIY价格
				if(empty($priceArr) and empty($pid)){
					$this->msg='';
					$priceArr=$this->get_buy_set($row,$ipnum);
					if(!empty($this->msg)) $_msg=$this->msg;
					$this->msg='';
				}

				$row=array_merge($row,Array("_price"=>$priceArr));
				$row=array_merge($row,Array("_msg"=>$_msg));
				$ret[$vmid]=$row;
				usleep(2000);
			}
		}

		//是否允许重启(批量重启使用)
		if($getinfo==2){
			//查询操作状态
			$cmdArr=Array();
			$results=$this->DB->query("select * from ".$this->Tbl['vm_order_cmd']." where cmd_stat=0 and vm_id IN($vmIN) order by cmd_id",2);
			foreach($results as $arr){
				$cmdArr[$arr['vm_id']]=$arr;
			}
			foreach($result as $arr){
				$deny=0;
				$vmid=$arr['vm_id'];
				$t=intval($arr['vm_reset_time']);
				$msg='';
				if($arr['vm_stat']>1){
					$deny=1;
					$msg='关闭订单';
				}
				else if($arr['vm_stat']==1 and $arr['vm_stoptype']>0 and $arr['vm_stoptype']!=3){
					$deny=1;
					$msg='暂停订单';
				}
				else if($arr['vm_install_stat']==0){
					$deny=1;
					$msg='未安装';
				}
				else if($arr['vm_install_stat']==1 and $arr['vm_setuptime']>time()-15*60){
					$deny=1;
					$msg='安装中';
				}else if(!isset($serverSet[$arr['server_id']])) {
					$deny=1;
					$msg='所在服务器暂停管理';
				}
				else if(isset($cmdArr[$vmid])){
					$row=$cmdArr[$vmid];
					if($row['cmd_type']==0) $msg='待启动';
					else if($row['cmd_type']==3) $msg='待重启';
					else if($row['cmd_type']>=10 and $row['cmd_type']<30) {
						$arr['vm_install_stat']=0;//等待安装
						$msg='待安装';
					}
					if(!empty($msg)){
						$deny=1;
					}
				}

				$t0=time()-$t;
				if($t0<600) {
					$msg='10分钟内有重启';
					$deny=1;
				}

				$t0=round($t0/60,1);
				if($t0>2) $t0=round($t0);
				if($t<100){
					$_time='<font color=gray>无记录</font>';
				}
				else if($t0>72*60){
					$_time='<font color=gray>3天内无记录</font>';
				}
				else if($t0<180){
					$_time=$t0.'分钟前';
				}
				else{
					$_time=round($t0/60).'小时前';
				}

				$arr=array_merge($arr,Array("_time"=>$_time));//上次重启时间
				$arr=array_merge($arr,Array("_msg"=>$msg));//订单状态
				$arr=array_merge($arr,Array("_deny"=>$deny));//_deny=0允许重启，否则不允许重启
				$ret[$vmid]=$arr;
			}
			
		}
		$_ret['data']=$ret;
		$_ret['pageinfo']=$this->DB->pageinfo;
		return $_ret;
	}


	//新 (续费时设置$getinfo=1则会返回价格，非续期不要加$getinfo值)
	//返回订单配置数组，配置标识为数组KEY
	function get_order_info($vm_id=0,$getinfo=0){
		
		$vm_id=intval($vm_id);
		if(empty($vm_id) and !empty($this->orderID)) $vm_id=$this->orderID;
		if(empty($vm_id)) return Array();

		//返回格式(非_开头的为订单配置)
		$RS=Array();
		$RS['_table_info']=0;//是否有从表记录
		$RS['_confname']='';//如果为空，则不是推荐配置
		$RS['_confchange']=0;
		$RS['_osname']='';//操作系统名称
		$RS['_osintro']='';//操作系统介绍
		$RS['_ossize']='';//系统盘大小
		$RS['_osislinux']=0;//系统盘大小
		$RS['_serveruse']=0;//0 服务器暂停管理 1 可操作
		$RS['_serverstat']=-1; //服务器状态，-1，服务器不存在，>=0 与服务器状态对应
		$RS['_internet']=array();//拨号模式
		$RS['_prodtype']=array();//产品分类信息
		$RS['_snapstat']=0;//0 不在运行中，1在运行中
		$RS['_orderchange']=0;//订单配置是否变化 //订单有变化，订单配置大于推荐配置,代理不能计算价格
		$RS['_orderlimit']=0;//0不限制，1限制 限制订单除重启续期外的所有功能。
		$RS['_orderremark']='';

		if($getinfo>0){
			$RS['_iplist']=Array();//绑定的额外IP
			$RS['_price']=Array();
			$RS['_ipprice']=Array();
			$RS['_lastprice']=Array();
		}
		
		$_k=$_v='';

		$res=$this->DB->query("select * from ".$this->Tbl['vm_order']." where vm_id=$vm_id".($this->userID>1?" and user_id=".intval($this->userID):""),1);
		if($res){
			$res1=$this->DB->query("select * from ".$this->Tbl['vm_order_info']." where vm_id=$vm_id",1);
			if($res1){
				foreach($res1 as $_k =>$_v){
					if($_k=='vm_id') continue;
					$res[$_k]=$_v;
				}
				$RS['_table_info']=1;
			}
		}
		else{
			return Array();
		}


		//共享IP强制最大为5个
		if($res['vm_iptype']==0 and $res['vm_port_limit']>5) $res['vm_port_limit']=5;
		
		//查询分类
		$typeRes=$this->DB->query("select * from ".$this->Tbl['set_prod_type']." where type_sort=0 and type_stat<4 and type_id=".$res['type_id'],1);
		
		//资源限制模式
		if($res['vm_cpu_mode']==1 or $res['vm_cpu_mode']==2 or $res['vm_cpu_mode']==5){
		//1 固定限制  2 动态限制	5 不限制
			$RS['_prodtype']['limit_cpu_mode']=$res['vm_cpu_mode'];
		}else{
			//根据分类进行限制
			if(!empty($typeRes) and ($typeRes['limit_cpu_mode']==1 or $typeRes['limit_cpu_mode']==2 or $typeRes['limit_cpu_mode']==5)){
				$RS['_prodtype']['limit_cpu_mode']=$typeRes['limit_cpu_mode'];
			}else{
				$RS['_prodtype']['limit_cpu_mode']=1;
			}
		}
		
		if($res['vm_bw_mode']==1 or  $res['vm_bw_mode']==2){
			//1 固定限制  2 动态限制
			$RS['_prodtype']['limit_bw_mode']=$res['vm_bw_mode'];
		}else{
			//根据分类进行限制
			if(!empty($typeRes) and ($typeRes['limit_bw_mode']==1 or $typeRes['limit_bw_mode']==2)){
				$RS['_prodtype']['limit_bw_mode']=$typeRes['limit_bw_mode'];
			}else{
				$RS['_prodtype']['limit_bw_mode']=1;
			}
		}
		if($typeRes['limit_bw_out_set']<=0 or $typeRes['limit_bw_out_set']>100) $typeRes['limit_bw_out_set']=100;
		if($typeRes['limit_bw_in_set']<=0 or $typeRes['limit_bw_in_set']>100) $typeRes['limit_bw_in_set']=100;
		
		$RS['_prodtype']['limit_bw_out_set']=$typeRes['limit_bw_out_set'];
		$RS['_prodtype']['limit_bw_in_set']=$typeRes['limit_bw_in_set'];
		
		//判断快照
		
		if($res['vm_ismod']){
			
			$sql="select * from ".$this->Tbl['vm_order_snapshot']." where ";
			$sql.="vm_id=".intval($vm_id);
			$sql.=" and ((snapshot_stat=1 and  snapshot_date>".$this->DB->getsql(date("Y-m-d H:i:s",time()-3600))." )";
			$sql.=" or (snapshot_merge_stat=2 and snapshot_merge_date>".$this->DB->getsql(date("Y-m-d H:i:s",time()-3600)).")";
			$sql.=" ) limit 1";
			$snapRes=$this->DB->query($sql,1);
			
			if(!empty($snapRes)){
				$RS['_snapstat']=1;
			}
		}
			
		$OS=$this->DB->query("select * from ".$this->Tbl['set_ostype']." where os_id =".intval($res['os_id']),1);
		if($OS){
			$RS['_osname'] =$OS['os_name'];
			$RS['_osintro']=$OS['os_intro']; //操作系统介绍
			$RS['_ossize'] =$OS['os_disk_sys']; //系统盘大小
			$RS['_oshelp'] =$OS['os_help']; //帮助
			$RS['_osclose'] =$OS['os_close_root'];
			$RS['_osislinux'] =intval($OS['os_islinux']);
			
			$RS['_osftpport']=0;
			if(empty($RS['vm_iptype'])) {
				if(!empty($OS['os_port_ftp'])) $RS['_osftpport']=$RS['vm_port'].'1';
			}
			else if(!empty($OS['os_port_ftp'])){
				$RS['_osftpport'] =$OS['os_port_ftp']; //关闭ROOT
			}

			$RS['_osftp']   =$OS['os_close_ftp'];   //os_close_ftp 关闭FTP
			$RS['_oschange']=$OS['os_close_change']; //os_close_change 不允许更换系统
		}

		//Mac地址格式化(api/cloud_class.php中也需要作相同处理)
		$info=Array();
		$mac=$res['vm_mac'];
		$mac=str_replace('-','',$mac);
		$mac=str_replace(':','',$mac);
		if(strlen($mac)!=12){
			$res['vm_mac']='macerror';
		}
		else {
			$res['vm_mac']=substr($mac,0,2).':'.substr($mac,2,2).':'.substr($mac,4,2);
			$res['vm_mac']=$res['vm_mac'].':'.substr($mac,6,2).':'.substr($mac,8,2).':'.substr($mac,10,2);
		}

		//内网IP格式化(api/cloud_class.php中也需要作相同处理)
		if(empty($res['vm_lan_ip']) and isset($res['vm_lan'])){
			$res['vm_lan_ip']='10.'.intval($res['vm_lan']).'.'.floor($res['vm_port']/100).'.'.intval($res['vm_port']%100);
		}

		if(empty($this->queryPrice)){
			$this->query_price();
		}
		
		$netid=intval($res['net_id']);
		$res['_domain']=$res['vm_domain_limit'];
		if($this->netArr[$netid]['net_domain_close']>1){
			$res['vm_domain_limit']=0;
			$res['_domain']=-2;//机房不支持
		}
		else if($this->netArr[$netid]['net_domain_close']>0){
			$res['_domain']=-1; //暂停，不能绑定
		}
		//端口数
		$res['_port']=$res['vm_port_limit'];
		if($res['vm_port_stat']>2){
			$res['_port']=-3; //订单不支持
		}
		if($this->netArr[$netid]['net_port_close']>3){
			$res['_port']=-2; //机房不支持
		}
		$res['_dname']=$res['vm_ip'];//域名解析地址
		
		//拨号模式
		if($res['vm_mode']==1){
			$interServerRes=$this->DB->query("select * from ".$this->Tbl['set_internet_server']." where record_stat=0 and record_vm_id=".intval($vm_id),1);
			
			if(!empty($interServerRes) and $interServerRes['internet_id']>0){
				$interRes=$this->DB->query("select * from ".$this->Tbl['set_internet']." where internet_id=".intval($interServerRes['internet_id']),1);				
				if(!empty($interRes)){
					$RS['_internet']['username']=$interRes['internet_username'];
					$RS['_internet']['password']=$interRes['internet_password'];
				}
			}
		}
		
		$RS=array_merge($res,$RS);
		$RS['_netname']=$this->netArr[$netid]['net_name'];

		$_day=round((strtotime($RS['vm_enddate'])-time())/24/3600,2);//剩余时长（精确到0.01天）
		if($_day<0 or strtotime($RS['vm_enddate'])<=time()) $_day=0;
		$RS['_daynum']=$_day;//剩余时长(天)
		$RS['_ipclose']=0;
		$RS['_diyclose']=0;
		if($RS['vm_changedate']>date('Y-m-d H:i:s',time()-120) and $RS['vm_install_stat']==2) $RS['vm_install_stat']=1;

		$prodid=intval($RS['prod_id']);

		$prods=$this->DB->query("select * from ".$this->Tbl['prod_conf']." where prod_id=$prodid  and prod_stat<3 ",1);
		if($prods){
			$RS['_confname']=get_html($prods['prod_name'])."&nbsp;";
			if($prods['vm_ipclose']==1) $RS['_ipclose']=1;
			if($prods['vm_diyclose']==1) $RS['_diyclose']=1;
			
			$sql="select * from ".$this->Tbl['prod_conf_set'];
			$sql.=" where prod_id=".intval($prodid);
			$sql.=" and net_id=".intval($RS['net_id']);
			
			$prodsetRS = $this->DB->query($sql,1);
			if(!empty($prodsetRS)){
				$prods['vm_bandwidth_out'] = $prodsetRS['vm_bandwidth_out'];
				$prods['vm_bandwidth_in'] = $prodsetRS['vm_bandwidth_in'];
				$prods['vm_domain_limit'] = $prodsetRS['vm_domain_limit'];
				
			}
		}

		//2015-11-27修改$serverRS并新增
		$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_stat<4 and server_id=".intval($RS['server_id']),1);
		if(!empty($serverRS) and $RS['server_stat']<2) $RS['_serveruse']=1;
		if(!empty($serverRS)) $RS['_serverstat']=$serverRS['server_stat'];

		$RS['_serverip']='不可用';
		$RS['_agentip']='不可用';
		if(!empty($serverRS)) {
			$RS['_serverip'] =$serverRS['server_addr'];
			$RS['_agentip']  =$serverRS['server_addr_agent'];
		}
		$dm=$this->setConf['site_cp_domain'];
		if(empty($dm)) $dm=$_SERVER['HTTP_HOST'];

		$RS['_cpurl'] ='https://'.$dm.'/cp/index.php';//控制面板登录地址
		$RS['_mcpurl']='https://'.$dm.'/cp/mlogin.php';//控制面板手机登录地址
		$RS['_vncurl']='https://'.$dm.'/vnc/login.html';//控制面板登录地址
		
		$AS=$this->DB->query("select * from ".$this->Tbl['set_api']." where user_id>0 and user_id=".$RS['user_id'],1);
		if($AS){
			if(!empty($AS['site_url_cp'])){
				$RS['_cpurl']=$AS['site_url_cp'];
			}
			if(!empty($AS['site_url_mcp'])){
				$RS['_mcpurl']=$AS['site_url_mcp'];
			}
			if(!empty($AS['site_url_vnc'])){
				$RS['_vncurl']=$AS['site_url_vnc'];
			}
		}

		if($prodid>0){
			//print_r($prods);
			//推荐配置价格(使用推荐配置条件：CPU、内存、硬盘、带宽必须一致)get_order_list中有相同设置
			if($prods){
				$RS['_confname']=get_html($prods['prod_name'])."&nbsp;";
				if($RS['vm_disk']>$prods['vm_disk_data']
					or $RS['vm_cpunum']>$prods['vm_cpunum']
					or $RS['vm_cpulimit']>$prods['vm_cpulimit']
					or $RS['vm_memory']>$prods['vm_memory']
					//or $RS['vm_iptype']>$prods['vm_iptype']
					//or $RS['vm_bandwidth_out']>$prods['vm_bandwidth_out']
					//or $RS['vm_bandwidth_in']>$prods['vm_bandwidth_in']
				)
				{
					$RS['_confname']='';
					$prodid=0;
				}

				if($prodid>0 and $prods['prod_stat']<2 and 
					(empty($this->isAPI) and  $prods['prod_api']<2
						or !empty($this->isAPI) and  $prods['prod_api']>0
					)
				) {
					if($RS['vm_disk']<$prods['vm_disk_data']
						or $RS['vm_cpunum']<$prods['vm_cpunum']
						or $RS['vm_cpulimit']<$prods['vm_cpulimit']
						or $RS['vm_memory']<$prods['vm_memory']
						or $RS['vm_bandwidth_out']<$prods['vm_bandwidth_out']
						or $RS['vm_bandwidth_in']<$prods['vm_bandwidth_in']
						or $RS['vm_domain_limit']<$prods['vm_domain_limit'] and empty($this->netArr[$netid]['net_domain_close'])
						or $RS['vm_port_limit']<$prods['vm_port_limit'] and $this->netArr[$netid]['net_port_close']<2
					)
					{
						$RS['_confchange']=1;
					}
				}
			}
			else{
				$prodid=0;
			}
		}
		
		//查找分类
		$typeRS = array();
		if($RS['type_id']>0){
			$sql="select * from ".$this->Tbl['set_prod_type'];
			$sql.=" where type_stat<4 and type_id=".intval($RS['type_id']);
			$typeRS = $this->DB->query($sql,1);
		}
		$RS['_orderlimit'] = intval($typeRS['limit_order']);
		$RS['_orderremark'] = $typeRS['limit_order_remark'];
		
		
		$netSet=$this->netArr[$netid];
		//print_r($netSet);
		$net_type_id=$netSet['net_type_id'];
		$RS['_nettype']=$net_type_id;
		//服务器订单已经开满，暂停的不能恢复！
		if($RS['vm_stat']==1 and $getinfo==1 and $this->isAdmin<1 and $serverRS['server_vmrun']>$serverRS['server_vmuse']+1){
			$getinfo=0;
			$RS['_msg']='服务器已满,暂停的订单不能恢复';
		}
		//查询价格(续期时使用)
		if($getinfo==1 and $serverRS){
			$res=$this->DB->query("select * from ".$this->Tbl['set_ippool']." where vm_id =$vm_id ",2);
			$ipnum=0;
			foreach($res as $arr){
				$ipnum=$ipnum+1;
				if($RS['vm_ip']==$arr['ip_addr']) continue;//主IP跳过了(2015-6-19)
				$RS['_iplist'][]=Array('ip'=>$arr['ip_addr']);
			}
			$ipsub=0;//用于校正IP计价
			if($ipnum>0 and !empty($prods['vm_iptype'])) {
				$ipsub=1;//独立IP推荐配置价格包括1个IP，需要扣除
			}

			$RS['_ipnum']=$ipnum-$ipsub;

			if(isset($this->priceSet[$net_type_id][19])){
				$res=$this->priceSet[$net_type_id][19];
				$RS['_ipprice']=$res;
			}

			//查询推荐配置价格
			if($prodid>0){
				
				$ipPrice=0;//所有额外IP每天的价格
				//print_r($this->priceSet);
				if(isset($this->priceSet[$net_type_id][19])){
					$res=$this->priceSet[$net_type_id][19];
					$RS['_ipprice']=$res;
					$RS['_price']=Array();
					if($ipnum-$ipsub>0){
						$ipPrice=$this->get_day_price(19,$ipnum,$res,$ipsub);
					}

					//2015-6-19
					if($ipnum-$ipsub>0 and $ipPrice<=0){
						$RS['_msg']='IP价格未设置';
						return $RS;
					}
				}

				//IP价格设置正常或无额外IP
				if($ipnum-$ipsub>0){
					$ipBuy=Array();
					$prodBuy=Array();
					//查找对应的设置
					foreach($this->periodSet as $arr){
						if($arr['type_id']==$net_type_id){
							$ipBuy[]=$arr;
						}
						if($arr['type_id']==$RS['type_id']){
							$prodBuy[]=$arr;
						}
					}

				}
			
				
				
				
				
				//推荐配置价格
				$result=$this->DB->query("select net_type,period,period_unit,price  from ".$this->Tbl['prod_conf_price']." where  prod_id=$prodid and net_type={$net_type_id}  order by price",2);
				$priceArr=Array();
				foreach($result as $arr){
					$_ip_cost=0;
					if($ipnum-$ipsub>0){
						$_ip_cost=$this->get_ip_price($ipPrice,$arr['period'],$arr['period_unit'],$arr['period'],$ipBuy,$prodBuy);
						//无法查询IP价格
						if($_ip_cost<0.01){
							continue;
						}
					}
					
					$diff_bandwidth_out=0;
					$diff_bandwidth_in=0;
					$diff_domain_limit=0;
					
					//订单出网带宽数大于推荐配置出网带宽数
					if($RS['vm_bandwidth_out']>$prods['vm_bandwidth_out']
						or $RS['vm_domain_limit']>$prods['vm_domain_limit']	
						or $RS['vm_bandwidth_in']>$prods['vm_bandwidth_in']
						){
						//订单有变化，订单配置大于推荐配置,代理不能计算价格
						$RS['_orderchange']=1;
							
						$diff_order=$this->get_diy_price($arr['period'],$arr['period_unit'],$RS);
						
						$prods['net_id']=$RS['net_id'];
						$prods['vm_disk']=$RS['vm_disk'];
						$diff_prod=$this->get_diy_price($arr['period'],$arr['period_unit'],$prods);
						
						
						if(!empty($this->msg)){
							continue;
						}
						
						if($RS['vm_bandwidth_out']>$prods['vm_bandwidth_out']){
							$diff_bandwidth_out = $diff_order[11]-$diff_prod[11];
							if($diff_bandwidth_out<0){
								continue;
							}
						}
						
						if($RS['vm_bandwidth_in']>$prods['vm_bandwidth_in']){
							$diff_bandwidth_in = $diff_order[10]-$diff_prod[10];
							if($diff_bandwidth_in<0){
								continue;
							}
						}
						
						if($RS['vm_domain_limit']>$prods['vm_domain_limit']){
							$diff_domain_limit = $diff_order[4]-$diff_prod[4];
							if($diff_domain_limit<0){
								continue;
							}
						}
						
					}
					
					$arr['price']=$arr['price']+$_ip_cost;//加上IP的费用
					$arr['price']=$arr['price']+$diff_bandwidth_out+$diff_bandwidth_in+$diff_domain_limit;
					
					$arr['price']=number_format($arr['price'],2,'.','');
					if($arr['period_unit']==1) $tag='H';
					else if($arr['period_unit']==2) $tag='M';
					else if($arr['period_unit']==0) $tag='D';
					else{
						continue;//其它错误设置跳过
					}
					$tag=$tag.$arr['period'];
					if(!isset($priceArr[$tag])){ //相同时长下只使用最优惠的价格
						$priceArr[$tag]=$arr;
					}
				}

			}
			
			//调用DIY价格
			if(empty($priceArr) and empty($prodid)){
				$this->msg='';
				$priceArr=$this->get_buy_set($RS,$ipnum);
				if(!empty($this->msg)) $RS['_msg']=$this->msg;
				$this->msg='';
			}
			$RS['_price']=$priceArr;
			
			//上次消费
			$sql="select * from ".$this->Tbl['vm_last_price'];
			$sql.=" where vm_id=".intval($vm_id);
			$RS['_lastprice'] = $this->DB->query($sql,1);
			if(!empty($RS['_lastprice'])){
				$key="";
				if($RS['_lastprice']['period']>0 and $RS['_lastprice']['period_unit']==0){
					$key = 'D'.$RS['_lastprice']['period'];
				}
				if(!empty($RS['_price'][$key])){
					$RS['_lastprice']['price'] = $RS['_price'][$key]['price'];
				}else{
					$RS['_lastprice']  =array();
				}
			}
			
			
			
		}
		return $RS;
	}



	//F3.4 批量重启（后台重新）
	//参数$form=Array(订单号1,订单号2,订单号3)
	//返回$ret['_error']:成功为空，错误时为错误信息
	//返回$ret['renewnum']:成功执行条数
	function orders_restart($userid,$form){

		$this->msg='';
		$vmIN=implode(',',$form);
		if(empty($vmIN)){
			return $this->error('未提交任何订单！');
		}

		$cmdArr=Array();
		$results=$this->DB->query("select * from ".$this->Tbl['vm_order_cmd']." where cmd_stat=0 and vm_id IN($vmIN) order by cmd_id",2);
		foreach($results as $arr){
			$cmdArr[$arr['vm_id']]=$arr;
		}

		$result=$this->DB->query("select * from ".$this->Tbl['vm_order']." where vm_id IN($vmIN) and user_id=$userid",2);
		//echo $vmIN; print_r($form); print_r($result);
		
		$sids='';
		foreach($result as $arr){
			$sids=$sids.','.intval($arr['server_id']);
		}
		//检查服务器是否关闭
		$serverSet=Array();
		if(strlen($sids)>1){
			$sids=substr($sids,1);
			$resultss=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_stat<3 and server_id IN ($sids)",2);
			foreach($resultss as $arr){
				$serverSet[$arr['server_id']]=1+abs($arr['server_stat']);
			}
		}
		
		$ret=Array();
		$ret['_error']=0;
		$n=0;
		foreach($result as $arr){
			
			$vmid=$arr['vm_id'];
			$t=intval($arr['vm_reset_time']);
			if($arr['vm_stat']>1){
				$this->msg.=$vmid.'关闭订单;';
				continue;
			}
			else if($arr['vm_stat']==1 and $arr['vm_stoptype']>0 and $arr['vm_stoptype']!=3){
				$this->msg.=$vmid.'暂停订单;';
				continue;
			}
			else if($arr['vm_install_stat']==0){
				$this->msg.=$vmid.'未安装;';
				continue;
			}
			else if($arr['vm_install_stat']==1 and $arr['vm_setuptime']>time()-15*60){
				$this->msg.=$vmid.'安装中;';
				continue;
			}else if(!isset($serverSet[$arr['server_id']]) or $serverSet[$arr['server_id']]>2){
				$this->msg.='所在服务器暂停管理';
				continue;
			}
			else if(isset($cmdArr[$vmid])){
				$row=$cmdArr[$vmid];
				if($row['cmd_type']==0) $msg='待启动';
				else if($row['cmd_type']==3) $msg='待重启';
				else if($row['cmd_type']>=10 and $row['cmd_type']<30) {
					$arr['vm_install_stat']=0;//等待安装
					$msg='待安装';
				}
				if(!empty($msg)){
					$this->msg.=$vmid.$msg.';';
					continue;
				}
			}
			else{
				$t0=time()-$t;
				if($t0<600){
					$this->msg.=$vmid.'10分钟有重启;';
					continue;
				}
			}
			$ret=$this->DB->query("insert into ".$this->Tbl['vm_order_cmd']." (vm_id,cmd_type,cmd_adddate,server_id) values ($vmid,3,NOW(),".intval($arr['server_id']).")");
			usleep(2000);
			$n++;
			if($n>30) break;
		}
		$ret['renewnum']=$n;
		return $ret;
	}


	//F3.5 批量分组或修改标注
	function orders_change($client_id=0,$form){

		$ret=Array();
		$ret['_error']='';
		$client_id=intval($client_id);
		foreach($form['data'] as $arr){
			$_title=trim($arr['title']);
			if(strlen($_title)>60){
				$ret['_error']=$ret['_error'].'订单号['.$arr['vmid'].']标注太长；';
			}
		}
		if(!empty($ret['_error'])) return $ret;
		//$this->userID;
		foreach($form['data'] as $arr){
			$vm_id=intval($arr['vmid']);
			$RS=$this->DB->query("select * from ".$this->Tbl['vm_order']." where user_id=".$this->userID." and  vm_id=".$vm_id." and vm_stat<2",1);
			if(empty($RS)){
				continue;
			}
			$_group=intval($arr['group']);
			$_title=trim($arr['title']);
			if($client_id==0){
				$this->DB->query("update ".$this->Tbl['vm_order']." set order_group=$_group where vm_id=".$vm_id."");
				usleep(5000);
				$this->DB->query("update ".$this->Tbl['vm_order_info']." set userid=".$this->userID.",clientid=".intval($RS['client_id']).",vm_title=".$this->DB->getsql($_title)." where vm_id=".$vm_id."");
				usleep(5000);
			}
			else if($client_id==$RS['client_id']){
				$this->DB->query("update ".$this->Tbl['vm_order']." set order_groups=$_group where vm_id=".$vm_id."");
				usleep(5000);
				$this->DB->query("update ".$this->Tbl['vm_order_info']." set userid=".$this->userID.",clientid=".$client_id.",vm_titles=".$this->DB->getsql($_title)." where vm_id=".$vm_id."");
				usleep(5000);
			}
			usleep(20000);
		}
		return $ret;
	}


	//本方法不返回XML格式，直接返回图片文件
	//本地调用时则只需要 id,isFirst
	//API调用时需要能数vm_id,isFirst(是否第一次)
	//直接输出图片
	function order_preview($vm_id,$isFirst=0,$client_id=0){
		$vm_id=intval($vm_id);
		$isFirst=intval($isFirst);
		$RS=$this->DB->query("select * from ".$this->Tbl['vm_order']." where vm_id=".$vm_id,1);
		$err=0;
		if(empty($RS)){
			$err=1;
		}
		else if($this->userID>0 and $RS['user_id']!=$this->userID){
			$err=2;
		}
		else if($client_id>0 and $RS['client_id']!=$client_id){
			$err=2;
		}
		else if($RS['vm_stat']>0 or $RS['vm_install_stat']==0){
			$err=3;
		}

		$ret=Array();
		$ret['result']=0;
		$ret['img']='';

		//出错（权限图标20*13）
		if($err>0 ){
			$imgStr='R0lGODlhFAANAJEAAP///8HBwf///wAAACH5BAEAAAIALAAAAAAUAA0AAAItlI+pGe2+TgC01hDFfA7guDWe+C3hNHrZZnkqOKLkGtelcj6v2Vo3zoFkhokCADs=';
			if($this->isAPI){
				$ret['img']=$imgStr;
				return $ret;
			}
			header("Cache-Control: no-chache"); 
			Header("Content-type: image/GIF");
			echo base64_decode($imgStr);
			exit;
		}

		$server_id=intval($RS['server_id']);
		if(empty($err)){
			//检查服务器状态
			$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_stat<2 and  server_id=".$server_id,1);
			if(empty($serverRS)){
				$err=4;
			}
		}


		//出错服务器或命令关闭
		if($err>0 or empty($this->sentcmd)){
			// 8*5图片，用于显示状态异常
			//echo $vm_id;exit;
			$imgStr='R0lGODlhCAAFAIABAG1tbf///yH5BAEAAAEALAAAAAAIAAUAAAIJRI4Blrr9YDsFADs=';
			if($this->isAPI){
				$ret['img']=$imgStr;
				return $ret;
			}
			header("Cache-Control: no-chache"); 
			Header("Content-type: image/GIF");
			echo base64_decode($imgStr);
			exit;
		}

		//获取载图
		$API=new ServerMOD($this->DB,$server_id,$serverRS,0);
		$sesstag='__previewtime__'.$vm_id;
		$filetime=intval($_SESSION[$sesstag]);//记录上一次更新的时间
		if($isFirst==1){
			$isfirst=1;
			$filetime=0;
		}
		else {
			$isfirst=0;
		}

		//echo $filetime;exit; 
		$ret=$API->screenshot($RS,460,345,$isfirst,$filetime);
		//print_r($ret);exit;
		//header("Expires:" . gmdate("D, d M Y H:i:s",time()+10) . " GMT");
		if($ret['result']==0 and strlen($ret['filecont'])>20){
			$_SESSION[$sesstag]=intval($ret['filetime']);
			/*
			header("Cache-Control: private");
			header("Cache-Control: max-age=20");
			Header("Content-type: image/JPEG");
			*/
			if($this->isAPI){
				$ret['img']=$ret['filecont'];
				return $ret;
			}
			header("Cache-Control: no-chache"); 
			Header("Content-type: image/GIF");
			echo base64_decode($ret['filecont']);

		}
		else if($ret['result']==0){
			//(16*16)用于显示未更新
			$imgStr='R0lGODlhEAAQAJECAAAAMwAAAP///wAAACH5BAEAAAIALAAAAAAQABAAAAIOlI+py+0Po5y02ouzPgUAOw==';
			if($this->isAPI){
				$ret['img']=$imgStr;
				return $ret;
			}
			header("Cache-Control: no-chache"); 
			Header("Content-type: image/GIF");
			echo base64_decode($imgStr);//默认小图片，防止JS出错

		}
		else{
			// 9*9图片，用于显示请求异常
			$imgStr='R0lGODlhCQAJAIABAFZWVv///yH5BAEAAAEALAAAAAAJAAkAAAINjI+JYJzuVpMh1qmyLgA7';
			if($this->isAPI){
				$ret['img']=$imgStr;
				return $ret;
			}
			header("Cache-Control: no-chache"); 
			Header("Content-type: image/GIF");
			echo base64_decode($imgStr);
		}
		exit;
	}

	//VNC自动登录处理
	//返回 $ret=Array('_error'=>'错误信息','url'=>'登录地址');
	function order_vnclogin($vm_id,$logintype=0,$client_id=0){
		$vm_id=intval($vm_id);
		$logintype=intval($logintype);
		$RS=$this->DB->query("select * from ".$this->Tbl['vm_order']." where vm_id=".$vm_id,1);
		$ret=Array('_error'=>'','url'=>'','urlstr'=>'');
		if(empty($RS)){
			$ret['_error']='订单不存！';
		}
		else if($this->userID>0 and $RS['user_id']!=$this->userID){
			$ret['_error']='订单无授权！';
		}
		else if($client_id>0 and $RS['client_id']!=$client_id){
			$ret['_error']='订单无授权！';
		}
		else if($RS['vm_stat']>0){
			$ret['_error']='订单暂停或关闭中不能登录！';
		}
		else if($RS['vm_install_stat']==0){
			$ret['_error']='订单未安装不能登录！';
		}
		else{

			$server_id=intval($RS['server_id']);
			$serverRS=$this->DB->query("select * from ".$this->Tbl['set_server']." where server_stat<3 and  server_id=".$server_id,1);
			if(empty($serverRS)){
				$ret['_error']='订单所在服务器暂停中不能登录！';
			}
			$sql="select * from ".$this->Tbl['vm_order_info']." where vm_id=$vm_id";
			$RSS=$this->DB->query($sql,1);
			if(empty($RSS) or empty($RSS['vm_vnc_pass'])){
				$ret['_error']='订单未能完成初始化，不能登录！';
			}
		}
		if(!empty($ret['_error'])){
			return $ret;
		}
		//主控必须是https://并且正式环境才能用此功能
		$vncport=$RS['vm_port'].'0';

		$vnchost=$this->setConf['site_cp_domain'];
		//if(empty($vnchost)) $vnchost=$this->setConf['site_url_cp'];
		if(empty($vnchost)) $vnchost=$_SERVER['HTTP_HOST'];

		$cpurl='https://'.$vnchost;

		$loginURL='%3Cserver%3E'.$serverRS['server_addr'].'%3C%2Fserver%3E%3Cport%3E'.$vncport.'%3C%2Fport%3E';
		$ret['vncip']  =$serverRS['server_addr'];
		$ret['vncport']=$vncport;
		if($logintype==2){
			$pStr='abcdefghijkmnpqrstuvwxyABCDEFGHIJKMNPQRSTUVWXYZ23456789';
			$passStr='';
			$passStr=$passStr.$alogin.$pStr[rand(0,53)].$pStr[rand(0,53)].$pStr[rand(0,53)].$pStr[rand(0,53)];
			$passStr=$passStr.$pStr[rand(0,53)].$pStr[rand(0,53)].$pStr[rand(0,53)].$pStr[rand(0,53)].$pStr[rand(0,53)];
			$passStr=$passStr.$pStr[rand(0,53)].$pStr[rand(0,53)].$pStr[rand(0,53)];
			$passStr=substr($passStr,0,10);
			$encode=md5('vnc'.$vm_id.$passStr.$cpurl);
			$this->DB->query("update ".$this->Tbl['vm_order_info']." set vm_safe_key='$passStr' where vm_id=".$vm_id);
			$loginURL=$loginURL.'%3Cid%3E'.$vm_id.'%3C%2Fid%3E%3Ctype%3E'.$logintype.'%3C%2Ftype%3E';
			$loginURL=$loginURL.'%3Ckeystr%3E'.$passStr.'%3C%2Fkeystr%3E';
			$loginURL=$loginURL.'%3Ccpurl%3E'.$cpurl.'%3C%2Fcpurl%3E';
			$loginURL=$loginURL.'%3Cencode%3E'.$encode.'%3C%2Fencode%3E';
			$ret['vmid']=$vm_id;//如果有此值，则访问 cpurl+"/monitor/vminfo.php?id=##&type=$type&checkcode=##"获取密码
			$ret['key']=$passStr;
			$ret['cpurl']=$cpurl;
			$ret['encode']=$encode;//检查cpurl及密码是否合法
		}
		$url='https://'.$vnchost.'/vnc/login.html';
		//如果代理则使用自己的域名或IP
		$AS=$this->DB->query("select * from ".$this->Tbl['set_api']." where user_id>0 and user_id=".$this->userID,1);
		if($AS and !empty($AS['site_url_vnc'])){
			$url=$AS['site_url_vnc'];
		}
		$ret['url']=$url.'?'.$loginURL;
		$ret['urlstr']=$loginURL;
		return $ret;
	}


	//设置控制面板密码
	//返回空为正常，返回非空则是错误信息
	function order_cp_set($vm_id,$setstat=0,$password="",$client_id=0){
		$vm_id=intval($vm_id);
		if($setstat!=1) $setstat=0;
		$RS=$this->DB->query("select * from ".$this->Tbl['vm_order']." where  vm_id=".$vm_id." and vm_stat<2",1);
		$RES=$this->DB->query("select * from  ".$this->Tbl['vm_order_info']." where vm_id=$vm_id",1);
		if(empty($RS)){
			$Msg=$Msg.'订单不存！';
		}
		else if($this->userID>0 and $RS['user_id']!=$this->userID){
			$Msg=$Msg.'订单无授权！';
		}
		else if($client_id>0 and $RS['client_id']!=$client_id){
			$Msg=$Msg.'订单无授权！';
		}
		else if (empty($password))
		{
			if($setstat>0 and $RS['vm_login_stat']==0 and empty($RES['vm_login_password'])){
				$Msg='开启独立控制面板必须设置密码！';
			}
		}
		else if (strlen($password)<8)
		{
			$Msg=$Msg.'[登录密码]长度不能小于8个字符！<br />';
		}
		else if (strlen($password)>32)
		{
			$Msg=$Msg.'[登录密码]长度不能大于32个字符！<br />';
		}
		else if (!preg_match('/^[!-~]+$/i',$password))
		{
			$Msg=$Msg.'[登录密码]格式不对，必须为英文非空字符！<br />';
		}
		else if (preg_match('/^[0-9]+$/i',$password))
		{
			$Msg=$Msg.'[登录密码]不能全是数字(建议使用数字和字母组合)<br />';
		}

		if(!empty($Msg)){
			return $this->error($Msg);
		}

		$newpass='';
		if(!empty($password)) $newpass=md5($password);

		if($RES){
			$sql="UPDATE ".$this->Tbl['vm_order_info']." SET vm_login_errors=0,vm_login_stat=".$setstat;
			if(!empty($password)){
				$sql=$sql.",vm_login_tmp='',vm_login_password=".$this->DB->getsql($newpass);
			}
			$sql=$sql." WHERE vm_id=".$vm_id;
		}
		else {
			$sql="insert into  ".$this->Tbl['vm_order_info']." (vm_id,vm_login_stat,vm_login_password) values ($vm_id,".$setstat.",".$this->DB->getsql($newpass).")";
		}
		$ret=$this->DB->query($sql);
		if(!$ret){
			$Msg='保存出错！';
		}



		$ret=Array();
		$ret['_error']='';
		return $ret;
	}


	//处理登录面板自动登录(本地使用会员session，不需要再设置任何参数；API则需要设置临时vm_login_key后再跳转)
	//返回$ret=Array('_error'=>'错误信息','url'=>'登录地址')
	function order_cp_login($vm_id,$pagetype=0,$client_id=0){
		$vm_id=intval($vm_id);
		$RS=$this->DB->query("select * from ".$this->Tbl['vm_order']." where  vm_id=".$vm_id." and vm_stat<2",1);
		if (empty($RS))
		{
			$Msg='订单不存在不能登录控制面板！';
		}
		else if($RS['vm_stat']>1){
			$Msg='产品关闭中，不能登录控制面板！';
		}
		else if($this->userID>0 and $RS['user_id']!=$this->userID){
			$Msg='当前账号无此订单管理权限！';
		}
		else if($client_id>0 and $RS['client_id']!=$client_id){
			$Msg='当前账号无此订单管理权限！';
		}

		if(!empty($Msg)){
			return $this->error($Msg);
		}

		$ret=Array();
		$ret['_error']='';
		$cp_url=Array();
		$cp_url[11]='server_info.php';
		$cp_url[12]='server_reset.php';
		$cp_url[13]='server_install.php';
		#$cp_url[30]='network_domain.php';
		#$cp_url[31]='network_port.php';
		$cpurl='https://'.$_SERVER['HTTP_HOST'];
		$topage=$cpurl.'/cp/home.php?orderid='.$vm_id;
		if(!empty($pagetype) and isset($cp_url[$pagetype])){
			$topage=$cpurl.'/cp/'.$cp_url[$pagetype].'?orderid='.$vm_id;
		}
		$ret['url']=$topage;
		return $ret;
	}





	//获取服务器可用模板(用于更换模板)
	function get_server_os($serverRS,$isadmin=0,$queryType=0,$vmismod=0){
		$queryType=intval($queryType);
		$vmismod =($vmismod==1)?1:0;
		$isadmin=intval($isadmin);
		if($isadmin!=0) $isadmin=2;
		$server_id=intval($serverRS['server_id']);
		if($serverRS['server_os_store']>0) $server_id=intval($serverRS['server_os_store']);
		$sql="select * from ".$this->Tbl['set_ostype']." where os_filetype=0 and os_filemod={$vmismod} and os_father_id=0 and os_id IN (select os_id from ".$this->Tbl['set_server_os']." where os_isclose<=$isadmin and server_id=$server_id)";
		$result=$this->DB->query($sql,2);
		$ret=Array();
		foreach($result as $arr){
			$osid=intval($arr['os_id']);
			//2015-9-25 限制分类的
			if($queryType>2){
				$tag="/,$queryType,/i";
				$type_str=",{$arr['type_id']},";
				if(strlen($arr['type_id'])>0 and !preg_match($tag,$type_str)) continue;//如果限制了分类，不符合则跳过
			}

			$ret[$arr['os_id']]=$arr;
		}
		return $ret;
	}



	//检查产品是否允许进行管理，包括（重装、重启、安装、开关机、绑IP、升级等操作）
	//返回为空表示允许操作
	function order_check($RS,$serverRS,$checkStat=0,$checkInstall=0,$DelSever=1,$serverCheckSkip=0)
	{
		$Msg='';

		if (!isset($serverRS))
		{
			$Msg='当前订单所在服务器状态异常，不能执行当前管理操作。';
		}
		else if ($serverRS['server_stat']>1 and $serverCheckSkip<1)
		{
			$Msg='服务器暂停管理：'.get_html($form['server_stopmsg']);
		}
		else if ($RS['vm_stat']>$checkStat)
		{
			$Msg='当前订单处于暂停或关闭中，不能执行当前管理操作！';
		}
		else if ($RS['vm_install_stat']<1 and $DelSever==1)
		{
			$Msg='当前订单尚未进行初始化安装，不能执行当前管理操作！';
		}
		else if ($RS['vm_install_stat']==1 and $RS['vm_setupdate']>date('Y-m-d H:i:s',time()-600))
		{
			$Msg='当前订单正在安装中，不能执行当前管理操作，请在安装时间('.$RS['vm_setupdate'].')之后10分钟之后再操作！';
		}
		else if(time()-$RS['vm_disk_uptime']<=CONF_DISK and $RS['vm_stat']==0){
			$tmsg="";
			if(CONF_DISK/3600>=1){
				$tmsg=(CONF_DISK/3600)."小时";
			}else if(CONF_DISK/60>=1){
				$tmsg=(CONF_DISK/60)."分钟";
			}else{
				$tmsg=(CONF_DISK)."秒";
			}
			$Msg='升级了硬盘后的'.$tmsg.'之内不允许再进行任何升级操作！';
		}
		//如果已经完成安装则无限制
		else if ($checkInstall>0 and $RS['vm_setupdate'] >= date('Y-m-d H:i:s',time()-600))
		{
			$Msg='此订单在15分钟内执行过安装或重装，请间隔10分钟后再申请！';
		}
		//重装过的服务器需要等待7分钟
		else if ($checkInstall>0 and $serverRS['server_install_time'] > time()-120)
		{
			$theTime=$serverRS['server_install_time']+120;
			$Msg='所在服务器正在执行其它订单安装操作，请于('.date('Y-m-d H:i:s',$theTime).')后再执行当前管理操作！';
		}
		//重启过的需要等待2分钟
		if ($checkInstall>0 and $serverRS['server_reset_time'] > time()-60)
		{
			$theTime=$serverRS['server_reset_time']+60;
			$Msg='所在服务器正在执行其它订单安装操作，请于('.date('Y-m-d H:i:s',$theTime).')后再执行当前管理操作！';
		}

		return $Msg;

	}


	function order_stop($RS,$type,$remark='')
	{
		$type=intval($type);
		$sql="update  ".$this->Tbl['vm_order']." set vm_stoptype=$type,vm_stopdate=NOW(),vm_stat=1";

		$sql=$sql." where vm_id=".intval($RS['vm_id']);
		$this->DB->query($sql);
		//echo 'STOP:'.$this->DB->err_msg();
	}

	//发送开/关/重装/重启命令
	function order_cmd($RS,$cmdtype){
		$this->DB->query("insert into ".$this->Tbl['vm_order_cmd']."(vm_id,cmd_type,cmd_adddate,server_id) values (".intval($RS['vm_id']).",".intval($cmdtype).",NOW(),".intval($RS['server_id']).")");

	}


	//订单取消(class_agent.php中作相同处理)
	function order_cancel($id){
		$id=intval($id);
		$this->DB->query("update ".$this->Tbl['vm_order']." set vm_stat=4,vm_deltime=UNIX_TIMESTAMP(),vm_startdate=vm_adddate,vm_enddate=vm_adddate ,vm_stoptype=9 where vm_id=$id");

		//2016-1-27 (以前未清除占用资源)
		$this->DB->query("update ".$this->Tbl['set_ippool']." set ip_stat=0,vm_id=0,ip_freedate=NOW() where ip_stat<5 and vm_id=".intval($id));
		$this->DB->query("update ".$this->Tbl['set_internet_server']." set record_vm_id=0  where record_vm_id=$id");

	}


	//type:0 
	/*
	type
	  0  用户操作产品记录（重启、重装、升级）
	  1  开设、续期、暂停、删除操作
	  2  转移会员号或代理号记录
	  3  管理员或系统操作产品记录
	  4  系统操作出错记录（新）
	*/
	function add_log($RS,$actname,$remark='',$type=0,$adduser=0)
	{
		$type=intval($type);
		if($type!=1 and $type!=2 and $type!=3 and $type!=4) $type=0;
		if(empty($adduser)) $adduser=$this->userID;
		if(empty($adduser)) $adduser=$RS['user_id'];

		if($this->logkeep!=1) $this->logkeep=0;
		$this->DB->query("insert into ".$this->Tbl['vm_logprod']." (log_type,log_keep,log_date,vm_id,user_id,log_add_user,log_intro,log_remark) values ($type,".intval($this->logkeep).",NOW(),".intval($RS['vm_id']).",".intval($RS['user_id']).",".intval($adduser)."," .$this->DB->getsql($actname)."," .$this->DB->getsql($remark).")");

	}

	//系统报警（新，以后程序均使用此方法）
	//type=0 一般报警 1 严重报警
	function add_alert($vmid,$actname,$remark='',$type=0){
		$thetype=intval($type);
		$this->DB->query("insert into ".$this->Tbl['vm_logalert']." (log_type,log_date,vm_id,log_intro,log_remark) values ($thetype,NOW(),".intval($vmid).',' .$this->DB->getsql($actname)."," .$this->DB->getsql($remark).")");
	}

	function error($msg){
		$ret=Array();
		$ret['_error']=$msg;
		return $ret;
	}


	//返回$type分类下，核心数为$cores时可用Hz值例表;
	//在升级时，此方法不检查返回值是否是正在使用中的，请在返回后再检查
	//使用$this->resArr而不是每次查询数据库
	function get_cpu_limit($type,$cores){

		$cores=intval($cores);
		if($cores<1) $cores=1;
		$cpulimitArr=Array();

		if($cores==1) $min=200;
		else $min=($cores-1)*getConfig('_core_to_hz_min');
		//最小值为核心数*1000MHz，1核是取100MHz,
		//order_add()、order_upgrade_check()及include/functions_res.php 中有相关检查

		foreach($this->resArr as $arr){
			if($arr['price_type']!=2) continue;
			if($arr['type_id']!=$type) continue;
			if($arr['price_size']>=$min and $arr['price_size']<=$cores*getConfig('_core_to_hz_max')){
				$cpulimitArr[]=intval($arr['price_size']);
			}
		}

		return $cpulimitArr;
	}

	function check_cpu_limit($cores=1,$limit=0){
		$cores=intval($cores);
		if($cores<1) $cores=1;
		if($cores==1) $min=200;
		else $min=($cores-1)*getConfig('_core_to_hz_min');
		if($limit<$min){
			return 1;
		}
		else if($limit>$cores*getConfig('_core_to_hz_max')){
			return 2;
		}
		return 0;
	}

	//订单连接数（暂时根据CPU和内存生成）
	function get_links($cpulimit,$memory){
		$links= 350 + $cpuimit*0.10+ $memory*0.05;
		$links= 10*ceil($links/10);//最小单位10
		return $links;
	}

	//读取订单端口及IP相关信息($RS需要包括order_info表记录)
	//$ret=Array('vnc_port'=>'VNC端口','vnc_ip'=>'VNC母机地址(或为数组)','vnc_pass'=>'VNC密码','ftp_port'=>'FTP端口','ftp_ip'=>'VNC地址(或为数组)','ssh_port'=>'','ssh_port_lan'=>'','ssh_user'=>'','ssh_pass'=>'','ssh_ip'=>'SSH地址(或为数组)','api_port'=>'','api_ip'=>'','api_pass'=>'','web_pass'=>'网站管理密码')
	//未考虑操作系统禁止SSH情况
	//2015-12-01 取消$serverRS参数；返回值取消$ret['vnc_url']和$ret['cp_url']

	function get_manager($RS){
		$ret=Array();

		//端口分配
		$ret['vnc_port']=$RS['vm_port'].'0'; //VNC
		if($RS['vm_iptype']==0){
			$ret['ftp_port']=$RS['vm_port'].'1'; //FTP
			$ret['ssh_port']=$RS['vm_port'].'2'; //SSH
			$ret['api_port']=$RS['vm_port'].'3'; //实例API
		}
		else{
			$ret['ftp_port']='21'; //FTP
			$ret['ssh_port']=$RS['vm_admin_port']; //SSH
			$ret['api_port']='8433'; //实例API
		}
		$ret['ssh_port_lan']=$RS['vm_admin_port']; //实例中的SSH port

		//帐号及密码
		$ret['vnc_pass']=$RS['vm_vnc_pass'];
		$ret['ssh_user']=$RS['vm_admin_user'];
		$ret['ssh_pass']=$RS['vm_admin_pass'];
		$ret['api_pass']=$RS['vm_api_key'];
		$ret['web_pass']=$RS['vm_web_pass'];

		//IP地址
		if($RS['vm_iptype']==0 and !empty($RS['_agentip'])){
			$tmp=explode(":",$RS['_agentip']);
			$ret['vnc_ip']  =$tmp[0];
			$ret['ssh_ip']  =$tmp[0];
			$ret['api_ip']  =$tmp[0];
			$ret['ftp_ip']  =$tmp[0];
		}
		else if($RS['vm_iptype']==0){
			$ret['vnc_ip']  =$RS['_serverip'];
			$ret['ssh_ip']  =$RS['_serverip'];
			$ret['api_ip']  =$RS['_serverip'];
			$ret['ftp_ip']  =$RS['_serverip'];
		}
		//独立IP
		else{
			$ret['vnc_ip']  =$RS['_serverip']; //实例API地址（未考虑命令转发及多IP情况）
			$ret['ssh_ip']  =$RS['vm_ip']; //实例API地址（未考虑命令转发及多IP情况）
			$ret['api_ip']  =$RS['vm_ip']; //实例API地址（未考虑命令转发情况）
			$ret['ftp_ip']  =$RS['vm_ip'];
		}

		return $ret;
	}

	
	//get_ip_price取得IP费用
	//$ipPrice 是设置的每天的价格;$period 计费时长;$period_unit 单位;
	//$_period 以此时长为基础计算费用(比如用户购买了30天，用了15天之后再绑IP，余下的15天可按30天的价格标准计费)
	function get_ip_price($ipPrice,$period,$period_unit,$_period,$ipBuy,$prodBuy){
		$payrate=0;
		$payperiod=0;
		if($_period<$period) $_period=$period;
		//IP设置了，则优先使用IP的设置
		foreach($ipBuy as $arr){
			if($arr['period_unit']!=$period_unit) continue;
			if($_period<$arr['period']){//可用的，一直到最后一个(最接近$period的)
				break;
			}
			$payrate=$arr['period_rate'];
			$payperiod=$arr['period'];
		}

		//使用产品对应分类的设置
		if(empty($payperiod)){
			foreach($prodBuy as $arr){
				if($arr['period_unit']!=$period_unit) continue;
				if($_period<$arr['period']){
					break;
				}
				$payrate=$arr['period_rate'];
				$payperiod=$arr['period'];
			}
		}

		//未设置时，设置默认1倍的倍率(get_diy_price()中有对应设置，需要同步)
		if(empty($payperiod)){
			if($period_unit==1){
				$payrate=0.1;//0.1倍/小时
				$payperiod=1;//
			}
			else if($period_unit==2){
				$payrate=0.005;//0.005倍/分钟
				$payperiod=1;//
			}
			else{
				//1倍/天
				$payrate=1;
				$payperiod=1;
			}
		}

		$price=round($payrate*$period/$payperiod,4);
		if($period_unit>=1 and $price>1) $price=1; //小时的计费倍率不得超过1（天）
		$price=round($price*$ipPrice,2);

		//2016-9-20 查找时间更长价格却更便宜的
		$newprice=$price;
		foreach($ipBuy as $arr){
			if($arr['period_unit']!=$period_unit) continue;
			$tmpprice=round($ipPrice*$arr['period_rate'],2);
			if($arr['period']>$period and $tmpprice<$newprice and $tmpprice>0){
				$newprice=$tmpprice;
			}
		}
		if($newprice==$price){//没找到合适的IP倍率设置，再找分类设置
			foreach($prodBuy as $arr){
				if($arr['period_unit']!=$period_unit) continue;
				$tmpprice=round($ipPrice*$arr['period_rate'],2);
				if($arr['period']>$period and $tmpprice<$newprice and $tmpprice>0){
					$newprice=$tmpprice;
				}
			}
		}
		if($newprice<$price) $price=$newprice;

		$price=round($price,2);
		return $price;
	}


	//计算DIY方式的可用时长及价格
	//$RS产品配置数组(可以是推荐配置或DIY配置);$ipnum独立IP数
	//返回格式$ret=Array('D1'=>Array('price'=>1,'period'=>1,'period_unit'=>0),'D30'=>Array(),'net_type'=>0);
	function get_buy_set($RS,$ipnum=0){
		$typeid=intval($RS['type_id']);

		if(empty($this->queryPrice)){
			$this->query_price();
		}
		//查找对应的设置
		$prodBuy=Array();
		foreach($this->periodSet as $arr){
			if($arr['type_id']==$typeid){
				$prodBuy[]=$arr;
			}
		}
		//未设置时长时默认只显示一个月付
		if(empty($prodBuy)){
			$prodBuy[]=Array('period'=>30,'period_unit'=>0,'period_rate'=>30,'net_type'=>0);
		}
		//echo $nettype,';',$ip type;
		//print_r($priceSet);
		$ret=Array();
		foreach($prodBuy as $row){
			if($row['period_unit']==1) $tag='H';
			else if($row['period_unit']==2) $tag='M';
			else if($row['period_unit']==0) $tag='D';
			else{
				continue;//其它错误设置跳过
			}
			$tag=$tag.$row['period'];
			//计算指定时长价格
			$this->msg='';
			$_priceArr=$this->get_diy_price($row['period'],$row['period_unit'],$RS,$ipnum);
			//echo "<br>$tag ($ipnum): ";print_r($_priceArr);
			if(!empty($this->msg)){
				$row['price']=0;
				$row['_error']=$this->msg;
				//$this->msg='';
			}
			else{
				$_price=array_sum($_priceArr);
				$row['price']=number_format(round($_price,2),2,'.','');
				$row['_error']='';
				if($row['price']<=0) {
					$row['price']=0;
					$row['_error']='无法计算价格！';
				}
			}
			$ret[$tag]=Array('period'=>$row['period'],'period_unit'=>$row['period_unit'],'price'=>$row['price']
				,'_error'=>$row['_error'],'net_type'=>0);//,'_priceSet'=>$_price
		}
		return $ret;
	}


	//计算DIY方式指定时长的各项资源价格
	//$period时长，返回为数组
	function get_diy_price($period,$period_unit,$RS,$ipnum=0){

		if(empty($this->queryPrice)){
			$this->query_price();
		}
		$resSet=Array();
		$resSet[]=Array('内存',$RS['vm_memory'],1,0,'_price'=>array());//格式Array('名称','值','对应price_type','是否允许价格为空')
		$resSet[]=Array('CPU核心',$RS['vm_cpunum'],2,0,'_price'=>array());
		$resSet[]=Array('CPU频率',$RS['vm_cpulimit'],3,0,'_price'=>array());
		$resSet[]=Array('域名白名单',$RS['vm_domain_limit'],4,1,'_price'=>array());
		$resSet[]=Array('硬盘',$RS['vm_disk'],21,0,'_price'=>array());
		$resSet[]=Array('带宽出',$RS['vm_bandwidth_out'],11,0,'_price'=>array());
		$resSet[]=Array('带宽入',$RS['vm_bandwidth_in'],10,0,'_price'=>array());
		$resSet[]=Array('IP',$ipnum,19,1);
		$typeid=intval($RS['type_id']);
		$netid=intval($RS['net_id']);
		$nettype=$this->netArr[$netid]['net_type_id'];
		//$ip type=$this->netArr[$netid]['ip_type_id'];

		$ipBuy=Array();
		//查找对应的设置
		foreach($this->periodSet as $arr){
			if($arr['type_id']!=$nettype or $arr['period_unit']!=$period_unit){
				continue;
			}
			if($period<$arr['period']) break;
			$ipBuy=$arr;//以最后一个为准（最接近或等于$period）
		}
		$row=Array();
		foreach($this->periodSet as $arr){
			if($arr['type_id']!=$typeid or $arr['period_unit']!=$period_unit){
				continue;
			}
			if($period<$arr['period']){
				break;
			}
			$row=$arr;//以最后一个为准（最接近或等于$period）
		}
		//print_r($row);

		//硬件及带宽资源计费率(get_ip_price()中有对应设置，需要同步)
		if(empty($row)){
			if($period_unit==1){
				$payrate=0.1;//0.1倍/小时
				$payperiod=1;//
			}
			else if($period_unit==2){
				$payrate=0.005;//0.005倍/分钟
				$payperiod=1;//
			}
			else{
				//1倍/天
				$payrate=1;
				$payperiod=1;
			}
		}
		else{
			$payrate=$row['period_rate'];
			$payperiod=$row['period'];
		}

		//计算价格(返回以price_type作下标的数组)
		$ret=Array();
		foreach($resSet as $arr){
			$pricetype=$arr[2];
			$value=intval($arr[1]);
			if($value<=0) {
				$ret[$pricetype]=number_format(0,3,'.','');
				if(empty($arr[3])) $this->msg.="[{$arr[0]}]不能为空;";
				continue;
			}
			if($pricetype==11 or $pricetype==10 or $pricetype==19) $_type=$nettype;
			else $_type=$typeid;
			$resPrice=$this->priceSet[$_type][$pricetype];
			if(empty($resPrice)){
				$ret[$pricetype]=number_format(0,3,'.','');
				if($value>0) $this->msg.="[{$arr[0]}]无法计算价格;";
				//echo '<pre>',$this->msg;print_r($priceSet);exit;
				continue;
			}

			//2015-11-2 判断$resPrice['present_size']时单位不对(赠送值大>=实际值要出错)
			if($pricetype==3) {
				$rate=1000;
				$resPrice['present_size']=$resPrice['present_size']*$rate;
			}
			if($pricetype==1) {
				$rate=getConfig('_mem_unit');
				$resPrice['present_size']=$resPrice['present_size']*$rate;
			}

			//某个资源每天的价格
			$day_price=$this->get_day_price($pricetype,$value,$resPrice);
			//大于赠送值时不允许价格为0
			if($day_price<=0 and $value>$resPrice['present_size']){
				$ret[$pricetype]=number_format(0,3,'.','');
				$this->msg.="[{$arr[0]}]无法计算价格,";
				continue;
			}
			//独立IP优先使用IP的设置
			if($pricetype==19 and !empty($ipBuy)){
				$payrate=$ipBuy['period_rate'];
				$payperiod=$ipBuy['period'];
			}

			//在指定时长的费用
			$_price=round($period*$payrate/$payperiod,4);
			if($period_unit>=1 and $_price>1) $_price=1; //小时的计费倍率不得超过1（天）
			$_price=round($_price*$day_price,3);
			$_price=number_format($_price,3,'.','');
			if($_price<=0 and $value>$resPrice['present_size']){
				$this->msg.="[{$arr[0]}]无法计算价格。";
			}
			$ret[$pricetype]=$_price;

		}
		return $ret;
	}

	//计算某个资源每天价格（其它时长价格根据每天的价格换算）
	//参数:资源$value 数值或数量; $priceArr当前资源价格设置; $issub：1时独立IP推荐配置减除自带IP的价格，其它情况为0
	//返回值float
	function get_day_price($pricetype,$value,$priceArr,$issub=0){

		$value=intval($value);
		if($value<=0) return 0;

		if($issub!=1 or $pricetype!=19) $issub=0;
		//内存和CPU频率要换算单位
		if($pricetype==1){
			$rate=getConfig('_mem_unit');
			if(empty($rate)) $rate=1024;
			$value=round($value/$rate,3);
		}
		else if($pricetype==3){
			$value=round($value/1000,3);
		}

		$price=0;
		$present_size=0;
		$s=$priceArr;
		if($s['present_size']>0) $present_size=$s['present_size'];
		//赠送值不能大于高资源值
		if($s['division_size']>0 and $s['division_size']<$present_size) $present_size=$s['division_size'];
		if($pricetype==19) $present_size=0;//独立IP不赠送

		//非线形价格计算
		//echo "$value<$present_size<br />";
		if($value<$present_size){
			$price=0;
		}
		else if($s['division_size']>0 and $s['division_price']>0 and $value>$s['division_size']){
			$price=0+$s['base_price']*($s['division_size']-$present_size-$issub);//默认价格
			$price=$price+$s['division_price']*($value-$s['division_size']);//超出部分的特殊价格
		}
		//基本价格
		else if(isset($s['base_price']) and $s['base_price']>0){
			$value=$value-$present_size;//小于等于赠送值时要算价格
			$value=$value-$issub;
			if($value<0) $value=0;
			$price=0+$s['base_price']*$value;
		}
		if($price<0) $price=0;
		$price=number_format(round($price,3),3,'.','');
		return $price;

	}

	//返回推荐配置升级价格
	function get_conf_price($priceConf,$prodid,$nettype,$_period=0,$_day=0){
		if($_period==0 or $_day==0) return 0;
		$_period=doubleval($_period);
		$_day=doubleval($_day);
		$price=0;//在$_period内的价格，由于$price是价格，不能设置默认倍率
		$period=0;
		//$priceArr=Array();//大于$_period的最小价格()

		foreach($priceConf as $arr){
			if($arr['prod_id']!=$prodid) continue;
			if($arr['net_type']!=$nettype) continue;
			if($arr['period_unit']!=0) continue;

			$p=intval($arr['period']);
			if($_period<$p){ //不符合条件则跳出
				if(empty($period)){//没有符合条件的则取最小值
					$price=$arr['price'];
					$period=$p;
				}
				break;
			}
			//多次处理，直到最接近$_period的一次
			$price=$arr['price'];
			$period=$p;
		}


		if($period>0){
			$price=$price*$_day/$period;
		}
		$price=number_format(round($price,2),2,'.','');
		return $price;
	}

	//返回资源可用规格(最多返回50项)
	function get_res_select($res){

		$tmp=Array();
		//自定义值
		if(strlen($res['size_other'])>0){
			$tmp=explode(',',$res['size_other']);
		}
		//连续值
		if($res['size_min']>0 and $res['size_max']>$res['size_min']){
			if(empty($res['size_base'])) $res['size_base']=1;
			$n=0;
			for($i=$res['size_min'];$i<=$res['size_max'];$i=$i+$res['size_base']){
				if($i>$res['size_max']) break;
				
				$n++;
				array_push($tmp,$i);
				if($n>50) break;
			}
		}
		//去重
		array_unique($tmp);
		//从小到大排序
		sort($tmp);
		$min=0;
		if($res['present_size']>0 and $res['present_size']<$res['size_min']) $min=$res['present_size'];

		$ret=Array();
		$n=0;
		foreach($tmp as $value){
			if($value <$min) continue;//小于赠送值的不显示
			//转换单位
			if($res['price_type']==1) {
				$value=$value*getConfig('_mem_unit');//内存
				if($value<100) continue;
			}
			if($res['price_type']==3) {
				$value=$value*1000;//CPU频
				if($value<100) continue;
			}
			if($value<1) continue;//不能为空或小数

			$ret[]=intval($value);//取整
			$n++;
			if($n>=50) break;//限50个
		}
		return $ret;
	}

	//@增加订单时间专用 $addnum : 时长 ; $addtype: 0 天 1 小时 2分钟
	//@return 返回空则表示输入时间不正确，正确时返回日期及时间(包括时分秒)
	function date_add($datestr,$addnum=0,$addtype=0){

		$t=strtotime($datestr);
		if(empty($t)){
			return $t;
		}

		if(date('Y-m-d H:i:s',$t)!=$datestr){
			return '';
		}

		//不增加，只用于检查时间是否正确
		if($addnum==0){
			return $datestr;
		}

		if($addtype==1){
			$t=$t+$addnum*3600;//1增加小时
		}
		else if($addtype==2){
			$t=$t+$addnum*60;//2增加分钟
		}
		else { //0
			$t=$t+$addnum*24*3600;//增加天数
		}
		return date('Y-m-d H:i:s',$t);
	}

	function specialstr($str){
		$str=str_replace("\"",'\\"',$str);
		$str=str_replace("<","&lt;",$str);
		$str=str_replace(">","&gt;",$str);
		return $str;
	}

	//将订单号转换成字符
	function order_id2str($id){
		$id=intval($id);
		$tag0=floor($id/100000);
		if($tag0>0){
			$tag0=strval($tag0);
			$tag1=''.base_convert($tag0,10,30);
			$tag1=strval($tag1);
			$tag1=str_replace('l','x',$tag1);
			$tag1=str_replace('o','y',$tag1);
			if(strlen($tag1)<2) $tag1='00'.$tag1;
			else if(strlen($tag1)<3) $tag1='0'.$tag1;
		}
		else{
			$tag1='000';
		}

		$tag2=substr(strval($id),-5);
		while(strlen($tag2)<5){
			$tag2='0'.$tag2;
			if(strlen($tag2)>=5) break;
		}
		return $tag1.$tag2;
	}

	//将字符转换成订单号
	function order_str2id($str){
		$tag2=substr(strval($str),-5);
		if(!preg_match("/^[0-9]+$/",$tag2)){
			return 0;
		}
		$tag0=substr(strval($str),0,-5);
		if(empty($tag0)) $tag1=0;
		else {
			$tag1=strval($tag0);
			$tag1=str_replace('x','l',$tag1);
			$tag1=str_replace('y','o',$tag1);
			$tag1=''.base_convert($tag1,30,10);
			$tag1=intval($tag1);
		}
		$id=$tag1*100000+intval($tag2);
		return $id;
	}

	/*
	 * 函数分页
	 * @ $resulAll 原数组值
	 * @ $count 原数组总和
	 * @ $page 分页值
	 * @ $pagenum 页码
	 * @ $pagesize 页数
	 * return 新数组
	 */

	function array_page_list($resultAll,$count,&$page,&$pagenum,$pagesize=20)
	{
		$result=array();
		if(empty($resultAll) or $count<=0) return $result;
		if(empty($page)) $page=1;

		if($count<=$pagesize){
			$page=1;
			return $resultAll;
		}

		$pagenum=ceil($count/$pagesize);
		if($page>$pagenum) $page=$pagenum;
		$offset=($page-1<=0?0:$page-1)*$pagesize;
		$length=$pagesize;
		return array_slice($resultAll,$offset,$length,true);
	}


	//返回订单绑定域名，并将计算个数返回到$this->msg值中
	function get_domain($vm_id){
		
		$result=$this->DB->query("select d_name from ".$this->Tbl['vm_domain']." where d_stat<4 and vm_id=$vm_id",2);
		$ret=Array();
		$num=0;
		$dArr=Array();
		foreach($result as $arr){
			$ret[]=$arr;
			if($arr['d_stat']>1) continue;
			//计数
			$dm=$arr['d_name'];
			$zone=getDomainZone($dm);
			if ($dm=='www.'.$zone)
			{
				$dm=$zone;
			}
			if(!isset($dArr[$dm])){
				$dArr[$dm]=1;
				$num++;
			}
		}
		$this->msg=$num;
		return $ret;
	}

	//检查订单续期，单个或批量续期使用(class_order.php中使用)
	function orders_renew_check($RS,$select_value){
		if(empty($RS)){
			return '订单不存在！';
		}
		$vm_id=intval($RS['vm_id']);
		if($RS['vm_stat']==1 and $RS['vm_stoptype']!=3 and $RS['vm_stoptype']>0)
		{
			return '订单'.$vm_id.'暂停中，不能续期，请联系管理员！';
		}
		if ($RS['vm_stat']>=2)
		{
			return '订单'.$vm_id.'尚未开通或已关闭，不能续期！';
		}
		if(empty($RS['_price']) or !is_array($RS['_price'])){
			return '订单'.$vm_id.'无法计算价格，不能续期！';
		}
		if(!isset($RS['_price'][$select_value])){
			return '订单'.$vm_id.'选择的续期时长不符合条件，不能续期！';
		}
		
		$serverid=intval($RS['server_id']);
		$result=$this->DB->query("select * from ".$this->Tbl['set_server']." where  server_stat>2  and server_id=".$serverid,1);
		if(!empty($result)){
			return '当前所在服务器暂停续费！';
		}
		return '';
	}
	
	//查询订单总个数、过期或快过期、最后一个到期订单
	function get_order_total($client_id=0){
		$where='';
		if($client_id>0){
			$where=" and client_id=".$client_id;
		}
		$ret=array();
		$__result=$this->DB->query("select count(*) as num from ".$this->Tbl['vm_order'].' where vm_stat<2 and user_id='.intval($this->userID).$where,1);
		$__orderOpenNum=intval($__result['num']);

		$_endtime=date("Y-m-d H:i:s",time()+3*24*3600);
		$__result=$this->DB->query("select count(*) as num from ".$this->Tbl['vm_order'].' where vm_stat<2 and vm_enddate<'.$this->DB->getsql($_endtime).' and user_id='.intval($this->userID).$where,1);
		$__orderEndNum=intval($__result['num']);

		$__result=$this->DB->query("select vm_enddate from ".$this->Tbl['vm_order'].' where user_id='.intval($this->userID).$where." order by vm_enddate desc",1);
		$__enddate=$__result['vm_enddate'];	

		$ret['order_num_all']=intval($__orderOpenNum);
		$ret['order_num_over']=intval($__orderEndNum);
		$ret['order_enddate']=empty($__enddate)?'':$__enddate;
		return $ret;
	}
	
	function get_order_last_price($orderid){
		$sql="select * from ".$this->Tbl['vm_last_price'];
		$sql.=" where vm_id=".intval($orderid);
		
		return $this->DB->query($sql,1);
	}
	
	function add_order_last_price($orderid,$period,$unit){
		$sql="replace into ".$this->Tbl['vm_last_price']." set ";
		$sql.="vm_id=".intval($orderid);
		$sql.=",period=".intval($period);
		$sql.=",period_unit=".intval($unit);
		$sql.=",last_time=NOW()";
		$ret = $this->DB->query($sql);
		$retArr = array();
		if($ret){
			$retArr['result'] = 0;
		}else{
			$retArr['result'] = 1;
		}
		return $retArr;
	}
}

?>
