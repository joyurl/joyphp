<?PHP
/*

	通用接口解析
	=========================

	作用：(业务逻辑处理) 根据请求参数，调用对应对象获取内容；并把内容转换为HTML代码

	注意：本部分不定义任何内容，所有内容均在调用的方法中定义；本部分也不包含认证或解密，认证或解密在调用本对象之前做。


*/



class _views
{

	protected $_para;
	protected $_obj;
	protected $_bindObj;   //绑定处部对象作处理
	protected $_pagename='';
	protected $_hiddenstr='';

	public $_info=Array();
	public $_errMsg='';
	public $_jsString='';
	public $_menuSet=Array();

	public function _views($bindObj=''){
		if(!empty($bindObj) and is_object($bindObj)){
			$this->_bindObj=$bindObj;
		}

		$this->_pagename=$_SERVER['SCRIPT_NAME'].'?';
		$_menu=getpost('_menu');
		if(!empty($_menu)) $this->_pagename=$this->_pagename."_menu=".urlencode($_menu).'&';
		if(!empty($_menu)) $this->_hiddenstr=$this->_hiddenstr.'<input type="hidden" name="_menu" value="'.urlencode($_menu).'">';
	}

	public function __construct() {
		$this->_views();
	}

	public function self_init(){
		return new _views();
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


	public function deal($obj) {

		$this->_obj=$obj;

		//获得菜单
		if(method_exists($this->_obj,'main')){
			$ret=$this->_obj->main('menuSet');
			if($ret['result']>0){
				$this->_errMsg=$ret['msg'];
				return $ret;
			}
			$this->_menuSet=$ret;
		}
		else {

			//处理错误
			$ret=Array('result'=>1,'msg'=>'Undefined method main!');
			$this->_errMsg=$ret['msg'];
			return $ret;
		}

		$this->_cmd=$this->_obj->_data['_cmd'];
		if(empty($this->_cmd) and isset($this->_menuSet['menuconf']) and isset($this->_menuSet['menuconf']['cmd'])){
			$this->_cmd=$this->_menuSet['menuconf']['cmd'];
		}

		//请求接口或对象
		if(!empty($this->_cmd) and $this->_cmd!=$initMenu){
			$ret=$this->sent();
		}
		//默认请求
		else{
			$ret=$this->_menuSet;
		}

		$ret['html']='';
		//处理返回数据：错误显示
		if($ret['result']>0){
			if(empty($ret['pagetitle'])){
				foreach($this->_menuSet['menu'] as $arr){
					if($arr['cmd']==$this->_cmd){
						$ret['pagetitle']=$arr['title'];
					}
				}
			}
		}
		//处理返回数据（正常的返回）
		else{

			if(!empty($ret['msg'])){
			}
			else if($ret['type']=='text'){
				$ret['_html']=$ret['data'];
			}
			else if($ret['type']=='list'){
				$ret['_html']=$this->view_list($ret);
			}
			else if($ret['type']=='form'){
				$ret['_html']=$this->view_form($ret);
			}
		}
		return $ret;
	}

	public function sent(){

		$_save =getpost('_save');
		$_check=getpost('_check');
		//预检查
		if(!empty($_save) and !empty($_check)){
			$sets=explode(';',$_check);
			if(!empty($sets[0]) and !empty($sets[1]) and !empty($sets[2])){
				$value=getpost($sets[2]);
				if(!empty($value)){

					$data=Array();
					$data[$sets[1]]=$value;
					$res=call_user_func(Array($this->_bindObj,$sets[0]),$data);
					if($res['result']>0){
						$this->_errMsg=$ret['msg'];
						$ret=Array('result'=>1,'msg'=>'Function ('. $sets[0].'): '.$res['msg'].'!');
						return $ret;
					}
					if($this->_obj->_isAPI) {
						usleep(300000);//sleep 0.3s
					}
				}
			}
		}

		$ret=$this->_obj->main($this->_cmd);

		if($ret['result']>0){
			$this->_errMsg=$ret['msg'];
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
					header("location: ".$this->_pagename.'_cmd='.$ret['jumpcmd'].'&showMsg='.urlencode($ret['jumpmsg']));
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
					$this->_errMsg=$ret['msg'];
				}
			}
		}

