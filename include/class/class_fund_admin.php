<?php
/*
	财务或积分数据管理对象（显示及管理）

	作用：用于查看和管理数据
	
*/


class FUND_ADMIN extends FUND{


	private $_payMode=Array();
	private $_payModeType=Array(
		0=>Array('title'=>'线下付款','note'=>'除在线支付及银行转账之外的方式，比如邮局汇款、客户上门付款、上门收款等（请在[备注]中填写地址及邮编等信息）')
		,1=>Array('title'=>'银行转账','note'=>'通过银行转账付款，这种方式手工查账并入款（请填写[开户户名]及[开户账号]）')
		,2=>Array('title'=>'在线支付','note'=>'比如通过支付宝等在线支付的方式，这种方式自动入款（请填写[开户户名]，并在保留存后在[修改]中设置在线支付相关代码）')
	);


	public function main($method=""){

		$ret=Array();
		$method=trim($method);
		if(empty($method)){
			$ret['result']=1;
			$ret['msg']='method is required!';
		}

		if(!method_exists($this,$method)){
			$ret['result']=1;
			$ret['msg']='Undefined method ($method)!';
		}

		$ret=call_user_func(array($this,$method));
		return $ret;

	}

	//菜单显示设置
	public function menuSet(){

		$menu=Array();
		$menu['result']=0;
		$menu['pagetitle']='';
		$menu['type']='conf';
		$menu['menuhead']=Array();//用于设置主菜单（暂时不确定是否需要）
		if($this->_type==1) $title='积分';
		else $title='财务';

		$menu['menuconf']=Array('name'=>$title.'管理','version'=>'v1.00.20170913','update'=>'20170913','cmd'=>'query');
		$menu['menu']=Array(

			Array('cmd'=>'query','title'=>$title.'管理','type'=>'menu','menu'=>'')
			,Array('cmd'=>'fundadd','title'=>'付款/退款录入','type'=>'','menu'=>'query')
			,Array('cmd'=>'fundadd_save','title'=>'付款/退款录入','type'=>'','menu'=>'query')
			,Array('cmd'=>'fundsub','title'=>'消费/退费录入','type'=>'','menu'=>'query')
			,Array('cmd'=>'fundsub_save','title'=>'付款/退款录入','type'=>'','menu'=>'query')
			,Array('cmd'=>'fundedit','title'=>$title.'记录修改','type'=>'','menu'=>'query')
			,Array('cmd'=>'fundedit_save','title'=>$title.'记录修改','type'=>'','menu'=>'query')
			,Array('cmd'=>'fund_del','title'=>$title.'记录删除','type'=>'','menu'=>'query')
			,Array('cmd'=>'fund_resume','title'=>$title.'记录恢复','type'=>'','menu'=>'query')

			,Array('cmd'=>'paymode','title'=>'付款方式设置','type'=>'menu','menu'=>'setpaymode')
			,Array('cmd'=>'paylog','title'=>'在线支付记录','type'=>'menu','menu'=>'paylog')

			,Array('cmd'=>'setcp','title'=>'平台设置','type'=>'menu','menu'=>'setcp')
			//,Array('cmd'=>'querys','title'=>'积分管理','type'=>'menu','menu'=>'')
			//,Array('cmd'=>'paylog','title'=>'支付记录','type'=>'menu','menu'=>'')
		);

		return $menu;
		
	}

	//用户查询列表
	//@返回：二维数组;  (Array)$this->_fundinfo：用户当年余额信息及分页信息
	public function query(){

		$form=$this->_data;
		$page=intval($form['_page']);
		$size=intval($form['_size']);
		$this->_para='';
		$_k=(isset($form['_k'])?$form['_k']:'');//搜索关键字
		$_f=(isset($form['_f'])?$form['_f']:'');//搜索项

		$sql=" where 1 ";
		if(empty($this->_ishash)) $sql=$sql." and left(fund_date,4)=".intval($this->_year);
		$_orderby="order by fund_date desc,fund_id desc";
		if(!empty($this->_userid)){
			$funds=$this->DB->query("select * from ".$this->gettable("fund")." where fund_year=".intval($this->_year)." and user_id=".intval($this->_userid),1);
		}
		else if($this->_isadmin==1){
			$_orderby="order by fund_id desc";
		}

		//搜索
		$findArr=Array();
		//分类
		$sortArr=Array();

		//管理员可搜索
		//$paymodeResult=$this->DB->query("select * from ".$this->gettable("paymode")." where 1",2);
		$this->get_paymode();
		$paySet=Array();
		$paySet[]=Array("title"=>"--- 所有来源 ---","value"=>"");
		foreach($this->_payMode as $arr){
			$paySet[]=Array("title"=>get_html($arr['title']),"value"=>intval($arr['id']));
		}
		$result=$this->DB->query("select * from ".$this->gettable("site_cp")." where 1",2);
		$cpArr=Array();
		foreach($result as $arr){
			$paySet[]=Array("title"=>get_html('[消费] @ '.$arr['cp_title']),"value"=>intval($arr['cp_id']));
			$cpArr[$arr['cp_id']]=$arr['cp_order_url'];
		}

		if($this->_isadmin==1){

			/*
			$userSet=Array(
				array("/^[1-9][0-9]{0,9}\$/i","user_id",1)
				,array("/^1[3-9][0-9]{9}\$/i","user_mobile",0)
			);
			$findArr['user']   =Array("type"=>15,'title'=>'会员账号','field'=>'user_name','set'=>$userSet);
			*/
			$findArr['userid'] =Array("type"=>1,'title'=>'会员编号','field'=>'user_id');
			$findArr['order']  =Array("type"=>1,'title'=>'订单号','field'=>'order_id');
			$findArr['fid']    =Array("type"=>1,'title'=>'流水号','field'=>'fund_id');
			$findArr['fund']   =Array("type"=>0,'title'=>'金额',  'field'=>'fund');

			$findArr['date']   =Array("type"=>10,'title'=>'结算时间','field'=>'fund_date');

			$findArr['adduser']=Array("type"=>1,'title'=>'录入人编号','field'=>'add_user');
			$findArr['addtime']=Array("type"=>10,'title'=>'录入时间','field'=>'add_time');
			$findArr['remart'] =Array("type"=>13,'title'=>'备注',  'field'=>'fund_remark');

			//自定义搜索
			//_k =关键字 _f=搜索字段
			if(!empty($_k) and !empty($_f) and !empty($findArr)) {
				$sql=$sql.$this->getSearchSQL($_k,$_f,$findArr);
			}

			$payType=Array();
			$payType[]=Array("title"=>"---所有类型---","value"=>"");
			foreach($this->_typeArr as  $_tmp => $_name){
				$payType[]=Array("title"=>get_html($_name),"value"=>intval($_tmp));
			}
			$sortArr[]=Array('name'=>'_t','field'=>'fund_type','type'=>1,'set'=>$payType);
			if($this->_type!=1){
				$sortArr[]=Array('name'=>'_payid','field'=>'fund_pay_id','type'=>1,'set'=>$paySet);
				if(!empty($sortArr)){
					$sql=$sql.$this->getSortSQL($form,$sortArr);
				}
			}

		}
		else {
			//会员必须用$this->_userid值，会员不提供搜索
			$sql=$sql." and user_id=".intval($this->_userid);
			$sql=$sql." and fund_stat=0";
			$sql=$sql." and fund_date<=".$this->DB->getsql(date('Y-m-d'));
		}

		if($funds){
			$info['fund']     =number_format(0 + $funds['fund_last'] + $funds['fund_pay'] + $funds['fund_gift'] + $funds['fund_used'], 2, '.', '');
			$info['fund_last']=number_format(0 + $funds['fund_last'], 2, '.', '');
			$info['fund_pay'] =number_format(0 + $funds['fund_pay'], 2, '.', '');
			$info['fund_gift']=number_format(0 + $funds['fund_gift'], 2, '.', '');
			$info['fund_used']=number_format(0 + $funds['fund_used'], 2, '.', '');
		}

		$querySet=Array();
		$querySet['field']='*';
		$querySet['where']=$sql;
		$querySet['group']='';
		$querySet['order']=$_orderby;
		$querySet['page'] =$page;
		if($size>0 and in_array($size,Array(10,20,30,50,100))) $this->DB->pageSize = $size;

		$this->DB->setTable($this->gettable("fund_detail"));
		$result=$this->DB->select($querySet);

		$ret=Array();
		$ret['pagetitle']='财务管理';
		$ret['pageinfo']=$this->DB->pageInfo;
		$ret['type']='list';
		$conf=Array();
		$conf['pageset']='10,20,30,50,100';
		if($this->_isadmin==1){
			$conf['searchtitle']='财务搜索：';
			$conf['searchsubmit']='搜索';
			$conf['searchfind']=$findArr;
			$conf['searchsort']=$sortArr;
		}
		$conf['headheight']=26;
		$conf['rowheight']=26;
		$conf['addstring']=' &nbsp;  <a href="{pagename}_cmd=fund_add">添加入款/退款记录</a>  &nbsp;  <a href="{pagename}_cmd=fund_sub">添加消费/退费记录</a>';
		$ret['pageconf']=$conf;

		$headSet=Array();
		if($this->_isadmin==1){
			$headSet[] =Array("name"=>"del","title"=>"删除","width"=>"4%","align"=>"");
			$headSet[] =Array("name"=>"edit","title"=>"编辑","width"=>"4%","align"=>"");

			$headSet[] =Array("name"=>"id","title"=>"流水号","width"=>"7%","align"=>"");
			$headSet[] =Array("name"=>"fund","title"=>"金额","width"=>"7%","align"=>"right");
			$headSet[] =Array("name"=>"fund_type","title"=>"类型","width"=>"10%","align"=>"");
			$headSet[] =Array("name"=>"order_id","title"=>"订单号","width"=>"7%","align"=>"");

			$headSet[] =Array("name"=>"user_id","title"=>"会员编号","width"=>"6%","align"=>"");

			$headSet[] =Array("name"=>"fund_date","title"=>"结算时间","width"=>"7%","align"=>"");
			$headSet[] =Array("name"=>"add_time","title"=>"录入时间","width"=>"13%","align"=>"");
			$headSet[] =Array("name"=>"remark","title"=>"[录入者]备注(起止时间)","width"=>"*%","align"=>"left");
		}
		else{
			$headSet[] =Array("name"=>"id","title"=>"流水号","width"=>"9%","align"=>"");
			$headSet[] =Array("name"=>"fund","title"=>"金额","width"=>"9%","align"=>"right");
			$headSet[] =Array("name"=>"fund_type","title"=>"类型","width"=>"13%","align"=>"");
			$headSet[] =Array("name"=>"order_id","title"=>"订单号","width"=>"9%","align"=>"");
			$headSet[] =Array("name"=>"fund_date","title"=>"结算时间","width"=>"9%","align"=>"");
			//$headSet[] =Array("name"=>"add_time","title"=>"录入时间","width"=>"16%","align"=>"");
			$headSet[] =Array("name"=>"remark","title"=>"备注(起止时间)","width"=>"*%","align"=>"left");
		}

		$ret['listhead']=$headSet;
		$data=Array();
		foreach($result as $arr){
			$row=Array();
			if($this->_isadmin==1){
				if($arr['add_time']>$this->_editdateShow){
					if($arr['fund_stat']==4){
						$row['del']='<a href="{pagename}&_cmd=fund_resume&id='.$arr['fund_id'].'">恢复</a>';
					}
					else{
						$row['del']='<a href="{pagename}&_cmd=fund_del&id='.$arr['fund_id'].'" onclick="return confirm(\'您确定要删除此记录吗？\');">删除</a>';
					}

					$row['edit']='<a href="{pagename}&_cmd=fund_edit&id='.$arr['fund_id'].'">修改</a>';
				}
				else{
					$row['del']='-';
					$row['edit']='-';
				}
			}

			$row['id']=$arr['fund_id'];
			$row['fund']=($arr['fund']>0?'+'.$arr['fund']:$arr['fund']);
			$row['fund_type']='-';
			//显示支付方式
			if($arr['fund_pay_id']>0 and $arr['fund_type']==0){
				if(isset($this->_payMode[$arr['fund_pay_id']])){
					$row['fund_type']=get_html($this->_payMode[$arr['fund_pay_id']]['title']);
				}
			}
			//显示类型
			else if(isset($this->_typeArr[$arr['fund_type']])){
				$row['fund_type']=get_html($this->_typeArr[$arr['fund_type']]);
			}

			$row['order_id']='-';
			//订单号
			if($arr['order_id']>1) {
				$row['order_id']=$arr['order_id'];
				$payid=intval($arr['fund_pay_id']);
				if($payid>0 and isset($cpArr[$payid]) and !empty($cpArr[$payid])){
					$row['order_id']='<a href="'.$cpArr[$payid].$arr['order_id'].'" target="_blank">'.$arr['order_id'].'</a>';
				}
			}
			//支付方式ID
			else if($arr['order_id']<0){
				$row['order_id']=abs($arr['order_id']);
			}

			if($this->_isadmin==1){
				$row['user_id']=($arr['user_id']>1?'<a href="{pagename}&_menu=user&id='.$arr['user_id'].'" target="_blank">'.$arr['user_id'].'</a>':$arr['user_id']);//加链接
			}
			$row['fund_date']=$arr['fund_date'];
			$row['add_time']=$arr['add_time'];
			$row['remark']=($arr['add_user']>0?"[<b>".$arr['add_user']."</b>] ":"").get_html($arr['fund_remark']).($arr['fund_type']>=10?" (从".$arr['order_initdate']."至".$arr['order_enddate'].")":'');
			$data[]=$row;
		}
		$ret['listdata']=$data;
		$ret['remark']='使用说明';
		return $ret;
	}

