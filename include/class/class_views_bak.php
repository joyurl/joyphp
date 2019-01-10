<?PHP
/*
	ͨ����ͼ�ӿ�
	=========================


*/



class views
{
	var $api_cli;
	var $api_ip;
	var $api_port;
	var $api_cert;
	var $ftpserver;
	var $ftpport;
	var $timeout=20;

	var $domainCheck=0;
	var $domainArr=Array();
	var $domainNum=0;
	var $domainUsed=0;
	var $domainURL='';
	var $domainDns='ϵͳδ����';
	var $cpulimit='';

	var $_cmd;
	var $orderid;
	var $editpage;
	var $errMsg='';
	var $jsStr='';
	var $retXML='';
	var $pagetitle='';
	var $hiddenstr='';

	function views(){
		
	}
	
	public function __construct() {
		$this->views();
	}

	

	public function servercheck(){
		$cmd_str="<cmd>servercheck</cmd>";
		$cmd_str.="<encode>".md5($this->api_cert)."</encode>";
		$ret = $this->sent($cmd_str);
		return $ret;
	}
	
	
	//��������
	public function run($form,$_cmd){
		$this->_cmd=$_cmd;

		//������ύ����
		$domain='';//��Ҫͬ����ӵİ�����
		if($form['_save']==1){
			$form['_para']=urldecode($form['_para']);
			$cmdstr=$this->checkForm($form,$domain);
		}
		//����GET�ύ
		else{
			$cmdstr="<cmd>$_cmd</cmd>".$form['_para'];
			//��ҳ
			if($form['_page']>0){
				$cmdstr=$cmdstr.'<page>'.intval($form['_page']).'</page>';
			}
			//����
			if(!empty($form['_key'])){
				$form['_key']=str_replace("\t","",$form['_key']);
				$form['_key']=trim($form['_key']);
				if(strlen($form['_key'])>0){
					$cmdstr=$cmdstr.'<search>'.$this->strencode($form['_key']).'</search>';
				}
			}
		}


		//���ύ����ʱ����ʾԭ�б������û������ύ
		if(!empty($this->errMsg) and $form['_save']==1){
			$tag='@'.$this->orderid.'__'.$form['_cmdlast'].'__';
			$ret=Array();
			$this->_cmd=$form['_cmdlast'];
			$form['_cmd']=$form['_cmdlast'];
			//��ȡsession��ԭ���ı���ʽ
			if(!empty($_SESSION[$tag.'dataconf']) and !empty($_SESSION[$tag.'data'])){
				$ret['dataconf']=$_SESSION[$tag.'dataconf'];
				$ret['data']   =$_SESSION[$tag.'data'];
				$ret['remark'] =$_SESSION[$tag.'remark'];
				$html=$this->showForm($form,$ret);
			}
			//��API��ȡ����ʽ
			else{
				header("location: ".$this->editpage."_cmd=".$this->_cmd.(!empty($form['_para']) ?'&_para='.$form['_para']:'').'&errMsg='.urlencode($this->errMsg));
				exit;
			}
		}
		else if(empty($_cmd)){
			if(empty($this->pagetitle)) $this->pagetitle='@��������';
			$html=$this->errBox('�����������󣨲���Ϊ�գ���');
		}
		//�����������
		else if(empty($this->errMsg)){
			$ret=$this->sent($cmdstr);

			//echo $cmdstr;print_r($ret);exit;
			if(!empty($ret['pagetitle'])) {
				$this->pagetitle=$ret['pagetitle'];
			}
			elseif(!empty($form['_pagetitle'])){
				$this->pagetitle=urldecode($form['_pagetitle']);
			}

			if(isset($ret['time']) and $ret['time']>10000){
				$tag='@'.$this->orderid.'_time_';
				$_SESSION[$tag]=intval(time()-intval($ret['time']));
			}

			if($ret['result']>0){
				if(empty($this->pagetitle)) $this->pagetitle='@��������';
				$html=$this->errBox('�ӿڷ��أ�'.$ret['msg']);
			}
			elseif(!in_array($ret['type'],Array('save','text','table','form','data','conf'))){
				$html=$this->errBox('@��������typeֵ���Ϸ���');
			}
			else if($ret['type']=='save'){
				if(empty($ret['jumpcmd'])){
					$html=$this->errBox('@���ز�����������');
				}
				else {

					//ͬ����Ӱ�����
					if($form['_save']==1 and !empty($domain) and $this->domainCheck) {
						$html=$this->domain_add($domain);
					}
					//ͬ��ɾ��������
					else if(!empty($ret['deldomain']) and $this->domainCheck){
						//echo "[DEL]{$ret['deldomain']}<br>";
						$Msg=$this->domain_del($ret['deldomain']);
						if(!empty($Msg)) $html='@��վ�����������ɹ�ɾ������ɾ��������������������������������';
						//echo "<br>[OK]";exit;
					}

					//����ɹ�
					if(empty($html)) {
						usleep(500000);//sleep 0.5s
						header("location: ".$this->editpage."_cmd=".$ret['jumpcmd']."&info=1&_para=".$form['_para'].(!empty($ret['jumpmsg']) ? '&_msg='.urlencode($ret['jumpmsg']):''));
						exit;
					}
					else $html=$this->errBox($html);
					//echo "[ERROR]";
				}
			}
			else if(
					($ret['type']=='form' or $ret['type']=='conf') and empty($ret['dataconf'])
				or $ret['type']=='table' and (empty($ret['dataconf']) or empty($ret['datahead']))
				or ($ret['type']=='text' or $ret['type']=='data') and empty($ret['data'])
				){
				$html=$this->errBox('@�������ݸ�ʽ��������');
			}
			else if($ret['type']=='form'){
				$html=$this->showForm($form,$ret);
				//�����ص����ݷ���SESSION���Ա����ʱ�����������������
				$tag='@'.$this->orderid.'__'.$_cmd.'__';
				$_SESSION[$tag.'dataconf']=$ret['dataconf'];
				$_SESSION[$tag.'data']  =$ret['data'];
				$_SESSION[$tag.'remark']=$ret['remark'];
			}
			else if($ret['type']=='table'){
				$html=$this->showTable($form,$ret);
			}
			else if($ret['type']=='data'){
				$html=$this->showData($form,$ret);
			}
			else if($ret['type']=='text'){
				$html=$this->strdecode($ret['data']);
			}
			else if($ret['type']=='conf'){
				$html='<div style="padding:6px;">��ǰ������API������Ϣ���£�<br><br>
				API���ƣ�'.$ret['dataconf']['cpname'].'<br />
				API�汾��'.$ret['dataconf']['version'].'<br />
				����ʱ�䣺'.$ret['dataconf']['update'].'<br />
				<br /></div>';
			}
		}

		return $html;

	}