		return $ret;

	}

	protected function view_list($ret){

		//显示搜索及页码
		$conf=$ret['pageconf'];
		$fconf=$ret['formhead'];
		$js_form='';
		$form=getpost();

		$html='
	<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="25">';

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
			$html=$html.'<input type="text" name="_k" value="'.get_html($form['_k']).'">';
			if(isset($conf['searchsort']) and is_array($conf['searchfind'])) 
				$html=$html.$this->get_sort_box($conf['searchsort']);//searchsort
			$html=$html.'
			<input type="submit" value="'.$conf['searchsubmit'].'"></form>
			</td></tr>
		<tr><td height="25">';
		}

		$html=$html.'';
		$pageinfo=$ret['pageinfo'];
		$page=$pageinfo['page'];
		//print_r($pageinfo);
		$pageinfo['pagenum']=intval($pageinfo['pagenum']);
		$html=$html.'<span class="v_page_list">'.($conf['pageprev']).str_replace('*',intval($pageinfo['rowsnum']),$this->_info['total']);
		
		//显示页码
		if($pageinfo['pagenum']>1){

			//上一页
			if($page>1){
				$html=$html.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.($page-1).'">'.$this->_info['pageprev'].'</a></span>';
			}
			else {
				$html=$html.'<span class="page_none">'.$this->_info['pageprev'].'</span>';
			}

			//显示数字页码
			if($this->_info['pagenum']>0){
				if($page==1){
					$html=$html.'<span class="page_now">'.str_replace('*',1,$this->_info['pageshow']).'</span>';
				}
				else{
					$html=$html.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page=1">' .str_replace('*',1,$this->_info['pageshow']).'</a></span>';
				}
				$page0=$page-$this->_info['pagenum'];
				if($page0>$pageinfo['pagenum']-$this->_info['pagenum']*2-1) $page0=$pageinfo['pagenum']-$this->_info['pagenum']*2-1;
				if($page0<2) $page0=2;
				if($pageinfo['pagenum']<=$this->_info['pagenum']*2+3) $page0=2;

				$page1=$page0+$this->_info['pagenum']*2;
				if($page1>$pageinfo['pagenum']-1) $page1=$pageinfo['pagenum']-1;

				if($page0>2) $html=$html.'<span class="page_none">*</span>';
				for($i=$page0;$i<=$page1;$i++){
					
					if($i==$page){
						$html=$html.'<span class="page_now">'.str_replace('*',$i,$this->_info['pageshow']).'</span>';
					}
					else{
						$html=$html.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.$i.'">' .str_replace('*',$i,$this->_info['pageshow']).'</a></span>';
					}
					if($i>=$page1) break;
				}

				if($page1<$pageinfo['pagenum']-1) $html=$html.'<span class="page_none">*</span>';

				if($page==$pageinfo['pagenum']){
					$html=$html.'<span class="page_now">'.str_replace('*',$pageinfo['pagenum'],$this->_info['pageshow']).'</span>';
				}
				else{
					$html=$html.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.$pageinfo['pagenum'].'">' .str_replace('*',$pageinfo['pagenum'],$this->_info['pageshow']).'</a></span>';
				}
			}

			//下一页
			if($page<$pageinfo['pagenum'])
				$html=$html.'<span><a href="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'_page='.($page+1).'">'.$this->_info['pagenext'].'</a></span>';
			else $html=$html.'<span class="page_none">'.$this->_info['pagenext'].'</span>';


			
		}



		if($pageinfo['pagenum']>1){
			if(!empty($this->_info['pageinfo'])){
				$html=$html.'<span>'.str_replace('*',intval($pageinfo['pagenum']),$this->_info['pageinfo']).'</span>';
			}
		}
		//设置每页显示数
		$_showSet=0;
		if(!empty($conf['pageset'])){
			$tmp=explode(',',$conf['pageset']);
			$_pset=Array();
			foreach($tmp as $psize){
				$t=intval($psize);
				if($t>0 and $t<300) $_pset[]=$t;
			}
			if(count($_pset)>1){
				
				$_showSet=1;
				$html=$html.'<span><select id="_v_page_size">';
				foreach($_pset as $psize){
					$html=$html.'<option value="'.$psize.'"'.($pageinfo['pagesize']==$psize?" selected":"").'>'.str_replace('*',$psize,$this->_info['pageselect']).'</option>';
				}
				$html=$html.'</select></span>';
			}
		}

		//显示跳转
		if($pageinfo['pagenum']>2){
			if(!empty($this->_info['pagenow'])){
				
				$_showSet=1;
				$html=$html.'<span>'.str_replace('*','<input type="text" style="width:22px;" value="'.intval($page).'" id="_v_page_num" onkeyup="this.value=this.value.replace(/\D/g,\'\');">',$this->_info['pagenow']).'</span>';
			}
		}
		if($_showSet){
			$html=$html.'<span><input type="submit" value="'.$this->_info['pagego'].'" onclick="_v_page_go();"></span>';
		}

	$html=$html.'</span><span>'.$this->encode($conf['addstring']).'</span></td></tr></table>
