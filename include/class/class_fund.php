<?php
/*
	财务或积分数据处理对象（不包括显示部分）

	作用：负责财务和积分数据表的录入、余额查询、扣款等，所有涉及表的写操作均使用本对象的方法，非财务核心数据表用子类来实现
	注意：不建议用本对象以外的SQL对数据库进行直接的写操作。计划任务生成报表调用本对象的方法即可。

	+ 2017-7-27 实际基本结构及代码

*/


class FUND{

	//通用
	public $_isAPI=0;//0 local ; 1 API
	public $_para='';//view转向或链接需要的参数
	public $_data='';//请求时提供的数据


	protected $_ishash=1; 
	protected $_regdate='';//用户注册时间
	protected $_type=0;  //0财务 1积分
	protected $_year=0;
	protected $_set=Array(); //默认设置
	protected $_editdate='';


	protected $DB;
	protected $P;

	public $_userid=0;
	public $_isadmin=0;
	public $_error='';
	public $_typeArr=''; //类型字段
	public $_balance=0;//当年可用余额
	public $_discount=0;//当前用户可享受的折扣
	public $_RS;
	protected $_last=0; //可结转的余额

	public $_info=Array();

	public function __construct($_initSet) {
		return $this->FUND($_initSet);
	}


	public function FUND($_initSet){

		$this->_set=$_initSet;

		//定义实例化时需要的参数
		if(isset($_initSet['DB']))      $this->DB=$_initSet['DB'];
		if(isset($_initSet['user_id'])) $this->_userid=intval($_initSet['user_id']);
		if(isset($_initSet['isadmin'])) $this->_isadmin=($_initSet['isadmin']?1:0);
		if(isset($_initSet['fundtype'])) $this->_type=intval($_initSet['fundtype']);
		if(isset($_initSet['year']))    $this->_year=intval($_initSet['year']);

		if($this->_year<2001 or $this->_year>=2100) $this->_year=date('Y');
		$this->_year=intval($this->_year);
		$this->_typeArr=Array();
		//@fund_type(余额): 0 真实付款; 1 退款;  2 测试(停用，改为5) 3 从他人账号转入 4 转出给其它账号; 5 管理赠送 ; other(>9):消费
		//@fund_type(积分): 0 非消费获得 1 自己消费赠送;  2 推荐他人消费赠送; 3 转入; 4 转出; 5 管理赠送 other(>9):消费
		if($this->_type==1){
			$this->_typeArr[0]='赠送积分';
			$this->_typeArr[1]='消费赠送积分';
			$this->_typeArr[2]='推荐赠送积分';
			$this->_typeArr[3]='其他账号转入';
			$this->_typeArr[4]='转出到其他账号';
			$this->_typeArr[5]='赠送测试积分';
		}
		else{
			$this->_typeArr[0]='付款';
			$this->_typeArr[1]='退款';
			$this->_typeArr[2]='测试款';
			$this->_typeArr[3]='其他账号转入';
			$this->_typeArr[4]='转出到其他账号';
			$this->_typeArr[5]='赠送测试款';
		}

		$this->_typeArr[10]='其它消费';
		$this->_typeArr[21]='订单购买';
		$this->_typeArr[22]='订单续费';
		$this->_typeArr[23]='订单升级';
		$this->_typeArr[24]='订单退费';
		$this->_typeArr[25]='其它消费';

		$this->_editdate=date('Y-m-d H:i:s',time()-7*24*3600);// 录入时间在此之后的允许修改
		$this->_editdateShow=date('Y-m-d H:i:s',time()-7*24*3600+120);
	
	}