	//���������ʽΪHTML����
	function showTable($form,$ret)
	{

		//��ʾ������ҳ��
		$conf=$ret['dataconf'];
		$html='
	<table width="100%" cellspacing="1" cellpadding="3" border="0"><tr><td height="26">';

		//��ʾ������
		if(!empty($conf['searchname'])){
			if(empty($conf['submitstr'])) $conf['submitstr']='�ύ';
			$html=$html.'
			<form action="'.$this->editpage.'" method="get" style="padding:2px;margin:2px;">'.$this->hiddenstr.'<input type="hidden" name="_cmd" value="'.$this->_cmd.'">'.$conf['searchname'].': <input type="text" name="_key" value="'.urlencode($form['_key']).'"><input type="submit" value="'.$conf['submitstr'].'"></form>';
		}

		$html=$html.$this->strdecode($conf['pageprev']).(!empty($form['search']) ? " �������" :'').'��'.intval($conf['rowsnum']).'����¼';
		$conf['pagenum']=intval($conf['pagenum']);
		$page=intval($conf['page']);
		if($page<1) $page=1;
		if($conf['pagenum']>1){
			$html=$html.' &nbsp; ÿҳ'.$conf['pagesize'].'�� ��'.$page.'ҳ/��'.$conf['pagenum'].'ҳ';
			//��ʾҳ��
			if($page>1)
				$html=$html.' &nbsp;<a href="'.$this->editpage.'&_cmd='.$this->_cmd.'&_page='.($page-1).'">��һҳ</a>';
			else $html=$html.' &nbsp;<font color="gray">��һҳ</font>';
			if($conf['pagenum']>3){
				$n=$page-3;
				if($n<1) $n=1;
				for ($i=$n;($i<$n+7 and $i<=$conf['pagenum']);$i++){
					if($i==$page) $html=$html.' &nbsp;<font color="red" class="pagefont">['.$i.']</font>';
					else $html=$html.' &nbsp;<a href="'.$this->editpage.'&_cmd='.$this->_cmd.'&_page='.$i.'" class="pagefont">['.$i.']</a>';
				}
			}
			if($page<$conf['pagenum'])
				$html=$html.' &nbsp;<a href="'.$this->editpage.'&_cmd='.$this->_cmd .(!empty($form['search']) ? '&search='.urlencode($form['search']) :'').'&_page='.($page+1).'">��һҳ</a>';
			else $html=$html.' &nbsp;<font color="gray">��һҳ</font>';
		}

		//��Ҫ��ʾ������ʹ�����
		if($this->domainCheck and !empty($conf['domaincheck'])){
			if($this->domainNum<1) 
				$html=$html.' &nbsp; <font color="red">��ǰ����δ������������޷����</font>';
			else{ // if($this->domainNum>$this->domainUsed)
				$html=$html." &nbsp; ".$this->strdecode($conf['addstring'])." (��������ʹ��".$this->domainUsed."��/��".$this->domainNum."��)";
			}
			/*
			else{
				$html=$html." &nbsp; (��������ʹ��".$this->domainUsed."��/��".$this->domainNum."�������������)";
			}
			*/
		}
		else{
			$html=$html.' &nbsp; '.$this->strdecode($conf['addstring']);
		}

		$html=$html.'</td></tr></table>
	<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="8"></td></tr></table>';

		//��ʾ����
		$html=$html.'<table width="100%" cellspacing="1" cellpadding="2" border="0" bgcolor="#DDDDDD" class="tbl_main">';

		//��ͷ
		$html=$html.'<tr bgcolor="#E6E6E6" class="tbl_head" align="center">';
		$tdconf=Array();
		$n=0;
		foreach($ret['datahead'] as $arr){
			$name=$arr['name'];
			$html=$html.'
			<td width="'.$arr['width'].'"'.($n==0 ? ' height="27"':'').'>'.$arr['title'].'</td>';
			$tdconf[$name]=" ".$arr['tdset'];
			$n++;
		}
		$html=$html.'</tr>';

		//����
		$r=0;
		foreach($ret['data'] as $arr){
			$html=$html.'<tr bgcolor="#FFFFFF" class="tbl_row" align="center">';
			$n=0;
			foreach($tdconf as $name =>$tdset){
				$value=$this->strdecode($arr[$name]);
				//����Ƿ��ڰ�������
				if(!empty($conf['domaincheck']) and $conf['domaincheck']==$name){
					$tmp=explode(";",$value);
					$showstr='';
					foreach($tmp as $dm){
						$showstr=(empty($showstr) ? '' :$showstr.';');
						if(preg_match('/\./i',$dm) and !isset($this->domainArr[$dm]))
							$showstr=$showstr.'<a '.(!empty($this->domainURL) ? ' href="'.$this->domainURL.$dm.'&#adddomain" ' :'').'style="color:blue;" title="'.$dm.'���ڰ������У��뵽�������������">'.$dm.'</a>';
						else
							$showstr=$showstr.$dm;
					}
				}
				else{
					$showstr=$value;
				}

				$html=$html.'
			<td'.(strlen($tdset)>2 ? ' '.$tdset :'').($n==0 ? ' height="27"':'').' class="tdrow">'.$showstr.'</td>';
				$n++;
			}
			$html=$html.'</tr>';
			$r++;
		}
		$html=$html.'</table>';
		//��ʾ��ע
		if(strlen($ret['remark'])>0){
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td height="8"></td></tr></table>
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td>';
			$html=$html.$this->strdecode($ret['remark']).'</td></tr></table>';
		}
		return $html;
	}