	//恢复
	public function fund_resume(){
		$this->getRecord();
		$RS=$this->_RS;

		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$ret['jumpcmd']='query';
		if($RS['add_time']<$this->_editdateShow){
			$ret['result']=1;
			$ret['msg']='7天前的记录不能修改';
			return $ret;
		}

		$res=$this->resume($RS);
		if(!$res){
			$ret['result']=1;
			$ret['msg']='更新数据表出错！';
		}
		return $ret;
	}

	//删除
	public function fund_del(){
		
		$this->getRecord();
		$RS=$this->_RS;
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$ret['jumpcmd']='query';
		if($RS['add_time']<$this->_editdateShow){
			$ret['result']=1;
			$ret['msg']='7天前的记录不能修改';
			return $ret;
		}
		$res=$this->del($RS);
		if(!$res){
			$ret['result']=1;
			$ret['msg']='更新数据表出错！';
		}
		return $ret;
	}
	
	//充值或真实退款表单
	public function fund_add(){
		$this->get_paymode();

		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['pagetitle']='支付或退款录入';
		$ret['type']='form';

		$ret['formhead']=Array();
		$ret['formhead'][]=Array('title'=>$ret['pagetitle'],'savecmd'=>'fund_add_save','submitstr'=>'保存','tdwidth'=>15,'backstr'=>'返回','backcmd'=>'query');

		$data=Array();
		$data[]=Array('name'=>'user_id','title'=>'会员编号','type'=>'view','value'=>$this->_set['user_id']);
		$data[]=Array('name'=>'','title'=>'会员账号','type'=>'view','value'=>$this->_set['user_name']);
		$data[]=Array('name'=>'add_user','title'=>'','type'=>'hidden','value'=>'');
		$data[]=Array('name'=>'add_time','title'=>'','type'=>'hidden','value'=>date('Y-m-d H:i:s'));

		if($this->_type!=1){
			$paySet=Array();
			$paySet[]=Array('value'=>0,'option'=>'--- 请选择 ---','selected'=>(empty($form['fund_addid'])?1:0));
			foreach  ($this->_payMode as $_v => $arr) {
				if(strlen($form['fund_pay_id'])>0 and $form['fund_pay_id']==$_v) $sele=1;
				$title=($arr['stat']>0?'[关闭]'.$arr['title']:'['.$_v.']'.$arr['title']);
				$paySet[]=Array('value'=>$_v,'option'=>$title,'selected'=>$sele);
			}
			$data[]=Array('name'=>'fund_pay_id','title'=>'入款来源','type'=>'select','value'=>$paySet);
		}
		$typeSet=Array();
		$n=0;
		foreach($this->_typeArr as $_t => $_name){
			if($_t<10){
				if($this->_type==0 and $_t==2) continue;
				$sele=0;
				if(strlen($form['fund_type'])>0 and $form['fund_type']==$_t or $n==0 and !isset($form['fund_addid'])) $sele=1;
				$typeSet[]=Array('value'=>$_t,'option'=>'['.$_t.']'.$_name,'selected'=>$sele,'remark'=>'<br>');
				$n++;
			}
		}

		if(empty($form['fund_date'])) $form['fund_date']=date('Y-m-d');
		$data[]=Array('name'=>'fund_type','title'=>'类别','type'=>'radio','value'=>$typeSet);
		$data[]=Array('name'=>'fund'     ,'title'=>'金额','type'=>'text','value'=>get_html($form['fund']),'size'=>9,'width'=>'54px','limit'=>'-999999.99&999999.99','note'=>'请填写-999999.99至999999.99之间的数字' ,'preg'=>'^-?[0-9]{1,6}(\.[0-9]{1,2})?$','error'=>'格式不对,必须填写-999999.99至999999.99之间的数字');
		$data[]=Array('name'=>'fund_date','title'=>'结算日期','type'=>'text','value'=>get_html($form['fund_date']),'preg'=>'^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?)$','size'=>10,'width'=>'80px','note'=>'请填写(年-月-日)格式日期','error'=>'格式不对,应为如'.date('Y-m-d').'相似的格式');
		$data[]=Array('name'=>'order_id','title'=>'支付流水号','type'=>'text','value'=>get_html($form['order_id']) ,'preg'=>'^-?[0-9]{1,10}$','size'=>10,'width'=>'60px','note'=>'','error'=>'限制10位以内的数字');
		$data[]=Array('name'=>'fund_remark','title'=>'备注','type'=>'text','value'=>get_html($form['fund_remark']),'size'=>100,'width'=>'360px','note'=>'限制100字节以内','error'=>'限制100字节以内');

		$ret['formdata']=$data;
		return $ret;
	}

	//save充值或真实退款
	public function fund_add_save(){

		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';

		$type=$form['fund_type'];
		$fund=$this->formatNum($form['fund']);
		$Msg='';
		if($type>9 or !isset($this->_typeArr[$type])){
			$Msg=$Msg.'[类型]不正确';
		}
		else if($type==1 or $type==4) {
			if($fund>=0) $Msg=$Msg.$this->_typeArr[$type].'[金额]必须为负数！';
		}
		else {
			if($fund<0) $Msg=$Msg.$this->_typeArr[$type].'[金额]必须为正数！';
		}
		if(empty($Msg)) $Msg=$Msg.getnum($form['fund'],'金额',999999.99,-999999.99);
		if(!preg_match('/^2[0-9]{3}-[0-1][0-9]?-([0-3][0-9]?)$/i',$form['fund_date'])){
			$Msg=$Msg.'[结算日期]格式不正确';
		}
		$Msg=$Msg.getnum($form['fund_pay_id'],'',9999,0);
		if($form['fund_pay_id']>0){
			if($form['fund_pay_id']<1000) $Msg=$Msg.'入款来源格式不对!';
		}
		$Msg=$Msg.getnum($form['order_id'],'支付流水号',2100000000,0);
		$Msg=$Msg.getstr($form['fund_remark'],'备注',120,0);
		if(!empty($Msg)){
			$ret['result']=1;
			$ret['msg']=$Msg;
			$ret['jumpcmd']='fund_add';
			return $ret;
		}

		$savedata=Array();
		$savedata['user_id']  =$this->_set['user_id'];
		$savedata['fund']     =$fund;
		$savedata['fund_type']=$type;
		$savedata['fund_date']=$form['fund_date'];
		$savedata['fund_pay_id']=$form['fund_pay_id'];
		$savedata['order_id']=intval($form['order_id']);
		$savedata['add_time']=date('Y-m-d H:i:s');
		$savedata['fund_remark']=$form['fund_remark'];
		$res=$this->payment($savedata);
		if(!$res){
			$ret['result']=1;
			$ret['jumpcmd']='fund_add';
			$ret['msg']='更新数据表出错！';
			return $ret;
		}
		$ret['jumpcmd']='query';
		return $ret;
	}

