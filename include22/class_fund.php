<?php
/*
	�������ִ������

	���ã��������ͻ������ݵĶ�ȡ���޸ĺ�д�룬�����漰���д������ʹ�ñ�����ķ������������÷������SQL�����ݿ����д����
	
	+ 2017-7-27 ʵ�ʻ����ṹ������
*/


class FUND{

	protected $_ishash=0; 
	protected $_regdate='';//�û�ע��ʱ��
	protected $_type=0;  //0���� 1����
	protected $_year=0;
	protected $_balance=0;//����������
	protected $_last=0; //�ɽ�ת�����

	protected $DB;

	public $_userid=0;
	public $_error='';
	public $_typeArr=''; //�����ֶ�

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

	//�������

	//@$name:�����
	//@���أ��ַ�����ֵΪ���������Ϊ�գ���˵������$name��Ӧ�ı����ڣ�
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

	//��������� $year���֧��2329�꣬��ϵͳ����Ϊ2000��2099
	//@���أ�bool
	public function createtable(){

		if(empty($this->_ishash)) return false;

		//�������ϣ���������´���ֱ��ɾ����
		$year=intval($this->_year);
		if($year<2001 or $year>=2100){
			return false;
		}

		//2017 => 117 ; Max 2328 => 428
		//2018-2042 ( 24year)
		$tag=$year-2000;

		#
		#fund :���
		#fund_date��ʵ�ʽ������ڣ������Դ�Ϊ׼��������Ϊ��Ч���ڣ���ȷ���켴�ɣ�
		#add_time:  ¼������
		#site_tag:  ����ƽ̨��־��һ����Աƽ̨�ɽӶ�����أ���չ����ҵ��
		#order_id:  ����ʱΪ������ţ�����ʱΪ֧����ʽ���
		#order_initdate�����Ѽ�¼Ϊ����֮ǰ������Чʱ��
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

	//��ȡ���
	//@$gettype: 0 ���  1 ����
	//@���أ���ֵ�ͣ�ֵΪ�û�������λС����
	public function balance($gettype=-1){

		$this->_balance=0;//��ʼ����0
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
				//�Զ����»���
				$rets=$this->reload_cache($RS);
				if($rets){
					return $this->_balance; //���ؼ�����������
				}
			}
			return 0;
		}


		#@fund��˵����
		#`fund_last` decimal(10,2) default '0.00', # ���������ࣨ�������Ϳ����ǻ��֣����ٽ�ת����һ�꣩
		#`fund_pay` decimal(10,2) default '0.00',  # ������ʵ�����ܺ� fund_type<2 ��0 ���� 1 �˿�  ��ͻ��Զ��۳��˿
		#`fund_gift` decimal(8,2) default '0.00',  # �������Ϳ�       fund_type=2�����Ϳ���޵���ʹ�ã��������ϡ�������Ϊ��һ������Ч��
		#`fund_used` decimal(10,2) default '0.00', # �������ѿ��ܺ�   fund_type>2 ������Ϊ���������������˷ѣ��˷ѻ������Ӧ�����Ѷ�

		$fund = 0 + $RS['fund_last'] + $RS['fund_pay'] + $RS['fund_gift'] + $RS['fund_used'];
		if($this->_type==1 and $fund<0.1) $fund=0; //����С��0.1����ʹ��
		$fund=number_format($fund, 2, '.', '');
		$this->_balance=$fund;
		return $fund;
	}

	//�������ֱ�����˿�(fund_type����С��10)
	//@�̶�������fund:��fund_type������;fund_date:����������ڣ�����Ϊ��Чʱ�䣩;add_time��¼��ʱ�䣻add_user��¼���ˣ��Զ����ļ���0��remark����ע�����ÿգ�
	//@����ר�ò�����order_id:֧����ʽ���;
	//@���أ�bool
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
		//�������
		$errCode=0+$this->DB->err_code();
		if($errCode>0){
			$this->_error=$this->DB->err_msg();
			if($this->_year==date('Y') and $errCode==1146){
				//����ǵ�������Զ�������
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


	//���ѣ�fund_type����>=10������Ϊ��������Ϊ����(�˿�)��
	//@�̶��������ο�payment()
	//@ר�ò�����site_tag:ƽ̨��־(ר�в���)
	//@ר�ò�����order_id:�������;initdate:��������ѵ���ʼʱ�䣨����ǰ����Чʱ�䣩��enddate�����������Чʱ��
	//@���أ�bool
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
		//�������
		$errCode=0+$this->DB->err_code();
		if($errCode>0){
			$this->_error=$this->DB->err_msg();
			if($this->_year==date('Y') and $errCode==1146){
				//����ǵ�������Զ�������
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

	
	//����Ƿ���Ҫ����
	//@return true��Ҫ���»���
	//@���أ�bool
	public function reload_check(){
		usleep(2000);
		$_nowDate=date('Y-m-d');
		$RS=$this->DB->query("select * from ".$this->gettable("fund_detail")." where user_id=".intval($this->_userid)." and fund_stat=0 ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)).($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")."  limit 1",1);
		if(empty($RS)) {
			$year=$this->_year;
			$this->_year=$year-1;//��ѯ��һ��
			usleep(2000);
			$RS=$this->DB->query("select * from ".$this->gettable("fund_detail")." where user_id=".intval($this->_userid)." and fund_stat=0 ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)).($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")." limit 1",1);
			$this->_year=$year;//��ԭ
			if(!empty($RS)){
				return true;
			}
			return false;
		}

		return true;
	}

	//���»���
	//@���أ�bool
	public function reload_cache($RS,$level=0){

		//SELECT fund_type,sum(fund) as funds,count(*) as rows FROM `site_fund_y*` WHERE user_id=100001 and fund_stat=0  group by fund_type


		if(empty($userid)) $userid=$this->_userid;

		//��ѯ���긶����������ڼ���fund_lastֵ��
		if((empty($RS) or $RS['fund_uptime']==0) and $level<1){
			$year=$this->_year;
			//��ѯ��������һ��ֵ������������ת��������������Ҫ��ֵ��
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

		//��ѯ���긶�����
		$_nowDate=date('Y-m-d');
		$result=$this->DB->query("SELECT fund_type,sum(fund) as funds,count(*) as rows FROM ".$this->gettable("fund_detail")." WHERE user_id=".intval($userid)." and fund_stat=0  ".($this->_ishash ? "":" and left(fund_date,4)=".intval($this->_year)." ").($this->_type==1 ? " and fund_date<= ".$this->DB->getsql($_nowDate):"")." group by fund_type",2);
		//�������
		$errCode=0+$this->DB->err_code();
		if($errCode>0){
			$this->_error=$this->DB->err_msg();
			if($this->_year==date('Y') and $errCode==1146){
				//����ǵ�������Զ�������
				$this->createtable();
				usleep(10000);
			}
			return false;
		}


		//pay:��ʵ����ѿ۳��˿,gift�����Ϳ�; used�����ѿ�; rows:�����ܼ�¼����
		//@fund_type(���): 0 ��ʵ����; 1 �˿�; 2  ���Ϳ�  3 ת���������˺�;  ; other(>9):����
		//@fund_type(����): 0 �����ѻ��Ƽ�������� 1 �Լ���������;  2 �Ƽ��������շ�����; 3 ת���������˺� other(>9):����
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

		//���ֲ�Ҫgiftֵ
		if($this->_type==1) {
			$_data['pay']=$_data['pay']+$_data['gift'];
			$_data['gift']=0;
		}

		//д�뻺���
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

		//����������
		$fund=0 + $RS['fund_last'] + $_data['pay'] + $_data['gift'] + $_data['used'];
		if($this->_type==1 and $fund<0.1) $fund=0; //����С��0.1����ʹ��
		$fund=number_format($fund, 2, '.', '');
		$this->_balance=$fund;

		//����ɽ�ת����һ������
		$__last=0;
		//�˻�����
		if($this->_type==1){

			//last =20 -10  ; 
			//�����ת�Ļ��֣������ò��겻ת����һ�꣨����Ǹ�����Ҳ����ת��
			$__fund=$RS['fund_last'];
			$__pay =$_data['pay'];//���룺��ֵ���˿��ת����˺�
			$__used=$_data['used'];//֧����ʹ�û��˷ѵ��˺�

			//��������֧��������������������˿ת�ø������ˣ�
			if($__pay<0){
				//���������۳�
				$__fund=$__fund+$__pay;
				$__pay=0;
			}
			//��һ�깺��ģ���������˿�˺ŵ����
			if($__used>0){
				//�Ƶ���һ�������ȥ
				$__fund=$__fund+$__used;
				$__used=0;
			}

			//��һ����Ƿ��
			if($__fund<0){
				//���õ�������ֿۣ�����ʱ��Ƿ����0����ת
				$__last=$__pay+$__fund;
				if($__last<0) $__last=0;
				//����۳����Ѻ�ת�ᣨ����Ϊ������
				$__last=$__last+$__used;
			}
			else {
				//�ȿ����������δ���꣬��0����ת
				$__last=$__fund+$__used;
				if($__last>0) $__last=0; 
				//��������۳����Ѻ�ת�ᣨ����Ϊ��������$__last<=0
				$__last=$__pay+$__last;
			}

		}
		//�˻����
		else{

			//@1 ��ʵ����(��ֵ)һֱ��Ч��
			//@2 ���Ϳ���꣬����δ������0�����Ϳ�֮����Ϊ������Ҳ��0
			//��ֵ������($__last>0)����ֻ��ת��ֵδ����Ĳ��֡�(fund_lastΪ��һ��δ����ĳ�ֵ��)
			$__last=$RS['fund_last']+$_data['pay']+$_data['used'];
			//��ֵ������ʱ���������Ϳ�ֿ�(�����Ϳ�֮�ʹ���0��ʹ��)
			if($__last<0 and $_data['gift']>0){
				$__last=$_data['gift']+$__last;
				if($__last>0) $__last=0;//�������Ϳ�δ������0����ת����һ�ꣻ����Ƿ������Ҫ��ת
			}

		}

		$__last=number_format($__last, 2, '.', '');
		$this->_last=$__last;
		return true;
	}


	//�û���ѯ�б�
	//@���أ���ά����;  (Array)$this->_fundinfo���û����������Ϣ����ҳ��Ϣ
	public function query($type,$year,$isAdmin=0,&$page){

		if($type!=1) $type=0;
		$form=$this->form();
		$_k=$form('_k');//�����ؼ���
		$_f=$form('_f');//������
		$_s=$this->formatNum($form('_s'));//״̬
		$_t=$this->formatNum($form('_t'));//����

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

		//����Ա������
		if($isAdmin==1){

			$findArr['user']   =Array(0,'��Ա���','==@','user_id',"","");
			$findArr['fid']    =Array(0,'��ˮ��','==@','fund_id',"","");
			$findArr['order']  =Array(0,'������','==@','order_id',"","");
			$findArr['fund']   =Array(0,'���',  '==@','fund',"","");
			$findArr['fund5']  =Array(5,'���',  '��','fund',"","");
			$findArr['fund6']  =Array(6,'���',  '��','fund',"","");
			$findArr['fund7']  =Array(7,'���',  '[@]','fund',"","");
			//$findArr['fund8']  =Array(8,'���',  '=@=','fund',"","");
			$findArr['date']   =Array(2,'����ʱ��','��','fund_date',"","");

			$findArr['adduser']=Array(1,'¼���˱��','��','add_user',"","");
			$findArr['addtime']=Array(1,'¼��ʱ��','��','add_time',"","");
			$findArr['remart'] =Array(1,'��ע',  '��','fund_remark',"","");

			//�Զ�������
			if(!empty($_k) and !empty($_f)) {
				$sql=$sql.searchSQL($_k,$_f,$findArr);
			}
			//����($_s=-1��Ϊ����ʾ����)
			if(!empty($_s)) {
				if($_s>=0){
					$sql=$sql." and fund_type=".intval($_s);
				}
			}
		}
		else{
			//��Ա������$this->_useridֵ����Ա���ṩ����
			$sql=$sql." and user_id=".intval($this->_userid);
			$sql=$sql." and fund_stat=0";
		}
		$info=Array('page'=>0,'pagenum'=>0,'rowsnum'=>0);
		$info['rowsnum']=0;
		if($isAdmin==1){
			$res=$this->DB->query("SELECT count(*) as rowsnum FROM ".$this->gettable("fund_detail")."  $sql",1);
			$info=Array('page'=>0,'pagenum'=>0,'rowsnum'=>0);
			$info['rowsnum']=0+$res['rowsnum'];//���м�¼��
		}
		else{
			$info['rowsnum']=0+$rows['fund_rows'];//��Ч�ļ�¼��
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
		$ret['pagetitle']='�������';
		$ret['pageinfo']=$info;
		if($isAdmin==1){
			$ret['_find']=$findArr;
			$ret['_sort']=Array($this->_typeArr);
		}

		$ret['data']=$result;
		$ret['remark']='ʹ��˵��';
		return $ret;
	}

	//��ֵ����ʵ�˿��
	public function add_from(){
		
	}

	//save��ֵ����ʵ�˿�
	public function add_save($data){
		$form=$this->form();
	}

	//�۷ѻ򶩵��˷ѵ��˺ű�
	public function sub_from(){
	}

	//save�۷ѻ򶩵��˷ѵ��˺�subtract
	public function sub_save($data){
		$form=$this->form();
	}

	//�޸ı�
	public function edit_from($id){
		$form=$this->form();
	}

	//save�޸�(ֻ���޸�7������ӵ�)
	public function edit_save($id,$data){
		$form=$this->form();
	}

	//ɾ��(ֻ��ɾ��7������ӵ�)����������ʵɾ��
	//@���أ�bool
	public function fund_del($id){
		$ret=$this->DB->query("update ".$this->gettable("fund_detail")." set fund_stat=4 where find_id=".formatNum($id));
		return $ret;
	}

	//�ָ�(ֻ���޸�7������ӵ�)����fund_del��Ӧ
	//@���أ�bool
	public function fund_resume($id){
		$ret=$this->DB->query("update ".$this->gettable("fund_detail")." set fund_stat=0 where find_id=".formatNum($id));
		return $ret;
	}

	//���������ͨ�ã�
	//+ 2017-8-17
	//@����SQL 
	protected function searchSQL($_k,$_f,$findArr){

		//@$findArr=Array();����������
		//Array(0����,"1��ʾ����","2��������","3��Ӧ�ֶ�����(�и���ʱΪ�����ֶ�)","4�������","5�����ֶλ�ڶ��ֶ�")
		//����˵��:0 ==@���Ե��ڣ�����ֵ�ͣ�������@��������ֵ�� 1 ��ȫģ������ 2 ���Զ������ģ������(�����ַ���) 3 ���������� 4 �ٷ����Զ������ģ������
		//      5 �ݴ��ڵ��ڣ�����ֵ�ͣ�  6 ��С�ڵ��ڣ�����ֵ�ͣ� 7 [@]��A��B������ֵ��,��@�ָ���  8 =@= ˫�ֶ�����������ֵ�ͣ�
		//      9 == �����������������������������Ҿ��Ե��ڣ����������Զ������ Ĭ��Ϊ��Ӧ�ֶ��Զ������ģ������; �����ֶ�Ϊ���飬��ʽΪ�� Array(array("/^[1-9][0-9]{0,9}\$/i","user_id",1),array("/^[1-9][0-9]{10}\$/i","user_mobile",0));

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
				//Ĭ��
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

	//������ֵ����д�����ݱ�
	protected function formatNum($value){
		return preg_replace('/[^-.0-9]/','',$value);
	}

	//���POST����
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