	//���Data��ʽΪHTML���룬������ʾFLASHͼ�����״ͼ
	function showData($form,$ret)
	{

		$html='<table width="100%" cellspacing="1" cellpadding="3" border="0" bgcolor="#DDDDDD" class="tbl_body">
		<form action="'.$this->editpage.'" method="post" onsubmit="return _checkForm(this)">
		<tr class="tbl_head" bgcolor="#E6E6E6" align="center"><td colspan="2" height="27"><b>'.$ret['pagetitle'].'</b></td></tr>
	</table>
    <TABLE cellSpacing=1 cellPadding=3 width="100%" align=center  bgColor="#CCCCCC" class="tbl_main" border=0>';

		foreach($ret['data'] as $set){
			$set['type']=intval($set['type']);

			if($set['type']>0){
				$percent=round(100*doubleval($set['used'])/doubleval($set['value']),0);
				if($percent>100) $percent=100;
				$v2=100-$percent;
			}

			$html=$html.'
		<tr  bgcolor="#FFFFFF" class="tbl_row"><td align="right" width="20%" class="tdrow" style="padding:4px;">'.$set['title'].'</td>
		<td width="80%" class="tdrow" style="padding:7px;">';

			//��״ͼ
			if($set['type']==1){
				$html=$html.'<table width="160" border="0" cellpadding="0" cellspacing="0" style="border:1px solid #9AC04B;" bgcolor="#F5F5F5" align="left">
<tr>
	<td height=11 bgcolor="#C0F0B0" width="'.($percent).'%" background="../images/bg_bar.gif"></td>
	<td width="'.($v2).'%"></td>
</tr>
</table> &nbsp; '.$percent.'% &nbsp; ';
			}
			//��״ͼ��ʹ��FLASH��ʾ
			else if($set['type']==2){
				$feevalue=round($set['value']-$set['used'],2);
				$datastr='����,'.doubleval($set['used']).';����,'.$feevalue.'';//For Flash
				$html=$html.'<embed src="../images/PieChart.swf"  width="420" height="180" FlashVars="data='.$datastr.'&unit='.$set['unit'].'&time='.time().'" loop="false" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" name="scriptmain" menu="false" wmode="transparent"></embed>';
				$html=$html.'<br>';
			}
			else{
				$html=$html.$this->strdecode($set['value']).$set['unit'].' &nbsp; ';
			}
			$html=$html.'<font color="#666666">'.$this->strdecode($set['note']).'</font>';
			$html=$html.'</td></tr>';
		}
		$html=$html.'
		</table>';

		//��ʾ��ע
		if(strlen($ret['remark'])>0){
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td height="8"></td></tr></table>
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td>';
			$html=$html.$this->strdecode($ret['remark']).'</td></tr></table>';
		}
		return $html;
		
	}