	//定义表名
	//@$name:表别名
	//@返回：字符串（值为表名，如果为空，则说明别名$name对应的表不存在）
	protected function gettable($name){

		if(empty($this->_year) or empty($name)){
			return '';
		}
		$tbl_config=Array();
		//@user_info为用户表（用以下字段缓存余额及付款时间，用作管理查询及检查是否更新，不直接用作余额值）
		//用户表记录：user_fund 余额  user_fund_date 余额最后更新时间 user_pay_date 支付时间
		// user_funds 积分余额 user_funds_date 积分最后更新时间 user_fund_update缓存数据更新标记
		$tbl_config['user_info']    = 'site_user_info';//用户资料表
		$tbl_config['site_cp']    = 'site_cp';       //设置主控平台
		//以上为非财务核心表，但有辅助需要

		//基本表
		if($this->_type==0){
			$tbl_config['fund'] ="fund"; //余额表，直接使用此值作用户余额
			$tbl_config['fund_detail'] ="fund_detail".($this->_ishash ? $this->_year:'');
			$tbl_config['fund_history']="fund_history";
			$tbl_config['fund_date']   ="fund_report_date";
			$tbl_config['fund_user']   ="fund_report_user"; //报表，不直接使用
		}
		else {
			$tbl_config['fund'] ="funds";
			$tbl_config['fund_detail'] ="funds_detail".($this->_ishash ? $this->_year:'');
			$tbl_config['fund_history']="funds_history";
			$tbl_config['fund_date']   ="funds_report_date";
			$tbl_config['fund_user']   ="funds_report_user";
		}

		//付款及优惠表
		$tbl_config['paylog']     = 'fundset_paylog';   //用户支付记录
		$tbl_config['paymode']    = 'fundset_paymode';   //支付方式
		$tbl_config['paynotify']  = 'fundset_paynotify'; //支付接口返回记录
		$tbl_config['fundcard']   = 'fundset_card';       //充值卡
		$tbl_config['fundcode']   = 'fundset_code';       //优惠码(一般不提供此方法)
		$tbl_config['fundcodelog']= 'fundset_code_log';   //优惠码使用记录(一般不提供此方法)
		$tbl_config['funddiscount']= 'fundset_discount';   //设置会员折扣
		$tbl_config['fundsale']   = 'fundset_sale';       //设置优惠活动

		$tbl_config['paymode']    = 'site_paymode';   //支付方式(测试用)

		//返回表名
		if(isset($tbl_config[$name])){
			return $tbl_config[$name];
		}
		return '';
	}

