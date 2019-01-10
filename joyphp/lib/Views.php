<?PHP
/*

	通用接口解析
	=========================

	作用：(业务逻辑处理) 根据请求参数，调用对应对象获取内容；并把内容转换为HTML代码

	注意：本部分不定义任何内容，所有内容均在调用的方法中定义；本部分也不包含认证或解密，认证或解密在调用本对象之前做。

	未解决：列表页提交时参数传递

	
*/



class _views
{

	public $_para;
	public $_obj;
	public $_bindObj;   //绑定处部对象作处理
	protected $DB;
	protected $_pagename='';
	protected $_hiddenstr='';
	protected $_optionSet='';

	public $_info=Array();
	public $_error='';
	public $_jsString='';
	public $_menuSet=Array();
	public $_menu='';
	public $_cmd='';

	public function _views($DB,$bindObj=''){
		$this->DB=$DB;
		if(!empty($bindObj) and is_object($bindObj)){
			$this->_bindObj=$bindObj;
		}

		$this->_pagename=$_SERVER['SCRIPT_NAME'].'?';
		$_menu=getpost('_menu');
		if(!empty($_menu)) $this->_pagename=$this->_pagename."_menu=".htmlspecialchars($_menu).'&';
		if(!empty($_menu)) $this->_hiddenstr=$this->_hiddenstr.'<input type="hidden" name="_menu" value="'.htmlspecialchars($_menu).'">';
	}

	public function __construct($DB,$bindObj='') {
		$this->_views($DB,$bindObj='');
	}

	public function self_init($DB,$bindObj=''){
		return new _views($DB,$bindObj='');
	}

	public function setpage($info) {

		if(is_array($info)){
			$this->_info=$info;
		}

		if(empty($this->_info['total'])) $this->_info['total']='共*条';
		if(empty($this->_info['pageinfo'])) $this->_info['pageinfo']='共*页';
		if(empty($this->_info['pagenow']))  $this->_info['pagenow']='第*页';
		if(empty($this->_info['pageprev'])) $this->_info['pageprev']='上一页';
		if(empty($this->_info['pagenext'])) $this->_info['pagenext']='下一页';
		if(empty($this->_info['pageshow'])) $this->_info['pageshow']='*';
		if(empty($this->_info['pageselect'])) $this->_info['pageselect']='每页*条';
		if(empty($this->_info['pagego'])) $this->_info['pagego']='GO';
		$this->_info['pagenum']=intval($this->_info['pagenum']);
		if($this->_info['pagenum']<0 or $this->_info['pagenum']>5) $this->_info['pagenum']=3;

	}

	public function set_option($name,$arr){
		if(empty($name)) return false;
		$this->_optionSet[$name]=$arr;
	}

	public function get_option($name,$id=''){
		if(empty($name)) {
			if(!empty($id)) return '';
			 return Array();
		}
		
		if(strlen($id)>0) {
			if(isset($this->_optionSet[$name]) and isset($this->_optionSet[$name][$id])) return $this->_optionSet[$name][$id]['options'];
			return '';
		}
		if(isset($this->_optionSet[$name])) return $this->_optionSet[$name];
		return Array();
	}

	//读取缓存中的菜单数据
	public function menu($menuArr){

		/*

#菜单自动缓存
CREATE TABLE `conf_menu` (
  `menu_name` varchar(30) NOT NULL,
  `menu_title` varchar(50) NOT NULL,
  `menu_cont` varchar(5000) default NULL,
  `menu_cmd` varchar(30) NOT NULL,
  `menu_version` varchar(30) NOT NULL,
  `menu_update` date default '0000-00-00',
  `menu_expired` date default '0000-00-00',
  PRIMARY KEY  (`menu_name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;



#以下值来由M层对象的menu方法返回
#menu_cont    接口菜单设置
#menu_cmd     默认命令
#menu_expired 缓存有效期
#menu_version 记录版本号

		*/

		$result=$this->DB->query("select * from conf_menu");
		$tmp=Array();
		$set=Array();
		//echo '<pre>'; print_r($result);
		while ($arr = mysql_fetch_array($result,1)){
			$menu=$arr['menu_name'];
			//echo $arr['menu_cont'].";;<br />";
			$tmp[$menu]=$this->menu_to_arr($arr['menu_cont']);
			//print_r($tmp[$menu]);
			//echo '<br>';
			$set[$menu]=$arr;
		}

		foreach($menuArr as $_m => $arr){
			if(isset($tmp[$_m])){
				$menuArr[$_m]['menu']=$tmp[$_m];
			}
			if(isset($set[$_m])){
				$menuArr[$_m]['cmd']=$set[$_m]['menu_cmd'];//默认的方法
				$menuArr[$_m]['expired']=$set[$_m]['menu_expired'];
			}
		}
		return $menuArr;

	}