	//����Form��ʽΪHTML����
	function showForm($form,$ret)
	{
		//Formͷ
		$html='<table width="100%" cellspacing="1" cellpadding="3" border="0" bgcolor="#DDDDDD" class="tbl_body">
		<form action="'.$this->editpage.'" method="post" onsubmit="return _checkForm(this)">
		<tr class="tbl_head" bgcolor="#E6E6E6" align="center"><td colspan="2" height="27"><b>'.$ret['pagetitle'].'</b></td></tr>
	</table>
    <TABLE cellSpacing=1 cellPadding=3 width="100%" align=center  bgColor="#CCCCCC" class="tbl_main" border=0>';

		//form��input
		$n=0;
		foreach($ret['data'] as $set){
			$name=$set['name'];
			if(empty($name) or preg_match('/^_/',$name) or $name=="cmd") continue;
			if($n<1){
				$w1=$ret['dataconf']['tdwidth'];
				if($w1<5 or $w1>50) $w1=10;
			}
			else $w1=0;
			$html=$html.$this->showInput($form,$set,$w1);
			$n++;
		}

		if(empty($ret['dataconf']['submitstr'])) {
			$ret['dataconf']['submitstr']='�ύ';
		}


		//form��submit
		//name="_para"����ʱʹ�ã�����ʱ��ʹ��hidden�����������

		$html=$html.'
		<tr  class="tbl_rows" bgcolor="#F2F2F2"><td align="right" height="28">����</td>
		<td>
		<input type="hidden" name="_save" value="1">
		<input type="hidden" name="_cmdlast" value="'.$this->_cmd.'">
		<input type="hidden" name="_para" value="'.urlencode($form['_para']).'">
		<input type="hidden" name="_pagetitle" value="'.urlencode($this->pagetitle).'">
		<input type="hidden" name="_cmd" value="'.$ret['dataconf']['savecmd'].'">';

		//hidden input
		foreach($ret['data'] as $set){
			$name=$set['name'];
			if(preg_match('/^_/',$name)) continue;
			if($set['type']!='hidden') continue;
			$html=$html.'<input type="hidden" name="'.$name.'" id="'.$name.'" value="'.$this->strdecode($set['value']).'">';
		}

		$html=$html.'
		<input type="submit" value="'.get_html($ret['dataconf']['submitstr']).'">
		</td></tr>
		</form>
		</table>';

		//��ʾ��ע
		if(strlen($ret['remark'])>0){
			$html=$html.'
	<table width="100%" cellspacing="0" cellpadding="2" border="0"><tr><td height="8"></td></tr></table>
	<table width="100%" cellspacing="0" cellpadding="3" border="0"><tr><td>';
			$html=$html.$this->strdecode($ret['remark']).'</td></tr></table>';
		}


		//����JS�жϵ�������ʽ
		$html=$html.'
<script>
var _inputSet=new Array();
'.$this->jsStr.'

String.prototype.strlen = function()
{
	var len=this.length;
	var arr=this.match(/[^\x00-\xff]/ig);
	var charSet = document.charset ? document.charset : (document.characterSet ? document.characterSet : null);
	charSet=charSet.toUpperCase();
	if(arr!=null) {
		len = len + arr.length
		if (charSet=="UTF-8") len = len + arr.length;
	}
	return len;
}

function _checkForm(f){

	for(name in _inputSet){
		values=f[name].value;
		if(values.length<1){
			if(_inputSet[name][1]==1){
				alert("["+_inputSet[name][0]+"]����Ϊ��");
				f[name].focus();
				return false;
			}
			continue;
		}

		if(_inputSet[name] && (_inputSet[name][2] || _inputSet[name][3])){
			regExpStr=_inputSet[name][2];
			regExpStr2=_inputSet[name][3];
			try {
				var err=0;
				if(regExpStr){
					var expObj=new RegExp(regExpStr);
					err =(expObj.test(values) ? 0 : 1);
				}
				if(regExpStr2 && err<1){
					var expObj2=new RegExp(regExpStr2);
					err=(expObj2.test(values) ? 1:0);
				}

				if(err>0){
					alert("["+_inputSet[name][0]+"]��ʽ����!");
					f[name].focus();
					return false;
				}
			}
			catch (e) {;}
		}
	}
	return true;
}

function _checkInput(name,values){
	msgBox=document.getElementById("_errmsg_box_"+name);
	if(values.length<1) {
		if(_inputSet[name][1]==1) msgBox.innerHTML="<font color=red>["+_inputSet[name][0]+"]����Ϊ��!</font>";
		else msgBox.innerHTML="";
		return 1;
	}

	if(_inputSet[name] && (_inputSet[name][2] || _inputSet[name][3])){

		regExpStr =_inputSet[name][2];
		regExpStr2=_inputSet[name][3];
		try {
			var err=0;
			if(regExpStr){
				var expObj=new RegExp(regExpStr);
				err =(expObj.test(values) ? 0 : 1);
			}
			if(regExpStr2 && err<1){
				var expObj2=new RegExp(regExpStr2);
				err=(expObj2.test(values) ? 1:0);
			}

			if(err>0){
				msgBox.innerHTML="<font color=red>["+_inputSet[name][0]+"]��ʽ����!</font>";
			}
			else{
				msgBox.innerHTML="";
			}
			return 1;
		}
		catch (e) {;}
	}
	
}
</script>';

		return $html;
	}

