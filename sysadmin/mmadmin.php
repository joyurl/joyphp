<?PHP
/********************************************************

	Mini Mysql Admin Version 2.0
	==================================
    + 2018-5-11 session_write_close();
	+ 2018-2-8  执行时间改为ms,增加数据解析Display时间显示
	+ 2018-1-30 增加数据库连接时间显示
	+ 2017-7-5 增加查询时间
	+ 2017-6-7  BUG导出结构换行被清除
	+ 2017-3-21   +加问题
	+ 2015-07-30  回车问题
	+ 2006-10-09  自动显示用户被授权的数据库
	+ 2006-10-06  Version 2.0 程序重写
    
SET character_set_client = utf8 用来设置客户端送给MySQL服务器的数据的 字符集 
SET character_set_results = utf8 服务器返回查询结果时使用的字符集 
SET character_set_connection = utf8 MySQL 服务器 把客户端传来的数据，从character_set_client字符集转换成character_set_connection字符集 

character_set_client：          MySQL服务器, 客户端的编码集
character_set_connection：传输给MySQL服务器的时候的编码集
character_set_results：       期望MySQL返回的结果的编码集
*********************************************************/


//-----------  config  --------------

header("P3P: CP=CAO PSA OUR");
define('MMA_PAGENAME','mmadmin.php'); //page 


#使用session更安全(2014-4-29)
session_start();


//
error_reporting(~E_ALL & ~E_NOTICE);
@set_time_limit(1500);


$sql    =getPost('sql');
$sqlold =getPost('sqlold');
$page   =getPost('page',1);
$full   =getPost('full',1);
$psize  =getPost('psize',1);
$godo   =getPost('godo');
$ordSet =getPost('ordSet');
$action =getPost('action');
$db_name=getPost('db_name');
$action =trim($action);
$sql    =trim($sql);

$lang   =getPost('lang');
$_charset=getPost('_charset');
if(!empty($lang) ){
    $lang=strtolower($lang);
    if(in_array($lang,Array('gbk','utf-8','big5','iso-8859-1'))){
        header("Content-type: text/html; charset=".strtolower($lang));
    }
    else {
        $lang='';
    }
}
if(empty($lang)){
    header("Content-type: text/html; charset=GBK");
}

$_mma_charset=$_charset;
if($_mma_charset=='lang'){
    $_mma_charset=str_replace('-','',strtolower($lang));
    if($_mma_charset=='iso88591') $_mma_charset='latin1';
}
else if($_charset!='latin1'){
    $_mma_charset='latin1';
}
//echo $_mma_charset;
define('MMA_charset',$_mma_charset); //default charset

$DB_MMA=new DB_MMA;
$db_conf=array();



$Msg='';
//login
$SS=$_SESSION;
if (empty($SS['_db_user']))
{
	if (!empty($_POST['_db_host']) and !empty($_POST['_db_user']))
	{
		$db_conf=$_POST;
		$res=$DB_MMA->conn($db_conf);
		if (!$res)
		{
			$_SESSION['_db_host']='';
			$_SESSION['_db_user']='';
			$_SESSION['_db_password']='';
			$Msg=mysql_error();
		}
		else
		{
			$_SESSION['_db_host']=$_POST['_db_host'];
			$_SESSION['_db_user']=$_POST['_db_user'];
			$_SESSION['_db_password']=$_POST['_db_password'];
			header('location:'.MMA_PAGENAME);
			exit;
		}
	}
	else if (!empty($_POST['login']))
	{
		$Msg='Missing value in the input!';
	}
	$html='
	<TABLE width="100%" height="100%" cellpadding=0 cellspacing=0 border=0>
	  <tr>
	    <td height="100%" align=center>

			<TABLE width="320" cellpadding=0 cellspacing=0 border=0>
			  <tr>
			    <td height=30 align=center style="font-size: large;"><B>MMAdmin 2.0</B></td>
			  </tr>
				<FORM NAME="editForm" METHOD="post" ACTION="'.MMA_PAGENAME.'">
			  <tr>
			    <td height=30 align=center><font color=red>'.$Msg.'</font></td>
			  </tr>
			  <tr>
			    <td height=30 align=center>Host: <INPUT TYPE="text" NAME="_db_host" VALUE="localhost" SIZE="20" MAXLENGTH="20"></td>
			  </tr>
			  <tr>
			    <td height=30 align=center>User: <INPUT TYPE="text" NAME="_db_user" VALUE="" SIZE="20" MAXLENGTH="20"></td>
			  </tr>
			  <tr>
			    <td height=30 align=center>Pass: <INPUT TYPE="password" NAME="_db_password" VALUE="" SIZE="20" MAXLENGTH="20"></td>
			  </tr>
			  <tr>
			    <td height=30 align=center>
				<INPUT TYPE="submit" name="login" VALUE=" Login ">
				</td>
			  </tr></form>
			</TABLE>

		</td>
	  </tr>
	</TABLE>';
}