	//扣费或订单退费到账号表单
	public function fund_sub(){
		
		$this->get_paymode();

		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['pagetitle']='消费或退费到账号录入';
		$ret['type']='form';
		
		$ret['formhead']=Array();
		$ret['formhead'][]=Array('title'=>$ret['pagetitle'],'savecmd'=>'fund_sub_save','submitstr'=>'保存','tdwidth'=>15,'backstr'=>'返回','backcmd'=>'query');


		$data=Array();
		$data[]=Array('name'=>'user_id','title'=>'会员编号','type'=>'view','value'=>$this->_set['user_id']);
		$data[]=Array('name'=>'','title'=>'会员账号','type'=>'view','value'=>get_html($this->_set['user_name']));
		$data[]=Array('name'=>'add_user','title'=>'','type'=>'hidden','value'=>'');
		$data[]=Array('name'=>'add_time','title'=>'','type'=>'hidden','value'=>date('Y-m-d H:i:s'));

		$useSet=Array();
		$useSet[]=Array('value'=>0,'option'=>'[默认平台]','selected'=>1);
		$n=0;
		$result=$this->DB->query("select * from ".$this->gettable("site_cp")." where 1",2);
		foreach  ($result as $arr) {
			$_v =$arr['cp_id'];
			$sele=0;
			if(strlen($form['fund_pay_id'])>0 and $form['fund_pay_id']==$_v) $sele=1;
			$useSet[]= Array('value'=>$_v,'option'=>get_html($arr['cp_title']),'remark'=>''. get_html($arr['cp_domain']).'<br />','selected'=>$sele);
			$n++;
		}

		$data[]=Array('name'=>'fund_pay_id','title'=>'消费平台','type'=>'select','value'=>$useSet);
		$typeSet=Array();
		$n=0;
		foreach($this->_typeArr as $_t => $_name){
			if($_t>=10){
				$sele=0;
				if(strlen($form['fund_type'])>0 and $form['fund_type']==$_t or $n==0 and !isset($form['fund_type'])) $sele=1;
				$typeSet[]=Array('value'=>$_t,'option'=>'['.$_t.']'.$_name,'remark'=>'<br />','selected'=>$sele);
				$n++;
			}
		}

		$data[]=Array('name'=>'fund_type','title'=>'类别','type'=>'radio','value'=>$typeSet);
		if(empty($form['fund_date'])) $form['fund_date']=date('Y-m-d');
		$data[]=Array('name'=>'fund'     ,'title'=>'金额','type'=>'text','value'=>get_html($form['fund']) ,'size'=>9,'width'=>'54px','limit'=>'-999999.99&999999.99','note'=>'请填写-999999.99至999999.99之间的数字' ,'preg'=>'^-?[0-9]{1,6}(\.[0-9]{1,2})?$','error'=>'格式不对,必须填写-999999.99至999999.99之间的数字');
		$data[]=Array('name'=>'fund_date','title'=>'结算日期','type'=>'text','value'=>get_html($form['fund_date']) ,'preg'=>'^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?)$' ,'size'=>10,'width'=>'80px','note'=>'请填写(年-月-日)格式日期','error'=>'格式不对,应为如'.date('Y-m-d').'相似的格式');

		$data[]=Array('name'=>'order_id','title'=>'订单编号','type'=>'text','value'=>get_html($form['order_id']) ,'preg'=>'^-?[0-9]{1,10}$','size'=>10,'width'=>'60px','note'=>'','error'=>'限制10位以内的数字');
		$data[]=Array('name'=>'order_initdate','title'=>'消费订单起始时间','type'=>'text','value'=>get_html($form['order_initdate']) ,'size'=>19,'width'=>'128px','preg'=>'^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$', 'note'=>'请输入'.date('Y-m-d H:i:s').'格式','error'=>'格式错误，必须为YYYY-MM-DD HH:II:SS格式');
		$data[]=Array('name'=>'order_enddate','title'=>'消费订单到期时间','type'=>'text','value'=>get_html($form['order_enddate']) ,'size'=>19,'width'=>'128px','preg'=>'^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$', 'note'=>'请输入'.date('Y-m-d H:i:s').'格式','error'=>'格式错误，必须为YYYY-MM-DD HH:II:SS格式');
		$data[]=Array('name'=>'fund_remark','title'=>'备注','type'=>'text','value'=>get_html($form['fund_remark']),'size'=>100,'width'=>'360px','note'=>'限制100字节以内','error'=>'限制100字节以内');

		$ret['formdata']=$data;
		return $ret;
	}

	//save扣费或订单退费到账号subtract
	public function fund_sub_save(){

		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';

		$type=$form['fund_type'];
		$fund=$this->formatNum($form['fund']);
		$Msg='';
		if($type<10 or !isset($this->_typeArr[$type])){
			$Msg=$Msg.'[类型]不正确';
		}
		else if($type==24) {
			if($fund<0) $Msg=$Msg.$this->_typeArr[$type].'[金额]必须为正数！';
		}
		else {
			if($fund>=0) $Msg=$Msg.$this->_typeArr[$type].'[金额]必须为负数！';
		}
		if(!preg_match('/^2[0-9]{3}-[0-1][0-9]?-([0-3][0-9]?)$/i',$form['fund_date'])){
			$Msg=$Msg.'[结算日期]格式不正确';
		}
		if(empty($Msg)) $Msg=$Msg.getnum($form['fund'],'金额',999999.99,-999999.99);
		$Msg=$Msg.getnum($form['order_id'],'订单编号',2100000000,0);
		if(!preg_match('/^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$/i',$form['order_initdate'])){
			$Msg=$Msg.'[消费订单起始时间]格式不正确';
		}
		if(!preg_match('/^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$/i',$form['order_enddate'])){
			$Msg=$Msg.'[消费订单到期时间]格式不正确';
		}
		$Msg=$Msg.getnum($form['fund_pay_id'],'',999,0);
		if($form['fund_pay_id']>0){
			if($form['fund_pay_id']>999) $Msg=$Msg.'消费平台格式不对!';
		}
		$Msg=$Msg.getstr($from['fund_remark'],'备注',120,0);
		if(!empty($Msg)){
			$ret['result']=1;
			$ret['msg']=$Msg;
			$ret['jumpcmd']='fund_sub';
			return $ret;
		}
		
		$savedata=Array();
		$savedata['user_id']  =$this->_set['user_id'];
		$savedata['fund']     =$fund;
		$savedata['fund_type']=$type;
		$savedata['fund_date']=$form['fund_date'];
		$savedata['fund_pay_id']=$form['fund_pay_id'];
		$savedata['order_id']=$form['order_id'];
		$savedata['fund_remark']=$form['fund_remark'];
		$savedata['add_time']=date('Y-m-d H:i:s');
		$savedata['order_initdate']=$form['order_initdate'];
		$savedata['order_enddate']=$form['order_enddate'];
		$res=$this->spend($savedata);
		if(!$res){
			$ret['result']=1;
			$ret['jumpcmd']='fund_sub';
			$ret['msg']='更新数据表出错！';
			return $ret;
		}
		$ret['jumpcmd']='query';
		return $ret;
	}