	//����Form�ύ���������ݣ�����ʽ�������XML��ʽ���ݱ����ύ
	function checkForm($form,&$domain){
		$_cmd=$form['_cmdlast'];
		$tag='@'.$this->orderid.'__'.$_cmd.'__';
		$conf=$_SESSION[$tag.'dataconf'];
		$data=$_SESSION[$tag.'data'];

		//ʹ��PHP��������ʽ�Ƿ���ȷ��ͬʱ������
		$cmdstr='<cmd>'.$form['_cmd'].'</cmd>';
		$this->errMsg='';
		$domain='';
		foreach($data as $set){

			$name=$set['name'];
			if(empty($name) or $name=="cmd") continue;
			if(preg_match('/^_/',$name)) continue;
			if($set['type']=='view') continue;
			if(strtoupper($set['notnull'])=='Y' and empty($form[$name])){
				$this->errMsg=$this->errMsg.'@['.$set['title'].']����Ϊ�գ�';
			}
			//$set['preg']=str_replace('"','\\"',$set['preg']);
			if(strlen($form[$name])>0 and (!empty($set['preg']) or !empty($set['nopreg']))){
				$v1=0;
				if(strlen($set['preg'])>2)  $v1=(@preg_match($set['preg'],$form[$name]) ? 0 :1);
				if(strlen($set['nopreg'])>2 and $v1<1) $v1=(@!preg_match($set['nopreg'],$form[$name]) ? 0 :1);
				if($v1) $this->errMsg=$this->errMsg.'@['.$set['title'].']��ʽ���ԣ�';
			}
			if($set['type']=='checkbox') $value=@implode(",",$form[$name]);
			else $value=$form[$name];
			$cmdstr=$cmdstr."<$name>".$this->strencode($form[$name])."</$name>";
		}
		return $cmdstr;
	}