	public function main($obj) {

		$this->_obj=$obj;
		$this->_cmd=$this->_obj->_data['_cmd'];


		if(!method_exists($this->_obj,'main')){
			$this->_error='Undefined method main in '.$this->_menu.'!';
		}
		else if(!method_exists($this->_obj,'menu')){
			$this->_error='Undefined method menu in '.$this->_menu.'!';
		}

		if(!empty($this->_error)){
			$ret=Array('result'=>1,'error'=>$this->_error);
			return $ret;
		}

		//获得对象的菜单，若需要更新到缓存则自动更新
		$menus=$this->_menuSet[$this->_menu];
		//print_r($menus);
		$_date=date('Y-m-d');
		//die("OOOK");
		//需要更新
		if(!isset($menus['menu']) or empty($menus['menu']) or $menus['expired']>'2000-01-01' and $menus['expired']<$_date){
			
			$ret=call_user_func(Array($this->_obj,'menu'),$this);
			//echo "GET_MENU<br />"; print_r($ret);
			if(isset($ret['result']) and $ret['result']==0){
				$this->_menuSet[$this->_menu]['menu']=$ret['menu'];
				$this->_menuSet[$this->_menu]['cmd']=(isset($ret['menuconf']['cmd'])?$ret['menuconf']['cmd']:'');
				//echo "TODATA<br />";
				//更新到数据库
				$cont='';
				if($ret['menudata'] and !empty($ret['menudata']) and is_array($ret['menudata'])) {
					$this->_menuSet[$this->_menu]['menu']=$ret['menudata'];//以接口为准2017-12-8
					$cont=$this->menu_to_str($ret['menudata']);
				}
				$title=(isset($ret['menuconf']['title'])?$ret['menuconf']['title']:'');
				$version=(isset($ret['menuconf']['version'])?$ret['menuconf']['version']:'');
				$expired=(isset($ret['menuconf']['expired'])?$ret['menuconf']['expired']:'');
				if(!preg_match('/^2[0-9]{3}-[0-9]{1,2}-[0-9]{1,2}$/i',$expired)) $expired='0000-00-00';

				$sql="replace into conf_menu set menu_name=".$this->DB->getsql($this->_menu);
				$sql=$sql.",menu_title=".$this->DB->getsql($title);
				$sql=$sql.",menu_expired=".$this->DB->getsql($expired);
				$sql=$sql.",menu_cmd=".$this->DB->getsql($this->_menuSet[$this->_menu]['cmd']);
				$sql=$sql.",menu_version=".$this->DB->getsql($version);
				$sql=$sql.",menu_cont=".$this->DB->getsql($cont);
				$sql=$sql.",menu_update=".$this->DB->getsql(date('Y-m-d'));
				$this->DB->query($sql);
				//echo $sql, mysql_error();
			}
			if($this->_obj->_isAPI) {
				usleep(300000);//sleep 0.3s
			}

		}
		$cmd='';
		if(isset($this->_menuSet[$this->_menu]['cmd'])) $cmd=$this->_menuSet[$this->_menu]['cmd'];

		//请求接口或对象
		if(empty($this->_cmd) and !empty($cmd) ){
			$this->_cmd=$cmd;
		}

		if(!empty($this->_cmd)){

			if($this->_cmd!='menu' or empty($ret)) {
				$ret=$this->sent();//不发送两次
			}

		}
		//默认请求
		else {

			$ret=Array('result'=>1,'error'=>'Undefined method!');
			$this->_error=$ret['error'];
			return $ret;
		}

		$ret['html']='';
		//处理返回数据：错误显示
		if($ret['result']>0){

			if(empty($ret['pagetitle'])){

				if(isset($this->_menuSet[$this->_menu]['menu']))
				{
					foreach($this->_menuSet[$this->_menu]['menu'] as $arr){
						if($arr['cmd']==$this->_cmd){
							$ret['pagetitle']=$arr['title'];
						}
					}
					if(empty($ret['pagetitle'])) $ret['pagetitle']=$this->_menuSet[$this->_menu]['menu']['title'];
				}
			}
		}
		//处理返回数据（正常的返回）
		else{

			if($ret['type']=='text'){
				$ret['_html']=$ret['data'];
			}
			else if($ret['type']=='table'){
				//echo '<pre>';print_r($ret);exit;
				$ret['_html']=$this->view_list($ret,1);
			}
			else if($ret['type']=='list'){
				//echo '<pre>';print_r($ret);exit;
				$ret['_html']=$this->view_list($ret);
			}
			else if($ret['type']=='form'){
				$ret['_html']=$this->view_form($ret);
			}
			//json样式
			else if($ret['type']=='json'){
				header("Cache-Control: no-chache"); 
				header("Content-type: application/json");
				echo $ret['json'];
				exit;
			}
			//图片样式
			else if($ret['type']=='image'){
				$imgStr=$ret['img'];
				if(empty($imgStr)){
					$imgStr='R0lGODlhCQAJAIABAFZWVv///yH5BAEAAAEALAAAAAAJAAkAAAINjI+JYJzuVpMh1qmyLgA7';
				}
				if(empty($ret['imgtype'])) $ret['imgtype']='image/gif';
				header("Cache-Control: no-chache"); 
				header("Content-type: ".$ret['imgtype']);
				echo base64_decode($imgStr);
				exit;
			}
		}
		return $ret;
	}