	//修改表单
	public function fund_edit(){

		$this->getRecord();
		$RS=$this->_RS;
		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['pagetitle']='财务记录修改';
		$ret['type']='form';
		
		$RS['order_id']=abs($RS['order_id']);
		if(!isset($form['_save'])){
			$form=$RS;
		}

		if($RS['add_time']<$this->_editdateShow){
			$ret['result']=1;
			$ret['msg']='7天前的记录不能修改';
			return $ret;
		}

		
		$ret['formhead']=Array();
		$ret['formhead'][]=Array('title'=>$ret['pagetitle'],'savecmd'=>'fund_edit_save','submitstr'=>'保存修改','tdwidth'=>15 ,'backstr'=>'取消并返回','backcmd'=>'query');

		$data=Array();
		if($RS['fund']>0) $RS['fund']='+'.$RS['fund'];
		$data[]=Array('name'=>'','title'=>'流水号','type'=>'view','value'=>$RS['fund_id']);
		$data[]=Array('name'=>'','title'=>'会员编号','type'=>'view','value'=>$this->_set['user_id']);
		$data[]=Array('name'=>'','title'=>'会员账号','type'=>'view','value'=>$this->_set['user_name']);
		$data[]=Array('name'=>'','title'=>'金额','type'=>'view','value'=>$RS['fund']);
		$data[]=Array('name'=>'','title'=>'结算时间','type'=>'view','value'=>$RS['fund_date']);
		$data[]=Array('name'=>'','title'=>'录入时间','type'=>'view','value'=>$RS['add_time']);

		$type=$RS['fund_type'];
		$payid=$RS['fund_pay_id'];
		$data[]=Array('name'=>'id','title'=>'','type'=>'hidden','value'=>$RS['fund_id']);
		$data[]=Array('name'=>'id','title'=>'流水号','type'=>'view','value'=>$RS['fund_id']);
		$data[]=Array('name'=>'fund','title'=>'金额','type'=>'view','value'=>$RS['fund']);
		//显示支付方式
		if($RS['fund_type']<10){
			
			$data[]=Array('name'=>'fund_type','title'=>'类型','type'=>'view','value'=>(isset($this->_typeArr[$type]) ? '['.$RS['fund_type'].'] '.$this->_typeArr[$type]:$RS['fund_type']));
			if($RS['fund_type']==0){
				$this->get_paymode();
				$data[]=Array('name'=>'payid','title'=>'入款来源','type'=>'view' ,'value'=>(isset($this->_payMode[$payid]['title'])?'['.$payid.'] '.$this->_payMode[$payid]['title']:$payid));
			}
		}
		//消费显示平台
		else if($RS['fund_type']>=10){
			
			$cpArr=Array();
			$cpArr[0]=Array('title'=>'[默认平台]');
			$result=$this->DB->query("select * from ".$this->gettable("site_cp")." where cp_stat!=4",2);
			foreach($result as $arr){
				$cpArr[$arr['cp_id']]=get_html($arr['cp_title']);
			}
			$data[]=Array('name'=>'fund_type','title'=>'类型','type'=>'view','value'=>(isset($this->_typeArr[$type]) ? '['.$RS['fund_type'].'] '.$this->_typeArr[$type]:$RS['fund_type']));

			$data[]=Array('name'=>'cp_id','title'=>'消费平台','type'=>'view','value'=>(isset($cpArr[$payid]) ? '['.$payid.'] '.$cpArr[$payid]:$payid));
		}

		if($RS['fund_type']>=10){
			$data[]=Array('name'=>'order_id','title'=>'订单号','type'=>'text','value'=>show_form('order_id',$form,$RS),'size'=>11,'width'=>'60px','note'=>'','error'=>'限制11位以内的数字');
			$data[]=Array('name'=>'order_initdate','title'=>'消费订单起始时间','type'=>'text','value'=>show_form('order_initdate',$form,$RS) ,'size'=>19,'width'=>'128px','preg'=>'^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$', 'note'=>'请输入'.date('Y-m-d H:i:s').'格式','error'=>'格式错误，必须为YYYY-MM-DD HH:II:SS格式');
			$data[]=Array('name'=>'order_enddate','title'=>'消费订单到期时间','type'=>'text','value'=>show_form('order_enddate',$form,$RS) ,'size'=>19,'width'=>'128px','preg'=>'^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$', 'note'=>'请输入'.date('Y-m-d H:i:s').'格式','error'=>'格式错误，必须为YYYY-MM-DD HH:II:SS格式');
		}
		else if($RS['fund_type']==0){
			$data[]=Array('name'=>'order_id','title'=>'支付流水号','type'=>'text','value'=>show_form('order_id',$form,$RS),'preg'=>'^-?[0-9]{1,10}$','size'=>11,'width'=>'60px','note'=>'','error'=>'限制11位以内的数字');
		}
		$data[]=Array('name'=>'fund_remark','title'=>'备注','type'=>'text','value'=>show_form('fund_remark',$form,$RS),'size'=>100,'width'=>'360px','note'=>'','error'=>'限制100字节以内');
		$ret['formdata']=$data;
		return $ret;
	}

	//save修改(只能修改7天内添加的)
	public function fund_edit_save(){
		$form=$this->_data;
		$this->getRecord();
		$RS=$this->_RS;
		$ret=Array();
		$ret['type']='save';
		$ret['result']=0;
		if($RS['add_time']<$this->_editdateShow){
			$ret['result']=1;
			$ret['msg']='7天前的记录不能修改';
			return $ret;
		}
		$saveData=Array();
		if($RS['fund_type']>=10){
			$Msg=$Msg. getnum($form['order_id'],'订单号',2100000000,0);
			if(!preg_match('/^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$/',$form['order_initdate'])){
				$Msg=$Msg.'[消费订单起始时间]不是正确是时间格式，正确格式例如：'.date('Y-m-d H:i:s');
			}
			if(!preg_match('/^[0-9]{4}-[0-1][0-9]?-([0-3][0-9]?) [0-9]{2}:[0-9]{2}:[0-9]{2}$/',$form['order_enddate'])){
				$Msg=$Msg.'[消费订单到期时间]不是正确是时间格式，正确格式例如：'.date('Y-m-d H:i:s');
			}

			$Msg=$Msg. getnum($form['order_id'],'订单号',2100000000,0);
			$saveData['order_initdate']=$form['order_initdate'];
			$saveData['order_enddate'] =$form['order_enddate'];
			$saveData['order_id']=$form['order_id'];
		}
		else if($RS['fund_type']==0){
			$Msg=$Msg. getnum($form['order_id'],'支付流水号',2100000000,0);
			$saveData['order_id']=0-$form['order_id'];
		}

		$saveData['fund_remark']=$form['fund_remark'];
		$Msg=$Msg.getstr($form['fund_remark'],'备注',120,0);
		if(empty($Msg)){
			$res=$this->change($RS,$saveData);
			if($res){
				$ret['result']=0;
				$ret['jumpcmd']='query';
				$ret['jumpurl']='';
				return $ret;
			}
			$Msg='database save deny!';
		}

		if(!empty($Msg)){
			$ret['result']=1;
			$ret['msg']=$Msg;
			$ret['jumpcmd']='fund_edit';
		}
		return $ret;
	}






	//支付方式管理
	public function setcp(){

		$form=$this->_data;
		$page=$form['_page'];
		$ret=Array();
		$ret['pagetitle']='平台设置';
		$ret['result']=0;
		$ret['pageinfo']=$info;
		$ret['type']='list';
		
		$fconf=Array();
		$fconf['savecmd']='';
		$fconf['keyname']='';
		$fconf['submitstr']='';

		$ret['formhead']=$fconf;
		$conf=Array();
		$conf['headheight']=26;
		$conf['rowheight']=30;
		$conf['addstring']=' &nbsp;  <a href="{pagename}_cmd=setcp_add">添加记录</a>  ';

		$ret['pageconf']=$conf;
		$headSet=Array();
		$headSet[] =Array("name"=>"del","title"=>"删除","width"=>"7%","align"=>"","type"=>"");
		$headSet[] =Array("name"=>"edit","title"=>"编辑","width"=>"4%","align"=>"","type"=>"");
		$headSet[] =Array("name"=>"id","title"=>"主控编号","width"=>"7%","align"=>"","type"=>"");
		$headSet[] =Array("name"=>"title","title"=>"名称","width"=>"16%","align"=>"left","type"=>"text");
		$headSet[] =Array("name"=>"stat","title"=>"状态","width"=>"7%","align"=>"","type"=>"select");
		$headSet[] =Array("name"=>"name","title"=>"主控绑定域名","width"=>"*%","align"=>"left","type"=>"");
		$headSet[] =Array("name"=>"addtime","title"=>"添加时间","width"=>"16%","align"=>"","type"=>"");


		$ret['listhead']=$headSet;
		$data=Array();

		//$result=$this->DB->query("select * from ".$this->gettable("site_cp")." where 1",2);
		$querySet=Array();
		$querySet['field']='*';
		$querySet['where']=" where 1";
		$querySet['group']='';
		$querySet['order']=' order by cp_id desc';
		$querySet['page'] =$page;
		
		$this->DB->setTable($this->gettable("site_cp"));
		$result=$this->DB->select($querySet);
		$ret['pageinfo']=$this->DB->pageInfo;

		foreach($result as $arr){

			$row=Array();

			if($arr['cp_stat']==4){
				$row['del']='<a href="{pagename}&_cmd=setcp_resume&id='.$arr['cp_id'].'">恢复</a> <a href="{pagename}&_cmd=setcp_del&id='.$arr['cp_id'].'" onclick="return confirm(\'您确定要清除此记录吗（不可恢复）？\');">清除</a> ';
			}
			else{
				$row['del']='<a href="{pagename}&_cmd=setcp_del&id='.$arr['cp_id'].'" onclick="return confirm(\'您确定要删除此记录吗？\');">删除</a>';
			}

			$row['edit']='<a href="{pagename}&_cmd=setcp_edit&id='.$arr['cp_id'].'">修改</a>';

			$statSet=Array();
			$statSet[0]=Array('value'=>0,"option"=>'正常','selected'=>($arr['cp_stat']==0?1:0));
			$statSet[4]=Array('value'=>4,"option"=>'关闭','selected'=>($arr['cp_stat']==4?1:0));
			$row['id']=$arr['cp_id'];
			$row['title']=get_html($arr['cp_title']);
			$row['stat']=$statSet[$arr['cp_stat']]['option'];
			$row['name']=get_html($arr['cp_domain']);
			$row['addtime']=get_html($arr['cp_addtime']);
			
			$data[]=$row;
		}

		$ret['listdata']=$data;
		$ret['remark']='使用说明';
		return $ret;

	}

	public function setcp_resume(){
		$form=$this->_data;
		$id=form('id');
		$res=$this->DB->query("update ".$this->gettable("site_cp")." set cp_stat=0 where cp_id=".intval($id));
		
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$ret['jumpcmd']='setcp';
		return $ret;
	}

	public function setcp_del(){
		$form=$this->_data;
		$id=form('id');
		$RS=$this->DB->query("select * from ".$this->gettable("site_cp")." where cp_id=".$this->DB->getsql($id,1),1);
		if($RS){
			if($RS['cp_stat']==4){
				$res=$this->DB->query("delete from  ".$this->gettable("site_cp")." where cp_id=".intval($id));
			}
			else{
				$res=$this->DB->query("update ".$this->gettable("site_cp")." set cp_stat=4 where cp_id=".intval($id));
			}
		}
		
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$ret['jumpcmd']='setcp';
		return $ret;
	}