<script>
<!--
function _v_page_go(){
	var url="'.$this->_pagename.'&_cmd='.$this->_cmd.'&'.$this->_obj->_para.'";
	if(document.getElementById("_v_page_size")) url=url+"&_size="+document.getElementById("_v_page_size").value;
	if(document.getElementById("_v_page_num")) url=url+"&_page="+document.getElementById("_v_page_num").value;
	location.href=url;
}
-->
</script>
	<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="8"></td></tr></table>';
		if(!empty($fconf['savecmd'])){
			if(!isset($fconf['formname']) or empty($fconf['formname'])){
				$fconf['formname']='listForm';
			}
			$html=$html.'
	<form action="'.$this->_pagename.'" name="'.$fconf['formname'].'"  method="post" onsubmit="return _v_check_form(this)">';
		}
		//显示内容
		$html=$html.'<table width="100%" cellspacing="1" cellpadding="2" border="0" bgcolor="#DDDDDD" class="tbl_main">';

		$tdheight=26;
		if(isset($conf['headheight'])){
			$_tmp=intval($conf['headheight']);
			if($_tmp>=16 and $_tmp<=36) $tdheight=$_tmp;
		}

		//表头
		$html=$html.'
		<tr bgcolor="#EEEEEE" class="tbl_head" align="center">';
		$tdconf=Array();
		$n=0;
		foreach($ret['listhead'] as $arr){
			$html=$html.'
			<td width="'.$arr['width'].'"'.($n==0 ? ' height="'.$tdheight.'"':'').'>'.$arr['title'].'</td>';
			$n++;
		}
		$html=$html.'
		</tr>';

		$tdheight=26;
		if(isset($conf['rowheight'])){
			$_tmp=intval($conf['rowheight']);
			if($_tmp>=16 and $_tmp<=36) $tdheight=$_tmp;
		}
		//内容
		$r=0;
		$keyname=$fconf['keyname'];
		foreach($ret['listdata'] as $arr){
			$html=$html.'
		<tr bgcolor="#FFFFFF" class="tbl_row" align="center">';
			$n=0;
			$keyvalue=$arr[$keyname];
			foreach($ret['listhead'] as $row){
				$name=$row['name'];
				$value =$this->encode($arr[$name]);
				if(!isset($row['type']) or empty($fconf['savecmd'])){
					$showstr=$value;
				}
				else if($row['type']=='text'){
					$showstr='<input type="text" style="width:97%;" name="'.$name.'-'.$keyvalue.'" value="'.$value.'">';
				}
				else if($row['type']=='select'){
					$select=$arr[$name];
					//多个值
					if(is_array($select)){
						$showstr='
				<select style="width:97%;" name="'.$name.'-'.$keyvalue.'">';
						foreach($select as $rs){
							$showstr=$showstr.'<option value="'.$rs['value'].'"'.($rs['selected']?' selected':'').'>'.$rs['option'].'</option>';
						}
						$showstr=$showstr.'</select>';
					}
					//字符串
					else{
						$select=strval($select);
						$showstr='<input type="checkbox" name="'.$name.'-'.$keyvalue.'" value="'.$select.'">';
					}
				}
				else if($row['type']=='hidden'){
					$showstr='<input type="hidden" name="'.$name.'-'.$keyvalue.'" value="'.$value.'">'.$value;
				}
				else{
					$showstr=$value;
				}

				$html=$html.'
			<td'.(!empty($row['align'])?" align=\"".$row['align']."\"":"").($n==0 ? ' height="'.$tdheight.'"':'').' class="tdrow">'.$showstr.'</td>';
				$n++;
			}
			$html=$html.'
		</tr>';
			$r++;
		}
		$html=$html.'</table>
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td height="8"></td></tr></table>';

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
		<input type="submit" value="'.$fconf['submitstr'].'"></td></tr></table>';
			$html=$html.'</form>';
			$js_form=$js_form.'
		_v_form_clear_submit("'.$fconf['formname'].'");';
		}

		//显示备注
		if(strlen($ret['remark'])>0){
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td>';
			$html=$html.($ret['remark']).'</td></tr></table>';
		}
		//用于JS判断的正则表达式
		$html=$html.'