//admin show
else
{
	$t0= mm_get_mtime();

	$res=$DB_MMA->conn($SS);
	if (!$res)
	{
		$_SESSION['_db_host']='';
		$_SESSION['_db_user']='';
		$_SESSION['_db_password']='';
		header('location:'.MMA_PAGENAME);
		exit;
	}
	$t01=mm_get_mtime();
	$t02= mm_show_mtime($t01-$t0);
	$db_name=$db_name;

//::::::::::::: BEGAIN ::::::::::::




	if($godo!="tbl" and $godo!="sql" and $godo!="backup" and $godo!="logout")
		$godo="tbl";
	$addlink="_charset=$_charset&lang=$lang&db_name=$db_name";

	$result=mysql_list_dbs($DB_MMA->DB);

	$dblist='';
	$option='';
	if(!isset($_SESSION['_db_tb_sum_'])) $_SESSION['_db_tb_sum_']=Array();
	while ($arr=mysql_fetch_array($result,1))
	{
		$dbn=$arr['Database'];
		if (empty($dblist))
		{
			$dblist=$dbn;
		}
		//2018-2-7 增加session缓存
		if(isset($_SESSION['_db_tb_sum_'][$dbn])){
			$option=$option.'
			<OPTION VALUE="'.$dbn.'"'.($db_name==$dbn ? ' selected' : '').'>'.$dbn.' ('.(0+$_SESSION['_db_tb_sum_'][$dbn]).')</OPTION>';
			continue;
		}

		if ($res=@mysql_list_tables($dbn))
		{
			$num=0+@mysql_num_rows($res);
			$_SESSION['_db_tb_sum_'][$dbn]=$num;
			$option=$option.'
			<OPTION VALUE="'.$dbn.'"'.($db_name==$dbn ? ' selected' : '').'>'.$dbn.' ('.$num.')</OPTION>';
		}
	}
	if (empty($db_name))
	{
		$db_name=$dblist;
	}

	mysql_select_db($db_name,$DB_MMA->DB);


	$html='
<TABLE cellpadding=5 cellspacing=0 border=0>
  <tr>
    <td>
	<B>+Database</B> <SELECT NAME="db_name" ONCHANGE="location.href=\''.MMA_PAGENAME.'?_charset='.$_charset.'&lang='.$lang.'&db_name=\'+this.value">
	'.$option.'
	</select> 
    &nbsp; 
	<B>+Charset</B> <SELECT NAME="_charset" id="_charset" ONCHANGE="location.href=\''.MMA_PAGENAME.'?_charset=\'+this.value+\'&lang=\'+document.getElementById(\'lang\').value+\'&db_name='.$db_name.'\'">
	<OPTION VALUE="latin1">-- latin1 --</OPTION>
	<OPTION VALUE="lang"'.(strtolower($_charset)=='lang' ? ' selected' :'').'>use Language</OPTION>
	</SELECT>
    &nbsp; 
	<B>+Language</B> <SELECT NAME="lang" id="lang" ONCHANGE="location.href=\''.MMA_PAGENAME.'?_charset=\'+document.getElementById(\'_charset\').value+\'&lang=\'+this.value+\'&db_name='.$db_name.'\'">
	<OPTION VALUE="">-- Default --</OPTION>
	<OPTION VALUE="BIG5"'.(strtolower($lang)=='big5' ? ' selected' :'').'>BIG5</OPTION>
	<OPTION VALUE="GBK"'.(strtolower($lang)=='gbk' ? ' selected' :'').'>GBK</OPTION>
	<OPTION VALUE="UTF-8"'.(strtolower($lang)=='utf-8' ? ' selected' :'').'>UTF-8</OPTION>
	<OPTION VALUE="iso-8859-1"'.(strtolower($lang)=='iso-8859-1' ? ' selected' :'').'>ISO-8859-1(En)</OPTION>
	</SELECT> &nbsp;

	<A HREF="'.MMA_PAGENAME.'?_charset='.$_charset.'&lang='.$lang.'&db_name='.$db_name.'"><B>[Home]</B></A> &nbsp;
	<A HREF="'.MMA_PAGENAME.'?godo=logout" onclick="return confirm(\'Are you really want to Logout?\')"><B>[Log Out]</B></A>
	</td>
  </tr>
</TABLE>
';

  //-------------- show tables -----------------
  if ($godo=="logout")
  {

		$_SESSION['_db_host']='';
		$_SESSION['_db_user']='';
		$_SESSION['_db_password']='';
	header('location:'.MMA_PAGENAME);
	exit;
	
  }

  //-------------------------------------------------------------------------------------
  else if($godo=="tbl")
  {

	if (!empty($sql))
	{
		$sql=urldecode($sql);
		if (preg_match('/^(SELECT |SHOW |CHECK TABLE |REPAIR TABLE )/i',$sql))
		{
			header('location:'.MMA_PAGENAME.'?'.$addlink.'&godo=sql&tbl='.$tbl.'&sql='.urlencode($sql));
			exit;
		}
		else
		{
            /*
            if(preg_match('/^(SELECT|update|insert)/i',$sql) and defined('MMA_charset') and strlen(MMA_charset)>0){
			    @mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".MMA_charset.", character_set_client=binary", $this->DB);
            }
            else if(preg_match('/^show/i',$sql) and !empty($lang)){
                $langs=strtolower($lang);
			    @mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".$langs.", character_set_client=binary", $this->DB);
            }
            if(!empty($lang)){
                $langs=strtolower($lang);
			    @mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".MMA_charset.", character_set_client=binary", $DB_MMA->DB);
            }
            */
			$DB_MMA->query($sql);
			header('location:'.MMA_PAGENAME.'?'.$addlink);
			exit;
		}
	}
	$up=$_FILES['upfile'];
	if (!empty($up['tmp_name']) and $up['tmp_name']!="none")
	{
		
		if (file_exists($up['tmp_name']) and preg_match('/(\.sql|\.txt)$/i',$up['name']))
		{
			unset($_SESSION['_db_tb_sum_'][$db_name]);
            session_write_close();
			$DB_MMA->upInFile($up['tmp_name']);
			header('location:'.MMA_PAGENAME.'?'.$addlink);
			exit;
		}
		else
		{
            session_write_close();
			$Msg='File type error!';
		}
	}

	$html=$html."<font color=red>$Msg</font>
	<FORM NAME=\"editForm\" METHOD=\"post\" ACTION=\"".MMA_PAGENAME."\" target=_blank onsubmit=\"return checkBox(editForm,'seleTbl[]','Table');\">
	<font style='font-size: large;'><B>Database ".$db_name."</B></font>
    <table cellspacing=1 cellpadding=3>
    <tr bgcolor='#CCCCCC'><td></td><td><B>Table</B></td>
	<td><B>Browse</B></td>
	<td><B>Rows</B></td>
	<td><B>Size</B></td><td><B>Free</B></td>
	<td><B>Optimize</B></td>
	<td><B>Check</B></td>
	<td><B>Repair</B></td>
	<td><B>Delete</B></td>
	<td><B>Empty</B></td>
	<td><B>Drop</B></td>
	<td><B>Create</B></td>
	<td><B>Keys</B></td>
	<td><B>Fields</B></td>
	</tr>\n";

	$bg="#E6E6E6";
	$num=0;
	$row=0;
	$size=0;
	$free=0;
	$tables= $DB_MMA->query("SHOW TABLE STATUS FROM `".$db_name.'`');

	$i=0;
    while($rows=mysql_fetch_array($tables))
	{

		$row=$row+$rows['Rows'];
		$tsize=round(($rows['Data_length']+$rows['Index_length']+$rows['Data_free'])/1024);
		$size=$size+$rows['Data_length']+$rows['Index_length']+$rows['Data_free'];
		$free=$free+$rows['Data_free'];
		$tblName=$rows['Name'];

		if($rows['Data_free']==0)	$OPTIMIZE="Optimize";
		else $OPTIMIZE="<a href='".MMA_PAGENAME."?$addlink&godo=tbl&tbl=$tblName&sql=OPTIMIZE+TABLE+`$tblName`' onclick=\"return confirm('Are you really want to : \\nOPTIMIZE TABLE `$tblName`')\">Optimize</a>";
		$i++;
		
		$html=$html."<tr bgcolor='$bg'>
		<td align=right>$i</td>
		<td><INPUT TYPE=\"checkbox\" NAME=\"seleTbl[]\" VALUE=\"$tblName\" id='sele_box".$i."'><label for='sele_box".$i."'><B>".$rows['Name']."</B></label></td>
		<td><a href='".MMA_PAGENAME."?$addlink&godo=sql&tbl=$tblName&sql=SELECT+*+FROM+`$tblName`'><B>Browse</B></a></td>
		<td align=right>".$rows['Rows']."</td><td align=right>".$tsize."KB</td><td  align=right>".round($rows['Data_free']/1024)."KB</td>
		<td>$OPTIMIZE</td>
		<td><a href='javascript:;' onclick=\"document.all.runForm.sql.value='CHECK TABLE `$tblName`';\" title=\"Run in 'Run SQL query' box\">Check</a></td>
		<td><a href='javascript:;' onclick=\"document.all.runForm.sql.value='REPAIR TABLE `$tblName`';\" title=\"Run in 'Run SQL query' box\">Repair</a></td>
		<td><a href='javascript:;' onclick=\"document.all.runForm.sql.value='DELETE FROM `$tblName`';\" title=\"Run in 'Run SQL query' box\">Delete</a></td>
		<td><a href='javascript:;' onclick=\"document.all.runForm.sql.value='TRUNCATE TABLE `$tblName`';\" title=\"Run in 'Run SQL query' box\">Empty</a></td>
		<td><a href='javascript:;' onclick=\"document.all.runForm.sql.value='DROP TABLE IF EXISTS `$tblName`';\" title=\"Run in 'Run SQL query' box\">Drop</a></td>
		<td><a href='".MMA_PAGENAME."?$addlink&godo=sql&tbl=$tblName&sql=SHOW+CREATE+TABLE+`$tblName`&full=1'>Create</a></td>
		<td><a href='".MMA_PAGENAME."?$addlink&godo=sql&tbl=$tblName&sql=SHOW+KEYS+FROM+`$tblName`'>Keys</a></td>
		<td><a href='".MMA_PAGENAME."?$addlink&godo=sql&tbl=$tblName&sql=SHOW+FIELDS+FROM+`$tblName`'><B>Fields</B></a></td>
		</tr>\n";

		if($bg=="#E6E6E6") $bg="#DDDDDD"; else $bg="#E6E6E6";
		$num++;
    }

	$size=floor($size/100)/10;
    $html=$html."
    <tr	bgcolor='cccccc'><td></td><td>
	<INPUT TYPE=\"hidden\" NAME=\"seleTbl[]\" value='' id='sele_box'>
	<INPUT TYPE=\"checkbox\" id='sele_box_all' onclick=\"selectAll(this.form,'seleTbl[]',this.checked);\"><label for='sele_box_all'><B>Check All</B></label>
	</td><td colspan=4 align=center><B>Data</B> (".$size."KB)</td>
	<td colspan=3 align=center><B>Maintenance</B></td>
	<td colspan=3 align=center><B>Action</B></td>
	<td colspan=3 align=center><B>Structure</B></td>
	</tr>
	</table>".'
	select where :<input type="text" name="_sql" value="" size=50><br />
	<B>Export selected save as file:</B>
	<input type="hidden" name="_charset" value="'.$_charset.'">
	<INPUT TYPE="hidden" NAME="lang" VALUE="'.$lang.'">
	<INPUT TYPE="hidden" NAME="db_name" VALUE="'.$db_name.'">
	<INPUT TYPE="hidden" NAME="godo" VALUE="backup">
	<INPUT TYPE="submit" name="action" VALUE="Structure & Data">
	<INPUT TYPE="submit" name="action" VALUE="Structure">
	<INPUT TYPE="submit" name="action" VALUE=" Data ">
	</form><BR><BR>';


	$html=$html."
	<form action='".MMA_PAGENAME."' method='post' name=\"runForm\" onsubmit=\"return checkChar(this,'sql','SQL',20000,10) && confirm('Do you really want to:\\n\\n'+this.sql.value.substring(0,1000))\">
	<li> Run	SQL query on database $db_name(Only display `select` or `show`)<br>
	<textarea cols=68 rows=8 name='sql'>".StripSlashes($sql)."</textarea><br>
	&nbsp; <input type=\"submit\" value='submit'>
	<input type=\"hidden\" name=\"_charset\" value='$_charset'>
	<input type=\"hidden\" name=\"lang\" value='$lang'>
	<input type=\"hidden\" name=\"goto\" value='tbl'>
	<input type=\"hidden\" name=\"db_name\" value='$db_name'>
	</form><form action='".MMA_PAGENAME."' method='post'  onsubmit=\"return checkChar(this,'upfile','File name',200,1)\"  enctype='multipart/form-data'>
	<input type=\"hidden\" name=\"_charset\" value='$_charset'>
	<input type=\"hidden\" name=\"lang\" value='$lang'>
	<input type=\"hidden\" name=\"goto\" value='tbl'>
	<input type=\"hidden\" name=\"db_name\" value='$db_name'>
	<li> Location of the file <input type=file name='upfile'> 
	<input type=submit value='upload'></form>

	";
	 $html=$html."<hr width='100%' align='left'><li><b>MMAdmin version 2.0(Mini Mysql Admin)</b>";
  }
  //-------------------------------------------------------------------------------------
  //----------backup-------------
  else if($godo=="backup")
  {
    session_write_close();
	$seleTbl=getPost('seleTbl');
	$_sql=getPost('_sql');
	$DB_MMA->dumpOutFile($db_name,$seleTbl,$action,$_sql);
  }
  //-------------------------------------------------------------------------------------
  //----------list query--------------
  else if($godo=="sql")
  {
	if (empty($sql))
	{
		header('location:'.MMA_PAGENAME.'?'.$addlink);
		exit;
	}

    session_write_close();
	$sql=trim($sql);
	$sqlold=urldecode($sqlold);
	$showsql=$sql;
     /*
            if(!empty($lang)){
                $langs=str_replace('-','',strtolower($lang));
                $charset="SET character_set_connection=".MMA_charset.", character_set_results=".MMA_charset.", character_set_client=binary";
                //echo $charset;
			   //mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".$langs.", character_set_client=".MMA_charset, $DB_MMA->DB);binary
			    @mysql_query($charset, $DB_MMA->DB);
               //echo mysql_error();
            }
      */
	if (!preg_match('/^(SELECT |SHOW |CHECK TABLE |REPAIR TABLE |explain )/i',$sql) and $sql!="_")
	{
		$res=$DB_MMA->query($sql);

		if (!$res)
		{
			$Msg='SQL Error: '.$sql.'<BR>'.mysql_error();
		}
		else if(preg_match('/^(drop table|create table/i',$sql)){
			unset($_SESSION['_db_tb_sum_'][$db_name]);
		}
		$showsql=$sql;
		$sql=$sqlold;
	}

	if(empty($page)) $page=1;
	$pagesize=$psize;
	if($pagesize<5 or $pagesize>200) $pagesize=30;
	$psize=$pagesize;

	if($sql=="_") $sql=$sqlold;

	$query_sql=$sql;
	
	if(!empty($ordSet)){
		$ordSet=urldecode($ordSet);
		$query_sql=$query_sql.' '.$ordSet;
	}
	$count_rows=0;
	if (preg_match('/^SELECT /i',$sql))
	{
		if (!preg_match('/ LIMIT /i',$sql))
		{
			$count_rows=1;
			$query_sql=$query_sql." LIMIT ".(($page-1)*$pagesize).",".$pagesize;
		}
	}
            /*
            $langs=str_replace('-','',strtolower($lang));
            if(preg_match('/^(SELECT|update|insert)/i',$sql) and defined('MMA_charset') and strlen(MMA_charset)>0){
                //echo MMA_charset."";
                @mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".MMA_charset.", character_set_client=binary", $DB_MMA->DB);
            }
            else if(preg_match('/^show/i',$sql) and !empty($lang)){
			    @mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".$langs.", character_set_client=binary", $DB_MMA->DB);
            }
            */
            if(preg_match('/^show create table /i',$sql) and !empty($lang)){
                $langs=str_replace('-','',strtolower($lang));
			    @mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".$langs.", character_set_client=binary", $DB_MMA->DB);
            }

	//echo $query_sql,";",$sql;exit;
	$t1= mm_get_mtime();
	
	$result=$DB_MMA->query($query_sql);
	$t2 = mm_get_mtime();
	if($t2<$t1) $t=10000+$t2-$t1;
	else $t=$t2-$t1;
	$t=mm_show_mtime($t);


	$html=$html."<font style='font-size: large;'><B>Tables $db_name.".$tbl."</B></font>
	<hr size=1>";
	if($result)
	{
		if ($count_rows==1)
		{
			$sql_cnt=eregi_replace('SELECT (.+) FROM ','SELECT count(*) as num FROM',$sql);
			//echo $sql_cnt;
			$res=$DB_MMA->query($sql_cnt,1);
			$res_num=$res['num'];
		}
		else
		{
			$res_num=mysql_num_rows($result);
		}
		$page_num=ceil($res_num/$pagesize);
		$page=intval($page);
		if($page>$page_num or $page<1) $page=1;
		$addlink=$addlink."&godo=$godo&tbl=$tbl&psize=$psize&full=$full&ordSet=$ordSet&sql=" .urlencode($sql);
		$pagelist="";
		if (preg_match('/^(SELECT|SHOW) /i',$sql))
		{
			$pagelist="RESULT:$res_num rows (query={$t},connect={$t02}), Show <input type=text name='psize' value='$psize' size='2'>rows
			on page<input type='text' name='page' value='$page'	size='2'>
			Of $page_num</b>&nbsp; <input type=submit value='show'>";

			if($page>1) $pagelist=$pagelist."&nbsp;	<input type=button value='&lt; Pre' onclick=\"window.open('".MMA_PAGENAME."?$addlink&page=".($page-1)."','_self')\">";
			else $pagelist=$pagelist."&nbsp;	<input type=button value=' &lt; Pre '>";

			if($page<$page_num)
				$pagelist=$pagelist."&nbsp;	<input type=button value='Next &gt;' onclick=\"window.open('".MMA_PAGENAME."?$addlink&page=".($page+1)."','_self')\">";
			else
				$pagelist=$pagelist."&nbsp;	<input type=button value=' Next &gt;'>";

			if($full==1) $pagelist=$pagelist."&nbsp;	<input type=button value='Part' onclick=\"window.open('".MMA_PAGENAME."?$addlink&page=$page&full=0','_self')\">";
			else $pagelist=$pagelist."&nbsp;	<input type=button value='Full' onclick=\"window.open('".MMA_PAGENAME."?$addlink&page=$page&full=1','_self')\">";

		}

		$html=$html."<font color=red>$Msg</font>
	    <form action='".MMA_PAGENAME."' method='get' onnsubmit=\"return checkInt(this,'psize','rows',100,1) && checkInt(this,'page','page',$page_num,1)\">

	    <input type=hidden name='_charset' value='$_charset'>
	    <input type=hidden name=lang value='$lang'>
	    <input type=hidden name=db_name value='$db_name'>
	    <input type=hidden name=godo value='$godo'>
	    <input type=hidden name=tbl value='$tbl'>
	    <input type=hidden name=full value='$full'>
	    <input type=hidden name=ordSet value='$ordSet'>
	    <input type=hidden name=sql value='_'>
	    <input type=hidden name=sqlold value='".urlencode($sql)."'>
	    <b>SQL-query:</b>$sql<br>
	    <b>$pagelist
	    </form>
	    <table cellspacing=1 cellpadding=4>
	<tr bgcolor='cccccc'>\n";

		$fields_cnt = mysql_num_fields($result);
		$typetag=Array();
		for($j = 0; (1 and $j < $fields_cnt); $j++)
		{
			$field=mysql_field_name($result, $j);
			$ftype=mysql_field_type($result, $j);
			//$fflag=mysql_field_flags($result, $j);
			//echo "$field=$ftype; $fflag<br>";
			if(preg_match("/blob/i",$ftype) and !preg_match("/^ *SHOW FIELDS/i",$sql))
				$typetag[$j]=2;
			else if(preg_match("/int|real/i",$ftype) and !preg_match("/^ *SHOW FIELDS/i",$sql)){
				$typetag[$j]=1;
			}
			else{
				$typetag[$j]=0;
			}

			if(preg_match('/^select /i',$sql)){
				$ord='';
				if (preg_match('/ desc/',$ordSet) or empty($ordSet))
				{
					$ord="ORDER+BY+`$field`";
				}
				else
				{
					$ord="ORDER+BY+`$field`+desc";
				}
				$desc='';
				if (preg_match("/`$field`/i",$ordSet))
				{
					if (preg_match('/ desc/',$ordSet))
					{
						$desc='[D]';
					}
					else
					{
						$desc='[A]';
					}
				}
				if (preg_match('/primary_key/i',$ftype))
				{
					$field="<U>$field</U>";
				}
				else if (preg_match('/unique_key/i',$ftype))
				{
					$field="<U><I>$field</I></U>";
				}
				$html=$html."<td><b><a href='".MMA_PAGENAME."?$addlink&psize=$psize&page=$page&ordSet=$ord'>$field</a> $desc</b></td>\n";
			}
			else 
				$html=$html."<td><b>$field</b></td>\n";

		}
		$html=$html."</tr>\n";
		//exit;
		$bg0="#E6E6E6";
		$bg="#DDDDDD";
		$isTable=0;
		if(preg_match('/^SHOW FIELDS FROM/i',$sql)){
			$isTable=1;
		}

		while($row=mysql_fetch_array($result))
		{

			if($bg==$bg0) 
				$bg="#DDDDDD";
			else $bg=$bg0;

			$html.="\r\n<tr bgcolor='$bg'>\n";
			$tmp='';
			for($j = 0;($j < $fields_cnt); $j++)
			{
				if($typetag[$j]==2)
					$str_row="[binary]".$row[$j];
				else $str_row=$row[$j];

				if($full!=1 and strlen($str_row)>72)
					$str_row=substr($str_row,0,872)."...";

				$str_row=$DB_MMA->gethtml($str_row,$lang);
				$str_row=nl2br($str_row);
				$str_row=str_replace("  ","&nbsp; ",$str_row);
				
				if ($isTable and $j==0)
				{
					$adds=$row[$j+1];
					if ($row[$j+2]!='YES')
					{
						$adds=$adds.' NOT NULL ';
					}
					if (strlen($row[$j+4])>0)
					{
						$adds=$adds." DEFAULT '".$row[$j+4]."'";
					}
					$adds=$adds.$row[$j+5];
					$adds=str_replace('\'','\\\'',$adds);
					$adds=str_replace('"','\\"',$adds);
					$adds=$DB_MMA->gethtml($adds,$lang);
					$tmp.="	<td><A HREF=\"javascript:;\" onclick=\"document.all.runsql.sql.value='ALTER TABLE `$tbl` CHANGE `$str_row` `$str_row` ".$adds."'\" title=\"Edit field in SQL\">$str_row</A></td>\n";
				}
				else
				{
					$tmp.="	<td".($typetag[$j]==1 ? ' align=right' : '').">".$str_row."</td>\n";
				}
			}
			$html.=$tmp;
			$html.="</tr>";

		}

		$html=$html;

	$t3 = mm_get_mtime();
	if($t3<$t0) $t=10000+$t3-$t0;
	else $t=$t3-$t0;

	$t=mm_show_mtime($t);
	$t4=$t3-$t2;
	$t4=mm_show_mtime($t4);

		$html=$html."</table>
		Total {$t}  (Display=$t4)<br>
	<form action='".MMA_PAGENAME."' method='post' onsubmit=\"return checkChar(this,'sql','SQL querys',10000,10) && confirm('Do you really want to:\\n\\n'+this.sql.value.substring(0,1000))\" name='runsql'>Run SQL query on database $db_name(Only display `select` or `show`)<br>
	    <input type=hidden name='_charset' value='$_charset'>
	    <input type=hidden name=lang value='$lang'>
	    <input type=hidden name=db_name value='$db_name'>
	    <input type=hidden name=godo value='$godo'>
	    <input type=hidden name=tbl value='$tbl'>
	    <input type=hidden name=full value='$full'>
	    <input type=hidden name=psize value='$psize'>
	    <input type=hidden name=ordSet value='$ordSet'>
	    <input type=hidden name=sqlold value='".urlencode($sql)."'>
		<textarea cols=68 rows=8 name=sql>".urldecode($showsql)."</textarea><br>
	<input type=submit value='submit'>
	</form>";

    }
	else
	{
		$html=$html.mysql_error().'<BR><BR><A HREF="'.MMA_PAGENAME.'?'.$addlink.'"><B>&lt;&lt;Back</B></A>';
	}

  }

}



