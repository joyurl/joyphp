<?php
/*
	_templates.php V1.0
	--------------------------


 */


/*
 * _Templates
 */
class _templates
{
	//Templates dir
	var $_tplDir  ='';
	var $_tplFile ='';
	var $_cacheTpl=0;
	var $_cacheDir='';

	//Templates string
	var $_code    =''; 
	var $_html    =''; 

	//item
	var $_item   =Array(); 
	var $_rows1  =Array(); 
	var $_rows2  =Array(); 

	function _templates($tplDir='',$cacheTpl=0,$cacheDir='')
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
		if ($filechecked==1 and (empty($this->_tplFile) or !file_exists($this->_tplDir.$this->_tplFile))){
			die("<CENTER>Error: Templates file {$this->_tplDir}{$this->_tplFile} does not exist!</CENTER>");
		}

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
		while(ereg("<!--INCLUDE\(([-A-Za-z0-9_\.\/]+)\)-->",$code,$det))
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




	/*
	 * parse to HTML Code
	 * @showHTML:0 get HTML none print, 1 print
	 */
	function makeHTML($TPL,$showHTML=1,$_VAR='_TPL')
	{
		
		$this->_html=$this->baseCode($this->_code);
		$this->_html=$this->parseHTML($this->_html,$TPL);
		return $this->_html;
	}

	//定制页面输出
	function printHTML($_VS){
		global $_TPL_DIR;
		$this->getFile(1);
		$this->makeHTML($_VS,1);
	}

	/*
	 * parse template to HTML Code
	 */
	function parseHTML($code,$TPL,$varTag='',$subnum=0)
	{
		
		if ($subnum>3)
		{
			return $this->parseVal($code,$TPL,$varTag);
		}

		$newstr=''; //parsed string
		while(ereg("<!--(IF|FOR)\(([-A-Za-z0-9_\.]*\[?[-A-Za-z0-9_]+\]?)\)-->",$code,$det))
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
		while (ereg('\{([-A-Za-z0-9_\.]+)\[?([-A-Za-z0-9_]*)\]?\}',$code,$det))
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
				$newstr=$newstr.substr($code,0,$pos1).@$TPL[$key];
			}

			$code=substr($code,$pos1+strlen($val));
		}
		return $newstr.$code;

	}


}//end class

?>