	public function setcp_save(){
		$form=$this->_data;
		$saveData=Array();
		foreach($form as $_name =>$_value){
			$tmp=explode('-',$_name);
			$id=intval($tmp[1]);
			$saveData[$id][$tmp[0]]=$_value;
		}
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		foreach($saveData as $id => $rows){
			$sql="update ".$this->gettable("site_cp")." set cp_title=".$this->DB->getsql($rows['title']).",cp_stat=".$this->DB->getsql($rows['stat'],1)." where cp_id=".intval($id);
			$this->DB->query($sql);
			usleep(10000);
		}
		$ret['jumpcmd']='setcp';
		return $ret;
	}

	
	public function setcp_id_check(){
		$form=$this->_data;

		$Msg=$Msg.getnum($form['cp_id'],'主控编号',999,100);
		if(empty($Msg)){
			$res=$this->DB->query("select * from ".$this->gettable("site_cp")." where cp_id=".$this->DB->getsql($form['cp_id'],1),1);
			if($res){
				$Msg=$Msg.'[主控编号]值('.$form['cp_id'].')已经存在，请重新填写！';
			}
		}
		if(!empty($Msg)){
			echo $Msg;
			exit;
		}
		echo '[OK]';
		exit;
	}
	
	public function setcp_add(){

		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['pagetitle']='添加平台';
		$ret['type']='form';

		$ret['formhead']=Array();
		$ret['formhead'][]=Array('title'=>$ret['pagetitle'],'savecmd'=>'setcp_add_save','submitstr'=>'保存','tdwidth'=>15,'backstr'=>'返回','backcmd'=>'setcp');
		$data=Array();

		$data[]=Array('name'=>'cp_id','title'=>'主控编号','type'=>'text','value'=>get_html($form['cp_id']) ,'size'=>3,'width'=>'30px','limit'=>'100&999','check'=>'setcp_id_check','note'=>'请填写100至999之间的数字' ,'preg'=>'^[1-9][0-9]{2}$','error'=>'格式不对,必须为100至999之间的数字');
		$data[]=Array('name'=>'cp_title','title'=>'名称','type'=>'text','value'=>get_html($form['cp_title']),'size'=>30,'width'=>'180px','limit'=>2,'note'=>'请填写30字节以内的字符','error'=>'必须为30字节以内字符且不能为空');

 
		//$data[]=Array('name'=>'paymode_type','title'=>'状态','type'=>'radio','value'=>$typeSet);
		$data[]=Array('name'=>'cp_domain'   ,'title'=>'主控绑定域名','type'=>'text','value'=>get_html($form['cp_domain']) ,'size'=>120,'limit'=>'6','width'=>'280px','note'=>'请填写120字节以内的字符','error'=>'必须为120字节以内字符且不能为空');
		$data[]=Array('name'=>'cp_order_url','title'=>'订单管理链接','type'=>'text','value'=>get_html($form['cp_order_url']) ,'size'=>120,'width'=>'280px','note'=>'请填写120字节以内的字符','error'=>'必须为120字节以内字符');



		$ret['formdata']=$data;
		return $ret;
	}


	public function setcp_add_save(){
		
		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$Msg=$Msg.getnum($form['cp_id'],'支付编号',999,100);
		if(empty($Msg)){
			$res=$this->DB->query("select * from ".$this->gettable("site_cp")." where cp_id=".$this->DB->getsql($form['cp_id'],1),1);
			if($res){
				$Msg=$Msg.'[支付编号]值('.$form['cp_id'].')已经存在，请重新填写！';
			}
		}
		$Msg=$Msg.getstr($form['cp_title'],'支付方式名称',30,2);
		$Msg=$Msg.getstr($form['cp_domain'],'开户户名',100,0);
		$Msg=$Msg.getstr($form['cp_order_url'],'开户账号',100,0);

		if(!empty($Msg)){
			$ret['result']=1;
			$ret['msg']=$Msg;
			$ret['jumpcmd']='setcp_add';
			return $ret;
		}
		$payid=intval($form['cp_id']);
		$sql="insert into ".$this->gettable("site_cp")." (cp_id,cp_title,cp_domain,cp_order_url,cp_addtime ) values ($payid,".$this->DB->getsql($form['cp_title']).",".$this->DB->getsql($form['cp_domain']).",".$this->DB->getsql($form['cp_order_url']).",".$this->DB->getsql(date('Y-m-d H:i:s')).")";
		$res=$this->DB->query($sql);
		if(!$res){
			$ret['result']=1;
			$ret['jumpcmd']='setcp_add';
			$ret['msg']='更新数据表出错！';
			return $ret;
		}
		$ret['jumpcmd']='setcp';
		return $ret;
	}




	public function setcp_edit(){

		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['pagetitle']='修改';
		$ret['type']='form';

		$id=intval($form['id']);
		$RS=$this->DB->query("select * from ".$this->gettable("site_cp")." where cp_id=".$this->DB->getsql($id,1),1);
		if(!isset($form['_save'])){
			$form=$RS;
		}


		$ret['formhead']=Array();
		$ret['formhead'][]=Array('title'=>$ret['pagetitle'],'savecmd'=>'setcp_edit_save','submitstr'=>'保存','tdwidth'=>15,'backstr'=>'返回','backcmd'=>'setcp');
		$data=Array();

		$data[]=Array('name'=>'cp_id','title'=>'主控编号','type'=>'view','value'=>get_html($RS['cp_id']));
		$data[]=Array('name'=>'id','title'=>'主控编号','type'=>'hidden','value'=>get_html($RS['cp_id']));
		$data[]=Array('name'=>'cp_title','title'=>'名称','type'=>'text','value'=>get_html($form['cp_title']),'size'=>30,'width'=>'180px','limit'=>2,'note'=>'请填写30字节以内的字符','error'=>'必须为30字节以内字符且不能为空');

		//$data[]=Array('name'=>'paymode_type','title'=>'状态','type'=>'radio','value'=>$typeSet);
		$data[]=Array('name'=>'cp_domain'   ,'title'=>'主控绑定域名','type'=>'text','value'=>get_html($form['cp_domain']) ,'size'=>120,'limit'=>'6','width'=>'280px','note'=>'请填写120字节以内的字符','error'=>'必须为120字节以内字符且不能为空');
		$data[]=Array('name'=>'cp_order_url','title'=>'订单管理链接','type'=>'text','value'=>get_html($form['cp_order_url']) ,'size'=>120,'width'=>'280px','note'=>'请填写120字节以内的字符','error'=>'必须为120字节以内字符');



		$ret['formdata']=$data;
		return $ret;
	}


	public function setcp_edit_save(){
		
		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';

		$id=intval($form['id']);
		$RS=$this->DB->query("select * from ".$this->gettable("site_cp")." where cp_id=".$this->DB->getsql($id,1),1);
		if(!isset($form['_save'])){
			$form=$RS;
		}

		$Msg=$Msg.getstr($form['cp_title'],'支付方式名称',30,2);
		$Msg=$Msg.getstr($form['cp_domain'],'开户户名',100,0);
		$Msg=$Msg.getstr($form['cp_order_url'],'开户账号',100,0);

		if(!empty($Msg)){
			$ret['result']=1;
			$ret['msg']=$Msg;
			$ret['jumpcmd']='setcp_edit';
			return $ret;
		}
		$sql="update  ".$this->gettable("site_cp")." set cp_title=".$this->DB->getsql($form['cp_title']).",cp_domain=" .$this->DB->getsql($form['cp_domain']).",cp_order_url=".$this->DB->getsql($form['cp_order_url'])." where cp_id=".intval($id);
		$res=$this->DB->query($sql);

		if(!$res){
			$ret['result']=1;
			$ret['jumpcmd']='setcp_add';
			$ret['msg']='更新数据表出错！';
			return $ret;
		}
		$ret['jumpcmd']='setcp';
		return $ret;
	}






	//支付方式管理
	public function paymode(){

		$form=$this->_data;
		$page=$form['_page'];

		$ret=Array();
		$ret['pagetitle']='付款方式设置';
		$ret['result']=0;
		$ret['pageinfo']=$info;
		$ret['type']='list';
		$conf=Array();
		$conf['headheight']=26;
		$conf['rowheight']=30;
		$conf['addstring']=' &nbsp;  <a href="{pagename}_cmd=paymode_add">添加记录</a>  ';
		$ret['pageconf']=$conf;
		$headSet=Array();
		$headSet[] =Array("name"=>"del","title"=>"删除","width"=>"4%","align"=>"");
		$headSet[] =Array("name"=>"edit","title"=>"编辑","width"=>"4%","align"=>"");
		$headSet[] =Array("name"=>"id","title"=>"支付编号","width"=>"7%","align"=>"");
		$headSet[] =Array("name"=>"title","title"=>"名称","width"=>"12%","align"=>"left");
		$headSet[] =Array("name"=>"stat","title"=>"状态","width"=>"8%","align"=>"");
		$headSet[] =Array("name"=>"name","title"=>"户名/开户账号","width"=>"18%","align"=>"left");
		$headSet[] =Array("name"=>"remark","title"=>"备注","width"=>"*%","align"=>"left");
		$headSet[] =Array("name"=>"addtime","title"=>"添加时间","width"=>"8%","align"=>"");


		$ret['listhead']=$headSet;
		$data=Array();
		$result=$this->DB->query("select * from ".$this->gettable("paymode")." where 1",2);
		$querySet=Array();
		$querySet['field']='*';
		$querySet['where']=" where 1";
		$querySet['group']='';
		$querySet['order']=' order by paymode_istop desc,paymode_id desc';
		$querySet['page'] =$page;
		$this->DB->setTable($this->gettable("paymode"));
		$result=$this->DB->select($querySet);
		$ret['pageinfo']=$this->DB->pageInfo;
		foreach($result as $arr){

			$row=Array();

			if($arr['paymode_stat']==4){
				$row['del']='<a href="{pagename}&_cmd=paymode_resume&id='.$arr['paymode_id'].'">恢复</a>';
			}
			else{
				$row['del']='<a href="{pagename}&_cmd=paymode_del&id='.$arr['paymode_id'].'" onclick="return confirm(\'您确定要删除此记录吗？\');">删除</a>';
			}

			$row['edit']='<a href="{pagename}&_cmd=paymode_edit&id='.$arr['paymode_id'].'">修改</a>';



			$row['id']=$arr['paymode_id'];
			$row['title']=get_html($arr['paymode_title']);
			$row['stat']=($arr['paymode_stat']==0?"正常":"关闭").($arr['paymode_istop']==1?" <font color=\"#CC0000\">[优先]</font>":"");
			if($arr['paymode_stat']==0){
				$row['stat']=$row['stat'].'<br /><a href="{pagename}&_cmd=paymode_settop&id='.$arr['paymode_id'].'">设置为优先</a>';
			}
			$row['name']=get_html($arr['paymode_name']).'<br />'.get_html($arr['paymode_account']);
			$row['remark']=get_html($arr['paymode_remark']);
			$row['addtime']=get_html($arr['paymode_addtime']);
			
			$data[]=$row;
		}

		$ret['listdata']=$data;
		$ret['remark']='使用说明';
		return $ret;

	}