	public function sent(){

		$_save =getpost('_save');
		$_check=getpost('_check');
		//三方接口预检查
		if(!empty($_save) and !empty($_check)){
			$sets=explode(';',$_check);
			if(!empty($sets[0]) and !empty($sets[1]) and !empty($sets[2])){
				$value=getpost($sets[2]);
				if(!empty($value)){

					$data=Array();
					$data[$sets[1]]=$value;
					$res=call_user_func(Array($this->_bindObj,$sets[0]),$data);
					if($res['result']>0){
						$this->_error=$ret['error'];
						$ret=Array('result'=>1,'error'=>'Function ('. $sets[0].'): '.$res['error'].'!');
						return $ret;
					}
					if($this->_obj->_isAPI) {
						usleep(300000);//sleep 0.3s
					}
				}
			}
		}

		$ret=$this->_obj->main($this->_cmd);
		//
		if($ret['result']>0){
			$this->_error=$ret['error'];
			//print_r($ret);exit;
		}

		if(isset($ret['type']) and $ret['type']=='save'){

			//表单类型提交成功
			if(isset($ret['result']) and $ret['result']==0){

				//附加处理
				if(!empty($ret['savecall']) and is_array($ret['savecall'])){
					$sets=$ret['savecall'];
					$data=Array();
					if(!empty($sets['para']) and !empty($sets['value'])){
						$data[$sets['para']]=$sets['value'];
					}
					if(!empty($this->_bindObj) and !empty($sets['func'])){
						$res=call_user_func(Array($this->_bindObj,$sets['func']),$data);
						//不出错则不显示任何信息
						if($res['result']>0){
							$ret['jumpmsg']=$ret['jumpmsg'].$sets['error'];
						}
					}
				}

				if($ret['jumpcmd']!=$this->_cmd){
					header("location: ".$this->_pagename.'_cmd='.$ret['jumpcmd'].'&'.$ret['jumpurl'].'showMsg='.urlencode($ret['jumpmsg']));
					exit;
				}

			}
			//表单类型失败（jumpcmd使用原来的cmd）
			else{
				//print_r($ret);
				$this->_cmd=$ret['jumpcmd'];
				if($this->_obj->_isAPI) {
					usleep(500000);//sleep 0.5s
				}
				//$ret=call_user_func(array($this->_obj,$this->_cmd));
				$ret=$this->_obj->main($this->_cmd);
				if($ret['result']>0){
					$this->_error=$this->_error." || ".$ret['error'];
				}
			}
		}

		return $ret;

	}

