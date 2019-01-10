<?php
/*
	财务或积分处理对象

	作用：负责财务和积分数据的读取、修改和写入，所有涉及表的写操作均使用本对象的方法，不建议用方法外的SQL对数据库进行写操作
	
	+ 2017-7-27 实际基本结构及代码
*/


class FUND{

	protected $_ishash=0; 
	protected $_regdate='';//用户注册时间
	protected $_type=0;  //0财务 1积分
	protected $_year=0;
	protected $_balance=0;//当年可用余额
	protected $_last=0; //可结转的余额

	protected $DB;

	public $_userid=0;
	public $_error='';
	public $_typeArr=''; //类型字段

	public $_info=Array();

	public function FUND($DB,$userid,$fundtype=0,$year=0){
		$this->DB=$DB;
		if($fundtype!=1) $fundtype=0;
		$this->_type=$fundtype;
		$this->_userid=intval($userid);
		$year=intval($year);
		if($year<2001 or $year>=2100) $year=date('Y');
		$this->_year=intval($year);
		$this->_typeArr=Array();


	}

	//定义表名

	//@$name:表别名
	//@返回：字符串（值为表名，如果为空，则说明别名$name对应的表不存在）
	protected function gettable($name){

		if(empty($this->_year) or empty($name)){
			return '';
		}
		$tbl_config=Array();
		if($this->_type==0){
			$tbl_config['fund'] ="fund";
			$tbl_config['fund_history']="fund_history";
			$tbl_config['fund_detail'] ="fund_detail".($this->_ishash ? $this->_year:'');
		}
		else {
			$tbl_config['fund'] ="funds";
			$tbl_config['fund_history']="funds_history";
			$tbl_config['fund_detail'] ="funds_detail".($this->_ishash ? $this->_year:'');
		}
		if(isset($tbl_config[$name])){
			return $tbl_config[$name];
		}
		return '';
	}