function mm_get_mtime()
{
	@list($usec,$sec)=explode(" ",microtime()); 
	$sec=$sec%10000;
	return number_format($sec + $usec,5,'.','');
}
function mm_show_mtime($t)
{
	return number_format($t*1000,2,'.','').'ms';
}

/*
 * Class 
 *
 */
class DB_MMA
{

	var $DB;
	var $conf;

	function conn($db_conf)
	{

		$this->conf=$db_conf;
		$this->DB= @mysql_pconnect($this->conf['_db_host'],$this->conf['_db_user'],$this->conf['_db_password']);
		if (!$this->DB)
		{
			return false;
		}

		if (defined('MMA_charset') and strlen(MMA_charset)>0)
		{
			@mysql_query("SET character_set_connection=".MMA_charset.", character_set_results=".MMA_charset.", character_set_client=binary", $this->DB);

		}
		return true;

	}

    function gethtml($str,$encode='utf-8'){
        if($encode=='gbk') $encode='ISO-8859-1';
        //echo $encode.'';
        return htmlspecialchars($str,ENT_COMPAT,$encode);
    }
	//Auto connect & query
	function query($sql_str,$fetch=0)
	{
		if(!$this->DB)
		{
			$this->conn($this->conf);
		}

		$result=mysql_query($sql_str,$this->DB);
		if (!$result)
		{
			return false;
		}
		if($fetch==1){
			$result=mysql_fetch_array($result,1);
		}
		return($result);
	}

