<?php


	function xml_to_arr($xml,$tidy=0){
		if(!$tidy){
			if(function_exists("libxml_disable_entity_loader")){
				libxml_disable_entity_loader(false);
			}
			$arr=@(array)simplexml_load_string($xml,"SimpleXMLElement",1);
		}
		else{
			$arr=$xml;
		}

		$retArr=Array();
		foreach ( $arr as $key => $value )
		{

			if ( is_string ( $value) )
			{
				$retArr [ $key ] =  $value;
				continue;
			}

			$value=(array) $value;
			//值为纯空格标签
			if(isset($value [0]) and count($value)==1 and trim($value[0])==''){
				$retArr [ $key ] = trim( $value[0]) ;
				unset($arr[$key]);
			}
			//空标签
			else if(count($value)==0){
				$retArr [ $key ] = '' ;
				unset($arr[$key]);
			}
			else
			{
				$retArr [ $key ] = xml_to_array ( $value , 1 ) ;
			}
		}
		return $retArr;
	}

	function curl_get($url,$poststr="",$timeOut=10)
	{
		$ch=@curl_init();
		@curl_setopt($ch,CURLOPT_URL,$url);
		@curl_setopt($ch,CURLOPT_POST,1);
		@curl_setopt($ch,CURLOPT_POSTFIELDS,$poststr); 
		@curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		@curl_setopt($ch,CURLOPT_COOKIESESSION,false);
		@curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
		@curl_setopt($ch,CURLOPT_HEADER,false);
		@curl_setopt($ch,CURLOPT_TIMEOUT,$timeOut);
		@curl_setopt($ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_0);//squid post 1.1 不能超过1k
		if(preg_match("/^https:/i",$url)){
			@curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
			@curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
		}
		$ret=Array();
		$ret['return']=@curl_exec($ch);
		$ret['result']=0+@curl_errno($ch);
		$ret['errmsg']='';
		//$curlerr=curl_errno($ch);
		if($ret['result']!==0){
			//28是超时
			if($ret['result']==28) $ret['result']=4; //Time out 
			else if($ret['result']==7) $ret['result']=3;//connect fail
			else if($ret['result']==6) $ret['result']=2;//connect deny
			else if($ret['result']==1 or $ret['result']===3) $ret['result']=1; //URL error
			else {
				$ret['result']=5;
			}
			$ret['errmsg']=@curl_error($ch);
		}
		return $ret;
	}
?>