	//创建财务表 $year最大支持2329年，本系统限制为2000至2099
	//@返回：bool
	public function createtable(){

		if(empty($this->_ishash)) return false;

		//如果不哈希本方法以下代码直接删除掉
		$year=intval($this->_year);
		if($year<2001 or $year>=2100){
			return false;
		}

		//2017 => 117 ; Max 2328 => 428
		//2018-2042 ( 24year)
		$tag=$year-2000;

		#
		#fund :金额
		#fund_date：实际交易日期（报表以此为准）；积分为生效日期（精确到天即可）
		#add_time:  录入日期
		#site_tag:  主控平台标志（一个会员平台可接多个主控，开展多种业务）
		#order_id:  消费时为订单编号；付款时为支付方式编号
		#order_initdate：消费记录为操作之前订单有效时间
		#
		$sql="CREATE TABLE `fund_detail{$year}` (
  `fund_id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) NOT NULL default '0',
  `fund` decimal(8,2) default '0.00',
  `fund_stat` tinyint(1) NOT NULL default '0',
  `fund_type` tinyint(3) NOT NULL default '0',
  `fund_date` date NOT NULL default '0000-00-00',
  `fund_remark` varchar(255) default NULL,
  `site_tag` varchar(5) default NULL,
  `order_id` int(10) NOT NULL default '0',
  `order_initdate` datetime NOT NULL default '0000-00-00 00:00:00',
  `order_enddate` datetime NOT NULL default '0000-00-00 00:00:00',
  `add_user` int(10) NOT NULL default '0',
  `add_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `check_user` int(10) NOT NULL default '0',
  `check_time` int(10) NOT NULL default '0',
  PRIMARY KEY  (`fund_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB CHARSET=latin1 AUTO_INCREMENT={$tag}00010001";

		$ret=$this->DB->query($sql);
		if($ret){
			return true;
		}
		else {
			$this->_error=$this->DB->err_msg();
			return false;
		}
	}

	//读取余额
	//@$gettype: 0 余额  1 积分
	//@返回：数值型（值为用户余额，带两位小数）
	public function balance($gettype=-1){

		$this->_balance=0;//初始化归0
		if(empty($this->_year)) return 0;
		if(empty($this->_userid)) return 0;
		$type=$this->_type;
		if($gettype==0 or $gettype==1){
			$this->_type=$gettype;
		}
		$RS=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid),1);
		if(empty($RS)) {
			
			$errCode=0+$this->DB->err_code();
			if($errCode>0){
				return 0;
			}

			$ret=$this->reload_check();
			if($ret) {
				//自动更新缓存
				$rets=$this->reload_cache($RS);
				if($rets){
					return $this->_balance; //返回计算出来的余额
				}
			}
			return 0;
		}


		#@fund表说明：
		#`fund_last` decimal(10,2) default '0.00', # 上年财务结余（不含赠送款，如果是积分，则不再结转到下一年）
		#`fund_pay` decimal(10,2) default '0.00',  # 当年真实付款总和 fund_type<2 （0 付款 1 退款  求和会自动扣除退款）
		#`fund_gift` decimal(8,2) default '0.00',  # 当年赠送款       fund_type=2（赠送款仅限当年使用，过期作废。积分则为下一年内有效）
		#`fund_used` decimal(10,2) default '0.00', # 当年消费款总和   fund_type>2 ，正常为负数。包括订单退费，退费会抵销对应的消费额

		$fund = 0 + $RS['fund_last'] + $RS['fund_pay'] + $RS['fund_gift'] + $RS['fund_used'];
		if($this->_type==1 and $fund<0.1) $fund=0; //积分小于0.1不能使用
		$fund=number_format($fund, 2, '.', '');
		$this->_balance=$fund;
		return $fund;
	}

	//付款或积分报酬或退款(fund_type必须小于10)
	//@固定参数：fund:金额；fund_type：类型;fund_date:付款或交易日期（积分为生效时间）;add_time：录入时间；add_user：录入人，自动入款的记作0；remark：备注（可用空）
	//@其它专用参数：order_id:支付方式编号;
	//@返回：bool
	public function payment($data){
		$type=intval($data['fund_type']);
		if($type>=10) return false;
		$sql="insert into ".$this->gettable("fund_detail");
		$sql=$sql." (user_id,fund,fund_type,fund_date,";
		$sql=$sql.",order_id,add_time,add_user";
		$sql=$sql.(!empty($data['remart'])?",fund_remark":"").") values (";

		$sql=$sql.intval($this->_userid).",".$this->formatNum($data['fund']).",".intval($type).",".$this->DB->getsql($data['fund_date'])."";
		$sql=$sql.",".intval($data['order_id']).",".$this->DB->getsql($data['add_time']).",".$this->DB->getsql($data['add_user'],1);
		$sql=$sql.(!empty($data['remart'])?",".$this->DB->getsql($data['remart']):"");
		$sql=$sql.")";
		$ret=$this->DB->query($sql);
		//处理错误
		$errCode=0+$this->DB->err_code();
		if($errCode>0){
			$this->_error=$this->DB->err_msg();
			if($this->_year==date('Y') and $errCode==1146){
				//如果是当年的则自动创建表
				$res=$this->createtable();
				if($res){
					usleep(1000);
					return $this->DB->query($sql);
				}
			}
			return false;
		}
		return $ret;
	}


	//消费（fund_type必须>=10，正常为负数，可为正数(退款)）
	//@固定参数：参考payment()
	//@专用参数：site_tag:平台标志(专有参数)
	//@专用参数：order_id:订单编号;initdate:购买或续费的起始时间（操作前的有效时间）；enddate：操作后的有效时间
	//@返回：bool
	public function spend($data){
	
		$type=intval($data['fund_type']);
		if($type<10) return false;

		$sql="insert into ".$this->gettable("fund_detail");
		$sql=$sql." (user_id,fund,fund_type,fund_date,";
		$sql=$sql.",order_id,add_time,add_user";
		$sql=$sql.",site_tag,initdate,enddate";
		$sql=$sql.(!empty($data['remart'])?",fund_remark":"").") values (";

		$sql=$sql.intval($this->_userid).",".$this->formatNum($data['fund']).",".intval($type).",".$this->DB->getsql($data['fund_date'])."";
		$sql=$sql.",".intval($data['order_id']).",".$this->DB->getsql($data['add_time']).",".$this->DB->getsql($data['add_user'],1);
		$sql=$sql.",".$this->DB->getsql($data['site_tag']).",".$this->DB->getsql($data['initdate']).",".$this->DB->getsql($data['enddate']);
		$sql=$sql.(!empty($data['remart'])?",".$this->DB->getsql($data['remart']):"");
		$sql=$sql.")";
		$ret=$this->DB->query($sql);
		//处理错误
		$errCode=0+$this->DB->err_code();
		if($errCode>0){
			$this->_error=$this->DB->err_msg();
			if($this->_year==date('Y') and $errCode==1146){
				//如果是当年的则自动创建表
				$res=$this->createtable();
				if($res){
					usleep(1000);
					return $this->DB->query($sql);
				}
			}
			return false;
		}
		return $ret;
	}

	
	//检查是否需要更新
	//@return true需要更新缓存
	//@返回：bool
	public function reload_check(){
		usleep(2000);
		$_nowDate=date('Y-m-d');
		$RS=$this->DB->query("select * from ".$this->gettable("fund_detail")." where user_id=".intval($this->_userid)." and fund_stat=0 ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)).($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")."  limit 1",1);
		if(empty($RS)) {
			$year=$this->_year;
			$this->_year=$year-1;//查询上一年
			usleep(2000);
			$RS=$this->DB->query("select * from ".$this->gettable("fund_detail")." where user_id=".intval($this->_userid)." and fund_stat=0 ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)).($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")." limit 1",1);
			$this->_year=$year;//还原
			if(!empty($RS)){
				return true;
			}
			return false;
		}

		return true;
	}

	//更新缓存
	//@返回：bool
	public function reload_cache($RS,$level=0){

		//SELECT fund_type,sum(fund) as funds,count(*) as rows FROM `site_fund_y*` WHERE user_id=100001 and fund_stat=0  group by fund_type


		if(empty($userid)) $userid=$this->_userid;

		//查询上年付款情况（用于计算fund_last值）
		if((empty($RS) or $RS['fund_uptime']==0) and $level<1){
			$year=$this->_year;
			//查询并更新上一年值，并获得上年结转余额（当年数据中需要此值）
			$this->_year=$year-1;
			$RSS=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid),1);
			if(empty($RSS)) $RSS['fund_last']=0;
			$this->reload_cache($RSS,1);
			usleep(50000);
			$RS['fund_last']=$this->_last;
			$this->_year=$year;
		}

		$this->_balance=0;
		$this->_last=0;

		//查询当年付款情况
		$_nowDate=date('Y-m-d');
		$result=$this->DB->query("SELECT fund_type,sum(fund) as funds,count(*) as rows FROM ".$this->gettable("fund_detail")." WHERE user_id=".intval($userid)." and fund_stat=0  ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)." ").($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")." group by fund_type",2);
		//处理错误
		$errCode=0+$this->DB->err_code();
		if($errCode>0){
			$this->_error=$this->DB->err_msg();
			if($this->_year==date('Y') and $errCode==1146){
				//如果是当年的则自动创建表
				$this->createtable();
				usleep(10000);
			}
			return false;
		}


		//pay:真实付款（已扣除退款）,gift：赠送款; used：消费款; rows:财务总记录数量
		//@fund_type(余额): 0 真实付款; 1 退款; 2  赠送款  3 转出给其它账号;  ; other(>9):消费
		//@fund_type(积分): 0 非消费或推荐免费赠送 1 自己消费赠送;  2 推荐他人买收费赠送; 3 转出给其它账号 other(>9):消费
		$_data=Array('pay'=>0,'gift'=>0,'used'=>0,'rows'=>0);
		foreach($result as $arr){
			$_type=$arr['fund_type'];
			if($_type==2){
				$_data['gift']=$_data['gift']+$arr['funds'];
			}
			else if($_type<5){
				$_data['pay']=$_data['pay']+$arr['funds'];
			}
			else{
				$_data['used']=$_data['used']+$arr['funds'];
			}
			$_data['rows']=$_data['rows']+$arr['rows'];
		}

		//积分不要gift值
		if($this->_type==1) {
			$_data['pay']=$_data['pay']+$_data['gift'];
			$_data['gift']=0;
		}

		//写入缓存表
		$sql="replace into ".$this->gettable("fund")." set ";
		$sql=$sql." fund_year=".intval($this->_year);
		$sql=$sql.",user_id="  .intval($this->_userid);
		$sql=$sql.",fund_last=".$this->formatNum($RS['fund_last']);
		$sql=$sql.",fund_pay=" .$this->formatNum($_data['pay']);
		$sql=$sql.",fund_gift=".$this->formatNum($_data['gift']);
		$sql=$sql.",fund_used=".$this->formatNum($_data['used']);
		$sql=$sql.",fund_rows=".intval($_data['rows']);
		$sql=$sql.",fund_uptime=".intval(time());
		$this->DB->query($sql);
		$errCode=0+$this->DB->err_code();
		if($errCode>0){
			$this->_error=$this->DB->err_msg();
			return false;
		}

		//计算可用余额
		$fund=0 + $RS['fund_last'] + $_data['pay'] + $_data['gift'] + $_data['used'];
		if($this->_type==1 and $fund<0.1) $fund=0; //积分小于0.1不能使用
		$fund=number_format($fund, 2, '.', '');
		$this->_balance=$fund;

		//计算可结转到下一年的余额
		$__last=0;
		//账户积分
		if($this->_type==1){

			//last =20 -10  ; 
			//上年结转的积分，当年用不完不转到下一年（如果是负数，也不结转）
			$__fund=$RS['fund_last'];
			$__pay =$_data['pay'];//收入：充值或退款或转款出账号
			$__used=$_data['used'];//支出：使用或退费到账号

			//非消费型支出大于收入情况（比如退款，转让给其它人）
			if($__pay<0){
				//用上年余额扣除
				$__fund=$__fund+$__pay;
				$__pay=0;
			}
			//上一年购买的，当年产生退款到账号的情况
			if($__used>0){
				//计到上一年余额中去
				$__fund=$__fund+$__used;
				$__used=0;
			}

			//上一年有欠费
			if($__fund<0){
				//先用当年收入抵扣：不够时，欠费清0不结转
				$__last=$__pay+$__fund;
				if($__last<0) $__last=0;
				//收入扣除消费后转结（可以为负数）
				$__last=$__last+$__used;
			}
			else {
				//先扣上年的余额，若未用完，清0不结转
				$__last=$__fund+$__used;
				if($__last>0) $__last=0; 
				//当年收入扣除消费后转结（可以为负数）；$__last<=0
				$__last=$__pay+$__last;
			}

		}
		//账户余额
		else{

			//@1 真实付款(充值)一直有效；
			//@2 赠送款不跨年，当年未用完清0。赠送款之和若为负数，也清0
			//充值若够用($__last>0)，则只结转充值未用完的部分。(fund_last为上一年未用完的充值款)
			$__last=$RS['fund_last']+$_data['pay']+$_data['used'];
			//充值不够用时，才用赠送款抵扣(且赠送款之和大于0才使用)
			if($__last<0 and $_data['gift']>0){
				$__last=$_data['gift']+$__last;
				if($__last>0) $__last=0;//当年赠送款未用完清0，不转到下一年；若有欠款则需要结转
			}

		}

		$__last=number_format($__last, 2, '.', '');
		$this->_last=$__last;
		return true;
	}


	//用户查询列表
	//@返回：二维数组;  (Array)$this->_fundinfo：用户当年余额信息及分页信息
	public function query($type,$year,$isAdmin=0,&$page){

		if($type!=1) $type=0;
		$form=$this->form();
		$_k=$form('_k');//搜索关键字
		$_f=$form('_f');//搜索项
		$_s=$this->formatNum($form('_s'));//状态
		$_t=$this->formatNum($form('_t'));//分类

		$sql=" where 1 ";
		if(empty($this->_ishash)) $sql=$sql." and left(fund_date,4)=".intval($this->_year);
		$_orderby="order by fund_date desc,fund_id desc";
		if(!empty($this->_userid)){
			$rows=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid),1);
		}
		else if($isAdmin==1){
			$_orderby="order by fund_id desc";
		}

		$findarr=Array();

		//管理员可搜索
		if($isAdmin==1){

			$findArr['user']   =Array(0,'会员编号','==@','user_id',"","");
			$findArr['fid']    =Array(0,'流水号','==@','fund_id',"","");
			$findArr['order']  =Array(0,'订单号','==@','order_id',"","");
			$findArr['fund']   =Array(0,'金额',  '==@','fund',"","");
			$findArr['fund5']  =Array(5,'金额',  '≥','fund',"","");
			$findArr['fund6']  =Array(6,'金额',  '≤','fund',"","");
			$findArr['fund7']  =Array(7,'金额',  '[@]','fund',"","");
			//$findArr['fund8']  =Array(8,'金额',  '=@=','fund',"","");
			$findArr['date']   =Array(2,'结算时间','＝','fund_date',"","");

			$findArr['adduser']=Array(1,'录入人编号','≈','add_user',"","");
			$findArr['addtime']=Array(1,'录入时间','≈','add_time',"","");
			$findArr['remart'] =Array(1,'备注',  '≈','fund_remark',"","");

			//自定义搜索
			if(!empty($_k) and !empty($_f)) {
				$sql=$sql.searchSQL($_k,$_f,$findArr);
			}
			//分类($_s=-1或为空显示所有)
			if(!empty($_s)) {
				if($_s>=0){
					$sql=$sql." and fund_type=".intval($_s);
				}
			}
		}
		else{
			//会员必须用$this->_userid值，会员不提供搜索
			$sql=$sql." and user_id=".intval($this->_userid);
			$sql=$sql." and fund_stat=0";
		}
		$info=Array('page'=>0,'pagenum'=>0,'rowsnum'=>0);
		$info['rowsnum']=0;
		if($isAdmin==1){
			$res=$this->DB->query("SELECT count(*) as rowsnum FROM ".$this->gettable("fund_detail")."  $sql",1);
			$info=Array('page'=>0,'pagenum'=>0,'rowsnum'=>0);
			$info['rowsnum']=0+$res['rowsnum'];//所有记录数
		}
		else{
			$info['rowsnum']=0+$rows['fund_rows'];//有效的记录数
		}
		$psize=abs($this->DB->pageSize);
		if($psize<0 or $psize>300) $psize=20;
		if($res){
			$info['fund']     =number_format(0 + $rows['fund_last'] + $rows['fund_pay'] + $rows['fund_gift'] + $rows['fund_used'], 2, '.', '');
			$info['fund_last']=number_format(0 + $rows['fund_last'], 2, '.', '');
			$info['fund_pay'] =number_format(0 + $rows['fund_pay'], 2, '.', '');
			$info['fund_gift']=number_format(0 + $rows['fund_gift'], 2, '.', '');
			$info['fund_used']=number_format(0 + $rows['fund_used'], 2, '.', '');
		}
		$limit='';
		if($psize<1) {
			$info['pagenum']=1;
			$page=1;
		}
		else{
			$info['pagenum']= ceil($info['rowsnum']/$psize);
			if($page > $info['pagenum'] or $page < 1) $page=1;
			$limit=' limit '.(($page-1)*$psize).','.$psize;
		}
		$info['page']=$page;

		$_sql ="SELECT  *  FROM ".$this->gettable("fund_detail")."  $sql $_orderby $limit";
		$result=$this->DB->query($_sql,2);  //_page($page,"*",$this->gettable("fund_detail"),$sql," order by fund_id desc");
		$ret=Array();
		$ret['pagetitle']='财务管理';
		$ret['pageinfo']=$info;
		if($isAdmin==1){
			$ret['_find']=$findArr;
			$ret['_sort']=Array($this->_typeArr);
		}

		$ret['data']=$result;
		$ret['remark']='使用说明';
		return $ret;
	}

	//充值或真实退款表单
	public function add_from(){
		
	}

	//save充值或真实退款
	public function add_save($data){
		$form=$this->form();
	}

	//扣费或订单退费到账号表单
	public function sub_from(){
	}

	//save扣费或订单退费到账号subtract
	public function sub_save($data){
		$form=$this->form();
	}

	//修改表单
	public function edit_from($id){
		$form=$this->form();
	}

	//save修改(只能修改7天内添加的)
	public function edit_save($id,$data){
		$form=$this->form();
	}

	//删除(只能删除7天内添加的)，不允许真实删除
	//@返回：bool
	public function fund_del($id){
		$ret=$this->DB->query("update ".$this->gettable("fund_detail")." set fund_stat=4 where find_id=".formatNum($id));
		return $ret;
	}

	//恢复(只能修改7天内添加的)，与fund_del对应
	//@返回：bool
	public function fund_resume($id){
		$ret=$this->DB->query("update ".$this->gettable("fund_detail")." set fund_stat=0 where find_id=".formatNum($id));
		return $ret;
	}

	//处理搜索项（通用）
	//+ 2017-8-17
	//@返回SQL 
	protected function searchSQL($_k,$_f,$findArr){

		//@$findArr=Array();定义搜索项
		//Array(0类型,"1显示名称","2搜索符号","3对应字段名称(有附表时为附表字段)","4附表别名","5关联字段或第二字段")
		//类型说明:0 ==@绝对等于（限数值型，可以用@搜索两个值） 1 ≈全模糊搜索 2 ＝自定义规则模糊搜索(适用字符型) 3 ≌正则搜索 4 ≠反向自定义规则模糊搜索
		//      5 ≥大于等于（限数值型）  6 ≤小于等于（限数值型） 7 [@]从A到B（限数值型,用@分隔）  8 =@= 双字段搜索（限数值型）
		//      9 == 智能搜索（关联搜索限搜索主表且绝对等于，请搜索可自定义规则） 默认为对应字段自定义规则模糊搜索; 关联字段为数组，格式为： Array(array("/^[1-9][0-9]{0,9}\$/i","user_id",1),array("/^[1-9][0-9]{10}\$/i","user_mobile",0));

		$_sql='';
		if(!empty($_k) and !empty($_f) and isset($findArr[$_f])){

			$_tmp='';
			$fArr=$findArr[$_f];

			$fArr[3]=preg_replace('/[^-_0-9a-z]/i','',trim($fArr[3]));
			$_fname=isset($fArr[5]) ? preg_replace('/[^-_0-9a-z]/i','',trim($fArr[5])):'';

			if($fArr[0]==0) {
				$tmp=explode("@",$_k);
				$tmp[0]=0+$this->formatNum($tmp[0]);
				$tmp[1]=isset($tmp[1])?(0+$this->formatNum($tmp[1])):'';
				if(strlen($tmp[0])>0){
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp." IN (".$tmp[0].", ".$tmp[1].")";
					}
					else {
						$_tmp=$_tmp." = ".$this->formatNum($tmp[0]);
					}
				}
			}
			else if($fArr[0]==1) $_tmp=$_tmp." like ".$this->DB->getsql('%'.$_k.'%');
			else if($fArr[0]==2) $_tmp=$_tmp." like ".$this->DB->getsql($_k);
			else if($fArr[0]==3) $_tmp=$_tmp." rlike ".$this->DB->getsql(''.$_k.'');
			else if($fArr[0]==4) $_tmp=$_tmp." not like ".$this->DB->getsql(''.$_k.'');
			else if($fArr[0]==5) $_tmp=$_tmp." >= ".$this->formatNum($_k);
			else if($fArr[0]==6) $_tmp=$_tmp." <= ".$this->formatNum($_k);
			else if($fArr[0]==7) {
				$tmp=explode("@",$_k);
				$tmp[0]=0+$this->formatNum($tmp[0]);
				$tmp[1]=isset($tmp[1])?(0+$this->formatNum($tmp[1])):'';
				if(strlen($tmp[0])>0 and strlen($tmp[1])>0 and $tmp[1]>=$tmp[0] ) {
					$_tmp=$_tmp." >= ".$this->formatNum($tmp[0]) ." and {$fArr[3]} <= ".$this->formatNum($tmp[1]);
				}
			}
			else if($fArr[0]==8 and !empty($_fname)){
				$tmp=explode("@",$_k);
				$tmp[0]=0+$this->formatNum($tmp[0]);
				$tmp[1]=isset($tmp[1])?(0+$this->formatNum($tmp[1])):'';
				if(strlen($tmp[0])>0){
					$_tmp=$_tmp." = ".$this->formatNum($tmp[0]);
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp." and {$fArr[5]} = ".$this->formatNum($tmp[1]);
					}
				}
			}
			else if($fArr[0]==9){
				if(isset($fArr[5]) and is_array($fArr[5]))
				{
					foreach($fArr[5] as $arr){
						$arr[1]=preg_replace('/[^-_0-9a-z]/i','',trim($arr[1]));
						$type = ($arr[2]==1?1:0);
						if(empty($arr[1])) continue;
						if(@preg_match($arr[0],$_k)){
							$_tmp=$_tmp." and {$arr[1]} =".$this->DB->getsql($_k,$type);
							return $_tmp;
							//break;
						}
					}
				}
				//默认
				$_tmp=$_tmp." like ".$this->DB->getsql($_k);
			}

			if(!empty($_tmp) and !empty($fArr[3])){

				$_tablename=isset($fArr[4]) ? $this->gettable($fArr[4]):'';

				if(!empty($_tablename) and !empty($_fname)){
					$_sql=$_sql." and $_fname IN (select  $_fname from $_tablename where {$fArr[3]} $_tmp )";
				}
				else{
					$_sql=$_sql." and {$fArr[3]} $_tmp ";
				}
			}
		}
		return $_sql;
	}

	//处理数值用于写入数据表
	protected function formatNum($value){
		return preg_replace('/[^-.0-9]/','',$value);
	}

	//获得POST数据
	protected function form(){
		$rval=array_merge($_GET,$_POST);
		if (get_magic_quotes_gpc()) {
			$this->stripVar($rval);
		}
		return $rval;
	}

	protected function stripVar(&$val)
	{
		if(is_array($val))
		{
			foreach($val as $key => $v)
			{
				$this->stripVar($val[$key]);
			}
		}
		else
		{
			$val=StripSlashes($val);
		}
	}
}

?>