	//��ʾ�����
	function showInput($form,$set,$w1=0)
	{
		if($set['type']=='hidden') return '';

		$js='';
		$name=$set['name'];
		$value=(isset($form[$name]) ? $this->strdecode($form[$name]) : $this->strdecode($set['value']));
		$width=intval($set['width']);
		$size=intval($set['size']);
		$seleArr=Array();
		if(!empty($set['select'])){
			$tmps=explode(";",$set['select']);
			foreach($tmps as $str){
				$tmp=explode("|",$str);
				if(!empty($tmp[0]) and !empty($tmp[1])){
					$tag=$this->strdecode($tmp[0]);
					$seleArr[$tag]=$this->strdecode($tmp[1]);
				}
			}
		}

		//��ʾ�ж�����ֵʱʹ�õ�JS����
		$v1="";
		$v2=0;
		$v3='""';
		$onblur='';
		if(!empty($set['preg'])){
			//$set['preg']=str_replace('\\','\\\\',$set['preg']);
			//$set['preg']=str_replace('"','\\"',$set['preg']);
			if(!in_array($set['type'],Array('radio','checkbox','select'))){
				$v1=$set['preg'];
				$onblur=" onblur=\"_checkInput('{$name}',this.value);\"";
			}
		}
		if(!empty($set['nopreg'])){
			if(!in_array($set['type'],Array('radio','checkbox','select'))){
				$v3=$set['nopreg'];
				$onblur=" onblur=\"_checkInput('{$name}',this.value);\"";
			}
		}

		if(strtoupper($set['notnull'])=='Y'){
			$v2=1;
		}

		if(!empty($v1) or !empty($v2)){
			$js=$js.'
_inputSet["'.$name.'"]=Array("'.$set['title'].'",'.$v2.','.$v1.','.$v3.');';
		}
		if(empty($set['title'])) $set['title']='[δ����]';
		if($w1>0) $w2=100-$w1;
		$html='
		<tr  bgcolor="#FFFFFF" class="tbl_row"><td align="right" height="27" '.($w1>0?' width="'.$w1.'%"':'').' class="tdrow">'.$this->strdecode($set['title']).(strtoupper($set['notnull'])=='Y' ? ' <font color="#EE0000">*</font>':'').'</td><td'.($w1>0?' width="'.$w2.'%"':'').' class="tdrow">';

		//��ǰ���˵��
		if(!empty($set['intro'])){
			$html=$html.$this->strdecode($set['intro']);
		}

		//���ݲ�ͬ������ʾinput��
		if($set['type']=='view'){
			$html=$html.$this->strdecode($set['value']);
		}
		else if($set['type']=='text'){
			$html=$html.'<input type="text" name="'.$name.'" id="'.$name.'" value="'.$value.'"'.(!empty($set['size']) ? ' maxlength="'.$size.'"' :'').($width>0 ? ' style="width:'.$width.';"' :'').$onblur.'>'.$this->strdecode($set['unit']);
		}
		else if($set['type']=='password'){
			$html=$html.'<input type="password" name="'.$name.'" id="'.$name.'" value="'.$value.'"'.(!empty($set['size']) ? ' maxlength="'.$size.'"' :'').($width>0 ? ' style="width:'.$width.';"' :'').$onblur.'>'.$this->strdecode($set['unit']);
		}
		else if($set['type']=='textarea'){
			$html=$html.'<textarea name="'.$name.'" id="'.$name.'" rows="'.$size.'"'.($width>0 ? ' style="width:'.$width.';"' :'').'>'.$value.'</textarea>';
		}
		else if($set['type']=='radio'){
			$n=0;
			foreach($seleArr as $_k => $_v){
				$html=$html.'<input type="radio" name="'.$name.'" value="'.$_k.'"'. (($n==0 or $_k==$value and strlen($value)>0) ? ' checked':'').'>'.$_v;
				$n++;
			}
			if(!empty($set['note'])) $html=$html.'<br>';
		}
		else if($set['type']=='select'){

			$html=$html.'<select name="'.$name.'" id="'.$name.'" '.($width>0 ? ' style="width:'.$width.';"' :'').'>';
			$n=0;
			foreach($seleArr as $_k => $_v){
				$html=$html.'<option value="'.$_k.'"'. (($n==0 or $_k==$value and strlen($value)>0) ? ' checked':'').'>'.$_v.'</optoin>';
				$n++;
			}
			$html=$html.'</select>'.$this->strdecode($set['unit']);
		}
		else if($set['type']=='checkbox'){
			$n=0;
			foreach($seleArr as $_k => $_v){
				$html=$html.'<input type="checkbox" name="'.$name.'[]" value="'.$_k.'">'.$_v;
				$n++;
			}
			if(!empty($set['note'])) $html=$html.'<br>';
		}
		else{
			$html=$html.'<b>δ֪��������</b>';
		}

		//��ʾ����Ĵ�����ʾ
		$html=$html.' &nbsp;<span id="_errmsg_box_'.$name.'"></span>';
		//��ʾ�����ע��
		if(!empty($set['note'])){
			$html=$html.' &nbsp; <font color="#666666">'.$this->strdecode($set['note']).'</font>';
		}

		$html=$html.'</td></tr>';
		$this->jsStr=$this->jsStr.$js;
		return $html;
	}

	//���ڴ���POST��GET�ϴ�����,�ӵ���ǩ��,������<>��&����(��Ӱ��GET����)
	function strencode($str){
		return urlencode($str);
	}