	function upInFile($fileName)
	{
		if (!file_exists($fileName))
		{
			return false;
		}

		$sqlfile= file($fileName);
		//echo count($sqlfile);exit;
		$Msg='';
		$tmp='';
		foreach($sqlfile as $sqls ){
			if(substr($sqls,0,1)=='#') continue;
			if(substr($sqls,0,2)=='--') continue;
			$sqls=trim($sqls);
			if(substr($sqls,-1)==";"){
				$tmp=$tmp.$sqls;
				$ret=$this->query($tmp);
				$tmp="";
				if(!$ret) $Msg=$Msg.$sqls."<br />".mysql_error();
				usleep(10000);
			}
			else{
				$tmp=$tmp.$sqls;
				usleep(1000);
			}

		}
        if(empty($Msg)){
            echo '上传成功';
        }
        else{
            echo $Msg;
        }
		exit;

		/*
		$sqlfile= implode("",file($fileName));
		$sqlfile= $this->remove_remarks($sqlfile);
		$sqls	= $this->split_sql_file($sqlfile, ";");
		$pieces_count = count($sqls);
		for	($i = 0; $i < $pieces_count; $i++)
		{
			$this->query($sqls[$i]);
			usleep(10000);
		}
		*/
		return true;
	}

	function dumpOutFile($db_name,$seleTbl,$action="",$_sql='')
	{
		$tmp_dump="\n# MMAdmin version 2.0 \n";

		$search = array("\x0a","\x0d","\x1a"); //\x08\\x09,	not required
		$replace= array("\\n","\\r","\Z");
		//$tables = $this->query("SHOW TABLE STATUS FROM `".$db_name."`");

		$cnt=count($seleTbl);
		for ($i=0; $i<$cnt; $i++)
		{
			$table_name=$seleTbl[$i];
			if (empty($table_name))
			{
				continue;
			}

			$tmp_dump=$tmp_dump."\r\n\r\n# =========================================================\r\n";
			$tmp_dump=$tmp_dump."\r\n#\r\n# Table ".$db_name.".$table_name\r\n#\r\n\r\n";
			if($action!="Data")
			{
				$tmp_dump=$tmp_dump."\r\nDROP TABLE IF EXISTS `$table_name`; \r\n";
				$result = $this->query("SHOW CREATE TABLE `$table_name`");
				$sql_tmp="";
				while($rows=mysql_fetch_array($result))
				{
					$sql_tmp=$sql_tmp.$rows[1];
				}

				$sql_tmp=eregi_replace("UNIQUE KEY Primary","PRIMARY KEY",$sql_tmp);
				$sql_tmp=eregi_replace("KEY Primary","PRIMARY KEY",$sql_tmp);
				$sql_tmp=eregi_replace("\r","",$sql_tmp);
				$sql_tmp=eregi_replace("\n","",$sql_tmp);
				$tmp_dump=$tmp_dump.$sql_tmp.";\r\n";
			}

			if($action!="Structure")
			{
				$tmp_dump=$tmp_dump."\r\n#---------------- Data --------------\r\n\r\n";
				$result=$this->query("SELECT * FROM `$table_name` ".$_sql);
				$fields_cnt = mysql_num_fields($result);
				unset($field_set);
				for($j = 0; $j < $fields_cnt; $j++)
				{
					$field_set[$j]=mysql_field_name($result, $j);
					$type= mysql_field_type($result, $j);
					if (eregi('int',$type) || eregi('(timestamp|float|double|decimal|real)',$type))
					{
						$field_num[$j] = 1;
					}
					else if(eregi('blob',$type))//bin
					{
						$field_num[$j] = 2;
					}
					else {
						$field_num[$j] = 0;
					}
				}

				$fields=implode(",",$field_set);
				$sql_tmp = "INSERT INTO `".$table_name."` (" .	$fields	. ") VALUES ";
				$n=0;
				while($row=mysql_fetch_array($result,MYSQL_NUM)){
					$values=array();
					for ($j=0; $j<$fields_cnt; $j++){
						if (!isset($row[$j]))
							$values[] ="NULL";
						else if($field_num[$j]==1)
							$values[] = $row[$j];
						//else if($field_num[$j]==2)
						//	$values[] = '0x'.bin2hex($row[$j]);
						else if (!empty($row[$j]))
							$values[] = "'".str_replace($search, $replace, addslashes($row[$j])) ."'";
						else
							$values[] = "''";
					}
					$n++;
					$sql_tmp=$sql_tmp.($n%100==1 ? "(" : "\r\n,(").implode(',', $values).")";
					if ($n>0 and $n%100==0)
					{
						$tmp_dump=$tmp_dump.$sql_tmp.";\r\n";
						$sql_tmp = "INSERT INTO `".$table_name."` (" .	$fields	. ") VALUES ";
					}
				}
				$tmp_dump=$tmp_dump.$sql_tmp.";\r\n";
			}
		}

		$dump_name=$db_name;
		if($action=="Structure")
			$dump_name=$dump_name."_tbl";
		else if($action=="Data")
			$dump_name=$dump_name."_data";


		$dump_name=$dump_name."_".date("Ymd").".sql";
		//------- out ------
		header("Context-Type:application/octetstream");
		header("Content-Disposition: attachment; filename=$dump_name");
		header('Pragma: no-cache');
		header('Expires: 0');
		echo $tmp_dump;
		exit;
	}