	public function paymode_id_check(){


		$form=$this->_data;
		$Msg=$Msg.getnum($form['paymode_id'],'支付编号',3999,1000);
		if(empty($Msg)){
			$res=$this->DB->query("select * from ".$this->gettable("paymode")." where paymode_id=".$this->DB->getsql($form['paymode_id'],1),1);
			if($res){
				$Msg=$Msg.'[支付编号]值('.$form['paymode_id'].')已经存在，请重新填写！';
			}
		}
		if(!empty($Msg)){
			echo $Msg;
			exit;
		}
		echo '[OK]';
		exit;
	}
	
	public function paymode_add(){

		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['pagetitle']='添加支付方式';
		$ret['type']='form';

		$ret['formhead']=Array();
		$ret['formhead'][]=Array('title'=>$ret['pagetitle'],'savecmd'=>'paymode_add_save','submitstr'=>'保存','tdwidth'=>15,'backstr'=>'返回','backcmd'=>'paymode');

		$data=Array();

		$data[]=Array('name'=>'paymode_id','title'=>'支付编号','type'=>'text','value'=>get_html($form['paymode_id']) ,'size'=>4,'width'=>'40px','limit'=>'1000&3999','check'=>'paymode_id_check','note'=>'请填写1000至3999之间的数字，编码规则：在线支付用3###，银行或网上转账用2###，线下支付用1###编号' ,'preg'=>'^[1-3][0-9]{3}$','error'=>'格式不对,必须为1000至3999之间的数字');
		$data[]=Array('name'=>'paymode_title','title'=>'支付方式名称','type'=>'text','value'=>get_html($form['paymode_title']),'size'=>30,'width'=>'180px','limit'=>2,'note'=>'请填写30字节以内的字符','error'=>'必须为30字节以内字符且不能为空');

		$n=0;
		foreach  ($this->_payModeType as $_v => $arr) {
			$sele=0;
			if(strlen($form['paymode_type'])>0 and $form['paymode_type']==$_v or $n==0) $sele=1;
			$typeSet[]= Array('value'=>$_v,'option'=>get_html($arr['title']),'remark'=>''. get_html($arr['note']).'<br />','selected'=>$sele);
			$n++;
		}

		$data[]=Array('name'=>'paymode_type','title'=>'类别','type'=>'radio','value'=>$typeSet);
		$data[]=Array('name'=>'paymode_name'   ,'title'=>'开户户名','type'=>'text','value'=>get_html($form['paymode_name']),'size'=>100,'width'=>'280px','note'=>'请填写100字节以内的字符','error'=>'必须为100字节以内字符');
		$data[]=Array('name'=>'paymode_account','title'=>'开户账号','type'=>'text','value'=>get_html($form['paymode_account']),'size'=>100,'width'=>'280px','note'=>'请填写100字节以内的字符','error'=>'必须为100字节以内字符');
		$data[]=Array('name'=>'paymode_remark' ,'title'=>'备注','type'=>'text','value'=>get_html($form['paymode_remark']),'size'=>200,'width'=>'360px','note'=>'请填写200字节以内的字符','error'=>'必须为200字节以内字符');


		$ret['formdata']=$data;
		return $ret;
	}

	public function paymode_add_save(){
		
		$form=$this->_data;
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$Msg=$Msg.getnum($form['paymode_id'],'支付编号',3999,1000);
		if(empty($Msg)){
			$res=$this->DB->query("select * from ".$this->gettable("paymode")." where paymode_id=".$this->DB->getsql($form['paymode_id'],1),1);
			if($res){
				$Msg=$Msg.'[支付编号]值('.$form['paymode_id'].')已经存在，请重新填写！';
			}
		}
		$Msg=$Msg.getstr($form['paymode_title'],'支付方式名称',30,2);
		$Msg=$Msg.getstr($form['paymode_name'],'开户户名',100,0);
		$Msg=$Msg.getstr($form['paymode_account'],'开户账号',100,0);
		$Msg=$Msg.getstr($form['paymode_remark'],'备注',200,0);
		if(!empty($Msg)){
			$ret['result']=1;
			$ret['msg']=$Msg;
			$ret['jumpcmd']='paymode_add';
			return $ret;
		}
		$payid=intval($form['paymode_id']);
		$type=intval($form['paymode_type']);
		if(!isset($this->_payModeType[$type])) $type=0;
		$sql="insert into ".$this->gettable("paymode")." (paymode_id,paymode_type,paymode_addtime,paymode_title,paymode_name,paymode_account,paymode_remark) values ($payid,$type,'".date('Y-m-d H:i:s')."',".$this->DB->getsql($form['paymode_title']).",".$this->DB->getsql($form['paymode_name']).",".$this->DB->getsql($form['paymode_account']).",".$this->DB->getsql($form['paymode_remark']).")";
		$res=$this->DB->query($sql);
		if(!$res){
			$ret['result']=1;
			$ret['jumpcmd']='paymode_add';
			$ret['msg']='更新数据表出错！';
			return $ret;
		}
		$ret['jumpcmd']='paymode';
		return $ret;
	}

	public function paymode_edit(){

		$form=$this->_data;
		$id=intval($form['id']);
		$RS=$this->DB->query("select * from ".$this->gettable("paymode")." where paymode_id=".$this->DB->getsql($id,1),1);
		if(!isset($form['_save'])){
			$form=$RS;
		}
		$ret=Array();
		$ret['result']=0;
		$ret['pagetitle']='修改支付方式';
		$ret['type']='form';

		$ret['formhead']=Array();
		$ret['formhead'][]=Array('title'=>$ret['pagetitle'],'savecmd'=>'paymode_edit_save','submitstr'=>'保存','tdwidth'=>15,'backstr'=>'返回','backcmd'=>'paymode');


		$viewdir=$this->_set['viewdir'];

		$data=Array();

		$data[]=Array('name'=>'paymode_id','title'=>'支付编号','type'=>'view','value'=>get_html($RS['paymode_id']));
		$data[]=Array('name'=>'id','title'=>'','type'=>'hidden','value'=>get_html($id));
		$type='未设置';
		if(isset($this->_payModeType[$RS['paymode_type']])) $type=$this->_payModeType[$RS['paymode_type']]['title'];
		$data[]=Array('name'=>'paymode_type','title'=>'类别','type'=>'view','value'=>get_html($type));
		
		$data[]=Array('name'=>'paymode_type','title'=>'','type'=>'hidden','value'=>$RS['paymode_type']);


		$data[]=Array('name'=>'paymode_title','title'=>'支付方式名称','type'=>'text','value'=>get_html($form['paymode_title']),'size'=>30,'width'=>'180px','limit'=>2,'note'=>'请填写30字节以内的字符','error'=>'必须为30字节以内字符且不能为空');
		$data[]=Array('name'=>'paymode_name'   ,'title'=>'开户户名','type'=>'text','value'=>get_html($form['paymode_name']),'size'=>100,'width'=>'280px','note'=>'请填写100字节以内的字符','error'=>'必须为100字节以内字符');
		$data[]=Array('name'=>'paymode_account','title'=>'开户账号','type'=>'text','value'=>get_html($form['paymode_account']),'size'=>100,'width'=>'280px','note'=>'请填写100字节以内的字符','error'=>'必须为100字节以内字符');
		$data[]=Array('name'=>'paymode_remark' ,'title'=>'备注','type'=>'text','value'=>get_html($form['paymode_remark']),'size'=>200,'width'=>'360px','note'=>'请填写200字节以内的字符','error'=>'必须为200字节以内字符');

		if($RS['paymode_type']==2){

			$data[]=Array('name'=>'paymode_api_id','title'=>'在线支付账号ID','type'=>'text','value'=>get_html($form['paymode_api_id']),'size'=>100,'width'=>'280px','note'=>'请填写100字节以内的字符','error'=>'必须为100字节以内字符且不能为空');
			$data[]=Array('name'=>'paymode_api_key','title'=>'在线支付接口密钥','type'=>'text','value'=>get_html($form['paymode_api_key']),'size'=>100,'width'=>'280px','note'=>'请填写100字节以内的字符','error'=>'必须为100字节以内字符且不能为空');

			$data[]=Array('name'=>'paymode_file1','title'=>'上传证书1','type'=>'file','value'=>get_html($RS['paymode_file1']),'size'=>2048000,'width'=>'280px','note'=>'请上传在线支付需要的证书文件，2MB以内','error'=>'','unit'=>$viewdir);
			
			$data[]=Array('name'=>'paymode_file2','title'=>'上传证书2','type'=>'file','value'=>get_html($RS['paymode_file2']),'size'=>2048000,'width'=>'280px','note'=>'请上传在线支付需要的证书文件，2MB以内','error'=>'','unit'=>$viewdir);
			
			$data[]=Array('name'=>'paymode_file3','title'=>'上传证书3','type'=>'file','value'=>get_html($RS['paymode_file3']),'size'=>2048000,'width'=>'280px','note'=>'请上传在线支付需要的证书文件，2MB以内','error'=>'','unit'=>$viewdir);
			
			$data[]=Array('name'=>'paymode_api_code' ,'title'=>'在线支付代码','type'=>'textarea','value'=>get_html($form['paymode_api_code']),'size'=>64000,'unit'=>18,'width'=>'99%','note'=>'请填写64000字节以内的字符','error'=>'必须为64000字节以内字符且');

		}

		$ret['formdata']=$data;
		return $ret;
	}