	protected function view_list($ret,$istable=0){

		//显示搜索及页码
		$conf=$ret['pageconf'];
		$fconf=$ret['formhead'];
		$js_form='';
		$form=getpost();

		$html='
	<table width="100%" cellspacing="0" cellpadding="4" border="0">
	  <tr><td height="25">';

		//显示搜索框
		if(!empty($conf['searchtitle']) and !empty($conf['searchfind'])){
			if(empty($conf['searchsubmit'])) $conf['searchsubmit']='Submit';
			$html=$html.'
			<form action="'.$this->_pagename.'" name="searchForm" method="GET">'.$conf['searchtitle'].$this->_hiddenstr.'<input type="hidden" name="_cmd" value="'.$this->_cmd.'">';
			if(isset($form['_size'])){
				$size=intval($form['_size']);
				$this->_obj->_para=$this->_obj->_para.'_size='.$size.'&';
				if(!empty($size)) $html=$html.'<input type="hidden" name="_size" value="'.$size.'">';
			}
			if(is_array($conf['searchfind'])) $html=$html.$this->get_search_box($conf['searchfind']);
			$html=$html.'<input type="text" name="_k" value="'.$this->get_html($form['_k']).'">';
			if(isset($conf['searchsort']) and is_array($conf['searchsort'])) {
				$html=$html.$this->get_sort_box($conf['searchsort']);//searchsort
			}
			if(!empty($conf['searchhidden'])){
				$html=$html.$conf['searchhidden'];
			}
			$html=$html.'
			<input type="submit" value="'.$conf['searchsubmit'].'"></form>
			</td></tr>
		<tr><td height="25">';
		}

		$pageinfo=$ret['pageinfo'];
		$page=$pageinfo['page'];
		//print_r($pageinfo);
		$p_html='<span class="v_page_list"><b class="font_hot">'.$ret['pagetitle'].'</b> &nbsp;';
		
		$p_code='';
		$pageinfo['pagenum']=intval($pageinfo['pagenum']);
		if(empty($conf['disalbed'])){
			$p_code=$p_code.''.$conf['pageprev'].str_replace('*',intval($pageinfo['rowsnum']),$this->_info['total']);
		}
		
		//显示页码
		if($pageinfo['pagenum']>1){

			//上一页
			if($page>1){
				$p_code=$p_code.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.($page-1).'">'.$this->_info['pageprev'].'</a></span>';
			}
			else {
				$p_code=$p_code.'<span class="page_none">'.$this->_info['pageprev'].'</span>';
			}

			//显示数字页码
			if($this->_info['pagenum']>0){
				if($page==1){
					$p_code=$p_code.'<span class="page_now">'.str_replace('*',1,$this->_info['pageshow']).'</span>';
				}
				else{
					$p_code=$p_code.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page=1">' .str_replace('*',1,$this->_info['pageshow']).'</a></span>';
				}
				$page0=$page-$this->_info['pagenum'];
				if($page0>$pageinfo['pagenum']-$this->_info['pagenum']*2-1) $page0=$pageinfo['pagenum']-$this->_info['pagenum']*2-1;
				if($page0<2) $page0=2;
				if($pageinfo['pagenum']<=$this->_info['pagenum']*2+3) $page0=2;

				$page1=$page0+$this->_info['pagenum']*2;
				if($page1>$pageinfo['pagenum']-1) $page1=$pageinfo['pagenum']-1;

				if($page0>2) $p_code=$p_code.'<span class="page_none">*</span>';
				for($i=$page0;$i<=$page1;$i++){
					
					if($i==$page){
						$p_code=$p_code.'<span class="page_now">'.str_replace('*',$i,$this->_info['pageshow']).'</span>';
					}
					else{
						$p_code=$p_code.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.$i.'">' .str_replace('*',$i,$this->_info['pageshow']).'</a></span>';
					}
					if($i>=$page1) break;
				}

				if($page1<$pageinfo['pagenum']-1) $p_code=$p_code.'<span class="page_none">*</span>';

				if($page==$pageinfo['pagenum']){
					$p_code=$p_code.'<span class="page_now">'.str_replace('*',$pageinfo['pagenum'],$this->_info['pageshow']).'</span>';
				}
				else{
					$p_code=$p_code.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.$pageinfo['pagenum'].'">' .str_replace('*',$pageinfo['pagenum'],$this->_info['pageshow']).'</a></span>';
				}
			}

			//下一页
			if($page<$pageinfo['pagenum'])
				$p_code=$p_code.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.($page+1).'">'.$this->_info['pagenext'].'</a></span>';
			else $p_code=$p_code.'<span class="page_none">'.$this->_info['pagenext'].'</span>';


			
		}

		if($pageinfo['pagenum']>1){
			if(!empty($this->_info['pageinfo'])){
				$p_code=$p_code.'<span>'.str_replace('*',intval($pageinfo['pagenum']),$this->_info['pageinfo']).'</span>';
			}
		}

		$p_html=$p_html.$p_code;
		//设置每页显示数
		$_showSet=0;
		if(!empty($conf['pageset']) and $pageinfo['pagenum']>1){
			$tmp=explode(',',$conf['pageset']);
			$_pset=Array();
			foreach($tmp as $psize){
				$t=intval($psize);
				if($t>0 and $t<300) $_pset[]=$t;
			}
			if(count($_pset)>1){
				
				$_showSet=1;
				$p_html=$p_html.'<span><select id="_v_page_size">';
				foreach($_pset as $psize){
					$p_html=$p_html.'<option value="'.$psize.'"'.($pageinfo['pagesize']==$psize?" selected":"").'>'.str_replace('*',$psize,$this->_info['pageselect']).'</option>';
				}
				$p_html=$p_html.'</select></span>';
			}
		}

		//显示跳转
		if($pageinfo['pagenum']>2){
			if(!empty($this->_info['pagenow'])){
				$_showSet=1;
				$p_html=$p_html.'<span>'.str_replace('*','<input type="text" style="width:22px;" value="'.intval($page).'" id="_v_page_num" onkeyup="this.value=this.value.replace(/\D/g,\'\');">',$this->_info['pagenow']).'</span>';
			}
		}
		if($_showSet){
			$p_html=$p_html.'<span><input type="submit" value="'.$this->_info['pagego'].'" onclick="_v_page_go();"></span>';
		}

		$p_html=$p_html.'<span>'.$this->encode($conf['addstring']).'</span>';

		$p_html=$p_html.'</span>';


		if($_showSet){
			$p_html=$p_html.'
<script>
<!--
function _v_page_go(){
	var url="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'";
	if(document.getElementById("_v_page_size")) url=url+"&_size="+document.getElementById("_v_page_size").value;
	if(document.getElementById("_v_page_num")) url=url+"&_page="+document.getElementById("_v_page_num").value;
	location.href=url;
}
-->
</script>';
		}

		$html=$html.$p_html.'</td></tr></table>
	<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="8"></td></tr></table>';
		if(!empty($fconf['savecmd'])){
			if(!isset($fconf['formname']) or empty($fconf['formname'])){
				$fconf['formname']='listForm';
			}
			$html=$html.'
	<form action="'.$this->_pagename.'" name="'.$fconf['formname'].'"  method="post" onsubmit="return _v_check_form(this)">';
		}

		$divide=0;
		if(isset($conf['dividesize'])) $divide=intval($conf['dividesize']);
		//显示头
		$tdnum=0;
		if($istable){
			$html=$html.'<table width="100%" cellspacing="'.intval($conf['cellspacing']).'" cellpadding="'.intval($conf['cellpadding']).'" border="0" bgcolor="#DDDDDD" class="tbl_main">';

			$tdheight=26;
			if(isset($conf['headheight'])){
				$_tmp=intval($conf['headheight']);
				if($_tmp>=16 and $_tmp<=36) $tdheight=$_tmp;
			}

			//表头
			$html=$html.'
		<tr bgcolor="#F6F6F6" class="tbl_head" align="center">';
			$n=0;
			if(!empty($ret['listhead']) and is_array($ret['listhead']) and isset($ret['listhead'][0]))
			foreach($ret['listhead'] as $arr){
				$html=$html.'
			<td width="'.$arr['width'].'"'.($n==0 ? ' height="'.$tdheight.'"':'').'>'.$arr['title'].'</td>';
				$n++;
			}
			$html=$html.'
		</tr>';
			$tdnum=$n;
		}
		else{

			if($divide){
				$html=$html.'
		<table cellSpacing="0" cellPadding="0" width="100%"  border="0"  align="center"><tr><td height="'.$divide.'" style="padding:0px;margin:0px;" bgcolor="'.$conf['dividecolor'].'"></td></tr></table>
		<table cellSpacing="0" cellPadding="0" width="100%"  border="0"  align="center"><tr><td height="6"></td></tr></table>';
			}

		}

		$tdheight=26;
		if(isset($conf['rowheight'])){
			$_tmp=intval($conf['rowheight']);
			if($_tmp>=16 and $_tmp<=500) $tdheight=$_tmp;
		}

		//显示内容
		$r=0;
		$keyname=$fconf['keyname'];
		$submitShow=0;

		if(!empty($ret['listdata']) and is_array($ret['listdata']) and isset($ret['listdata'][0]) and is_array($ret['listdata'][0]))
		foreach($ret['listdata'] as $arr){

			$n=0;
			$keyvalue=$arr[$keyname];
			$tdstr='';
			foreach($ret['listhead'] as $row){

				$name=$row['name'];
				$value =$this->encode($arr[$name]);
				$fdname=$name.'-'.$keyvalue;
				if(!isset($row['type']) or empty($fconf['savecmd'])){
					$showstr=$value;
				}
				else if($row['type']=='text'){
					$showstr='<input type="text" style="width:97%;" name="'.$fdname.'" value="'.$value.'">';
					$submitShow=1;
				}
				else if($row['type']=='select'){
					$select=$arr[$name];
					//自定义的多个值
					if(is_array($select)){
						$showstr='
				<select style="width:98%;" name="'.$fdname.'"'.(!empty($fconf['seleaction'])?' onchange="'.$fconf['seleaction'].'"':'').'>';

						foreach($select as $rs){
							$showstr=$showstr.'<option value="'.$rs['values'].'"'.($rs['titles'] ? ' title="'.$this->get_html($rs['titles']).'"':'').($rs['selected']?' selected>'.(!empty($rs['values'])?'* ':''):'>').''.$rs['options'].'</option>';
						}
						$showstr=$showstr.'</select>';
						$submitShow=1;
					}
					//使用跨平台参数
					else if(!empty($row['option'])){
						//$value=$select;
						$option=$row['option'];
						$select=$this->get_option($option);
						if(empty($select) or !is_array($select)){
							$showstr='--';
						}
						else{
							$showstr='
				<select style="width:98%;" name="'.$fdname.'"'.(!empty($fconf['seleaction'])?' onchange="'.$fconf['seleaction'].'"':'').'>';

							foreach($select as $rs){
								$showstr=$showstr.'<option value="'.$rs['values'].'"'.($rs['titles'] ? ' title="'.$this->get_html($rs['titles']).'"':'').($value==$rs['values']?' selected>'.(!empty($rs['values'])?'* ':''):'>').''.$rs['options'].'</option>';
							}
							$showstr=$showstr.'</select>';
							$submitShow=1;
						}
					}
					//字符串
					else if(empty($select)){
						$showstr='--';
					}
				}
				else if($row['type']=='hidden'){
					$showstr='<input type="hidden" name="'.$fdname.'" value="'.$value.'">'.$value;
				}
				else if($row['type']=='checkbox'){
					$showstr='--';
					if(!empty($value)){
						$showstr='<input type="checkbox" name="'.$fdname.'" value="'.$value.'">';
						$submitShow=1;
					}
				}
				else if($row['type']=='view'){
					if(isset($row['option']) and !empty($row['option'])){
						$option=$row['option'];
						//$showstr='='.$value; //$this->get_option($option,$value);
						$showstr=$this->get_option($option,$value);
					}
					else{
						$showstr=$value;
					}
				}
				else{
					$showstr=$value;
				}

				$tdstr=$tdstr.'
			<td width="'.$row['width'].'" '.(!empty($row['align'])?" align=\"".$row['align']."\"":"").($n==0 ? ' height="'.$tdheight.'"':'').' '.(!empty($row['tdset'])?' '.$row['tdset'].'':'').'>'.$showstr.'</td>';
				$n++;
			}


			if($istable){
				$html=$html.'
		<tr bgcolor="#FFFFFF" class="tbl_row" align="center">';
				$html=$html.$tdstr.'
		</tr>';
			}
			else{
				$html=$html.'
		<table cellSpacing="0" cellPadding="0" width="100%"  border="0"  align="center"><tr align="center">';
				$html=$html.$tdstr.'
		</tr></table>';
				if($divide){
					$html=$html.'
		<table cellSpacing="0" cellPadding="0" width="100%"  border="0"  align="center"><tr><td height="6"></td></tr></table>
		<table cellSpacing="0" cellPadding="0" width="100%"  border="0"  align="center"><tr><td height="'.$divide.'" style="padding:0px;margin:0px;" bgcolor="'.$conf['dividecolor'].'"></td></tr></table>
		<table cellSpacing="0" cellPadding="0" width="100%"  border="0"  align="center"><tr><td height="6"></td></tr></table>';
				}
			}
			$r++;
		}

		if($r==0 and $istable){
				$html=$html.'
		<tr bgcolor="#FFFFFF" class="tbl_row" align="center">';
				$html=$html.'<td colspan="'.$tdnum.'" height="'.$tdheight.'" align=center><span class="font_disabled">EMPTY</span></td>
		</tr>';
		}

		if($istable){
			$html=$html.'</table>';
		}

		//显示页码在下端
		if($pageinfo['pagenum']>1 and !empty($p_code) and $r>6){
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="8"></td></tr></table>
		<table width="100%" cellspacing="1" cellpadding="3" border="0">
		<tr><td height="35" align="lef"><span class="v_page_list"><b class="font_hot">'.$ret['pagetitle'].'</b> &nbsp;'.$p_code.'
		</span>
		</td></tr></table>';
		}

		if(!empty($ret['actionbar'])){
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="5"></td></tr></table>
		<table width="100%" cellspacing="1" cellpadding="2" border="0" bgcolor="#DDDDDD" class="tbl_main">
		<tr><td bgcolor="#FFFFFF" class="tbl_row" height="32">'.$this->encode($ret['actionbar']).'
		</td></tr></table>';
		}

		//操作按钮
		$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="8"></td></tr></table>';
		//显示按钮
		if(!empty($fconf['savecmd'])){
			if(empty($fconf['submitstr'])) $fconf['submitstr']='Submit';
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td height="28" align="center">';

			if(!empty($fconf['sentaction']) and is_array($fconf['sentoption'])){
				$html=$html.'<select name="'.$fconf['sentaction'].'">';
				foreach($fconf['sentoption'] as $tmp){
					$html=$html.'<option value="'.$tmp['value'].'">'.$tmp['option'].'</option>';
				}
				$html=$html.'</select>';
			}

			$html=$html.'
		<input type="hidden" name="_cmd" value="'.$fconf['savecmd'].'">
		<input type="submit" value="'.$fconf['submitstr'].'"  id="submit_'.$fconf['formname'].'" '.(empty($submitShow)?' disabled=true':'').' class="save_button" '.(!empty($fconf['selecheck'])?' onclick="return _v_check_checkbox(\''.$fconf['formname'].'\',\''.$fconf['selecheck'].'\',\''.$fconf['selealert'].'\');"':'').'>';


			/*

			if(!empty($fconf['selebox']) and !empty($fconf['selename'])){
				$html=$html.' <input type="checkbox" onclick="_v_checkbox_selectall(\''.$fconf['formname'].'\',\''.$fconf['selename'].'\',this.checked);">' .$fconf['selebox'];
			}

			*/

			$html=$html.'
		</td></tr></table>';
			$html=$html.'</form>';

			$js_form=$js_form.'
		_v_form_clear_submit("'.$fconf['formname'].'");';
		}

		//用于JS判断的正则表达式
		$html=$html.'
<script>
';
		if(!empty($js_form)){
			$html=$html.'

'.$js_form.'
';
		}
		$html=$html.'
</script>';
		return $html;
	}