    function split_sql_file($sql, $delimiter)
    {
	$sql		   = trim($sql);
	$char		   = '';
	$last_char	   = '';
	$ret		   = array();
	$string_start  = '';
	$in_string	   = FALSE;
	$escaped_backslash = FALSE;

	for ($i	= 0; $i	< strlen($sql);	++$i) {
	    $char = $sql[$i];
	    if ($char == $delimiter && !$in_string) {
		$ret[]	   = substr($sql, 0, $i);
		$sql	   = substr($sql, $i + 1);
		$i	   = 0;
		$last_char = '';
	    }

	    if ($in_string) {
		if ($char == '\\') {
		    if ($last_char != '\\') {
			$escaped_backslash = FALSE;
		    } else {
			$escaped_backslash = !$escaped_backslash;
		    }
		}
		if (($char == $string_start)
		    && ($char == '`' ||	!(($last_char == '\\') && !$escaped_backslash))) {
		    $in_string	  = FALSE;
		    $string_start = '';
		}
	    } else {
		if (($char == '"') || ($char ==	'\'') || ($char	== '`')) {
		    $in_string	  = TRUE;
		    $string_start = $char;
		}
	    }
	    $last_char = $char;
	}

	if (!empty($sql)) {
	    $ret[] = $sql;
	}
	return $ret;
    }