<script>
';
		if(!empty($js_form)){
			$html=$html.'

window.onload=function(){
'.$js_form.'
}';
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

			if(!empty($rows['savecmd'])) {
				$html=$html.'
	<form action="'.$this->_pagename.'" name="'.$rows['formname'].'"  enctype="multipart/form-data" method="post" onsubmit="return _v_check_form(this)">';
			}
			$html=$html.'
    <TABLE cellSpacing=1 cellPadding=3 width="100%" align=center  bgColor="#CCCCCC" class="tbl_main" border=0>
		<tr class="tbl_head" bgcolor="#EEEEEE" align="center"><td colspan="2" height="26"><b>'.$rows['title'].'</b></td></tr>';

			//form的input
			$n=0;
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
				if(empty($name) or preg_match('/^_/',$name)) continue;
				$tmp=$this->showInput($form,$set,$w1);
				if(!empty($tmp)) $w1=0;
				$html=$html.$tmp;
				if(!empty($set['check']) and $set['type']!='radio') {
					$checkArr[$set['check']]=$set['name'];
				}

				$n++;

			}


		//form的submit
		//name="_para"出错时使用，保存时请使用hidden包括必须参数
		//<input type="hidden" name="_para" value="'.get_html($form['_para']).'">
		//<input type="hidden" name="_pagetitle" value="'.get_html($this->pagetitle).'">

			if(empty($rows['submitstr'])) {
				$rows['submitstr']='Sumbit';
			}
			$html=$html.'
		<tr  class="tbl_rows" bgcolor="#EEEEEE"><td align="right" height="28"></td>
		<td>';
			if(!empty($rows['savecmd'])){
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
					if(preg_match('/^_/',$name)) continue;
					if($set['type']!='hidden') continue;
					$html=$html.'<input type="hidden" name="'.$name.'" id="'.$name.'" value="'.($set['value']).'">';
				}

		$html=$html.'
		<input type="submit" value="'.get_html($rows['submitstr']).'">';

				if(!empty($rows['backstr']) and !empty($rows['backcmd'])){
				$html=$html.'
		<input type="button" value="'.get_html($rows['backstr']).'" onclick="window.open(\''.$this->_pagename.'&_cmd='.$rows['backcmd'].'\',\'_self\');">';
				}
			}
		$html=$html.'
		</td></tr>
		</table>';
			if(!empty($rows['savecmd'])) {
				$html=$html.'</form>';
			}
			
			$html=$html.'
		<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td height="8"></td></tr></table>';
			$js_form=$js_form.'
		_v_form_clear_submit("'.$rows['formname'].'");';
		}

		//显示备注
		if(strlen($ret['remark'])>0){
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="3" border="0"><tr><td>';
			$html=$html.($ret['remark']).'</td></tr></table>';
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

window.onload=function(){
'.$js_form.'
}';
		}
		$html=$html.'

