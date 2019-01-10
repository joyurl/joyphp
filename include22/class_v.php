<?php
/*
	phpuu_templates.php V1.0
	--------------------------
	@copyright   : (c)2008 phpuu

	# ----- V1.0 -----
	+ 2008-05-01 V1.0 基本功能完成，可以正式使用
	* 2008-05-01 修正HTML模式中字串重复替代问题
	+ 2008-04-30 完善FOR及IF嵌套，嵌套达到最佳方式
	+ 2008-04-28 处理FOR及IF嵌套(最多三层)
	+ 2008-04-23 变量优化设计(开始使用"_")

	# ----- V0.9  -----
	+ 2005年12月 完善基本模型(仅小范围内使用)
	+ 2004年12月 编写基本模型(仅测试使用)


	e.g.
	------------------
	$_Tpl=array();
	$TPL = new _Templates(string tempDir);
	$TPL->replace(string from,string to);
	$TPL->_tplFile='admin_main.htm';
	$TPL->showHTML(Array $_Tpl);
	echo $TPL->_html;


	使用说明
	-------------------
	(1). 变量: {varname}
	(2). 引用: <!--INCLUDE(filename)-->
	(3). 演示: <!--DEMO-->For demo text<!--/DEMO-->

	(4). IF: 允许三层IF或FOR混用，但不能三层皆是FOR
		(4.1 IF: <!--IF(boolvar)-->HTML1 {var} <!--ELSE(boolvar)-->HTML2 {var}<!--/IF(boolvar)-->
			elseif  通过多个 boolvar 来控制，boolvar必须是通过PHP判断处理后的变量
		(4.2 IF in IF（此IF中可以包含FOR）
			允许IF中包括IF
		(4.3 FOR in IF（此FOR中可以包含IF）
			允许IF中包括FOR

	(5). FOR: 
		(5.1 FOR: <!--FOR(rs)-->HTML1 {rs[row1]} HTML2{rs[row2]}<!--/FOR(rs)-->
		(5.2 FOR in FOR （此FOR中可以包含IF）
			<!--FOR(rs[key])-->List: {rs[row3]} - {key[rows1]}<!--/FOR(rs[key])-->
		(5.3 IF in FOR  （此IF中可以包含FOR）
			<!--IF(rs[isok])-->HTML<!--/IF(rs[isok])-->



 */


/*
 * _Templates
 */
class _Templates
{
	//Templates dir
	var $_tplDir  ='';
	var $_tplFile ='';
	var $_cacheTpl=0;
	var $_cacheDir='';

	//Templates string
	var $_code    =''; //templates Code
	var $_html    =''; //deal HTML code

	//item
	var $_item   =Array(); //replace item
	var $_rows1  =Array(); //Foreach value 
	var $_rows2  =Array(); //Foreach value

	function _Templates($tplDir='',$cacheTpl=0,$cacheDir='')
	{
		$this->_tplDir  =$tplDir;
		$this->_cacheTpl=$cacheTpl;
		$this->_cacheDir=$cacheDir;
		$this->_item    = Array();
	}


	function loadFile($filename)
	{
		$code='';
		if (function_exists("file_get_contents"))
		{
			$code=$code.@file_get_contents($filename);
		}
		else
		{
			$code=$code.@implode("", @file($filename));
		}
		return $code;
	}


	/*
	 * @$parseType:0 PHP
	 */
	function getFile($filechecked=1)
	{
		if ($filechecked==1 and (empty($this->_tplFile) or !file_exists($this->_tplDir.$this->_tplFile)))
			die("<CENTER>Error: Templates file {$this->_tplDir}{$this->_tplFile} does not exist!</CENTER>");

		$this->_code=$this->loadFile($this->_tplDir.$this->_tplFile);

		//strip DEMO tag
		$pos1=strpos($this->_code,'<!--DEMO-->');
		$newstr='';
		while (is_int($pos1))
		{
			$pos2  =strpos($this->_code,'<!--/DEMO-->',$pos1+1);
			if ($pos2 > $pos1)
			{
				$newstr=$newstr.substr($this->_code,0,$pos1);
				$this->_code=substr($this->_code,$pos2+12);
				$pos1=strpos($this->_code,'<!--DEMO-->');
			}
			else
			{
				break;
			}
		}
		$this->_code=$newstr.$this->_code;
		$newstr='';

	}