	//创建财务表
	//@返回：bool
	public function createtable(){

		if(empty($this->_ishash)) return false;

		//如果不哈希本方法以下代码直接删除掉
		$year=intval($this->_year);
		if($year<2001 or $year>=2100){
			return false;
		}

		//2018-2039 ( 21year)
		$tag=0+($year-2000)%40;

		#
		#fund :金额
		#fund_date：实际交易日期（报表以此为准）；或积分生效日期（精确到天即可）
		#add_time:  录入日期
		#fund_pay_id: 付款时为支付方式编号(4位)；消费时为平台编号(3位)
		#order_id:  消费时为订单编号(正数)； 付款时为支付记录编号（负数.以免查询时混乱）；
		#order_initdate：入款记录不需要；   消费记录为操作之前订单有效时间及到期时间(order_enddate)
		#

		$sql="CREATE TABLE `fund_detail{$year}` (
  `fund_id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) NOT NULL default '0',
  `fund` decimal(8,2) default '0.00',
  `fund_stat` tinyint(1) NOT NULL default '0',
  `fund_type` tinyint(3) NOT NULL default '0',
  `fund_date` date NOT NULL default '0000-00-00',
  `fund_remark` varchar(255) default NULL,
  `fund_pay_id` SMALLINT(4) NOT NULL default '0',
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

	//查询单条记录
	public function getRecord(){

		$id=getpost('id',1);
		//不重复查询
		if(!empty($this->_RS) and $this->_RS['fund_id']==$id){
			return $this->_RS;
		}

		$sql="select * from  ".$this->gettable("fund_detail");
		$sql=$sql." where fund_id=".intval($id);
		if($this->_userid>0){
			$sql=$sql." and user_id=".intval($this->_userid);
		}
		$RS=$this->DB->query($sql,1);
		$this->_RS=$RS;
		return $RS;
	}


	//读取余额
	//@$gettype: 0 余额  1 积分
	//@返回：数值型（值为用户余额，带两位小数）
	public function balance(){

		$this->_balance=0;//初始化
		if(empty($this->_year)) return 0;
		if(empty($this->_userid)) return 0;

		//同步用户可享受的折扣
		if($this->_type==0){
			$res=$this->DB->query("select * from ".$this->gettable("discount")." where user_id=".intval($this->_userid),1);
			if($res){
				$this->_discount=0+$res['user_discount'];
				if($this->_discount<0.5 or $this->_discount>1) $this->_discount=0;
			}
		}

		$RS=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid),1);
		if(empty($RS)) {

			$errCode=0+$this->DB->err_code();
			if($errCode>0){
				return 0;
			}

			$fundkey=($this->_type==1?'user_funds':'user_fund');
			//确定没有支付
			if(!empty($uinfo) and isset($uinfo[$fundkey]) and $uinfo[$fundkey]===0){
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

	//充值,付款,退款或积分报酬或退款(fund_type必须小于10)
	//@固定参数：fund:金额；fund_type：类型;fund_date:付款或交易日期（积分为生效时间）;add_time：录入时间；add_user：录入人，自动入款的记作0；remark：备注（可用空）
	//@其它专用参数：order_id:支付方式编号;
	//@返回：bool
	//入款大于50的，写入funds_report_user表
	public function payment($data){

		$type=intval($data['fund_type']);
		if($type>=10) return false;
		$sql="insert into ".$this->gettable("fund_detail");
		$sql=$sql." (user_id,fund,fund_type,fund_date,fund_pay_id";
		$sql=$sql.",order_id,add_time,add_user";
		$sql=$sql.(!empty($data['fund_remark'])?",fund_remark":"").") values (";

		$sql=$sql.intval($this->_userid).",".$this->DB->formatNum($data['fund']).",".intval($type).",".$this->DB->getsql($data['fund_date']).",".$this->DB->getsql($data['fund_pay_id'],1);
		$sql=$sql.",".intval(0-$data['order_id']).",".$this->DB->getsql($data['add_time']).",".$this->DB->getsql($data['add_user'],1);
		$sql=$sql.(!empty($data['fund_remark'])?",".$this->DB->getsql($data['fund_remark']):"");
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
					usleep(10000);
					//return $this->DB->query($sql);
				}
			}
			return false;
		}

		//更新余额缓存表
		usleep(10000);
		if($type==5){
			$this->DB->query("update ".$this->gettable("fund")." set fund_gift=fund_gift+".$this->DB->formatNum($data['fund'])." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid));
		}
		else {
			$this->DB->query("update ".$this->gettable("fund")." set fund_pay=fund_pay+".$this->DB->formatNum($data['fund'])." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid));
		}

		//写入用户表缓存字段
		usleep(10000);
		$fundtag='fund';
		if($this->_type==1) $fundtag='funds';
		$sql="update ".$this->gettable("user_info")." set user_{$fundtag}=user_{$fundtag}+".$this->DB->formatNum($data['fund']) .(($data['fund']>0 and $type<5)?",user_pay_date=".$this->DB->getsql(date("Y-m-d")):"").",user_{$fundtag}_date=".$this->DB->getsql(date("Y-m-d"))." where user_id=".intval($this->_userid);
		$this->DB->query($sql);

		//更新当月缓存表
		if($type!=5){
			usleep(10000);
			$_datem=str_replace('-','',substr($data['fund_date'],0,7));
			$res=$this->DB->query("select * from ".$this->gettable("fund_user")." where fund_month=".intval($_datem)." and user_id=".intval($this->_userid),1);
			if($res){
				$sql=$sql."update ".$this->gettable("fund_user")." set fund_pay=fund_pay+".$this->DB->formatNum($data['fund']).",fund_uptime=".intval(time())." where fund_month=".intval($_datem)." and user_id=".intval($this->_userid);
				$this->DB->query($sql);
			}
			else if($data['fund']>=50){
				//支付不明，fund_uptime=0
				$sql=$sql."insert into ".$this->gettable("fund_user")." (fund_month,user_id,fund_pay,fund_uptime) values (".intval($_datem).",".intval($this->_userid).",".$this->DB->formatNum($data['fund']).",0)";
				$this->DB->query($sql);
			}
		}

		return $ret;
	}


	//消费,使用（fund_type必须>=10，正常为负数，可为正数(退款)）
	//@固定参数：参考payment()
	//@专用参数：site_tag:平台标志(专有参数)
	//@专用参数：order_id:订单编号;initdate:购买或续费的起始时间（操作前的有效时间）；enddate：操作后的有效时间
	//@返回：bool
	public function spend($data){
	
		$type=intval($data['fund_type']);
		if($type<10) return false;

		$sql="insert into ".$this->gettable("fund_detail");
		$sql=$sql." (user_id,fund,fund_type,fund_date,fund_pay_id";
		$sql=$sql.",order_id,add_time,add_user";
		$sql=$sql.",order_initdate,order_enddate";
		$sql=$sql.(!empty($data['fund_remark'])?",fund_remark":"").") values (";

		$sql=$sql.intval($this->_userid).",".$this->formatNum($data['fund']).",".intval($type).",".$this->DB->getsql($data['fund_date']).",".$this->DB->getsql($data['fund_pay_id'],1);
		$sql=$sql.",".intval($data['order_id']).",".$this->DB->getsql($data['add_time']).",".$this->DB->getsql($data['add_user'],1);
		$sql=$sql.",".$this->DB->getsql($data['order_initdate']).",".$this->DB->getsql($data['order_enddate']);
		$sql=$sql.(!empty($data['fund_remark'])?",".$this->DB->getsql($data['fund_remark']):"");
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
					//return $this->DB->query($sql);
				}
			}
			return false;
		}


		//更新余额缓存表
		usleep(10000);
		$this->DB->query("update ".$this->gettable("fund")." set fund_used=fund_used+".$this->DB->formatNum($data['fund'])." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid));

		//写入用户表缓存字段
		usleep(10000);
		$fundtag='fund';
		if($this->_type==1) $fundtag='funds';
		$sql="update ".$this->gettable("user_info")." set user_{$fundtag}=user_{$fundtag}+".$this->DB->formatNum($data['fund']).",user_{$fundtag}_date=".$this->DB->getsql(date("Y-m-d"))." where user_id=".intval($this->_userid);
		$this->DB->query($sql);

		//更新月缓存表
		usleep(10000);
		$_datem=str_replace('-','',substr($data['fund_date'],0,7));
		$res=$this->DB->query("select * from ".$this->gettable("fund_user")." where fund_month=".intval($_datem)." and user_id=".intval($this->_userid),1);
		if($res){
			$sql=$sql."update ".$this->gettable("fund_user")." set fund_used=fund_used+".$this->DB->formatNum($data['fund']).",fund_uptime=".intval(time())." where fund_month=".intval($_datem)." and user_id=".intval($this->_userid);
			$this->DB->query($sql);
		}
		return $ret;
	}


	//删除(只能删除7天内添加的)，不允许真实删除
	//@返回：bool
	public function del($RS){
		$id=intval($RS['fund_id']);
		$tmp=$this->_year;
		$this->_year=substr($RS['fund_date'],0,4);
		$ret=$this->DB->query("update ".$this->gettable("fund_detail")." set fund_stat=4 where fund_id=".$this->DB->formatNum($id,1));
		if($ret){
			$RES=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid),1);
			$this->reload_cache($RES);
		}
		$this->_year=$tmp;
		return $ret;
	}

	//恢复(只能修改7天内添加的)，与fund_del对应
	//@返回：bool
	public function resume($RS){
		$id=intval($RS['fund_id']);
		$tmp=$this->_year;
		$this->_year=substr($RS['fund_date'],0,4);
		$ret=$this->DB->query("update ".$this->gettable("fund_detail")." set fund_stat=0 where fund_id=".$this->DB->formatNum($id,1));
		if($ret){
			$RES=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid),1);
			$this->reload_cache($RES);
		}
		$this->_year=$tmp;
		return $ret;
	}

	//修改记录
	//@返回：bool
	public function change($RS,$data){

		$id=intval($RS['fund_id']);
		$sql="update ".$this->gettable("fund_detail")." set ";
		//$sql=$sql."fund_type=".$this->DB->getsql($data['fund_type'],1);
		//@另：结算时间、金额及会员编号不能改（所以不用更新缓存）
		//$sql=$sql.",add_time=".$this->DB->getsql($data['add_time']);//录入时间不能改
		$sql=$sql." order_id=".$this->DB->getsql($data['order_id'],1);
		if($RS['fund_type']>=10){
			$sql=$sql.",order_initdate=".$this->DB->getsql($data['order_initdate']);
			$sql=$sql.",order_enddate=" .$this->DB->getsql($data['order_enddate']);
		}
		$sql=$sql.",fund_remark=".$this->DB->getsql($data['fund_remark']);
		$sql=$sql." where fund_id=".$this->DB->formatNum($id,1);
		$ret=$this->DB->query($sql);
		return $ret;

	}

	
	//检查是否需要更新
	//@return true需要更新缓存
	//@返回：bool
	public function reload_check(){
		usleep(2000);
		$_nowDate=date('Y-m-d');
		$RS=$this->DB->query("select * from ".$this->gettable("fund_detail")." where user_id=".intval($this->_userid)." and fund_stat=0 ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)).($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")."  limit 1",1);
		if(!empty($RS)){
			return true;
		}

		//查询上一年
		$year=$this->_year;
		$this->_year=$year-1;
		usleep(2000);
		$RS=$this->DB->query("select * from ".$this->gettable("fund_detail")." where user_id=".intval($this->_userid)." and fund_stat=0 ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)).($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")." limit 1",1);
		$this->_year=$year;//还原
		if(!empty($RS)){
			return true;
		}

		return false;

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
		$_nowMonth=date('Y-m');
		$sql="SELECT fund_type,sum(fund) as funds,count(*) AS rows,sum(if(left(fund_date,7)='$_nowMonth',funds,0)) AS nums";
		$sql=$sql." FROM ".$this->gettable("fund_detail")."";
		$sql=$sql." WHERE user_id=".intval($userid)." and fund_stat=0  ";
		if($this->_ishash===0) $sql=$sql." and left(fund_date,4)=".intval($this->_year);
		if($this->_type==1) $sql=$sql." and fund_date<= ".$this->DB->getsql($_nowDate);
		$sql=$sql." group by fund_type";
		$result=$this->DB->query($sql,2);
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
		$_data=Array('pay'=>0,'gift'=>0,'used'=>0,'rows'=>0,'nums'=>0);
		foreach($result as $arr){
			$_type=$arr['fund_type'];
			if($_type==5){
				$_data['gift']=$_data['gift']+$arr['funds'];
			}
			else if($_type<5){
				$_data['pay']=$_data['pay']+$arr['funds'];
			}
			else{
				$_data['used']=$_data['used']+$arr['funds'];
			}
			$_data['rows']=$_data['rows']+$arr['rows'];
			//当月实付款
			if($_type<5){
				$_data['nums']=$_data['nums']+$arr['nums'];
			}
		}


		//积分不要gift值
		if($this->_type==1) {
			$_data['pay']=$_data['pay']+$_data['gift'];
			$_data['gift']=0;
		}

		//写入用户月报表
		//用户月报表（支付额>=50，获得积分>=20）
		$sql="";
		$_datem=str_replace('-','',$_nowMonth);

		if($this->_year==date('Y') and $_data['rows']>0){
			usleep(2000);
			$res=$this->DB->query("select * from ".$this->gettable("fund_user")." where fund_month=".intval($_datem)." and user_id=".intval($this->_userid),1);

			if($res and ($this->_type==0 and $_data['nums']<50 OR $_data['nums']<20)){
				$sql=$sql."delete from  ".$this->gettable("fund_user")." where fund_month=".intval($_datem)." and user_id=".intval($this->_userid);
			}
			else if($res){
				$sql=$sql."update ".$this->gettable("fund_user")." set fund_pay=".$this->DB->formatNum($_data['nums']).",fund_uptime=0 where fund_month=".intval($_datem)." and user_id=".intval($this->_userid);
			}
			else{
				$sql=$sql."insert into ".$this->gettable("fund_user")." (fund_month,user_id,fund_pay,fund_uptime) values (".intval($_datem).",".intval($this->_userid).",".$this->DB->formatNum($_data['nums']).",0)";
			}

			//只有不同才更新
			if($res['fund_pay']!=$_data['pay']){
				$this->DB->query($sql);
			}
		}

		usleep(2000);
		//写入余额缓存表
		$sql="replace into ".$this->gettable("fund")." set ";
		$sql=$sql." fund_year=".intval($this->_year);
		$sql=$sql.",user_id="  .intval($this->_userid);
		$sql=$sql.",fund_last=".$this->DB->formatNum($RS['fund_last']);
		$sql=$sql.",fund_pay=" .$this->DB->formatNum($_data['pay']);
		$sql=$sql.",fund_gift=".$this->DB->formatNum($_data['gift']);
		$sql=$sql.",fund_used=".$this->DB->formatNum($_data['used']);
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

		usleep(2000);
		//写入用户表缓存字段
		$fundtag='fund';
		if($this->_type==1) $fundtag='funds';
		$sql="update ".$this->gettable("user_info")." set user_{$fundtag}=".$this->DB->formatNum($fund)." where user_id=".intval($this->_userid);
		$this->DB->query($sql);

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
			//付款不够用时，才用赠送款抵扣(且赠送款之和大于0才使用)
			if($__last<0 and $_data['gift']>0){
				$__last=$_data['gift']+$__last;
				if($__last>0) $__last=0;//当年赠送款未用完清0，不转到下一年；若有欠款则需要结转
			}

		}

		$__last=number_format($__last, 2, '.', '');
		$this->_last=$__last;
		return true;
	}

	//自动汇总fund_detail上一年的数据到fund表中去（积分和余额一起结算）
	//每用户每年一次。若上一年有更改需要将fund表fund_uptime设置为0
	//2-6月份每天运行一次（1月份用户登录后会自动处理）
	public function auto_cache(){
		$_datem=date('_m');
		$_date=date('Y-m-00');
		$tmp_type=$this->_type;
		$_year=0+date('Y');
		$_mindate=($_year-1).'-01-00';
		if($_datem<'_02' or $_datem>'_06') return false;
		$result=$this->DB->query("select user_id from ".$this->gettable('user_info')." where (user_fund_date>'$_mindate' OR  user_funds_date>'$_mindate') and user_fund_update!=".$_year."  limit 1000",2);

		sleep(2);
		foreach($result as $arr){

			$userid=0+$arr['user_id'];

			//余额
			$this->_type=0;
			$RS=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($_year)." and user_id=".intval($userid),1);
			$ret=$this->reload_cache($RS);
			usleep(300000);

			//积分
			$this->_type=1;
			$RS=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($_year)." and user_id=".intval($userid),1);
			$ret2=$this->reload_cache($RS);
			usleep(300000);
			if($ret and $ret2){
				$this->DB->query("update ".$this->gettable('user_info')." set user_fund_update=".intval($_year)." where user_id=$userid");
			}
		}
		$this->_type=$tmp_type;
	}

	//更新用户月付款和消费报表。（统计当月和上月的。积分和余额分开算，因此要执行两次）
	//赠送的不计在内，因此使用额可能大于支付额
	public function report_user(){

		$_t=strtotime('Y-m-01 00:00:00')-48*3600;
		$_month=0+date('Ym',$_t);
		$_date1=date('Y-m-01',$_t);
		$_date2=date('Y-m-31',$_t);

		$result=$this->DB->query("select user_id from ".$this->gettable('fund_user')." where fund_month>='$_month' and fund_uptime=0   limit 1000",2);
		sleep(1);
		foreach($result as $arr){
			$userid=$arr['user_id'];
			$sql  = "select sum(if(fund_type<5,fund,0)) as fund_pay";
			$sql .= ",sum(if(fund_type>9 and fund_type<29,fund,0)) as fund_use";
			$sql .= " from " . $this->gettable('fund_detail');
			$sql .= " where user_id=".intval($userid)." and fund_stat=0 ";
			$sql .= " and fund_date>=" . $this->DB->getsql ( $_date1 );
			$sql .= " and fund_date<=" . $this->DB->getsql ( $_date2 );
			$row = $this->DB->query ( $sql, 1 );
			if($row){
				$pays=0+$row['fund_pay'];
				$uses=0+$row['fund_use'];
				$this->DB->query("update ".$this->gettable('fund_user')." SET fund_pay=".$this->DB->formatNum($pays).",fund_used=".$this->DB->formatNum($uses).",fund_uptime=".intval(time())." where user_id=".intval($userid)." and fund_month=$_month");
			}
			usleep(200000);
		}
		return true;
	}

	//生成每天总收支报表。从fund_detail读取前四天的数据生成报表并保存到report中（积分和余额分开算，因此要执行两次）
	//每天一次
	public function report_date($type=0){
		$type=intval($type);
		if($type!=0 and $type!=1) return false;

		$tmp_type=$this->_type;
		$this->_type=$type;

		$_date1 = date ( "Y-m-d", time()-4*24*3600);
		$_date2 = date ( "Y-m-d" );
		if($this->_type==0){
			$sql  = "select sum(if(fund_type IN(0,3),fund,0)) as fund_all";
			$sql .= ",sum(if(fund_type IN(1,4),fund,0)) as fund_back";
		}
		else{
			$sql  = "select sum(if(fund_type<4,fund,0)) as fund_all";
			$sql .= ",sum(if(fund_type=4,fund,0)) as fund_back";
		}

		$sql .= ",sum(if(fund_type=21,fund,0)) as fund_use_new";
		$sql .= ",sum(if(fund_type=22,fund,0)) as fund_use_renew";
		$sql .= ",sum(if(fund_type>22 and fund_type<29,fund,0)) as fund_use_other";
		$sql .= ",fund_date as fdate ";
		$sql .= " from " . $this->gettable('fund_detail');
		$sql .= " where fund_stat=0";
		$sql .= " and fund_date>=" . $this->DB->getsql ( $_date1 );
		$sql .= " and fund_date<" . $this->DB->getsql ( $_date2 );
		$sql .= " group by fdate";
		
		$rot = $this->DB->query ( $sql, 2 );
		
		if (empty ( $rot )){
			return false;
		}

		sleep ( 1 );
		
		foreach ( $rot as $key => $value ) {
			$sql = "replace into " . $this->gettable('fund_date') . " set ";
			$sql .= "fund_date=" . $this->DB->getsql ( $value ['fdate'] );
			$sql .= ",fund_all=" . round ( $value ['fund_all'],2);
			$sql .= ",fund_use_new=" . round ( $value ['fund_use_new'] ,2);
			$sql .= ",fund_use_renew=" . round ( $value ['fund_use_renew'],2);
			$sql .= ",fund_use_other=" . round ( $value ['fund_use_other'],2);
			$sql .= ",fund_back=" . round ( $value ['fund_back'],2);
			$this->DB->query ( $sql );
			usleep ( 2000 );
		}
		$this->_type=$tmp_type;
		return true;
	}

	//清理过期数据（比如paynotify表和paylog表）
	//每天一次
	public function clear(){

		$this->DB->query("delete from ".$this->gettable("paynotify")." where log_date<".$DB->getsql(date('Y-m-d 00:00:00',time()-366*24*3600))." limit 3000");
		sleep(2);
		$this->DB->query("delete from ".$this->gettable("paylog")." where pay_addtime<".$DB->getsql(date('Y-m-d 00:00:00',time()-732*24*3600))." limit 3000");
		sleep(2);
		$this->DB->query("delete from ".$this->gettable("fundcode")." where code_adddate<".$DB->getsql(date('Y-m-d 00:00:00',time()-732*24*3600))." limit 1000");
		sleep(2);
		$this->DB->query("delete from ".$this->gettable("fundcodelog")." where log_time<".$DB->getsql(date('Y-m-d 00:00:00',time()-732*24*3600))." limit 1000");
		sleep(2);
		$_month=0+date('Ym');
		$tmp_type=$this->_type;
		$this->_type=0;
		$this->DB->query("delete from ".$this->gettable("fund_user")." where fund_month<".$DB->getsql($_month,1)." and fund_pay<200 limit 1000");
		sleep(2);
		$this->_type=1;
		$this->DB->query("delete from ".$this->gettable("fund_user")." where fund_month<".$DB->getsql($_month,1)." and fund_pay<30 limit 1000");
		$this->_type=$tmp_type;
	}
}

?>