</script>';



		return $html;
	}


	//显示输入框
	function showInput($form,$set,$w1=0)
	{
		if($set['type']=='hidden') return '';

		$js='';
		$name=$set['name'];
		$value='';
		if($set['type']!='checkbox'){
			$value=(isset($set['value'])?$set['value']:'');
			$value=(isset($form[$name])?get_html($form[$name]):$value);//有提交数据
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

			$js=$js.'
_v_input_set["'.$name.'"]={"title":"'.$set['title'].'","size":'.$size.''.(preg_match('/^[0-9]+$/i',$set['limit'])?',"limit":'.intval($set['limit']):'').$minstr.',"preg":"'.str_replace('"','\\"',$set['preg']).'","error":"'.$set['error'].'","note":"'.$set['note'].'"};';
		}

		if(empty($set['title'])) $set['title']=$name;
		if($w1>0) $w2=100-$w1;
		$html='
		<tr  bgcolor="#FFFFFF" class="tbl_row"><td align="right" height="27" '.($w1>0?' width="'.$w1.'%"':'').'>'.($set['title']).(strtoupper($set['notnull'])=='Y' ? ' <font color="#EE0000">*</font>':'').'</td><td'.($w1>0?' width="'.$w2.'%"':'').'>';

		//表单前面的说明
		if(!empty($set['intro'])){
			//$html=$html.$set['intro'];
		}

		//根据不同类型显示input表单
		
		if($set['type']=='text'){
			$html=$html.'<input type="text" name="'.$name.'" value="'.$value.'"'.(!empty($set['size']) ? ' maxlength="'.$size.'"' :'').($width>0 ? ' style="width:'.$width.';"' :'').$onblur.'>'.$set['unit'];
		}
		else if($set['type']=='file'){
			if(!empty($set['value'])){
				$html=$html.'已上传文件：'.$set['unit'].$set['value'].' '.(!empty($set['unit'])?' &nbsp; <a href="'.$set['unit'].$set['value'].'" target="_blank">打开/下载</a>':'').'<br />';
			}
			$html=$html.'<input type="file" name="'.$name.'" value="'.$value.'"'.($width>0 ? ' style="width:'.$width.';"' :'').'>';
		}
		else if($set['type']=='password'){
			$html=$html.'<input type="password" name="'.$name.'" value="'.$value.'"'.(!empty($set['size']) ? ' maxlength="'.$size.'"' :'').($width>0 ? ' style="width:'.$width.';"' :'').$onblur.'>';
		}
		else if($set['type']=='textarea'){
			$unit=intval($set['unit']);
			if($unit<3) $unit=5;
			$html=$html.'<textarea name="'.$name.'"  rows="'.$unit.'" style="resize:none;'.($width>0 ? ' width:'.$width.';' :'').'" '.$onblur.'>'.$value.'</textarea>';
		}
		else if($set['type']=='radio'){

			$n=0;
			if(!is_array($set['value'])) $set['value']=Array();

			foreach($set['value'] as $arr){
				if(!isset($arr['option']) or empty($arr['option'])) continue;
				$html=$html.'<input type="radio" name="'.$name.'" value="'.$arr['value'].'"'. ($arr['selected'] ? ' checked':'').'>'.$arr['option'];
				if(!empty($arr['remark'])){
					$html=$html.' &nbsp; <font color="#888888">'.$arr['remark'].'</font>';
				}
				$n++;
			}
			if(!empty($set['note'])) $html=$html.'<br>';
		}
		else if($set['type']=='select'){

			$html=$html.'<select name="'.$name.'" '.($width>0 ? ' style="width:'.$width.';"' :'').'>';
			$n=0;
			if(!is_array($set['value'])) $set['value']=Array();
			foreach($set['value'] as  $arr){
				if(!isset($arr['option']) or empty($arr['option'])) continue;
				$html=$html.'<option value="'.$arr['value'].'"'. ($arr['selected'] ? ' selected':'').'>'.$arr['option'].'</optoin>';
				$n++;
			}
			$html=$html.'</select>'.$set['unit'];
		}
		else if($set['type']=='checkbox'){
			$n=0;
			if(!is_array($set['value'])) $set['value']=Array();
			foreach($set['value'] as $arr){
				if(!isset($arr['option']) or empty($arr['option'])) continue;
				$html=$html.'<input type="checkbox" name="'.$name.'[]" value="'.$arr['value'].'" '. ($arr['selected'] ? ' checked':'').'>'.$arr['option'];
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
			$html=$html.$value;
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
			if(!empty($setArr['name']) and isset($setArr['set']) and is_array($setArr['set'])){
				$_v=isset($form[$_k])?$form[$_k]:'';
				//print_r($setArr['set']);
				foreach($setArr['set'] as $arr){
					$tmp=$tmp."<option value=\"".$arr['value']."\" ".(($_v==$arr['value'] and strlen($_v)>0)?" selected":"").">".$arr['title']."</option>";
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
		$showSet['2']='==@';
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


	function encode($str){
		$str=str_replace('{pagename}',$this->_pagename,$str);
		return $str;
	}


}













?>