	function showHTML($_TPL,$tplFileName='')
	{
		if (strlen($tplFileName)>1)
		{
			$this->_tplFile=$tplFileName;
		}
		$this->getFile($this->_tplFile);
		return $this->makeHTML($_TPL);
	}

	//add replace item
	function replace($fromString,$toString)
	{
		$this->_item[]=array($fromString,$toString);
	}

	//take code between $Tag
	function takeCode($Tag)
	{
		$pos1=@strpos($this->_code,'<!--'.$Tag.'-->');
		$pos2=@strpos($this->_code,'<!--/'.$Tag.'-->',$pos1+1);
		if ($pos2>0)
		{
			return substr($this->_code,$pos1,$pos2-$pos1);
		}
		else
		{
			return '';
		}
	}

	//replace code between $Tag (2008-05-01)
	function repCode($Tag,$repStr='')
	{
		$pos1=@strpos($this->_code,'<!--'.$Tag.'-->');
		$pos2=@strpos($this->_code,'<!--/'.$Tag.'-->',$pos1+1);
		if ($pos2>0)
		{
			return substr($this->_code,0,$pos1).$repStr.substr($this->_code,$pos2+strlen($Tag)+8);
		}
		else
		{
			return $this->_code;
		}
	}

	/*
	 * base parse
	 */
	function baseCode($code)
	{
		# INCLUDE
		$n=0;
		while(ereg("<!--INCLUDE([-A-Za-z0-9_]+)-->",$code,$det))
		{
			$file=$det[1];
			if (file_exists($this->_tplDir.$file))
			{
				$temp=$this->loadFile($this->_tplDir.$file);
			}
			else
			{
				$temp='';
			}
			$code =str_replace("<!--INCLUDE(".$file.")-->",$temp,$code);
			$n++;
			if ($n>20)
			{
				break;
			}
		}

		# replace
		foreach($this->_item as $arr)
		{
			$code = str_replace($arr[0],$arr[1],$code);
		}
		reset($this->_item);
		return $code;
	}

	//parse to PHP Code
	function makePHP($_VAR='_TPL',$phpFileName='')
	{

		$this->_html=$this->baseCode($this->_code);

		# parse var 
		$this->_html=preg_replace('#\{([a-z0-9\-_]+)\}#is', '<?PHP echo $'.$_VAR.'[\'\1\']; ?>',$this->_html);

		# parse FOR 
		$this->_html=$this->parsePHP($_VAR,$this->_html);

		# write in File
		if (!empty($phpFileName))
		{
			$fp=@fopen($phpFileName,'w+');
			@fwrite($fp,$this->_html);
			@fclose($fp);
		}

	}


