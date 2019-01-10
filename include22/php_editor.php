<?PHP

/*
	-------------------------
	phpuu_editor.php (V2.0 )
	-------------------------

	Last modify
	-------------------
	+ 2008.06.23 

	function (Row):
	-----------------
	1.1 deal() 
	1.2 dealSele()   
	1.3 dealForm()   
	1.4 showForm()   
	1.5 showInput()  
	1.6 showList()   
	1.7 downCheck()
	1.8 downRecords()

	2.1 encodeStr()
	2.2 decodeStr()
	2.3 xorStr()
	3.4 showHidden() 


*/


if(!defined('__PHP__EDITOR__')){
  define('__PHP__EDITOR__', 1);

/*
 * _Editor
 */
class _Editor
{
	var $DB;
	var $Msg   =''; #error message
	var $Conf  =array();
	var $Field =array();
	var $numField =array();
	var $fileField=array();
	var $editMode =2; //edit mode: 0 view only  ; 1 modify only ; 2 modify or del

	var $keySQL='';
	var $subSQL='';

	var $_edit ='';
	var $_save =0; // 1 save submit 2 download reports 3 change selected
	var $_page =0;
	var $_find =0;
	var $_stat =0;
	var $_order=0;

	var $_key  ='';
	var $_sort ='';
	var $_pagename='';
	var $_pagelink='';
	var $_baselink='';




	/*
	 * __construct (PHP5)
	 * @param object $DB
	 */
	function _Editor(&$DB)
	{
		$this->DB      =$DB;
		$this->_edit   =trim(getpost('_edit'));
		$this->_page   =intval(getpost('_page'));
		$this->_save   =intval(getpost('_save'));

		$this->_stat   =eregi_replace("[^0-9]","",getpost('_stat'));

	}



	/* NO 1.1
	 * @function deal
	 * @param array  $setEditor
	 * @param array  $setField
	 *  
	 * return String (HTML CODE)
	 */
	function deal()
	{

		$HTML='';

		$this->_baselink=(strlen($this->_pagelink)>0 ? $this->_pagelink.'&' : '');
		$this->Conf['upFilePre']=eregi_replace("[^a-z]","",$this->Conf['upFilePre']);
		$this->Conf['upFilePre']=substr($this->Conf['upFilePre'],0,10);


		$n=1;
		foreach ($this->Field as $fName => $arr)
		{

			$arr['name']=$fName;
			$arr['num']=$n;
			if ($arr['type']=='F')
			{
				$this->fileField[$fName]=$arr;
			}
			$this->Field[$fName]=$arr;
			$this->numField[$n]=$fName;
			$n++;
		}

		if (!isset($this->Field[$this->Conf['statField']]) or $this->Field[$this->Conf['statField']]['type']!="N")
		{
			$this->Conf['statField']='';
		}


		$form=getpost();
		$this->_order  =intval(getpost('_order'));
		if ($this->_order>0 or $this->_order<0)
		{
			$this->_pagelink=$this->_pagelink.'&_order='.$this->_order;
		}
		if (strlen($this->_stat)>0)
		{
			$this->_pagelink=$this->_pagelink.'&_stat='.$this->_stat;
		}
		/* sort order */
		if (isset($this->Conf['sortSet']) and count($this->Conf['sortSet'])>0)
		{
			$this->_sort   =trim(getpost('_sort'));
			$this->_sort   =eregi_replace('(\(|\)|"|\')','',$this->_sort);
			if ($this->_sort>0)
			{
				$this->_pagelink=$this->_pagelink.'&_sort='.$this->_sort;
			}
		}

		/* search */
		if (isset($this->Conf['searchSet']) and count($this->Conf['searchSet'])>0)
		{
			$this->_key    =trim(getpost('_key'));
			$this->_find   =intval(getpost('_find'));

			if (strlen($this->_key)>0)
			{
				$this->_key   =eregi_replace('(\(|\)|"|\')','',$this->_key);
				$this->_key   =str_replace('+',' ',$this->_key);
				$this->_pagelink=$this->_pagelink.'&_key='.urlencode($this->_key);
			}

			if ($this->_find>0)
			{
				$this->_pagelink=$this->_pagelink.'&_find='.$this->_find;
			}
		}

		/* down load */
		if ($form['_save']==2)
		{
			if (is_array($_REQUEST['_select']) and count($_REQUEST['_select'])>1)  //down now
			{
				$this->downRecords();
			}

			return $this->downCheck($form);

		}
		if (is_array($this->Conf['keyField']))
		{
			$this->tableSub='';
		}

		/* key field */
		$this->keySQL='';//For update or select
		if (is_array($this->Conf['keyField']))
		{
			$this->Conf['subTable']='';
			$n=0;
			$keyVal=explode(',',$this->_edit);
			foreach ($this->Conf['keyField'] as $key)
			{
				if ($this->Field[$key]['type']=='N')
				{
					$this->keySQL  =$this->keySQL." and $key=".doubleval($this->decodeStr($keyVal[$n]));
				}
				else
				{
					$this->keySQL  =$this->keySQL." and $key=".$this->DB->getstr($this->decodeStr($keyVal[$n]));
				}
				$n++;
			}
			if ($n==0)
			{
				$this->Conf['keyField']='';
			}
		}
		else
		{
			$key=strval($this->Conf['keyField']);
			if ($this->Field[$key]['type']=='N')
			{
				$this->keySQL  =" and $key=".doubleval($this->_edit);
			}
			else
			{
				$this->keySQL  =" and $key=".$this->DB->getstr($this->_edit);
			}
		}

		$RS=Array();
		if (strlen($this->_edit)>0 and $this->_edit!=-1)
		{
			$RS=$this->DB->query("SELECT * FROM ".$this->Conf['mainTable'] ." WHERE 1 ".$this->keySQL,1);
			if (empty($RS))
			{
				$this->_edit=0;
			}
			else if (!empty($this->tableSub))
			{
				$RSS=$this->DB->query("SELECT * FROM ".$this->tableSub." WHERE 1 ".$this->keySQL,1);
				if ($RSS)
				{
					$this->querySub=1;
					$RS=array_merge($RSS,$RS);
				}
			}
		}

		#deal
		if ($form['_save']==1 and !empty($this->_edit))
		{
			$ret=$this->dealForm($RS,$form);
			if ($ret)
			{
				header('location: '.$this->_pagename.'?'.$this->_pagelink.'&_page='.$this->_page);
				exit;
			}
		}
		else if($form['_save']==3)
		{
			if (is_array($_REQUEST['_select']) and count($_REQUEST['_select'])>0)
			{
				$ret=$this->dealSele();
			}

			header('location: '.$this->_pagename.'?'.$this->_pagelink.'&_page='.$this->_page);
			exit;

		}

		if (!empty($this->_edit))
		{
			if (strlen($this->_edit)>0 and $this->_edit!=-1)
			{
				$form=$RS;
			}
			$HTML=$this->showForm($form);
		}
		else
		{
			$HTML=$this->showList();
		}

		return $HTML;
	}
	/* END  function deal() */







	/* NO 1.2
	 * @function deal
	 *
	 * @action:Del or Back or Move sort
	 * @return bool
	 */
	function dealSele()
	{
		if (empty($this->Conf['keyField']))
		{
			return false;
		}

		if ($this->editMode<1)
		{
			return false;
		}

		$seleArr=getpost('_select');
		if (!is_array($seleArr) or count($seleArr)<1)
		{
			return false;
		}
		$_statnew=getpost('_statnew',1);

		#deal
		$updir=$this->Conf['upFileDir'];
		foreach ($seleArr as $n => $val)
		{
			if (empty($val))
			{
				continue;
			}

			$querySQL='';
			if (is_array($this->Conf['keyField']))
			{
				$querySQL=' 1 ';
				$keyVal=explode(',',$val);
				$n=0;
				foreach ($this->Conf['keyField'] as $key)
				{
					$key=strval($key);
					$querySQL  =$querySQL." and $key=".$this->DB->getstr($this->decodeStr($keyVal[$n]));
					$n++;
				}
			}
			else
			{
				$key=strval($this->Conf['keyField']);
				$querySQL=$key."=".$val;
			}

			if (empty($querySQL))
			{
				continue;
			}

			$delStat=0;
			if ($_statnew==3 and (!empty($this->Conf['statField']) or @count($this->fileField)>0))
			{
				$RS=$this->DB->query("SELECT * FROM ".$this->Conf['mainTable']." WHERE $querySQL",1);
				if (!empty($this->Conf['statField']) and $RS[$this->Conf['statField']]==3)
				{
					$delStat=1;
				}
			}

			//back
			if ($_statnew!=3 and !empty($this->Conf['statField']))
			{
				$ret=$this->DB->query("UPDATE  ".$this->Conf['mainTable']." SET ".$this->Conf['statField']."=$_statnew WHERE $querySQL");
			}
			//real del (not stat field or is deleted)
			else if ($_statnew==3 and (empty($this->Conf['statField']) or $delStat==1) and $this->editMode==2)
			{
				#delete File
				foreach ($this->fileField as $name => $arr)
				{
					if (!empty($RS[$name]))
					{
						upload_file('','',$updir.$RS[$name]);
					}
				}

/*
				if (isset($this->Conf['editLogSQL']) and strlen($this->Conf['editLogSQL'])>0)
				{
					$ret=@$this->DB->query($this->Conf['editLogSQL']." WHERE $querySQL");
					if ($ret)
					{
						$logID=$this->DB->last_id();
						if (!empty($this->Conf['editLogRec']))
						{
							@$this->DB->query(str_replace('{act}','DEL',$this->Conf['editLogRec']).$logID);
						}
						
					}
				}
*/
				//delete table
				$ret=$this->DB->query("DELETE FROM ".$this->Conf['mainTable']." WHERE $querySQL",1);
				if (!empty($this->Conf['subTable']))
				{
					$ret=$this->DB->query("DELETE FROM ".$this->Conf['subTable']." WHERE $querySQL",1);
				}


				//delele  file Table
			}
			//del as recycle
			else if ($_statnew==3 and !empty($this->Conf['statField']) and $this->editMode==2)
			{
				$ret=$this->DB->query("UPDATE  ".$this->Conf['mainTable']." SET ".$this->Conf['statField']."=3 WHERE $querySQL");
			}
			
			$sortnew   =trim(getpost('_sortnew'));
			$sortnew   =eregi_replace('(\(|\)|"|\')','',$sortnew);
			//change sort
			$skey='';
			if (isset($this->Conf['sortSet'][0]))
			{
				$skey=$this->Conf['sortSet'][0];
			}
			if (strlen($sortnew)>0 and strlen($skey)>0 and $this->Field[$skey])
			{
				if (eregi('M',$this->Field[$key]['main']))
				{
					$ret=$this->DB->query("UPDATE  ".$this->Conf['mainTable']." SET $skey =".($this->Field[$skey]['type']=='N' ? doubleval($sortnew) : $this->DB->getstr($sortnew))." WHERE $querySQL");
				}
				if (eregi('S',$this->Field[$key]['main']))
				{
					$ret=$this->DB->query("UPDATE  ".$this->Conf['subTable']." SET $skey =".($this->Field[$skey]['type']=='N' ? doubleval($sortnew) : $this->DB->getstr($sortnew))." WHERE $querySQL");
				}

			}

		}
		return 1;
	}



	/* NO 1.3
	 * @function dealForm
	 *
	 * @action:save sumbiit
	 * @param  Array $RS   (record data)
	 * @param  array $form (submit data)
	 * @return bool
	 */
	function dealForm($RS,$form)
	{
		$this->Msg='';

		if ($this->_edit==-1)
		{
			$act='ADD';
		}
		else
		{
			$act='EDIT';
		}

		$updir=$this->Conf['upFileDir'];

		$checkSQL='';# For check
		$checkIS =0;
		$keyName ='';
		if (is_array($this->Conf['keyField']))
		{
			$n=0;
			$keyVal=explode(',',$id);
			foreach ($this->Conf['keyField'] as $key)
			{
				$keyName=$keyName.'-'.$this->Field[$key]['name'];
				if ($act=='ADD' and eregi('A',$this->Field[$key]['mode']))
				{
					$checkSQL=$checkSQL." and $key=".($this->Field[$key]['type']=='N' ? doubleval($form[$key]) : $this->DB->getstr($form[$key]));
				}
				else if ($act=='EDIT')
				{
					$val = isset($form[$key]) ? $form[$key] : $RS[$key];
					if (eregi('E',$this->Field[$key]['mode']))
					{
						$checkIS=1;
					}

					$checkSQL=$checkSQL." and $key=".($this->Field[$key]['type']=='N' ? doubleval($val) : $this->DB->getstr($val));

				}
				$n++;
			}
			$keyName=substr($keyName,1);
		}
		else
		{
			$keyName=$this->Field[$key]['name'];
			$key=strval($this->Conf['keyField']);
			if ($act=='ADD' and eregi('A',$this->Field[$key]['mode']))
			{
				$checkSQL=$checkSQL." and $key=".($this->Field[$key]['type']=='N' ? doubleval($form[$key]) : $this->DB->getstr($form[$key]));
			}
			else if ($act=='EDIT')
			{
				$val = isset($form[$key]) ? $form[$key] : $RS[$key];
				if (eregi('E',$this->Field[$key]['mode']))
				{
					$checkIS=1;
				}

				$checkSQL=$checkSQL." and $key=".($this->Field[$key]['type']=='N' ? doubleval($val) : $this->DB->getstr($val));

			}
		}

		# Key unique
		if ($act=='ADD' and strlen($checkSQL)>0 or $checkIS==1)
		{
			$sql="SELECT * From ".$this->Conf['mainTable']."  WHERE 1 ".$checkSQL;
			$ret=$this->DB->query($sql,1);
			if ($ret)
			{
				$this->Msg=$this->Msg.'['.$keyName.']已经存在相同的值！';
			}
		}


		# Check sumbit form
		$updir=$this->Conf['upFileDir'];
		$saveData=Array();
		$saveDataSub=Array();
		$upFile=Array();
		foreach ($this->Field as $name => $Field)
		{
			if ($Field['type']=='L' or $Field['type']=='A' or $act=='ADD' and !ereg('A',$Field['mode']))
			{
				continue;
			}

			if ($act=='EDIT' and !ereg('E',$Field['mode']))
			{
				continue;
			}

			$fieldType=0;
			if ($Field['type']=='N')
			{
				$fieldType=1;
			}
			else if ($Field['type']=='C')
			{
				$fieldType=2;
			}

			#checkbox
			if ($Field['input']=='check')
			{
				$value='';
				if (is_array($form[$name]))
				{
					foreach ($form[$name] as $val)
					{
						if (isset($val,$Field['value'][$val]))
						{
							$value=$value.','.$val;
						}
					}
					if (strlen($value)>1)
					{
						$value=substr($value,1);
					}
				}
			}
			else if ($Field['input']=='hidden')
			{
				$value=$Field['value'];
			}
			else
			{
				$value=$form[$name];
			}

			#check unique Field
			if ($Field['format']=='-')
			{
				$sql="SELECT * From ".$this->Conf['mainTable']."  WHERE $name=".($this->Field[$name]['type']=='N' ? doubleval($form[$name]) : $this->DB->getstr($form[$name]));
				$ret=$this->DB->query($sql,1);
				if ($ret)
				{
					$this->Msg=$this->Msg.'['.$this->Field[$name]['title'].']已经存在相同的值！';
				}
			}
			#check Char Format (2008-4-6)
			else if (strlen($Field['format'])>0 and $Field['input']!='file')
			{
				if (!eregi($Field['format'],$value))
				{
					$this->Msg=$this->Msg.'['.$this->Field[$name]['title'].']'.'格式不符合要求！';
				}
			}
			#char
			else if ($Field['type']=='S')
			{
				if ($Field['min']>0)
				{
					$value=trim($value);
					if (empty($value))
					{
						$this->Msg=$this->Msg.'['.$Field['title'].']不能为空！';
					}
				}
				if (strlen($value)<$Field['min'])
				{
					$this->Msg=$this->Msg.'['.$Field['title'].']不能小于'.$Field['min'].'字节！';
				}
				if (strlen($value)>$Field['max'])
				{
					$this->Msg=$this->Msg.'['.$Field['title'].']不能大于'.$Field['max'].'字节！';
				}
			}
			#check file upload
			else if ($Field['type']=='F')
			{
				$upFile[$name]=getfile($name,$Field['title'],$Field['max'], $Field['min'],$this->Msg,$Field['format']);
				if (empty($upFile[$name]['tmp_name']) and $form['_file_del_'.$name]==1)
				{
					upload_file('','',$updir.$RS[$name]);
					if (eregi('M',$Field['main']))
					{
						$saveData[$name]=array("");
					}
					if (eregi('S',$Field['main']))
					{
						$saveDataSub[$name]=array("");
					}
				}
			}
			#check number
			else if ($Field['type']=='N')
			{
				$value=doubleval($value);
				if ($value > $Field['max'] or $value < $Field['min'])
				{
					$this->Msg=$this->Msg.'['.$Field['title'].']不在允许范围以内！';
				}
			}

			if ($Field['type']=='F')
			{
				;
			}
			else  if ($act=='ADD' and ereg('A',$Field['mode']) 
				or $act=='EDIT' and ereg('E',$Field['mode']))
			{
				if (eregi('M',$Field['main']))
				{
					$saveData[$name]=array($value,$fieldType);
				}
				if (eregi('S',$Field['main']))
				{
					$saveDataSub[$name]=array($value,$fieldType);
				}
				
			}
		}

		if ($act=='ADD' and count($saveData)<1 or count($saveData)<1 and count($saveDataSub)<1)
		{
			$this->Msg='未设置需要编辑的字段！';
			return false;
		}
		
		# Save into databases
		if (empty($this->Msg))
		{
			#file upload
			if (is_array($upFile) and count($upFile)>0)
			{
				if (!file_exists($updir))
				{
					mkdir($updir,0777);
				}
				# make dir
				if ($this->Conf['upDirMode']==2)
				{
					$mthDir=date('Y').'/';
					if (!file_exists($updir.$mthDir))
					{
						mkdir($updir.$mthDir,0777);
					}
					$mthDir=date('Y').'/'.date('Ymd').'/';
				}
				else if ($this->Conf['upDirMode']==1)
				{
					$mthDir=date('Ym').'/';
				}
				else
				{
					$mthDir='';
				}

				if ($this->Conf['upDirMode']>0 and !file_exists($updir.$mthDir))
				{
					mkdir($updir.$mthDir,0777);
				}
			}

			foreach($upFile as $name =>$files)
			{
				$randStr=chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122));
				$Field=$this->Field[$name];
				if (!empty($files['tmp_name']))
				{
					if (!empty($Field['value']))
					{
						$filename=$Field['value'];
					}
					else
					{
						$filename =$mthDir.$this->Conf['upFilePre'].date('YmdHis')."_".$randStr.$files['ext'];
					}
					upload_file($files['tmp_name'],$updir.$filename,$updir.$RS[$name]);
					if (eregi('M',$Field['main']))
					{
						$saveData[$name]=array($filename);
					}
					if (eregi('S',$Field['main']))
					{
						$saveDataSub[$name]=array($filename);
					}
				}
			}

			if ($act=='ADD')
			{
				$ret=$this->DB->insert($this->Conf['mainTable'],$saveData);
				
				if (!empty($this->Conf['subTable']))
				{
					if ($this->Field[$this->Conf['keyField']]['type']=='S' and !empty($form[$this->Conf['keyField']]))
					{
						$saveDataSub[$this->Conf['keyField']]=array($form[$this->Conf['keyField']]);
						$ret=$this->DB->insert($this->Conf['subTable'],$saveDataSub);
					}
					else if ($this->Field[$this->Conf['keyField']]['type']=='N' or $this->Field[$this->Conf['keyField']]['type']=='A')
					{
						$id=$this->DB->last_id();
						$saveDataSub[$this->Conf['keyField']]=array($id,1);
						$ret=$this->DB->insert($this->Conf['subTable'],$saveDataSub);
					}
				}
			}
			else
			{
				if (isset($this->Conf['editLogSQL']) and strlen($this->Conf['editLogSQL'])>0)
				{
					$ret=@$this->DB->query($this->Conf['editLogSQL']." WHERE 1 ".$this->keySQL);
					if ($ret)
					{
						$logID=$this->DB->last_id();
						if (!empty($this->Conf['editLogRec']))
						{
							@$this->DB->query(str_replace('{act}','MODI',$this->Conf['editLogRec']).$logID);
						}
						
					}

				}

				$ret=$this->DB->update($this->Conf['mainTable'],$saveData,"1 ".$this->keySQL);
				if (!empty($this->Conf['subTable']) and count($saveDataSub)>0)
				{
					if ($this->querySub==1)
					{
						$ret=$this->DB->update($this->Conf['subTable'],$saveDataSub,"1 ".$this->keySQL);
					}
					else if ($this->Field[$this->Conf['keyField']]['type']=='S')
					{
						$saveDataSub[$this->Conf['keyField']]=array($this->_edit);
						$ret=$this->DB->insert($this->Conf['subTable'],$saveDataSub);
					}
					else if ($this->Field[$this->Conf['keyField']]['type']=='N' or $this->Field[$this->Conf['keyField']]['type']=='A')
					{
						$saveDataSub[$this->Conf['keyField']]=array($this->_edit,1);
						$ret=$this->DB->insert($this->Conf['subTable'],$saveDataSub);
					}
				}
			}

			if ($ret)
			{
				return 1;
			}
			else
			{
				$this->Msg=$this->DB->err_msg();
				return false;
			}
			
		}
		return 0;
	}






	/* NO 1.4
	 * @function showForm
	 *
	 * @action:show form 
	 * @param  array $form (submit data)
	 * @return string  (HTML code)
	 */
	function showForm($form)
	{
		if ($this->_edit==-1) $act='ADD';
		else $act='EDIT';

		//<SCRIPT SRC="'.___dir___.'include/calender.js" LANGUAGE="JavaScript"></SCRIPT>
		$HTML='
    <TABLE width="100%" cellpadding=2 cellspacing=1 border=0 class="tbl_body" align="center">
        <tr>
          <td class="tbl_head" height=2><span class="li_title"><B>'.$this->Conf['title'].'</B></span></td></tr>
      </TABLE>
    <TABLE width="100%" cellpadding=0 cellspacing=0 border=0 align="center">
        <tr>
          <td height=2></td></tr>
      </TABLE>';
		$HTML=$HTML.'
    <TABLE width="100%" cellpadding=0 cellspacing=0 border=0 align="center">
        <tr>
          <td height=2>
<SCRIPT LANGUAGE="JavaScript">
<!--

function saveEdit()
{
	return 1;
}

//-->
</SCRIPT></td></tr>
      </TABLE>
    <table border=0 width="100%" cellspacing=1 cellpadding=2 bgcolor="#C6C6C9" align="center" class="tbl_body">
    <form action="'.$this->_pagename.'" method="post" name="_editForm"  enctype="multipart/form-data" onsubmit="return saveEdit()">
    <tr bgColor="#FFFFFF" class="tbl_head">
    <td width="15%" align=right height=26><B>项目</B></td>
    <td width="85%"><B>内容</B></td>
    </tr>';

		$updir=$this->Conf['upFileDir'];

		$inEdit=0;
		foreach ($this->Field as $name => $_Field)
		{
			if (empty($_Field['mode']))
			{
				continue;
			}

			if (empty($form[$name]) and eregi('(N|S)',$_Field['type']) and !empty($_Field['value']))
			{
				$form[$name]=$_Field['value'];
			}

			$fileStr='';
			if ($_Field['type']=='F')
			{
				if (!empty($form[$name]) and file_exists($updir.$form[$name]))
				{
					if (eregi('(\.gif|\.png|\.jpg|\.jpeg)$',$form[$name]) and filesize($updir.$form[$name]) < 64*1024)
					{
						$fileStr=get_img($updir.$form[$name],150,150);
					}
					else
					{
						$fileStr='<A href="'.$updir.$form[$name].'">'.$form[$name].'</A>';
					}
					$fileStr=$fileStr.' &nbsp; <input type="checkbox" name="_file_del_'.$name.'" value=1><B>删除此文件</B> （选择并提交后生效）';
				}
				else
				{
					$fileStr='<font class="font_hot">未上传文件！</font>';
				}
				$fileStr=$fileStr.'<br>';
			}

			if (eregi('S',$_Field['main']) and empty($this->Conf['subTable']))
			{
				;//从表保存但配置不正确
			}
			else if ($_Field['mode']=='L' and $act=='EDIT')
			{
				$fd=$_Field['value'];
				$val=get_html($form[$fd]);
				$val=str_replace('*',$val,$_Field['show']);
				$HTML=$HTML.'
    <tr bgColor="#FFFFFF" class="tbl_row" height=26>
    <td align=right>'.$_Field['title'].'</td>
    <td><b>'.$val.'</b></td>
    </tr>';
			}
			else if ($act=='EDIT' and eregi('S',$_Field['mode']))
			{
				if ($_Field['input']=='html')
				{
					$cont=$form[$name];
				}
				else if (is_array($_Field['value']))
				{
					$k_=$form[$name];
					$cont=$_Field['value'][$k_];
				}
				else
				{
					$cont=get_html($form[$name]);
				}
				$HTML=$HTML.'
    <tr bgColor="#FFFFFF" class="tbl_row" height=26>
    <td align=right>'.$_Field['title'].'</td>
    <td>'.$cont.'</td>
    </tr>';
			}
			else if ($act=='EDIT' and eregi('E',$_Field['mode']) or $act=='ADD' and eregi('A',$_Field['mode']) and $_Field['input']!='hidden')
			{
				$inEdit=1;
				$HTML=$HTML.'
    <tr bgColor="#FFFFFF" class="tbl_row" height=26>
    <td align=right>'.$_Field['title'].'</td>
    <td>'.$fileStr.$this->showInput($_Field,$form).'</td>
    </tr>';
			}
		}

		$HTML=$HTML.'
    <tr bgColor="#F6F6F6" class="tbl_rows" height=32>
    <td colspan="2" align=center>
    <input type="hidden" name="_save" value=1>
    <input type="hidden" name="_edit" value="'.$this->_edit.'">'.$this->str_Hidden($this->_pagelink);

		if ($inEdit==1)
		{
			$HTML=$HTML.'
	<input type=submit value=" 保存 " class="inputbtn"> &nbsp; ';
		}

		$HTML=$HTML.'
	<input type=button value=" 返回 " class="inputbtn" onclick="window.open(\''.$this->_pagename.'?'.$this->_pagelink.'&_page='.$page.'\',\'_self\');">
    </td>
    </tr>
    </form>
    </table>
		';

		if (!empty($this->Conf['editNote']))
		{
			$HTML=$HTML.'
    <TABLE width="100%" cellpadding=0 cellspacing=0 border=0 align="center">
        <tr>
          <td height=2></td></tr>
    </table>
    <TABLE width="100%"  cellspacing=1 cellpadding=5 align="center" class="tbl_body">
        <tr>
          <td height=2 class="tbl_row">'.$this->Conf['editNote'].'</td></tr>
      </TABLE>';
		}
		return $HTML;
	}







	/* NO 1.5
	 * @function showInput
	 *
	 * @action:show form input
	 * @param  array $_Field (setting)
	 * @param  array $form (submit data)
	 * @return string  (HTML code)
	 */
	function showInput($_Field,$form)
	{
		if ($_Field['height']<1)
		{
			$_Field['height']=60;
		}
		if ($_Field['size']<30)
		{
			$_Field['size']=180;
		}
		$width_style=" style=\"width:".$_Field['size']."px;\"";

		$HTML='';
		$value=$form[$_Field['name']];
		if ($_Field['type']=='N')
		{
			$showLen =strlen($_Field['max']);
			$showSize=$_Field['size'];
			if ($showSize<1)
			{
				$showSize=$showLen;
			}
		}
		else
		{
			$showLen =$_Field['max'];
			$showSize=$_Field['size'];

			if ($showSize<1)
			{
				$showSize=$showLen;
				if ($showSize>60)
				{
					$showSize=60;
				}
				else
				{
					$showSize=$showLen;
				}
			}

		}

		$isSet=1;
		if ($_Field['input']=='input')
		{
			//2008-04-12 22:51 防止换行丢失数据
			$value=str_replace("\n","",$value);
			$value=str_replace("\r","",$value);
			$HTML=$HTML.'<INPUT TYPE="text" NAME="'.$_Field['name'].'" VALUE="'.get_html($value).'" '.$width_style.' MAXLENGTH="'.$showLen.'" onkeydown="chkKey()">';
			if (eregi('date',$_Field['name']) and $_Field['max']==10)
			{
				$HTML=$HTML.'<IMG SRC="'.___dir___.'include/calender.gif" WIDTH=16 HEIGHT=16 onclick="JS_setDate(_editForm.'.$_Field['name'].')">';
			}
		}
		else if ($_Field['input']=='text')
		{
			$HTML=$HTML.'<TEXTAREA NAME="'.$_Field['name'].'"  style="height:'.$_Field['height'].'px;width:'.$_Field['size'].'px" onkeydown="chkKey(1)">'.get_html($value).'</TEXTAREA>';
		}
		else if ($_Field['input']=='html')
		{

			$HTML=$HTML.'
      <div name="_inputDIV_'.$_Field['name'].'" id="_inputDIV_'.$_Field['name'].'"><TEXTAREA NAME="'.$_Field['name'].'" style="width:'.$_Field['size'].'px;height:'.$_Field['height'].'px;"  onkeydown="chkKey(1)">'.get_html($value).'</TEXTAREA>
      </div>

<script language="javascript">
<!--

////////////////////////////
if(navigator.userAgent.indexOf("MSIE")>-1 || window.sidebar)
{
	if (_editForm.'.$_Field['name'].'.value.indexOf("\r\n") == 1)
	{
		_editForm.'.$_Field['name'].'.value="\n"+_editForm.'.$_Field['name'].'.value;
	}
	
}

var IsIE5 = navigator.userAgent.indexOf("MSIE ")  > -1;

if(IsIE5 && 1)//only IE5 or IE6
{
	document.all._inputDIV_'.$_Field['name'].'.style.display="none";
	document.all._inputDIV_'.$_Field['name'].'.visibility="hide";


	//------ edit box ---------
	var box_font="'.$this->Conf['editFont'].'";
	var box_charset="UTF-8";
	var box_form=_editForm;
	var box_cont=_editForm.'.$_Field['name'].';
	var box_width='.$_Field['size'].'; //editBox width
	var box_height='.$_Field['height'].';
	var box_scrolling="yes"; //yes/no
	var box_file_table="'.$this->Conf['fileTable'].'"; //save File table
	var box_read_id="'.$this->_edit.'"; //upFile file save id
	document.write("<script language=\"javascript\" src=\"htmleditor/edithtml.php\"><"+"/script>");
}

-->
</script>

';
			if (!file_exists('htmleditor/edithtml.php'))
			{
				$HTML=$HTML.'Error!';
			}

		}
		else if ($_Field['input']=='radio')
		{
			$n=0;
			foreach ($_Field['value'] as $val => $name)
			{
				$n++;
				$HTML=$HTML.'<INPUT TYPE="radio" NAME="'.$_Field['name'].'" ID="_check_'.$_Field['name'].'_'.$n.'" VALUE="'.$val.'"'.($val==$value ? ' checked': '').' onkeydown="chkKey()"><label for="_check_'.$_Field['name'].'_'.$n.'"><B>'.$name.'</B></label> ';
			}
		}
		else if ($_Field['input']=='check')
		{
			$n=0;
			foreach ($_Field['value'] as $val => $name)
			{
				$n++;
				$HTML=$HTML.'<INPUT TYPE="checkbox" NAME="'.$_Field['name'].'[]" ID="_check_'.$_Field['name'].'_'.$n.'" VALUE="'.$val.'"'.(strpos(' ,'.$value.',',','.$val.',')>0 ? ' checked': '').' onkeydown="chkKey()"><label for="_check_'.$_Field['name'].'_'.$n.'">'.$name.'</label>';
			}
		}
		else if ($_Field['input']=='select')
		{
			$HTML=$HTML.'
		<select name="'.$_Field['name'].'" onkeydown="chkKey()" '.$width_style.'>';
			foreach ($_Field['value'] as $val => $name)
			{
				$HTML=$HTML.'<OPTION VALUE="'.$val.'"'.($val==$value ? ' selected style="color:red;"': '').'>'.$name.'</OPTION>';
			}
			$HTML=$HTML.'
		</select>';
		}
		else if ($_Field['input']=='file')
		{
			$HTML=$HTML.'上传新文件：<INPUT TYPE="file" NAME="'.$_Field['name'].'" onkeydown="chkKey()" '.$width_style.'>';
		}
		else
		{
			$isSet=0;
			$HTML=get_html($value);
		}

		if ($isSet==1)
		{
			if ($_Field['min']>0)
			{
				$HTML=$HTML.' <font color=red>**</font>';
			}
			if ($_Field['width']>450 and $_Field['input']=='input' or $_Field['input']=='text')
			{
				$HTML=$HTML.'<br>';
			}
			$HTML=$HTML.' '.$_Field['note'];

			if ($_Field['input']=='file')
			{
				$HTML=$HTML.'<BR> 上传的新文件必须是'.$_Field['max'].'KB以内的('.$_Field['format'].')格式文件，若已有文件再上传则会删除以前的文件。';
			}
		}
		return $HTML;
	}




	/* NO 1.6
	 * @function showList
	 *
	 * @action:show list table
	 * @return string  (HTML code)
	 */
	function showList()
	{
		$HTML='';

		$where=" where 1 ";
		if (!empty($this->Conf['seleSQL']))
		{
			$where=$where.$this->Conf['seleSQL'];
		}

		# search print
		$searchHTML='';
		if (is_array($this->Conf['searchSet']) and count($this->Conf['searchSet'])>0)
		{
			$searchHTML=$searchHTML.$this->str_hidden($this->_baselink."&_order=".$this->order);

			$searchHTML=$searchHTML.'<B>关键字</B> <INPUT TYPE="text" NAME="_key" VALUE="'.get_html($this->_key).'" SIZE="20" MAXLENGTH="30">';
			$n=0;
			$option='';
			foreach ($this->Conf['searchSet'] as $name)
			{
				if (isset($this->Field[$name]))
				{
					$fnum=$this->Field[$name]['num'];
					$option=$option.'<option value="'.$fnum.'"'.($this->_find==$fnum ? ' selected style="font-color:#DD0000;"': '').'>'.get_html($this->Field[$name]['title']).'</option>';
					$n++;
				}
			}
			if ($n==1)
			{
				$searchHTML=$searchHTML.'[在'.$this->Field[$name]['title'].'中]';
			}
			else if ($n>1)
			{
				$searchHTML=$searchHTML.'
		<SELECT NAME="_find">'.$option.'</SELECT>';
			}

			//#SQL
			$findField=$this->numField[$this->_find];
			if (!empty($this->_key) and !empty($findField) and isset($this->Field[$findField]) and $n>0)
			{
				if ($this->Field[$findField]['type']=='S' or $this->Field[$findField]['type']=='F')
				{
					$where=$where." and $findField like ".$this->DB->getstr('%'.$this->_key.'%');
				}
				else if(($this->Field[$findField]['type']=='N' or $this->Field[$findField]['type']=='A') and eregi('^(>|>=|<|=|<=|!=|<>)',$this->_key,$det))
				{
					$key=str_replace($det[1],'',$this->_key);
					$key=intval($key);
					if($det[1]=='!=') $det[1]=='<>';
					$where=$where." and $findField ".$det[1].$key;
				}
				else if($this->Field[$findField]['type']=='N' or $this->Field[$findField]['type']=='A')
				{
					$this->_key=doubleval($this->_key);
					$where=$where." and $findField = ".$this->_key;
				}
			}

		}

		//状态设置
		$changeHTML='';
		if (strlen($this->Conf['statField'])>0 and isset($this->Field[$this->Conf['statField']]))
		{
			$option='';
			$checks='';
			$n=0;
			foreach ($this->Conf['statSet'] as $k =>$name)
			{
				$option=$option.'
			<option value="'.$k.'"'.((strlen($this->_stat)>0 and $this->_stat==$k) ? ' selected style="font-color:#DD0000;"': '').'>'.get_html($name).'</option>';
				$checks=$checks.'
			<input type="radio" name="_stat" value="'.$k.'"'.((strlen($this->_stat)>0 and $this->_stat==$k) ? ' selected style="font-color:#DD0000;"': '').' />'.get_html($name).'';
				$n++;

			}

			$statnew   =intval(getpost('statnew'));
			if ($n>1)
			{
				$searchHTML=$searchHTML.'
		<SELECT NAME="_stat"><option value="">-- 全部 --</option>'.$option.'</SELECT>';
				$changeHTML=$changeHTML.'
		更改为<SELECT NAME="_statnew"><option value="">-- 不更改 --</option>'.$option.'</SELECT>';
			}
			else
			{
				$searchHTML=$searchHTML.'
		<input type="radio" NAME="_stat" value="">全部 '.$checks;
				$changeHTML=$changeHTML.'
		<input type="radio" NAME="_statnew" value="">不更改 '.str_replace('"_stat"','"_statnew"',$checks);
			}
			$statF=$this->Conf['statField'];
			if(strlen($this->_stat)>0 and $n>0)
			{
				$where=$where." and $statF = ".$this->_stat;
			}
		}


		#type option
		if (isset($this->Conf['sortSet']) and count($this->Conf['sortSet'])>0)
		{
			$key=$this->Conf['sortSet'][0];
			$valArr=$this->Conf['sortSet'][1];

			$sortnew   =trim(getpost('_sortnew'));
			$sortnew   =eregi_replace('(\(|\)|"|\')','',$sortnew);
			if ($this->Field[$key]['type']=='N' or $this->Field[$key]['type']=='S' and $this->Field[$key]['max']<31)
			{
				if (strlen($this->_sort)<1)
				{
					;
				}
				else if ($this->Field[$key]['type']=='N')
				{
					$where=$where." and $key=".doubleval($this->_sort);
				}
				else
				{
					$this->_sort=substr($this->_sort,0,30);
					$where=$where." and $key=".$this->DB->getstr($this->_sort);
				}

				$searchHTML=$searchHTML.'
		<select name="_sort"><option value="">- '.$this->Field[$key]['title'].' -</option>';
				$changeHTML=$changeHTML.'
		&nbsp; 转移到<select name="_sortnew"><option value="">- '.$this->Field[$key]['title'].' -</option>';

				foreach ($valArr as  $val => $str)
				{
					if (strlen($str)<1)
					{
						continue;
					}
					$searchHTML=$searchHTML.'<option value="'.$val.'"'.(( strlen($val) == strlen($this->_sort) and $val == $this->_sort) ? ' selected style="color:red;"' : '').'>'.$str.'</option>';
					$changeHTML=$changeHTML.'<option value="'.$val.'"'.(( strlen($val) == strlen($sortnew) and $val == $sortnew) ? ' selected style="color:red;"' : '').'>'.$str.'</option>';
				}

				$searchHTML=$searchHTML.'
		</select>';
				$changeHTML=$changeHTML.'
		</select>';

			}
		}

		if (!empty($searchHTML))
		{
			$searchHTML=$searchHTML.'<input type="submit" class="inputbtn" value=" 搜索 "> &nbsp; <A HREF="'.$this->_pagename.'?'.$this->_baselink.'_order='.$this->_order.'"><B  class="font_link">显示所有</B></a><BR>';
		}

		# order by
		$orderSet='';
		$f_order=abs($this->_order);
		if ($f_order>0 and isset($this->numField[$f_order]))
		{
			if ($this->_order>0)
			{
				$orderSet=" desc ";
			}
			$f_name=$this->numField[$f_order];
			$orderSet=$this->Field[$f_name]['name'].$orderSet;


			if($this->Field[$f_name]['type']=='S' and $this->Field[$f_name]['max']>254 or $this->Field[$f_name]['type']=='F')
			{
				$orderSet='';
			}
		}

		if (!empty($this->Conf['orderSet']))
		{
			$orderSet=" order by ".(!empty($orderSet) ? $orderSet.',' : '')." ".$this->Conf['orderSet'];
		}
		$page=$this->_page;
		$result=$this->DB->query_page($page,"*",$this->Conf['mainTable'],$where,$orderSet);
		//echo $where;
		$pageShow= pageshow($page,$this->DB->pageinfo['pagenum'],$this->DB->pageOrder,$this->_pagename."?".$this->_pagelink."&_page=*");
		$this->_page=$page;
		//echo '<pre>';print_r($pageShow);echo '</pre>';

		$pagelink="共<B>{$this->DB->pageinfo['rowsnum']}</B>条记录 第{$page}页/共{$this->DB->pageinfo['pagenum']}页";
		if (!empty($pageShow['pageprev']))
		{
			$pagelink=$pagelink." &nbsp; <A HREF=\"".$pageShow['pageprev']."\">上一页</A>";
		}
		else
		{
			$pagelink=$pagelink." &nbsp; 上一页";
		}
		if (count($pageShow['pagelist'])>1)
		{
			foreach($pageShow['pagelist'] as $arr)
			{
				if ($arr['now']==1)
				{
					$pagelink=$pagelink.' &nbsp;<a href="'.$arr['url'].'"><font color=red>['.$arr['show'].']</font></a>';
				}
				else
				{
					$pagelink=$pagelink.' &nbsp;<a href="'.$arr['url'].'">['.$arr['show'].']</a>';
				}
				
			}
		}
		if (!empty($pageShow['pagenext']))
		{
			$pagelink=$pagelink." &nbsp; <A HREF=\"".$pageShow['pagenext']."\">下一页</A>";
		}
		else
		{
			$pagelink=$pagelink." &nbsp; 下一页";
		}
		$pagelink=$pagelink."&nbsp; ".$pageShow['link']."" ;
		if (!eregi('V',$this->Conf['addSet']))
		{
			$pagelink=$pagelink.' &nbsp; &nbsp; <A HREF="'.$this->_pagename.'?'.$this->_baselink.'_edit=-1"><B class="font_link">添加记录</B></A>';
		}

		#print begain
		$HTML='
    <TABLE width="100%" cellpadding=2 cellspacing=1 border=0 class="tbl_body" align="center">
        <tr>
          <td class="tbl_head" height=2><span class="li_title"><B>'.$this->Conf['title'].'</B></span></td></tr>
        <tr>
          <form action="'.$this->_pagename.'" method="GET">
          <td class="tbl_row" height=32>'.$searchHTML.'
		'.$pagelink.'   <!--&nbsp; <A HREF="'.$this->_pagename.'?'.$this->_pagelink.'_page='.$page.'&_save=2" target="_blank"><B>下载数据</B></a>--> </td></tr></form>
      </TABLE>
    <TABLE width="100%" cellpadding=0 cellspacing=0 border=0 align="center">
        <tr>
          <td height=2></td></tr>
      </TABLE>';

		#table head
		$HTML=$HTML.'
    <table border=0 width="100%" cellspacing=1 cellpadding=2 align="center" class="tbl_body">
		<form action="'.$this->_pagename.'" name="_listForm" method="POST" onsubmit="return  submitSel(_listForm,\'_select[]\',\'您没有选择任何项目！\',\'您确定要\' +_listForm._actName.value +\'所选项目吗？\')">
      <tr  bgColor="#FFFFFF" height=26  align=center class="tbl_head">';

		$colnum=0;
		if ($this->editMode>0)
		{
			$HTML=$HTML.'
        <td'.(!empty($this->Conf['seleColW']) ? ' width="'.$this->Conf['seleColW'].'"' : '').'><INPUT TYPE="checkbox" onclick="selectAllRow(_listForm,\'_select[]\',\'_seleRow\',this.checked)"></td>';
		}
		if (!empty($this->Conf['keyField']) and $this->editMode>1)
		{
			$colnum=$colnum+2;
			$HTML=$HTML.'
        <td'.(!empty($this->Conf['deleColW']) ? ' width="'.$this->Conf['deleColW'].'"' : '').'>删除</td>';
		}

		if (!empty($this->Conf['keyField']) and !empty($this->Conf['editColW']) and $this->editMode>0)
		{
			$colnum=$colnum+1;
			$HTML=$HTML.'
        <td width="'.$this->Conf['editColW'].'">编辑</td>';
		}

		foreach ($this->Field as $_Field)
		{
			if (strlen($_Field['colswidth'])<1 or !eregi('M',$_Field['main']))
			{
				continue;
			}
			$sortOrder=$_Field['num'];
			$orderTag='';
			if (strlen($this->_order)>0 and $this->_order==$sortOrder)
			{
				$orderTag='<span class="font_hot">V</font>';
				$sortOrder=0-$_Field['num'];
			}
			else if (strlen($this->_order)>0 and $this->_order==0-$sortOrder)
			{
				$orderTag='<span class="font_hot">A</font>';
			}

			$colnum=$colnum+1;
			$HTML=$HTML.'
        <td'.((!empty($_Field['colswidth']) and $_Field['colswidth']!='-') ? ' width="'.$_Field['colswidth'].'"' : '').'>';

			if ($_Field['type']=='S' and $_Field['max']>254 or $_Field['mode']=='L')
			{
				$HTML=$HTML.get_html($_Field['title']);
			}
			else
			{
				$HTML=$HTML.'<A HREF="'.$this->_pagename.'?'.eregi_replace('_order=[-0-9]+&','',$this->_pagelink) .'&_page='.$page.'&_order='.$sortOrder.'">' .get_html($_Field['title']).'</A>'.$orderTag;
			}
			$HTML=$HTML.'</td>';
		}

		$HTML=$HTML.'
      </tr>';

		#table body
		$autoCheck='';
		$rowclass='';
		$n=0;
		foreach($result as $arr)
		{
			$keyVal='';
			if (is_array($this->Conf['keyField']))
			{
				foreach ($this->Conf['keyField'] as $key)
				{
					$keyVal=$keyVal.','.str_replace('=','',$this->encodeStr($arr[$key]));
				}
				$keyVal=substr($keyVal,1);
				$showName='';
			}
			else
			{
				$key=strval($this->Conf['keyField']);
				if ($this->Field[$key]['type']=='N')
				{
					$keyVal=doubleval($arr[$key]);
				}
				else
				{
					$keyVal=urlencode($arr[$key]);
				}
				$showName=' ['.$arr[$key].'] ';
			}

			if (!empty($this->Conf['statField']) and $arr[$this->Conf['statField']]==3)
			{
				$delStr='<a href="'.$this->_pagename."?_save=3".$this->_pagelink."&_page=$page&_select[]=".$keyVal.'&_statnew=3"  onclick="return confirm(\'您确定要彻底清除此记录'.$showName.'吗？\')" style="color:#EE0000;">清除</a>';

				$editlink=' style="color:#777777;"';
				$editStr='<a href="'.$this->_pagename."?_save=3".$this->_pagelink."&_page=$page&_select[]=".$keyVal.'&_statnew=0"  onclick="return confirm(\'您确定要恢复此记录'.$showName.'吗？\')" style="color:blue;">恢复</a>';
			}
			else
			{
				$delStr='<a href="'.$this->_pagename."?_save=3".$this->_pagelink."&_page=$page&_select[]=".$keyVal.'&_statnew=3"  onclick="return confirm(\'您确定要删除此记录'.$showName.'吗？\')">删除</a>';
				$editlink=' href="'.$this->_pagename.'?'.$this->_pagelink.'&_page='.$page.'&_edit='.$keyVal.'"';
				$editStr='<a '.$editlink.'>编辑</a>';
			}
			if ($n%2==0)
			{
				$rowclass='tbl_row';
			}
			else
			{
				$rowclass='tbl_rows';
			}


			$HTML=$HTML.'
      <tr bgColor="#FFFFFF" class="'.$rowclass.'" height=26 align="center" id="_seleRow'.$n.'">';
			$onclickStr='';
			$autoCheck =$autoCheck.'
setTimeout("checkSeleRow(\'_seleRow'.$n.'\',\'_seleBox'.$n.'\',\''.$rowclass.'\',0)",100);';
			$onclickStr='onclick="checkSeleRow(\'_seleRow'.$n.'\',\'_seleBox'.$n.'\',\''.$rowclass.'\',1)"';
			if ($this->editMode>0)
			{
				$HTML=$HTML.'
        <td '.$onclickStr.'><input type="checkbox" name="_select[]" value="'.$keyVal.'" id="_seleBox'.$n.'"  '.$onclickStr.'></td>';
			}

			if (!empty($this->Conf['keyField']) and $this->editMode>1)
			{
				$HTML=$HTML.'
        <td>'.$delStr.'</td>';
			}

			if (!empty($this->Conf['keyField']) and !empty($this->Conf['editColW']) and $this->editMode>0)
			{
				$HTML=$HTML.'
        <td>'.$editStr.'</td>';
			}

			foreach ($this->Field as $fd => $_Field)
			{
				if (strlen($_Field['colswidth'])<1 or !eregi('M',$_Field['main']))
				{
					continue;
				}

				$val='';
				if (empty($fd))
				{
					$val='';
				}
				else if ($_Field['mode']=='L')
				{
					$fd=$_Field['value'];
					$val=get_html($arr[$fd]);
					$val=str_replace('*',$val,$_Field['show']);
				}
				else if (empty($_Field['show']))
				{
					$val=get_html($arr[$fd]);
				}
				else if (is_array($_Field['show']))
				{
					$v=$arr[$fd];
					$val=$_Field['show'][$v];
				}
				else if ($_Field['show']=='*')
				{
					$val=str_replace('*',get_html($arr[$fd]),$_Field['show']);
				}
				else if ($_Field['show']=='@')
				{
					$val=get_html($arr[$fd]);
					$val='<a '.$editlink.'>'.$val.'</a>';
				}
				else if (eregi('\[',$_Field['show']))
				{
					@preg_match_all('/\[([_0-9a-z]+)\]/',$_Field['show'],$det);
					$val=str_replace('*',get_html($arr[$fd]),$_Field['show']);
					foreach($det[1] as $k => $rF)
					{
						$val=str_replace($det[0][$k],$arr[$rF],$val);
					}
				}
				else if (eregi('^\{([0-9]+)\}$',$_Field['show'],$det))
				{
					$ret=abs(intval($det[1]));
					$val=sub_string(strip_tags($arr[$fd]),$ret);
				}
				else if (eregi('^#([0-9]?)$',$_Field['show'],$det) and $this->Field[$fd]['type']=='N')
				{
					$dec=intval($det[1]);
					$val=number_format($arr[$fd],$dec);
				}
				else if (strlen($_Field['show'],0,1)=='$')
				{
					$tmp=explode(',',$_Field['show']);
					$val=get_html($arr[$fd]);
					if (!empty($tmp[0]) and !empty($tmp[1]))
					{
						$res=$this->DB->query("select count(*) as cnum from ".$tmp[0]." where ".$tmp[1].'='.($_Field['type']=='S' ? $this->DB->getstr($val) : $val).(!empty($tmp[1]) ? ' and '.$tmp[1] : ''),1);
						$val=$res['cnum'];
					}
					else
					{
						$val=0;
					}
				}

				$align='';
				if (!empty($_Field['align']))
				{
					$align=$_Field['align'];
				}
				else if (is_array($_Field['show']))
				{
					$align='';
				}
				else if ($_Field['type']=='N')
				{
					$align='align="right"';
				}
				else if ($_Field['type']=='S' and $_Field['max']>15)
				{
					$align='align="left"';
				}

				$HTML=$HTML.'
        <td '.$align.' '.$onclickStr.'>'.$val.'</td>';
			}
			//END foreach

			$HTML=$HTML.'
      </tr>';

			$n++;
		}
		//END while

		if (strlen($changeHTML)>0 and $this->editMode>0)
		{
			$HTML=$HTML.'
      <tr bgColor="#FFFFFF"><td colspan='.$colnum.' height=32 class="tbl_menu">'.$this->str_hidden($this->_pagelink.'&page='.$page).'
		<INPUT TYPE="hidden" NAME="_select[]" VALUE="">
		<INPUT TYPE="checkbox" onclick="selectAllRow(_listForm,\'_select[]\',\'_seleRow\',this.checked)" id="_seleAll"><label for="_seleAll"><font color=blue>全部选择/取消</font></label> &nbsp; ';

		$HTML=$HTML.$changeHTML.'
		<input type="submit"  class="inputbtn" value=" 提交 "><input type="hidden" name="_save" value=3> &nbsp; 
		注：第一次删除只作标识，第二次则清除标识后的数据！
      </td></tr>';
		}

			$HTML=$HTML.'</form>
    </table>
<script>
<!--

'.$autoCheck.'

-->
</script>
';

		if (!empty($this->Conf['listNote']))
		{
			$HTML=$HTML.'
    <TABLE width="100%" cellpadding=0 cellspacing=0 border=0 align="center">
        <tr>
          <td height=2></td></tr>
    </table>
    <TABLE width="100%"  cellspacing=1 cellpadding=5 align="center" class="tbl_body">
        <tr>
          <td height=2 class="tbl_row">'.$this->Conf['listNote'].'</td></tr>
      </TABLE>';
		}
		return $HTML;
	}





	/* NO 1.7
	 * @function downCheck()
	 *
	 * @action:show field
	 * @return string  (HTML code)
	 */
	function downCheck($form)
	{
		$HTML='<B>请选择需要的数据</B>：<BR><form action="'.$this->_pagename.'" method="post">';

		foreach ($this->fieldName as $n => $_Field)
		{
			if ($this->Field[$_Field]['type']=='L')
			{
				continue;
			}

			$k=$n+1;
			$HTML=$HTML.'
      <input type="checkbox" name="_select[]" value="'.$k.'" id="_seleBox'.$k.'"><label for="_seleBox'.$k.'">'.$this->Field[$_Field]['title'].'</label><BR>';
		}
		
		$size=$this->Conf['pageSize']*200;
		if($size<2000) $size=2000;
		$HTML=$HTML.$this->showHidden().' <input type="hidden" name="_save" value=2><input type="submit" value=" 下载 "></form><BR>每次下载'.$size.'条(在第一页时下载第一个'.$size.'条,二页时下载第二个'.$size.'条)';
		return $HTML;
	}

	/* NO 1.8
	 * @function downRecords()
	 *
	 * @action:down records
	 * @return File  (for download)
	 */
	function downRecords()
	{
		$Fs='';
		$xml='';//
		$n=0;
		$inSub=0;
		$head='
   <Row ss:AutoFitHeight="0">';
		foreach($_REQUEST['_select'] as $num)
		{
			$n++;
			$k=$num-1;
			if (!empty($this->fieldName[$k]))
			{
				$name=$this->fieldName[$k];
				$t='t1.';
				if (eregi('S',$_Field['main']))
				{
					$inSub=1;
					$t='t2.';
				}
				if ($this->Field[$name]['type']=='A')
				{
					$xml=$xml.'
   <Column ss:Index="'.$n.'" ss:StyleID="centerAlign" ss:AutoFitWidth="0"/>';
				}
				$Fs=$Fs.','.$t.$name;
				$head=$head.'
      <Cell><Data ss:Type="String">'.$this->Field[$name]['title'].'</Data></Cell>';
			}
		}


		$head=$head.'
   </Row>';
		$xml=$xml.$head;

		if (strlen($Fs)>0)
		{
			$Fs=substr($Fs,1);
		}

		if (!empty($this->tableSub) and $inSub==1)
		{
			$where="".$this->Conf['mainTable']." as t1 left join ".$this->tableSub." as t2 on t1.".$this->Conf['keyField']."=t2.".$this->Conf['keyField']." where 1 ";
		}
		else
		{
			$where="".$this->Conf['mainTable']." as t1 where 1 ";
		}


		# search SQL
		if (!empty($this->_key) and !empty($searchHTML))
		{
			$sField=$this->Conf['searchSet'][$this->sField];
			if (!empty($sField))
			{
				if ($this->Field[$sField]['type']=='S' or $this->Field[$sField]['type']=='F')
				{
					$this->_key   =eregi_replace('(\(|\)|\!|=|<|>|\+)','',$this->_key);
					$where=$where." and t1.$sField like ".$this->DB->getstr('%'.$this->_key.'%');
				}
				else if(($this->Field[$sField]['type']=='N' or $this->Field[$sField]['type']=='A') and eregi('^(>|>=|<|=|<=|!=|<>)',$this->_key,$det))
				{
					$key=str_replace($det[1],'',$this->_key);
					$key=intval($key);
					if($det[1]=='!=') $det[1]=='<>';
					$where=$where." and t1.$sField ".$det[1].$key;
				}
				else if($this->Field[$sField]['type']=='N' or $this->Field[$sField]['type']=='A')
				{
					$this->_key=intval($this->_key);
					$where=$where." and t1.$sField = ".$this->_key;
				}
			}
		}

		#type option
		$_type=trim(getpost('_type'));
		if (isset($this->Conf['sortSet']) and count($this->Conf['sortSet'])>0)
		{
			//foreach ($this->Conf['sortSet'] as $key => $valArr)
			$key=$this->Conf['sortSet'][0];
			$valArr=$this->Conf['sortSet'][1];
			$_change=trim($_REQUEST[$key]);

			if ($this->Field[$key]['type']=='N' or $this->Field[$key]['type']=='S' and $this->Field[$key]['max']<31)
			{
				if (strlen($_type)<1)
				{
					;
				}
				else if ($this->Field[$key]['type']=='N')
				{
					$_type=intval($_type);
					$where=$where." and t1.$key=".$_type;
				}
				else
				{
					$_type=substr($_type,0,30);
					$where=$where." and t1.$key=".$this->DB->getstr($_type);
				}
			}
		}

		# order by
		$orderSet='';
		$_sf=abs($_s)-1;
		if (strlen($_REQUEST['_s'])>0 and isset($this->fieldName[$_sf]))
		{
			if ($_s>0)
			{
				$orderSet=" desc ";
			}
			$orderSet='t1.'.$this->fieldName[$_sf].$orderSet;

			$_sname=$this->fieldName[$_sf];

			if($this->Field[$_sname]['type']=='S' and $this->Field[$_sname]['max']>254 or $this->Field[$_sname]['type']=='F')
			{
				$orderSet='';
			}
		}

		if (!empty($this->Conf['orderSet']))
		{
			$orderSet=" order by ".(!empty($orderSet) ? $orderSet.',' : '')." t1.".$this->Conf['orderSet'];
		}

		

		//############ 从表; 仅显示10000条
		$size=$this->Conf['pageSize']*200;
		if($size<2000) $size=2000;
		if($this->page<1) $this->page=1;
		$startnum=($this->page-1)*$size;
		$result=$this->DB->query("SELECT $Fs FROM ".$where." $orderSet LIMIT $startnum,$size");
		$r=1;
		$colsArr=explode(',',$Fs);
		$result=$this->DB->fetch_array($result);
		foreach($result as $arr)
		{
			$xml=$xml.'
   <Row ss:AutoFitHeight="0">';
			foreach($colsArr as $_Field)
			{
				$_Field=str_replace("t1.",'',$_Field);
				$_Field=str_replace("t2.",'',$_Field);
				$Type='String';
				if ($this->Field[$_Field]['type']=='N' or $this->Field[$_Field]['type']=='A')
				{
					$Type='Number';
				}
				$value=$arr[$_Field];
				$value=str_replace("\r","",$value);
				$value=str_replace("\n","",$value);
				$value=str_replace("\t","",$value);
				$xml=$xml.'
      <Cell><Data ss:Type="'.$Type.'">'.get_html($value).'</Data></Cell>';
			}
			reset($colsArr);
			$xml=$xml.'
   </Row>';
			$r++;
		}

		//输出XLS格式(以XML方式输出)
		$name='report';
		if (!empty($this->Conf['reportName']))
		{
			$name=$this->Conf['reportName'];
		}
		$this->printXML($name.date('_Ymd').'_'.$this->page.'.xls',$xml,$r,$n);
		exit;
	}


