	public function paymode_edit_save(){
		
		$form=$this->_data;
		$id=intval($form['id']);
		$RS=$this->DB->query("select * from ".$this->gettable("paymode")." where paymode_id=".$this->DB->getsql($id,1),1);

		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$Msg=$Msg.getstr($form['paymode_title'],'支付方式名称',30,2);
		$Msg=$Msg.getstr($form['paymode_name'],'开户户名',100,0);
		$Msg=$Msg.getstr($form['paymode_account'],'开户账号',100,0);
		$Msg=$Msg.getstr($form['paymode_remark'],'备注',200,0);
		if(!empty($Msg)){
			$ret['result']=1;
			$ret['msg']=$Msg;
			$ret['jumpcmd']='paymode_add';
			return $ret;
		}

		$sql="update ".$this->gettable("paymode")." set ";
		$sql=$sql." paymode_title=".$this->DB->getsql($form['paymode_title']);
		$sql=$sql." ,paymode_name=".$this->DB->getsql($form['paymode_name']);
		$sql=$sql." ,paymode_account=".$this->DB->getsql($form['paymode_account']);
		$sql=$sql." ,paymode_remark=".$this->DB->getsql($form['paymode_remark']);


		$updir=$this->_set['updir'];
		$up1=getfile('paymode_file1','上传证书1',2048,0,$Msg);
		$up2=getfile('paymode_file2','上传证书2',2048,0,$Msg);
		$up3=getfile('paymode_file3','上传证书3',2048,0,$Msg);
		if(empty($Msg) and !empty($updir) and !empty($up1['tmp_name'])){
			$rets=$this->uploadFile($RS['paymode_file1'],$up1,'pay_'.$id.'a_',1);
			if(!empty($rets)){
				$sql=$sql." ,paymode_file1=".$this->DB->getsql($rets);
			}
		}
		if(empty($Msg) and !empty($updir) and !empty($up2['tmp_name'])){
			$rets=$this->uploadFile($RS['paymode_file2'],$up2,'pay_'.$id.'b_',1);
			if(!empty($rets)){
				$sql=$sql." ,paymode_file2=".$this->DB->getsql($rets);
			}
		}
		if(empty($Msg) and !empty($updir) and !empty($up3['tmp_name'])){
			$rets=$this->uploadFile($RS['paymode_file3'],$up3,'pay_'.$id.'c_',1);
			if(!empty($rets)){
				$sql=$sql." ,paymode_file3=".$this->DB->getsql($rets);
			}
		}


		$filedir='';
		if(!empty($form['paymode_type']) and $form['paymode_type']==2){
			
			$sql=$sql." ,paymode_api_id="  .$this->DB->getsql($form['paymode_api_id']);
			$sql=$sql." ,paymode_api_key=" .$this->DB->getsql($form['paymode_api_key']);
			$sql=$sql." ,paymode_api_code=".$this->DB->getsql($form['paymode_api_code']);
			if(!empty($this->_set['rootdir'])){
				$filedir=$this->_set['rootdir'].'pay/';
				$filename="pay".$id.'.php';
				$filereceive="receive_".intval($id).'.php';
				$filereturn="return".intval($id).'.php';
				$fileStr='<?PHP

$_path=str_replace("\\\\","/",dirname(__FILE__))."/";
$_pay_type='.$id.';

$_pay_file=$_path."pay'.$id.'.php";
if (!file_exists($_pay_file))
{
	echo "error (paytype)";
	exit;
}

@include_once($_pay_file);
@include_once($_path."receive_inc.php");

?>';
				$filerStr='<?PHP

$_path=str_replace("\\\\","/",dirname(__FILE__))."/";
$_pay_type='.$id.';

$_pay_file=$_path."pay'.$id.'.php";
if (!file_exists($_pay_file))
{
	echo "error (paytype)";
	exit;
}

@include_once($_pay_file);
@include_once($_path."return_inc.php");

?>';
			}
		}

		$sql=$sql." where paymode_id=".intval($id);
		//echo $sql;exit;
		$res=$this->DB->query($sql);
		
		if(!$res){
			$ret['result']=1;
			$ret['jumpcmd']='paymode_edit';
			$ret['msg']='更新数据表出错！';
			return $ret;
		}

		if(!empty($filedir)){
			if(!empty($filename)) writeFile($filedir.$filename,"w+",$form['paymode_api_code']);
			if(!empty($filereceive)) @writeFile($filedir.$filereceive,"w+",$fileStr);
			if(!empty($filereturn)) @writeFile($filedir.$filereturn,"w+",$filerStr);
		}
		$ret['jumpcmd']='paymode';
		return $ret;
	}

	public function paymode_del(){
		$form=$this->_data;
		$id=form('id');
		$res=$this->DB->query("update ".$this->gettable("paymode")." set paymode_stat=4 where paymode_id=".intval($id));
		
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$ret['jumpcmd']='paymode';
		return $ret;
	}

	public function paymode_resume(){
		$form=$this->_data;
		$id=form('id');
		$res=$this->DB->query("update ".$this->gettable("paymode")." set paymode_stat=0 where paymode_id=".intval($id));
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$ret['jumpcmd']='paymode';
		return $ret;
	}


	public function paymode_settop(){
		$form=$this->_data;
		$id=form('id');
		$this->DB->query("update ".$this->gettable("paymode")." set paymode_istop=0 where 1");
		$res=$this->DB->query("update ".$this->gettable("paymode")." set paymode_istop=1 where paymode_id=".intval($id));
		$ret=Array();
		$ret['result']=0;
		$ret['type']='save';
		$ret['jumpcmd']='paymode';
		return $ret;
	}