	/*
	 * parse template to PHP Code
	 */
	function parsePHP($_VAR,$code,$varTag='',$subnum=0)
	{
		$spaceStr=array('','','   ','      ');
		$brStr="\r\n";
		$subnum=$subnum+1;
		if ($subnum>3)
		{
			return $code;
		}


		$newstr=''; //parsed string
		while(ereg("<!--(IF|FOR)\(([-A-Za-z0-9_]*\[?[-A-Za-z0-9_]+\]?)\)-->",$code,$det))
		{
			$action =$det[1];
			$showKey=$det[2];
			$Tag=$action.'('.$showKey.')';
			$length=strlen($Tag);
			$pos1=strpos($code,'<!--'.$Tag.'-->');
			$pos2=strpos($code,'<!--/'.$Tag.'-->');

			$newstr = $newstr.substr($code,0,$pos1);

			if ($pos2>$pos1)
			{
				$parseStr= substr($code,$pos1+$length+7,$pos2-$pos1+1);
				$code = substr($code,$pos2+$length+8);

			}
			else 
			{
				$parseStr= '';
				$code = substr($code,$pos1+$length+7);
				$code = str_replace('<!--/'.$Tag.'-->','',$code);
			}

			if (strlen($parseStr)<1)
			{
				continue;
			}

			if ($action=='IF')
			{
				$pos=strpos($showKey,'[');
				if ($pos > 0 )
				{
					$codeTag=substr($showKey,0,$pos);
					if ($codeTag!=$varTag)
					{
						$newstr   = $newstr.$this->parsePHP($_VAR,$parseStr,$varTag,$subnum);
						continue;
					}
					$key=substr($showKey,$pos+1,strpos($showKey,']')-$pos-1);
					$expreIF='$_'.$varTag.'[\''.$key.'\']';
				}
				else
				{
					$expreIF='$'.$_VAR.'[\''.$showKey.'\']';
				}
				$noteStr='';
				$endStr='';
				if ($subnum==1)
				{
					$noteStr=$brStr.'/*************** IF ('.$expreIF.') ***************/';
					$endStr=$brStr.'/*************** IF END ***************/';
				}

				$parseStr =$this->parsePHP($_VAR,$parseStr,$varTag,$subnum);

				$parseStr =str_replace('<!--ELSE('.$showKey.')-->','<?PHP'.$brStr.$spaceStr[$subnum].'}'
					.$brStr.$spaceStr[$subnum].'ELSE'.$brStr.$spaceStr[$subnum].'{'.$brStr.'?>',$parseStr);
				$parseStr =str_replace('<!--/IF('.$showKey.')-->'
					,"<?PHP".$brStr.$spaceStr[$subnum]."}{$endStr}\r\n?>",$parseStr);
				$parseStr = '<?PHP'.$noteStr.$brStr.$spaceStr[$subnum].'if ( '.$expreIF.' )'
					.$brStr.$spaceStr[$subnum].'{'.$brStr.'?>'.$parseStr;
				$newstr = $newstr.$parseStr;
			}
			else if ($action=='FOR')
			{

				$pos=strpos($showKey,'[');
				if ($pos > 0)
				{
					$codeTag=substr($showKey,0,$pos);
					if ($codeTag!=$varTag)
					{
						$newstr   = $newstr.$this->parsePHP($_VAR,$parseStr,$varTag,$subnum);
						continue;
					}
					$key=substr($showKey,$pos+1,strpos($showKey,']')-$pos-1);
					$result="\$_{$varTag}['$key']";
				}
				else
				{
					$key=$showKey;
					$result='$'.$_VAR."['{$showKey}']";
				}

				$noteStr='';
				$endStr='';
				$VAR=''.$key;
				if ($subnum==1)
				{
					$noteStr=$brStr.'/*************** FOR ('.$result.') ***************/';
					$endStr=$brStr.'/*************** FOR END ***************/';
				}

				$parseStr = str_replace('<!--/'.$Tag.'-->',"",$parseStr);
				$parseStr = "<?PHP{$noteStr}{$brStr}{$spaceStr[$subnum]}foreach ({$result} as \$_$VAR){$brStr}{$spaceStr[$subnum]}{\r\n?>\r\n" 
					.preg_replace('#\{'.$key.'\[([A-Za-z0-9\-_]+)\]\}#is',"<?PHP echo \$_{$VAR}['\\1'];?>",$parseStr);
				$parseStr = $parseStr."<?PHP \r\n{$spaceStr[$subnum]}}{$endStr}\r\n?>";
				$newstr   = $newstr.$this->parsePHP($_VAR,$parseStr,$VAR,$subnum);
			}
		}

		$newstr = $newstr. $code;
		return $newstr;
	}




	/*
	 * parse to HTML Code
	 * @showHTML:0 get HTML none print, 1 print
	 */
	function makeHTML($TPL,$showHTML=1,$_VAR='_TPL')
	{
		if ($this->_cacheTpl==1)
		{
			$cacheFile=$this->_cacheDir.$this->_tplFile.'.php';
			if (!file_exists($cacheFile))
			{
				$this->makePHP($_VAR,$cacheFile);
			}

			if ($showHTML==1)
			{
				include($cacheFile);
			}
			else
			{
				$this->_html=$this->baseCode($this->_code);
				$this->_html=$this->parseHTML($this->_html,$TPL);
			}

		}
		else
		{
			$this->_html=$this->baseCode($this->_code);
			$this->_html=$this->parseHTML($this->_html,$TPL);
			if ($showHTML==1)
			{
				echo $this->_html;
			}
		}
		return $this->_html;
	}