	protected function view_form($ret){
		$from=getpost();
		//Form头
		$js_form='';
		$html='';
		foreach($ret['formhead'] as $rows){
			if(!isset($rows['formname']) or empty($rows['formname'])){
				$rows['formname']='sentForm';
			}

			//form的input
			$n=0;
			$tmp='';
			$checkArr=Array();
			$w1=$rows['tdwidth'];
			if($w1<5 or $w1>50) $w1=10;
			foreach($ret['formdata'] as $set){
				if(!isset($set['name']))  continue;
				if($set['formname']!='' and $set['formname']!=$rows['formname']
					or empty($set['formname']) and $rows['formname']!='sentForm'){
					continue;
				}

				$name=$set['name'];
				if(empty($name) or preg_match('/^_/',$name) or $set['type']=='hidden') continue;
				$tmp=$tmp.$this->showInput($form,$set,$w1,$rows);
				if(!empty($tmp)) $w1=0;
				//$html=$html.$tmp;
				if(!empty($set['check']) and $set['type']!='radio') {
					$checkArr[$set['check']]=$set['name'];
				}

				$n++;

			}

			if($n==0) continue;

		//form的submit
		//name="_para"出错时使用，保存时请使用hidden包括必须参数
		//<input type="hidden" name="_para" value="'.$this->get_html($form['_para']).'">

			if(!empty($rows['savecmd'])) {
				$html=$html.'
	<form action="'.$this->_pagename.'" name="'.$rows['formname'].'"  enctype="multipart/form-data" method="post" onsubmit="return _v_check_form(this)'.(!empty($rows['submitCheck'])?' && '.$rows['submitCheck']:'').'">';
			}
			$html=$html.'
    <TABLE cellSpacing=1 cellPadding=3 width="100%" align=center  bgColor="#CCCCCC" class="tbl_main" border=0>
		<tr class="tbl_head" bgcolor="#EEEEEE" align="center"><td colspan="2" height="26"><b>'.$rows['title'].'</b></td></tr>';
			$html=$html.$tmp;
			if(empty($rows['submitstr'])) {
				$rows['submitstr']='Sumbit';
			}

			if(!empty($rows['savecmd'])){
			$html=$html.'
		<tr  class="tbl_rows" bgcolor="#EEEEEE"><td align="right" height="35"></td>
		<td>';
				$html=$html.$this->_hiddenstr.'
		<input type="hidden" name="_save" value="1">
		<input type="hidden" name="_cmdlast" value="'.$this->_cmd.'">
		<input type="hidden" name="_cmd" value="'.$rows['savecmd'].'">';
				if(!empty($rows['checkcall']) and is_array($rows['checkcall'])){
					$sets=$rows['checkcall'];
					$html=$html.'
		<input type="hidden" name="_check" value="'.$sets['func'].';'.$sets['para'].';'.$sets['value'].'">';
				}

				//hidden input
				foreach($ret['formdata'] as $set){
					$name=$set['name'];
					//if(preg_match('/^_/',$name)) continue;
					if($set['type']!='hidden') continue;
					$html=$html.'<input type="hidden" name="'.$name.'" value="'.($set['value']).'">';
				}

		$html=$html.'
		<input type="submit" value="'.$this->get_html($rows['submitstr']).'"  class="save_button"  id="submit_'.$rows['formname'].'">';

				if(!empty($rows['backstr']) and !empty($rows['backcmd'])){
				$html=$html.'
		<input type="button" value="'.$this->get_html($rows['backstr']).'" onclick="window.open(\''.$this->_pagename.'&_cmd='.$rows['backcmd'].'\',\'_self\');" class="save_button">';
				}
		$html=$html.'
		</td></tr>';
			}
		$html=$html.'
		</table>';

			if(!empty($rows['savecmd'])) {
				$html=$html.'</form>';
			}
			$html=$html.'
		<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td height="8"></td></tr></table>';

			$js_form=$js_form.'
		_v_form_clear_submit("'.$rows['formname'].'");';

		}


		//用于JS判断的正则表达式
		$html=$html.'
<script>

'.$this->_jsString.'
';
		$tmp='';
		foreach($checkArr as $checkname=> $arrays){


			$tmp=$tmp."function user_func_{$checkname}(name,value){

	//alert(value+' = '+_v_input_check_true[name] +' - '+_v_input_check_false[name]);
	if(_v_input_check_true[name] && _v_input_check_true[name] == value) return true;
	if(_v_input_check_false[name] && _v_input_check_false[name] == value) return false;

	url=\"".$this->_pagename."&_cmd=$checkname&\";\r\n";
			$tmp=$tmp."	url=url+name+\"=\"+_v_submit_value(value);\r\n";
			//$tmp=$tmp."	url=url+name+\"=\"+js_submit_value(values));\r\n";
			$tmp=$tmp."
	ret=_CURL.getURL(url,'',20);
	if(/\[OK\]/i.test(ret)){_v_input_check_true[name]=value;return true;}else{_v_input_check_false[name]=value;return false;};";
			$tmp=$tmp."\r\n}\r\n\r\n";
		}
		if(!empty($tmp)){
			$tmp="\r\nvar _CURL=new getUrlCont('_CURL',20);\r\n".$tmp;
		}
		$html=$html.$tmp;

		if(!empty($js_form)){
			$html=$html.'

'.$js_form.'

';
		}
		$html=$html.'

</script>';



		return $html;
	}