    function remove_remarks($sql)
    {
	$i = 0;

	while ($i < strlen($sql)) {
	    if ($sql[$i] == '#'	&& ($i == 0 || $sql[$i-1] == "\n")) {
		$j = 1;
		while ($sql[$i+$j] != "\n") {
		    $j++;
		    if ($j+$i >	strlen($sql)) {
			break;
		    }
		} 
		$sql = substr($sql, 0, $i) . substr($sql, $i+$j);
	    }
	    $i++;
	}

	return $sql;
    }


}


function getPost($fields='',$toint=0){

	if(empty($fields))
		$rval=$_POST;
	else if(isset($_POST[$fields]))
		$rval=$_POST[$fields];
	else if(isset($_GET[$fields]))
		$rval=$_GET[$fields];
	else $rval='';

	if (!empty($fields) and $toint==1)
	{
		return intval($rval);
	}
	else if(!get_magic_quotes_gpc() or empty($rval)){
		return $rval;
	}
	else if(is_array($rval)){
		while(list($key,$val)=each($rval)){
			if(is_array($val)){
				while(list($k,$v)=each($val)){
					$rval[$key][$k]=StripSlashes($v);
				}
				reset($rval[$key]);
			}
			else{
				$rval[$key]=StripSlashes($val);
			}
		}
		reset($rval);
	}
	else{
		$rval=StripSlashes($rval);
	}
	return $rval;
}
?>
<html>
<head>
<title>MMAdmin - Mini MySQL Admin 2.0</title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo  (!empty($lang)? $lang:'GBK');?>">
<style type="text/css">