############################################################################
#
#                         Addon functions
#
############################################################################


	/* NO 2.1
	 * @function encodeStr
	 *
	 * @action:encode string
	 * @param string  $str
	 * @return string
	 */
	function encodeStr($str)
	{
		$retStr=$this->xorStr($str);
		$retStr=base64_encode($retStr);
		$retStr=str_replace('=','',$retStr);
		return $retStr;
	}


	/* NO 2.2
	 * @function decodeStr
	 *
	 * @action:decode string
	 * @param string  $str
	 * @return string
	 */
	function decodeStr($str)
	{
		$str=eregi_replace('[^a-z0-9/=]','',$str);
		$str=base64_decode($str);
		$retStr=$this->xorStr($str);
		return $retStr;
	}


	/* NO 2.3
	 * @function xorStr
	 *
	 * @action:XOR string
	 * @param string  $str
	 * @return string
	 */
	function xorStr($str)
	{
		$key='K'.SITE_KEYSTR;
		$strLen=strlen($str);
		$keyLen=strlen($key);

		$retStr='';
		for ($i=0; $i < $strLen; $i++)
		{
			$k = $i % $keyLen;
			$retStr=$retStr.($str[$i] ^ $key[$k]);
		}
		return $retStr;
	}


	function str_hidden($str)
	{
		$html='';
		$tmp=explode("&",$str);
		foreach($tmp as $items)
		{
			if (strlen($items)<3 or !eregi('=',$items))
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

	/* NO 2.4
	 * @function showHidden
	 *
	 * @action:show hidden form
	 * @param bool  $onlyUser
	 * @return string
	 */
	function showHidden($onlyUser=0)
	{
		if ($onlyUser==1 and strlen($this->Conf['addURL'])<3)
		{
			return '';
		}

		$HTML='

		<!-- **************  Hidden input  ************* -->';
		if ($onlyUser==0)
		{
			$HTML=$HTML.'
		<input type="hidden" name="_s" value="'.intval($_REQUEST['_s']).'">
		<input type="hidden" name="_edit" value="'.$this->_edit.'">
		<input type="hidden" name="_page" value="'.$this->page.'">';
			if (strlen($this->_key)>0)
			{
				$HTML=$HTML.'
		<input type="hidden" name="_k" value="'.$this->_key.'">
		<input type="hidden" name="_f" value="'.$this->sFeild.'">';
			}
			if (strlen($_REQUEST['_s'])>0)
			{
				$HTML=$HTML.'
		<input type="hidden" name="_s" value="'.intval($_REQUEST['_s']).'">';
				
			}
			if (strlen($_REQUEST['_type'])>0)
			{
				$HTML=$HTML.'
		<input type="hidden" name="_type" value="'.intval(getpost('_type')).'">';
				
			}
		}

		if (strlen($this->Conf['addURL'])>0)
		{
			$result=explode('&',$this->Conf['addURL']);
			foreach ($result as $string)
			{
				if (strlen($string)>3)
				{
					$arr=split('=',$string,2);
					if (!empty($arr[0]) and !empty($arr[1]))
					{
						$HTML=$HTML.'
		<input type="hidden" name="'.$arr[0].'" value="'.$arr[1].'">';
					}
				}
			}
			
		}
		$HTML=$HTML.'
		<!-- ***************  End Hidden  ************** -->

';
		return $HTML;
	}




	/* NO 2.5
	 * print XML file
	 *
	 * @param  String $filename: save file name
	 * @param  String $body: save file cont
	 * @param  Number $rownum: record rows number
	 * @param  Number $colsnum: record cols number
	 * @param  String $author: author
	 * @return Fix (print)
	 * 文件下载到本地后必须转换成UTF-8格式后才能使用 Excel2003打开
	 * 需要增加自动格式转换功能
	 */
	function printXML($filename,$body,$rownum,$colsnum,$author='PHP')
	{
		header("Context-Type:application/octetstream");
		header("Content-Disposition:attachment;filename=".$filename."");
		header('Pragma: no-cache');
		header('Expires: 0');
		echo '<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <LastAuthor>'.$author.'</LastAuthor>
  <Created>'.date('Y-m-d').'T'.date('H:i:s').'Z</Created>
  <LastSaved>'.date('Y-m-d').'T'.date('H:i:s').'Z</LastSaved>
  <Version>11.5606</Version>
 </DocumentProperties>
 <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
  <WindowHeight>9585</WindowHeight>
  <WindowWidth>18210</WindowWidth>
  <WindowTopX>480</WindowTopX>
  <WindowTopY>105</WindowTopY>
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="宋体" x:CharSet="134" ss:Size="12"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="centerAlign">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="report">
  <Table ss:ExpandedColumnCount="'.$colsnum.'" ss:ExpandedRowCount="'.$rownum.'" x:FullColumns="1"
   x:FullRows="1" ss:DefaultColumnWidth="80" ss:DefaultRowHeight="14.25">';
		echo $body;
		echo '
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <Unsynced/>
   <Selected/>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>';

		exit;
	}












/******************   _Editor is END  ********************/

} //END class

}//END __PHP__EDITOR__
?>