	//显示输入框
	function showInput($form,$set,$w1=0,$row)
	{
		if($set['type']=='hidden') return '';

		$js='';
		$name=$set['name'];
		$value=(isset($set['value'])?$set['value']:'');
		if($set['type']!='checkbox' and $set['type']!='view'){
			$value=(isset($form[$name])?$this->get_html($form[$name]):$value);//有提交数据
		//? $this->strdecode($form[$name]) : $this->strdecode($set['value']));
		}


		$width=trim($set['width']);
		$size=intval($set['size']);

		//显示判断输入值时使用的JS代码
		$v1="";
		$v2=0;
		$onblur='';
		if(!empty($set['preg']) or $size>0){
			//$set['preg']=str_replace('\\','\\\\',$set['preg']);
			//$set['preg']=str_replace('"','\\"',$set['preg']);
			if(!in_array($set['type'],Array('radio','checkbox','select','file'))){
				//$v1=$set['preg'];
				$v2=1;
				$onblur=" onblur=\"_v_check_input('{$name}',this.value);\"";
			}
		}


		if(!empty($v1) or !empty($v2)){

			$minstr='';
			if(isset($set['limit']) and preg_match('/&/',$set['limit'])){
				$tmp=explode("&",$set['limit']);
				$min=doubleval($tmp[0]);
				$max=doubleval($tmp[1]);
				if(preg_match('/^[-]?[0-9]+(\.[0-9+])?$/',$min) and preg_match('/^[-]?[0-9]+(\.[0-9+])?$/',$max)){
					$minstr=',"max":'.$max.',"min":'.$min.'';
				}
			}

			if(isset($set['check']) and !empty($set['check'])){
				$minstr=$minstr.',"check":"user_func_'.$set['check'].'"';
			}
			$_title=str_replace('"','',$set['title']);
			$_title=str_replace('*','',$set['title']);
			$_title=strip_tags($_title);
			$_title=$this->get_html(trim($_title));
			$js=$js.'
_v_input_set["'.$name.'"]={"title":"'.$_title.'","size":'.$size.''.(preg_match('/^[0-9]+$/i',$set['limit'])?',"limit":'.intval($set['limit']):'').$minstr.',"preg":"'.str_replace('"','\\"',$set['preg']).'","error":"'.$set['error'].'","note":"'.$set['note'].'"};';
		}

		if(empty($set['title'])) $set['title']=$name;
		if($w1>0) $w2=100-$w1;
		$html='
		<tr  bgcolor="#FFFFFF" class="tbl_row"><td align="right" height="27" '.($w1>0?' width="'.$w1.'%"':'').'>'.($set['title']).(strtoupper($set['notnull'])=='Y' ? ' <font color="#EE0000">*</font>':'').'</td><td'.($w1>0?' width="'.$w2.'%"':'').'>';

		//表单前面的说明
		if(!empty($set['intro'])){
			$html=$html.$set['intro'];
		}

		//根据不同类型显示input表单
		
		if($set['type']=='text'){
			$html=$html.'
			<input type="text" name="'.$name.'" value="'.$value.'"'.(!empty($set['size']) ? ' maxlength="'.$size.'"' :'').($width>0 ? ' style="width:'.$width.';"' :'').$onblur.'>'.$set['unit'];
		}
		else if($set['type']=='file'){
			if(!empty($set['value'])){
				$html=$html.'已上传文件：'.$set['unit'].$set['value'].' '.(!empty($set['unit'])?' &nbsp; <a href="'.$set['unit'].$set['value'].'" target="_blank">打开/下载</a>':'').'<br />';
			}
			$html=$html.'
			<input type="file" name="'.$name.'" value="'.$value.'"'.($width>0 ? ' style="width:'.$width.';"' :'').'>';
		}
		else if($set['type']=='password'){
			$html=$html.'
			<input type="password" name="'.$name.'" value="'.$value.'"'.(!empty($set['size']) ? ' maxlength="'.$size.'"' :'').($width>0 ? ' style="width:'.$width.';"' :'').$onblur.'>';
		}
		else if($set['type']=='textarea'){
			$unit=intval($set['unit']);
			if($unit<3) $unit=5;
			$html=$html.'
			<textarea name="'.$name.'"  rows="'.$unit.'" style="resize:none;'.($width>0 ? ' width:'.$width.';' :'').'" '.$onblur.'>'.$value.'</textarea>';
		}
		else if($set['type']=='radio'){

			$n=0;
			foreach($set['value'] as $arr){
				//if(!isset($arr['option']) or empty($arr['option'])) continue;
				$html=$html.'
			<input type="radio" name="'.$name.'" value="'.$arr['values'].'" '.(strlen($arr['onclick'])>0 ? ' onclick="'.$arr['onclick'].'"' :''). ($arr['titles'] ? ' title="'.$this->get_html($arr['titles']).'"':'').' '. ($arr['selected'] ? ' checked':'').'>'.$arr['options'];
				if(!empty($arr['remark'])){
					$html=$html.' &nbsp; <font color="#888888">'.$arr['remark'].'</font>';
				}
				$n++;
			}
			if(!empty($set['note'])) $html=$html.'<br>';
		}
		else if($set['type']=='select'){

			$html=$html.'
			<select name="'.$name.'" '.(strlen($set['onchange'])>0 ? ' onchange="'.$set['onchange'].'"' :'').($width>0 ? ' style="width:'.$width.';"' :'').(!empty($set['size']) ? ' size="'.intval($size).'"' :'').'>';
			$n=0;
	
			$select=Array();
			if(is_array($set['value'])) $select=$set['value'];
			else if(!empty($row['option'])){
				$select=$this->get_option($row['option']);
				if(!is_array($select))  $select=Array();
			}
			//if(!is_array($set['value'])) $set['value']=Array();
			foreach($set['value'] as  $arr){
				if(!isset($arr['options']) or empty($arr['options'])) continue;
				$html=$html.'<option value="'.$arr['values'].'"'. (($value==$arr['values'] or isset($arr['selected']) and $arr['selected']) ? ' selected':'').'>'.$arr['options'].'</option>';
				$n++;
			}
			$html=$html.'
			</select>'.$set['unit'];
		}
		else if($set['type']=='checkbox'){

			$n=0;
			if(!is_array($set['value'])) $set['value']=Array();
			foreach($set['value'] as $arr){
				if(!isset($arr['options']) or empty($arr['options'])) continue;
				$html=$html.'
			<input type="checkbox" name="'.$name.'[]" value="'.$arr['value'].'" '. ($arr['selected'] ? ' checked':'').'>'.$arr['options'];
				if(!empty($arr['remark'])){
					$html=$html.' &nbsp; <font color="#888888">'.$arr['remark'].'</font>';
				}
				$n++;
			}
			if(!empty($set['note'])) $html=$html.'<br>';
		}
		//type：其它未定义或为view
		else{
			$set['type']='view';
			if(!empty($row['option'])){
				$this->get_option($row['option'],$value);
			}
			else{
				$html=$html.$this->encode($value);
			}
			//$html=$html.'<b>Unknow type ('.$set['type'].')!</b>';
		}

		//显示表单后的错误提示或注释
		if($set['type']!='view'){
			$html=$html.' &nbsp; <span id="_v_error_msg_'.$name.'" class="font_note">'.(isset($set['note'])?$set['note']:'').'</span>';
		}

		$html=$html.'</td></tr>';
		$this->_jsString=$this->_jsString.$js;
		return $html;
	}