body       {font-family: Arial,verdana;}
textarea   {font-family: Fixedsys,Arial,verdana;}
input      {font-family: verdana,sans-serif;}
select     {font-family: Arial,sans-serif;}
td	       {font-family: Arial,verdana;}
.font_big  {font-family: Arial,verdana;}

A:link     {text-decoration: none; color: #0000ff}
A:visited  {text-decoration: none; color: #0000ff}
A:hover	   {text-decoration: none; color: #FF0000}

</style>
<script	language="javascript">
function checkInt(theForm,theFieldName,theList,max,min)
{
    var	theField =theForm.elements[theFieldName];
    var	val	 =parseInt(theField.value);
    var	themin = (typeof(min) != 'undefined');
    var	themax = (typeof(max) != 'undefined');
    if(themin && theField.value=="") {
	theField.select();
	alert("Missing value in the ["+theList+"]!");
	theField.focus();
	return false;
    }
    else if (theField.value && isNaN(val)) {
	theField.select();
	alert("["+theList+"] is not a number!");
	theField.focus();
	return false;
    }
    
    if (themin && val <	min) {
	theField.select();
	alert("["+theList+"] is not less than "+min+"！");
	theField.focus();
	return false;
    }

    if (themax && val >	max) {
	theField.select();
	alert("["+theList+"] is not more than "+max+"！");
	theField.focus();
	return false;
    }
    
    if(theField.value!=""){
	theField.value = val;
    }
    return true;
} 


function checkChar(theForm,theFieldName,theList,max,min)
{
    var	theField =theForm.elements[theFieldName];
    var	themin = (typeof(min) != 'undefined');
    var	themax = (typeof(max) != 'undefined');

    if (themin && theField.value.length	< min) {
	theField.select();
	if(min==1)
	   alert("Missing value in the ["+theList+"]!");
	else
	   alert("["+theList+"] is not less than "+min+" Byte!");
	theField.focus();
	return false;
    }
    if (themax)	{
	st=0;
	if(theField.value.length>max)
	    st=1;
	//use to Chinese
	else if(theField.value.length<255){
	    len=theField.value.length;
	    for(i=0;i<theField.value.length;i++)
		if(theField.value.substring(i,i+1)>"~")	len=len+1;
	    if(len>max)	st=1;
	}
	if(st==1){
	    theField.select();
	    alert("["+theList+"] is not more than "+max+" Byte!");
	    theField.focus();
	    return false;
	}
    }
    return true;
} 

function selectAll(form_name,Fields,do_check)
{

	var len=0;
	var theField=form_name.elements[Fields];

	len=(typeof(theField.length) != 'undefined') ? theField.length : 0;
	if(len){
		for(i=0;i<len;i++){
			theField[i].checked=do_check;
		}
	}
	else{
		theField.checked=do_check;
	}
	document.all.sele_box.checked=false;
}


function checkBox(form_name,Fields,theName){
	sel=0;
	len=0;
	var theField=form_name.elements[Fields];
	if(theField.length)
		len=theField.length;
	if(len){
		for(i=0;i<len;i++){
			if(theField[i].checked==true) {sel=sel+1;}
		}
	}
	else{
		if(theField.checked==true) sel=1;
	}
	if(sel==0){
		alert('['+theName+'] is not checked!');
		return false;
	}
	else return true;
}

</script>
</head>
<body bgcolor="#faf6f6">

<?php
echo $html;

?>

</body>
</html>