	//处理搜索项（通用）
	//+ 2017-8-17创建；2017-8-25 完善搜索类型设置
	//Array("type:类型","title:显示名称","field:搜索的字段名称(有附表时为附表字段)","tbl:附表表名(真实名称)","set:搜索设置")
	//@返回SQL 
	protected function getSearchSQL(&$_key,$_f,$findArr){

		//@$findArr=Array();定义搜索项

		$_sql='';
		//自动校正（不允许搜索引号，建议输入框设置最大长度maxlength=50）
		$_key=trim($_key);
		$_key=preg_replace('/(\"|\')/i','',$_key);

		$_k=trim($_key);
		if(!empty($_k) and !empty($_f) and isset($findArr[$_f])){

			$this->_para=$this->_para.'_k='.urlencode($_key).'&_f='.urlencode($_f).'&';
			$_tmp='';
			$fArr=$findArr[$_f];
			$_type =$fArr['type'];
			$_field=preg_replace('/[^-_0-9a-z]/i','',$fArr['field']);
			$_field2='';
			//$fArr[3]=preg_replace('/[^-_0-9a-z]/i','',trim($fArr[3]));
			//$_fname=isset($fArr[5]) ? preg_replace('/[^-_0-9a-z]/i','',trim($fArr[5])):'';
			$_tablename=isset($fArr['tbl']) ? preg_replace('/[^-_0-9a-z]/i','',trim('tbl')):'';
			if(empty($_field)) return $_sql;
			if($_type==0) {

				if(preg_match('/\|/i',$_k)){
					$tmp=explode("|",$_k);
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp." $_field IN (".$this->formatNum($tmp[0],1).", ".($this->formatNum($tmp[1],1)).")";
					}
				}
				else if(preg_match('/&/i',$_k)){
					$_k=preg_replace('/(\||!|<|>)/i','',$_k);
					$tmp=explode("&",$_k);
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp." $_field >= ".$this->formatNum($tmp[0],1)." and $_field <= ".($this->formatNum($tmp[1],1))."";
					}
				}
				else if(substr($_k,0,1)=='!'){
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field != ".($this->formatNum($_k,1));
				}
				else if(substr($_k,0,1)=='>'){
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field > ".($this->formatNum($_k,1));
				}
				else if(substr($_k,0,1)=='<'){
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field < ".($this->formatNum($_k,1));
				}


				if(empty($_tmp)){
					$_tmp=$_tmp."  $_field = ".($this->formatNum($_k,1));
				}
			}
			else if($_type==1) $_tmp=$_tmp." $_field = ".($this->formatNum($_k,1));
			else if($_type==2) {
				//此类型不能搜索两个表
				$_tablename='';
				if(isset($fArr['set'])){
					if(!is_string($fArr['set'])) $fArr['set']='';
					$_field2=preg_replace('/[^-_0-9a-z]/i','',trim($fArr['set']));
				}

				$tmp=explode("@",$_k);
				if(strlen($tmp[0])>0){
					$_tmp=$_tmp." $_field = ".($this->formatNum($tmp[0],1));
					if(strlen($tmp[1])>0 and !empty($_field2)){
						$_tmp=$_tmp." and $_field2 = ".($this->formatNum($tmp[1],1))."";
					}
				}
			}
			else if($_type==10) {
				if(substr($_k,0,1)=='!' and strlen($_k)>1)
				{
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field NOT LIKE ".$this->DB->getsql($_k);
				}
				else if(substr($_k,0,1)=='=' and strlen($_k)>1)
				{
					$_k=substr($_k,1);
					$_tmp=$_tmp." BINARY($_field) LIKE ".$this->DB->getsql($_k);
				}
				else if(substr($_k,0,1)=='/' and substr($_k,-1)=='/' and strlen($_k)>2)
				{
					$_k=substr($_k,1,strlen($_k)-2);
					//REGEXP or RLIKE
					$_tmp=$_tmp." $_field REGEXP ".$this->DB->getsql($_k);
				}
				else if(preg_match('/\|/i',$_k)){
					$tmp=explode("|",$_k);
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp."  ($_field LIKE ".$this->DB->getsql($tmp[0])." OR $_field LIKE ".$this->DB->getsql($tmp[1]).")";
					}
					else{
						$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($tmp[0]);
					}
				}
				else{
					$_k=str_replace('/','',$_k);
					$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($_k);
				}
			}
			else if($_type==12){
				
				//此类型不能搜索两个表
				$_tablename='';
				if(isset($fArr['set'])){
					if(!is_string($fArr['set'])) $fArr['set']='';
					$_field2=preg_replace('/[^-_0-9a-z]/i','',trim($fArr['set']));
				}

				$tmp=explode(":",$_k);
				if(strlen($tmp[0])>0){
					$_tmp=$_tmp." $_field = ".($this->DB->getsql($tmp[0]));
					if(strlen($tmp[1])>0 and !empty($_field2)){
						$_tmp=$_tmp." and $_field2 = ".($this->DB->getsql($tmp[1]))."";
					}
				}
			}
			else if($_type==13) {
				$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql('%'.$_k.'%');
			}
			else if($_type==15){
				//此类型不能搜索两个表
				$_tablename='';
				if(isset($fArr['set']) and is_array($fArr['set']))
				{
					foreach($fArr['set'] as $arr){
						$arr[1]=preg_replace('/[^-_0-9a-z]/i','',trim($arr[1]));
						$valuetype = ($arr[2]==1?1:0);
						if(empty($arr[1])) continue;
						if(@preg_match($arr[0],$_k)){
							$_tmp=$_tmp." AND {$arr[1]} = ".$this->DB->getsql($_k,$valuetype);
							return $_tmp;
							//break;
						}
					}
				}
				//默认
				$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($_k);
			}
			else if($_type==16){
				
				$_tablename='';
				if(isset($fArr['set']) and is_array($fArr['set'])){
					foreach($fArr['set'] as $_find){
						if(empty($_find) or !is_string($_find)) continue;
						$_find=preg_replace('/[^-_0-9a-z]/i','',trim($_find));
						if(empty($_find)) continue;
						$_tmp=$_tmp." OR  {$_find} LIKE ".$this->DB->getsql($_k);
					}
				}
				if(!empty($_tmp)){
					$_tmp=$_tmp." ($_field LIKE ".$this->DB->getsql($_k).substr($_tmp,4).")";
				}
				//默认
				else{
					$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($_k);
				}
			}

			if(!empty($_tmp)){
				if(isset($fArr['set'])){
					if(!is_string($fArr['set'])) $fArr['set']='';
					$_field2=preg_replace('/[^-_0-9a-z]/i','',trim($fArr['set']));
				}
				if(!empty($_tablename) and !empty($_field2)){
					$_sql=$_sql." AND {$_field2} IN (select  {$_field2} from $_tablename where $_tmp )";
				}
				else{
					$_sql=$_sql." AND $_tmp ";
				}
			}
		}
		return $_sql;
	}


	//处理分类项
	//@sortArr[]: Array("name"=>"变量名","field"=>"默认查询字段","type"=>"类型0|1|2(字符|整数|小数)","set"=>Array(选择项设置));
	//@set[] =Array("title"=>"@表单显示内容","value"=>"表单value值","fieldset"=>'查询字段，为空则使用field',"query"=>"对应查询值，为空时则直接使用value值进行查询，支持用|查询2-6个值");
	protected function getSortSQL($form,$sortArr){

		if(!is_array($sortArr)) return '';
		$sql='';
		foreach($sortArr as $conf){

			if(empty($conf['name'])) continue;

			$_name=$conf['name'];
			$_field=preg_replace('/[^-_0-9a-z]/i','',$conf['field']);
			$_type=$conf['type'];
			if(!in_array($_type,Array(1,2))) $_type=0;
			//没有输入值
			if(!isset($form[$_name]) or strlen($form[$_name])==0) continue;

			$_tmp='';//当前分类框
			$_value=trim($form[$_name]);
			$this->_para=$this->_para.$_name.'='.urlencode($_value).'&';
			foreach($conf['set'] as $sets){

				if($sets['value']!=$_value) continue;

				$_fieldset='';
				if(isset($sets['fieldset'])) $_fieldset=preg_replace('/[^-_0-9a-z]/i','',$sets['fieldset']);
				$_fieldset=(!empty($_fieldset)?$_fieldset:$_field);
				if(empty($_fieldset)) break;
				$_tmp=$_tmp." ".$_fieldset;
				$_query=(isset($sets['query'])?trim($sets['query']):"");
				if(!empty($sets['query'])){
					//支持搜索多个值
					if(preg_match("/\|/i",$sets['query'])){
						$tmpstr='';
						$_rows=explode("|",$sets['query']);
						$n=0;
						foreach($_rows as $_v){
							$_v=trim($_v);
							if(strlen($_v)>0){
								$tmpstr=$tmpstr.",".$this->DB->getsql($_v,$_type);
							}
							$n++;
							if($n>=6) break;
						}
						if(!empty($tmpstr)){
							$tmpstr=substr($tmpstr,1);
							$_tmp=$_tmp." IN (".$tmpstr.")";
						}
					}
					else if(strlen($_query)>0){
						$_tmp=$_tmp." = ".$this->DB->getsql($_query,$_type); //直接使用
					}
				}
				else{
					$_tmp=$_tmp." = ".$this->DB->getsql($_value,$_type); //直接使用
				}
				break;

			}

			if(!empty($_tmp)){
				$sql=$sql." and ".$_tmp;
			}

		}
		return $sql;

	}

	//文件上传
	//@$pre_name  前缀
	//@$randtype：是否需要随机字串：0 不需要 1 随机字母  2 原名
	protected function uploadFile($oldfilename,$upfile,$pre_name,$randtype=0){
		$filename='';
		$updir=$this->_set['updir'];
		if(!empty($updir) and file_exists($upfile['tmp_name'])){
			if(!preg_match('/(\\|\/)$/i',$updir)) $updir=$updir.'/';
			//删除以前的文件
			if(!empty($oldfilename) and file_exists($updir.$oldfilename)){
				unlink($updir.$oldfilename);
			}
			//HASH目录
			
			if(!empty($this->_set['filedir'])){
				$filename=$this->_set['filedir'];
				if(!preg_match('/(\\|\/)$/i',$filename)) $filename=$filename.'/';
				if(!file_exists($updir.$filename)) mkdir($updir.$filename,0777);
			}
			if(!empty($this->_set['filedirhash']) and in_array($this->_set['filedirhash'],Array(1,2))){
				$dir='';
				if($this->_set['filedirhash']==1) $dir=date('Y');
				else $dir=date('Ym');
				if(!empty($dir)){
					$filename=$filename.$dir.'/';
				}
				if(!file_exists($updir.$filename)) mkdir($updir.$filename,0777);
			}

			//购建文件名
			$tmp='';
			if(!empty($pre_name)) {
				$tmp=$pre_name;
			}

			$randtype=intval($randtype);
			if(!in_array($randtype,Array(1,2))) $randtype=0;
			$upfile['name']=str_replace("/[^-_a-z0-9\.]/i","",$upfile['name']);
			//6位随机值（防止被直接下载）
			if($randtype==1){
				$charstr='abcdefghijkmnpqrstuvwxy013456789';
				$tmp=$tmp.$charstr[rand(0,22)].$charstr[rand(0,31)].$charstr[rand(0,31)].$charstr[rand(0,31)].$charstr[rand(0,31)].$charstr[rand(0,31)].$upfile['ext'];
			}
			//文件原名
			else if($randtype==2){
				$tmp=$tmp.$upfile['name'];
			}
			//无附加字符
			else{
				if(empty($tmp)) $tmp=$upfile['name'];
				else $tmp=$tmp.'.'.$upfile['ext'];
			}
			$filename=$filename.$tmp;

			//保存文件并返回
			move_uploaded_file($upfile['tmp_name'],$updir.$filename);
		}
		return $filename;
	}


	protected function get_paymode(){
		if(empty($this->_payMode)){
			$rows=Array();
			$paymodeResult=$this->DB->query("select * from ".$this->gettable("paymode")." where 1",2);

			if(!empty($paymodeResult)){
			foreach($paymodeResult as $arr){
				if(!is_array($arr)) continue;
				$pid=intval($arr['paymode_id']);
				$rows[$pid]=Array("title"=>get_html($arr['paymode_title']),"id"=>$pid,"stat"=>$arr['paymode_stat']);
			}
			}
			$this->_payMode=$rows;
			//print_r($paymodeResult);
		}
	}


	//处理数值用于写入数据表
	protected function formatNum($value,$formart=0){
		$value=preg_replace('/\s/','',$value);
		$value=str_replace(',','',trim($value));
		if(!preg_match('/^(-[0-9]|[0-9])[0-9]*(\.[0-9]+)?$/',$value)) $value='';
		if($formart) $value=0+$value;
		return $value;
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