	function get_sort_box($sortArr){
		$form=getpost();
		$html='';
		foreach ($sortArr as  $setArr) {
			$tmp='';
			$_t=isset($setArr['type'])?intval($setArr['type']):0;
			$_k=$setArr['name'];
			if(!empty($setArr['name']) and isset($setArr['set'])){
				$_v=isset($form[$_k])?$form[$_k]:'';
				//echo "<br /><br />###";print_r($setArr['set']);
				if(is_array($setArr['set'])){
					$select=$setArr['set'];
				}
				//支持本地的数据
				else if(is_string($setArr['set'])){
					$select=$this->get_option($setArr['set']);
				}
				else{
					$select=Array();
				}

				//print_r($select);
				//print_r($this->_optionSet);
				foreach($select as $arr){
					$tmp=$tmp."<option value=\"".$arr['values']."\" ".(($_v==$arr['values'] and strlen($_v)>0)?" selected":"").">".$arr['options']."</option>";
				}
			}
			if(!empty($tmp)){
				$html=$html.'
			<select name="'.$_k.'">'.$tmp.'
			</select>';
			}
		}
		return $html;
	}

	function get_search_box($findArr){

		$showSet=Array();
		$showSet['0']='?==';
		$showSet['1']='===';
		$showSet['2']='==:';
		$showSet['3']='==@';
		$showSet['10']='?≈';
		$showSet['12']='＝:';
		$showSet['13']='≈';
		$showSet['15']='≌';
		$showSet['16']='∽';

		$_f=getpost('_f');
		$html='<select name="_f" style="text-align:right;">';
		$n=0;
		foreach($findArr as $_value =>$arr){
			$_t=intval($arr['type']);
			if(!isset($showSet[$_t])){
				continue;
			}
			$html=$html.'
			<option  style="text-align:right;" value="'.$_value .'"'.(($n==0 or $_value==$_f and strlen($_f)>0)?" selected":"").'>'.$arr['title'].'&nbsp;'.$showSet[$_t].'</option>';
			$n++;
		}
		$html=$html.'</select>';
		return $html;
	}

	public function replace_option($match){
		if(!isset($match[2]) or empty($match[2])) return '';
		return $this->get_option($match[1],$match[2]);
	}

	public function encode($str){
		if(is_array($str)) return $str;
		$str=str_replace('{[pagename]}',$this->_pagename,$str);
		$str=preg_replace_callback('/\{([a-z]+)=([0-9]+)\}/',array($this,"replace_option"),$str);//$this->getoptions
		return $str;
	}

	protected function menu_to_str($arr){
		if(is_string($arr)) return $arr;
		$str=json_encode($arr);
		return $str;
	}

	protected function menu_to_arr($str){
		if(is_array($str)) return $str;
		if(empty($str)) return Array();
		$arr=json_decode($str,true);
		return $arr;
	}

	public function get_html($str,$htmlsc=0){
		if($htmlsc==0 or $htmlsc==2){
			$str=htmlspecialchars($str);
		}
		if($htmlsc==1 or $htmlsc==2){
			$str=str_replace("  ","&nbsp; ",nl2br($str));
		}
		return $str;
	}
}













?>