	/*
	 * parse template to HTML Code
	 */
	function parseHTML($code,$TPL,$varTag='',$subnum=0)
	{
		$subnum=$subnum+1;
		if ($subnum>3)
		{
			return $this->parseVal($code,$TPL,$varTag);
		}

		$newstr=''; //parsed string
		while(ereg("<!--(IF|FOR)\(([-A-Za-z0-9_]*\[?[-A-Za-z0-9_]+\]?)\)-->",$code,$det))
		{
			$action =$det[1];
			$showKey=$det[2];

			$Tag=$action.'('.$showKey.')';
			$length=strlen($Tag);
			$pos1=strpos($code,'<!--'.$Tag.'-->');
			$pos2=strpos($code,'<!--/'.$Tag.'-->');

			$temp1=substr($code,0,$pos1);
			
			$newstr = $newstr.$this->parseVal($temp1,$TPL,$varTag);
			if ($pos2>$pos1)
			{
				$parseStr= substr($code,$pos1+$length+7,$pos2-$pos1-$length-7);
				$code = substr($code,$pos2+$length+8);

			}
			else 
			{
				$parseStr= '';
				$code = substr($code,$pos1+$length+7);
				$code = str_replace('<!--/'.$Tag.'-->','',$code);
			}

			if (strlen($parseStr)<1)
			{
				continue;
			}

			if ($action=='IF')
			{
				$temp=@explode('<!--ELSE('.$showKey.')-->',$parseStr);
				// e.g. IF(result[islogin])
				$pos=strpos($showKey,'[');
				if ($pos > 0)
				{
					$key=substr($showKey,$pos+1,strpos($showKey,']')-$pos-1);
					$codeTag=substr($showKey,0,$pos);
					if (empty($varTag))
					{
						$expreIF=0;
					}
					else if ($codeTag==$varTag)
					{
						$expreIF=$this->_rows1[$key];
					}
					else
					{
						$expreIF=$this->_rows2[$key];
					}
				}
				else
				{
					$expreIF=$TPL[$showKey];
				}

				if ($expreIF)
				{
					$newstr = $newstr.$this->parseHTML($temp[0],$TPL,$varTag,$subnum);
				}
				else if (strlen($temp[1])>0)
				{
					$newstr = $newstr.$this->parseHTML($temp[1],$TPL,$varTag,$subnum);
				}
			}
			else if ($action=='FOR')
			{
				// e.g. FOR(result[list])
				$pos=strpos($showKey,'[');
				if ($pos > 0)
				{
					$key=substr($showKey,$pos+1,strpos($showKey,']')-$pos-1);
					$codeTag=substr($showKey,0,$pos);
					if ($codeTag==$varTag)
					{
						$result=$this->_rows1[$key];
					}
					else
					{
						$newstr   = $newstr.$this->parseHTML($parseStr,$TPL,$varTag,$subnum);
						continue;
					}
				}
				else
				{
					$result=$TPL[$showKey];
					$key=$showKey;
				}

				$varTagNew=(empty($varTag) ? $key : $varTag);
				if (is_array($result))
				{
				 foreach ($result as $row)
				 {
					if (empty($varTag))
					{
						$this->_rows1=array();
						$this->_rows1=$row;
					}
					else
					{
						$this->_rows2=array();
						$this->_rows2=$row;
					}

					$newstr = $newstr.$this->parseHTML($parseStr,$TPL,$varTagNew,$subnum);
				 }
				}
			}
		}

		$newstr = $newstr. $this->parseVal($code,$TPL,$varTag);
		return $newstr;
	}


	/*
	 * parse to HTML
	 */
	function parseVal($code,$TPL,$varTag='')
	{
		$newstr='';
		while (ereg('\{([-A-Za-z0-9_]+)\[?([-A-Za-z0-9_]*)\]?\}',$code,$det))
		{
			$val  =$det[0];
			$pos1 =strpos($code,$val);
			$pos2 =strpos($val,'[');

			if ($pos2 > 0)
			{
				$varName = $det[1];
				$key  = $det[2];
				if (!empty($varName) and !empty($key))
				{
					if ($varName==$varTag)
					{
						$newstr=$newstr.substr($code,0,$pos1).$this->_rows1[$key];
					}
					else
					{
						$newstr=$newstr.substr($code,0,$pos1).$this->_rows2[$key];
					}
				}
			}
			else
			{
				$key   = $det[1];
				$newstr=$newstr.substr($code,0,$pos1).$TPL[$key];
			}

			$code=substr($code,$pos1+strlen($val));
		}
		return $newstr.$code;

	}


}//end class

?>