	//���ڻ�ԭ��ǩ�е�HTML����
	function strdecode($str){
		$str=str_replace('{pagename}', $this->editpage,$str);
		$str=str_replace('{ftpserver}',$this->ftpserver,$str);
		$str=str_replace('{ftpport}',  $this->ftpport,$str);
		$str=str_replace('{domaindns}',$this->domainDns,$str);
		$str=str_replace('{cpulimit}',$this->cpulimit,$str);
		$str=str_replace("&lt;","<",$str);
		$str=str_replace("&gt;",">",$str);
		return $str;
	}


	function errBox($str){

		$str='<div style="padding:6px;"><br /><br /><font color=red><b>��������</b>��<br /><br />'.$str.'</font><br /><br />';
		$str=$str.' &nbsp; <input type="button" onclick="history.back();" value="������ҳ"> <br /><br /></div>';
		return $str;
	}
	
	function bind_ip($serverRS,$RS,$result,$delip=""){
	
		//����
		$cmd="<cmd>bind_ip</cmd><ip>".$RS['vm_lan_ip']."/255.255.0.0";
		//��IP
		if($RS['vm_iptype']>0){
			$cmd=$cmd.";{$RS['vm_ip']}/".$serverRS['server_mask_wan'];
		}
		//����IP
		$n=0;
		if(is_array($result)){
			foreach($result as $arr){
				if($arr['ip_addr']==$RS['vm_ip']) continue;
				if($arr['ip_addr']==$delip) continue;
				$cmd=$cmd.";{$arr['ip_addr']}/".$serverRS['server_mask_wan'];
				$n++;
			}
		}
	
		if($RS['vm_iptype']>0 or $n>0){
			$gateway=$serverRS['server_gateway_wan'];
		}
		else{
			$gateway=$serverRS['server_gateway'];
		}
	
		$cmd=$cmd."</ip><gateway>$gateway</gateway>";
		return $cmd;
	}
	

	function sent($cmd_str="")
	{

		usleep(50);
		@list($usec,$sec)=explode(" ",microtime()); 
		$t1=number_format($sec + $usec,3,'.','');
		
		$url='https://'.$this->api_ip.':'.$this->api_port.'/manager.do?';

		$ret=Array();
		$timeNow=time();

		$skipcrypt=0;
		if(preg_match("/>(servercheck|gettime)</i",$cmd_str)){
			$skipcrypt=1;//������
		}

		$admin_key=$this->api_cert;
		$tag='@'.$this->orderid.'_time_';
		if(!isset($_SESSION[$tag])){
			$t=0;
			if(!preg_match("/>(servercheck|gettime)</i",$cmd_str)){
				
				$ch=@curl_init();
				@curl_setopt($ch,CURLOPT_URL,$url.'<cmd>gettime</cmd>');
				@curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
				@curl_setopt($ch,CURLOPT_COOKIESESSION,false);
				@curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
				@curl_setopt($ch,CURLOPT_HEADER,false);
				@curl_setopt($ch,CURLOPT_TIMEOUT,$this->timeout);
				@curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
				@curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
				$retstr=@curl_exec($ch);
				
				
				if (preg_match('/<time>([0-9]+)<\/time>/i',$retstr,$reg))
				{
					$t=intval($reg[1]);
					$_SESSION[$tag]=intval(time()-$t);
					usleep(500000);
				}
				else
				{
					$ret['result']=1;
					$ret['msg']='�޷������ӻ����ƽӿڻ����ӳ�ʱ��';
					return $ret;
				}
			}
		}
		else{
			$t=intval(time()-$_SESSION[$tag]);
		}
		$cmd_str=$cmd_str."<time>$t</time>";
		
		
		if ($skipcrypt==0 and strlen($admin_key)>7)
		{
			@include_once str_replace('\\','/',dirname(__FILE__)).'/crypt.php';
			
			if (!function_exists("mcrypt_cbc")){
				$ret['result']=1;
				$ret['msg']='���ܿⲻ����!';
				return $ret;
			}
				
			if(!class_exists("CloudCrypt")){
				$ret['result']=1;
				$ret['msg']='������ⲻ����!';
				return $ret;
			}
				
			$cloud_crypt = new CloudCrypt($admin_key);
			
			$cmd_str = $cloud_crypt->encrypt_aes_256_cbc($cmd_str);
			usleep(50);
		}

		//echo $url.$cmd_str.";".API_PASSWORD.";"; //!6e4caadeb
		//echo '<!-- cmd url: '.$url.$cmd_str.' -->';

		$ch=curl_init();
		@curl_setopt($ch,CURLOPT_URL,$url.$cmd_str);
		@curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		@curl_setopt($ch,CURLOPT_COOKIESESSION,false);
		@curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
		@curl_setopt($ch,CURLOPT_HEADER,false);
		@curl_setopt($ch,CURLOPT_TIMEOUT,$this->timeout);
		@curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		@curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
		$retstr=@curl_exec($ch);
		
		
		
		//echo '<!-- return: '.$retstr.' -->';
		if ($skipcrypt==0 and strlen($admin_key)>7 and strlen($retstr)>0)
		{
			$retstr =$cloud_crypt->decrypt_aes_256_cbc($retstr);
		}
		$retstr='  '.$retstr;
		//echo '<!-- decode: '.$retstr.' -->';

		$this->retXML=$retstr;
		if (strpos($retstr,'<body>'))
		{
			$ret=$this->parseXML($retstr,1,'body');
		}
		else
		{
			$ret=$this->parseXML($retstr,1,'');
		}

		//echo '<!-- decode: '; print_r($ret);echo ' -->';
		@list($usec,$sec)=explode(" ",microtime()); 
		$t2=number_format($sec + $usec,3,'.','');

		$ret['result']=abs(intval($ret['result']));
		if($ret['result']==4){
			$ret['msg']='�޷����ӵ��ӻ����ƽӿ�('.$this->api_ip.':'.$this->api_port.')�������Ի����Ƿ���������Կ�Ƿ���ȷ�����ӿ��Ƿ�����';
		}
		else if (preg_match('/time expire/i',$ret['msg']))
		{
			//����ʱ��
			if(isset($ret['time']) and $ret['time']>10000){
				$_SESSION[$tag]=intval(time()-intval($ret['time']));
			}
			$ret['msg']='���ӳ�ʱ�����ԣ��������Գ�ʱ����ϵ����Ա����';
		}

		$ret['second']=number_format($t2-$t1,3,'.','');
		return $ret;
	}



	function parseXML($str,$base=0,$bodyTag='body')
	{
		$ret=Array();
		if ($base==1)
		{
			$ret['result']=4;
		}
		if ($base==1 and strlen($bodyTag)>0)
		{
			$p1=strpos($str,'<'.$bodyTag.'>');
			$p2=strpos($str,'</'.$bodyTag.'>');
			if ($p1<1 or $p2<1 or $p2<$p1)
			{
				return $ret;
			}

			$str=substr($str,$p1+strlen($bodyTag)+2,$p2-$p1-strlen($bodyTag)-2);
			if (strlen($str)<1)
			{
				return $ret;
			}
		}

		$tmp=Array();
		$rs=preg_match("/<([-_a-z0-9]+)([\s]+[^>]*[\s]*)?>/i",$str,$rets);
		while ($rs)
		{
			$name=$rets[1];

			$tag1=$rets[0];
			$tag2="</$name>";
			$p1=strpos($str,$tag1);
			$p2=strpos($str,$tag2);
			$len=strlen($tag2);
			$key='';
			if ($p2>0 and $p1>=0 and $p2>$p1)
			{
				if (!isset($tmp[$name]))
				{
					$tmp[$name]=Array();
				}
				$value=substr($str,$p1+strlen($tag1),$p2-$p1-strlen($tag1));
				/*
				if(strlen($rets[2])>0 and preg_match('/key=([-_a-z0-9"\']+)/i',$rets[2],$det))
				{
					$key=str_replace("'",'',$det[1]);
					$key=str_replace('"','',$key);
				}
				*/
				$tmp[$name][]=Array($value,$key);
				$str=substr($str,$p2+$len);
			}
			else
			{
				$str=substr($str,strlen($rets[1]));
			}
			$rs=preg_match("/<([-_a-z0-9]+)([\s]+[^>]*[\s]*)?>/i",$str,$rets);
		}

		foreach ($tmp as $name => $result)
		{
			$len=count($result);
			$n=0;
			foreach ($result as $arr)
			{
				$value=$arr[0];//String
				if (preg_match('/</',$value))
				{
					$value=$this->parseXML($value);//Array
				}

				if ($base==1) {
					$ret[$name]=$value;
					if(0){
					//��һ�������ظ���ǩ
					if ($len==1)
						$ret[$name]=$value;
					else {
						if(strlen($arr[1])>0) $key=$arr[1];
						else{ $key=$n; $n++; }
						$ret[$name][$key]=$value;
					}
					}
				}
				#### �ظ���ǩ 
				else if ($len>1 and $name!='li' and $name!='ol')
				{
					if(strlen($arr[1])>0) $key=$arr[1];
					else{ $key=$n; $n++; }
					$ret[$name][$key]=$value;
				}
				else
				{
					if (strlen($arr[1])>0)
					{
						$key=$arr[1];
					}
					else if ($name=='li' or $name=='ol') // li: 0-n
					{
						$key=$n;
						$n++; 
					}
					else
					{
						$key=$name;
					}
					$ret[$key]=$value;
				}
			}
		}

		return $ret;
	}
}

?>