<?php

/**
* 邮件发送类
* 支持发送纯文本邮件和HTML格式的邮件，支持到服务器的ssl连接
* 需要的php扩展：sockets、openssl。
* 编码格式是UTF-8，传输编码格式是base64
* @example
* $mail = new SMTP();
* $mail->addAttachment("XXXX"); //添加附件，多个附件，调用多次
* $mail->sendmail("abc@aaa.com","test", "<b>test</b>"); //设置收件人、邮件主题、内容 （标题和内容需要是UTF-8编码）
*/



class SMTP{

	protected $_mailcfg=Array();
	protected $_attachment = array();
	protected $_socket;
	protected $_debug=0;
	public $_error='';

	function SMTP($config_set=''){

		$this->_mailcfg['smtp_auth'] =1;
		$this->_mailcfg['smtp_ssl']  =0;
		$this->_mailcfg['smtp_port'] =25;
		$this->_mailcfg['smtp_server']   =""; 
		$this->_mailcfg['smtp_from']     ='';// '"support"<sup@aaa.com>'
		$this->_mailcfg['smtp_username'] ="";
		$this->_mailcfg['smtp_password'] ="";

		//config from Array
		if(is_array($config_set)){
			foreach  ($config_set as $_key =>$_val) {
				if(isset($this->_mailcfg[$_key])) $this->_mailcfg[$_key]=$_val;
			}
		}
		//config from file
		else if(!empty($config_set) and is_string($config_set) and file_exists($config_set)){
			$string=@file_get_contents($config_set);
			if(strlen($string)<4096){
				$xmlConf=array();
				@preg_match_all("/<([_a-z0-9]+)>(.*)<\/\\1>/i", $string, $result);
				foreach ($result[1] as $k => $v)
				{
					if(!isset($xmlConf[$v])) $xmlConf[$v]=$result[2][$k];
				}
				foreach  ($xmlConf as $_key =>$_val) {
					if(isset($this->_mailcfg[$_key])) $this->_mailcfg[$_key]=$_val;
				}
			}
		}
		if(empty($this->_mailcfg['smtp_from'])) $this->_mailcfg['smtp_from']=$this->_mailcfg['smtp_username'];
		//print_r($this->_mailcfg);exit;
	}


	public function sendmail($email_to,$email_subject,$email_message,$isHTML=0)
	{

		if(!preg_match('/^[-_a-z0-9]+(\.[-_a-z0-9]+)*@[-a-z0-9]+(\.[a-z][-a-z]*)*$/i',$email_to)){
			$this->_error = 'email error!';
			return false;
		}
		if(empty($this->_mailcfg['smtp_server'])){
			$this->_error = 'smtp_server is empty!';
			return false;
		}
		if($this->_mailcfg['smtp_auth']){
			if(empty($this->_mailcfg['smtp_username'])){
				$this->_error = 'smtp_username is empty!';
				return false;
			}
			if(empty($this->_mailcfg['smtp_password'])){
				$this->_error = 'smtp_password is empty!';
				return false;
			}
		}

		$command = $this->getCommand($email_to,$email_subject,$email_message,$isHTML);

		if($this->_mailcfg['smtp_ssl']){
			if(!function_exists("stream_socket_client")){
				$this->_error = 'stream_socket_client() deny!';
				return false;
			}
			$ret=$this->socketSecurity();
		}
		else{
			if(!function_exists("socket_create")) {
				$this->_error = 'socket_create() deny!';
				return false;
			}
			$ret=$this->socket();
		}
		if(!$ret){
			if($this->_debug) echo $this->_error;
			return false;
		}
		foreach ($command as $value) {
			$result = $this->_mailcfg['smtp_ssl'] ? $this->sendCommandSecurity($value[0], $value[1]) : $this->sendCommand($value[0], $value[1]);
			if($result) {
				continue;
			}
			else{
				return false;
			}
		}
		return true;
	}

	protected function socket() {

		$this->_socket = @socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));

		if(!$this->_socket) {
			$this->_error = @socket_strerror(socket_last_error());
			if($this->_debug) echo '<br />socket_create:' . $this->_mailcfg['smtp_server'] . ' ('.$this->_mailcfg['smtp_port'].'): '.$errstr.'<br />';
			return false;
		}
		else{
			if($this->_debug) echo '<br />socket_connect:' . $this->_mailcfg['smtp_server'] . ' ('.$this->_mailcfg['smtp_port'].'): OK<br />';
		}

		@socket_set_block($this->_socket);//设置阻塞模式

		if(!socket_connect($this->_socket, $this->_mailcfg['smtp_server'], $this->_mailcfg['smtp_port'])) {
			$this->_error = @socket_strerror(socket_last_error());
			return false;
		}
		$str = @socket_read($this->_socket, 1024);
		if($this->_debug) echo 'socket_response:' . $str . '<br />';
		if(!preg_match("/220+?/", $str)){
			$this->_error = $str;
			return false;
		}
		return true;
	}

	protected function socketSecurity() {
		$remoteAddr = "tcp://" . $this->_mailcfg['smtp_server'] . ":" . $this->_mailcfg['smtp_port'];
		$this->_socket = @stream_socket_client($remoteAddr, $errno, $errstr, 20);
		if(!$this->_socket){
			$this->_error = $errstr;
			if($this->_debug) echo '<br />socket_connect:' . $this->_mailcfg['smtp_server'] . ' ('.$this->_mailcfg['smtp_port'].'): '.$errstr.'<br />';
			return false;
		}
		else{
			if($this->_debug) echo '<br />socket_connect:' . $this->_mailcfg['smtp_server'] . ' ('.$this->_mailcfg['smtp_port'].'): OK<br />';
		}

		stream_socket_enable_crypto($this->_socket, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT);
		stream_set_blocking($this->_socket, 1); //设置阻塞模式
		$str = fread($this->_socket, 1024);
		if($this->_debug) echo 'socket_response:' . $str . '<br />';
		if(!preg_match("/220+?/", $str)){
			$this->_error = $str;
			return false;
		}
		return true;
	}

	public function addAttachment($file_name,$file_mime_type='',$file_tmp_name='') {


		if(empty($file_tmp_name)){
			$file=$file_name;
			if(!file_exists($file)) {
				$this->_error = "file (" . $file . ") does not exist.";
				return false;
			}
			$file_mime_type='application/octet-stream';
			if(preg_match('/\.gif$/i',$file_name)){
				$file_mime_type='image/gif';
			}
			else if(preg_match('/\.(jpg|jpeg)$/i',$file_name)){
				$file_mime_type='image/jpeg';
			}
			else if(preg_match('/\.png$/i',$file_name)){
				$file_mime_type='image/png';
			}
			else if(preg_match('/\.bmp$/i',$file_name)){
				$file_mime_type='application/x-bmp';
			}
		}
		else {
			$file=$file_tmp_name;
			if(!file_exists($file)) {
				$this->_error = "file (" . $file_name . ") does not exist.";
				return false;
			}
		}
		$file_name=basename($file_name);
		$this->_attachment[] = Array($file,$file_name,$file_mime_type);
		return true;
	}

	protected function getCommand($email_to,$email_subject,$email_message,$isHTML) {

		$separator = "----=_Part_" . md5($this->_mailcfg['smtp_username'] . time());
		$separator = $separator.rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9); 

		//EHLO
		$command = array(
				array("HELO mail\r\n", 250)
			);
		if(!empty($this->_mailcfg['smtp_auth'])){
			$command[] = array("AUTH LOGIN\r\n", 334);
			$command[] = array(base64_encode($this->_mailcfg['smtp_username']) . "\r\n", 334);
			$command[] = array(base64_encode($this->_mailcfg['smtp_password']) . "\r\n", 235);
		}

		//设置发件人
		$command[] = array("MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $this->_mailcfg['smtp_from']) . ">\r\n", 250);
		$from=$this->_mailcfg['smtp_from'];
		if(!preg_match("/\<(.+?)\>/",$from)) $from="<$from>";
		$data = "FROM: " . $from . "\r\n";

		//设置收件人
		$command[] = array("RCPT TO: <" . $email_to . ">\r\n", 250);
		$data .= "TO: <" . $email_to .">\r\n";

		//主题
		$data .= "Subject: =?UTF-8?B?" . base64_encode($email_subject) ."?=\r\n";
		if(isset($this->_attachment)) {
			//含有附件的邮件头需要声明成这个
			$data .= "Content-Type: multipart/mixed;\r\n";
		}
		elseif(false){
			//邮件体含有图片资源的,且包含的图片在邮件内部时声明成这个，如果是引用的远程图片，就不需要了
			$data .= "Content-Type: multipart/related;\r\n";
		}
		else{
			//html或者纯文本的邮件声明成这个
			$data .= "Content-Type: multipart/alternative;\r\n";
		}

		//邮件头分隔符
		$data .= "\t" . 'boundary="' . $separator . '"';

		$data .= "\r\nMIME-Version: 1.0\r\n";
		if($isHTML!=1){
			$email_message=preg_replace("/(http:\/\/[^ \r\n]+)/is",'<A HREF="\\1" target="_blank">\\1</A>',$email_message);
			$email_message=nl2br($email_message);
			$email_message=str_replace('  ',' &nbsp;',$email_message);
		}
		//这里开始是邮件的body部分，body部分分成几段发送
		$data .= "\r\n--" . $separator . "\r\n";
		$data .= "Content-Type:text/html; charset=utf-8\r\n";
		$data .= "Content-Transfer-Encoding: base64\r\n\r\n";
		$data .= chunk_split(base64_encode($email_message)) . "\r\n";
		$data .= "--" . $separator . "\r\n";

		//加入附件
		if(!empty($this->_attachment) and is_array($this->_attachment)){
			$count = count($this->_attachment);
			foreach($this->_attachment as $fileArr){
				$data .=$this->encode_file($fileArr,$separator);
			}
		}

		//结束邮件数据发送
		$data .= "\r\n.\r\n";
		$command[] = array("DATA\r\n", 354);
		$command[] = array($data, 250);
		$command[] = array("QUIT\r\n", 221);
		 
		return $command;
	}


	protected function sendCommand($command, $code) {
		if($this->_debug) echo '<br />Send command:' . $command . ' ('.$code.'): <br />';
		//发送命令给服务器
		try{
			if(socket_write($this->_socket, $command, strlen($command))){

				//当邮件内容分多次发送时，没有$code，服务器没有返回
				if(empty($code))  {
					return true;
				}

				//读取服务器返回
				$data = trim(socket_read($this->_socket, 1024));
				if($this->_debug) echo 'response:' . $data . '<br />';

				if($data) {
					$pattern = "/^".$code."+?/";
					if(preg_match($pattern, $data)) {
						return true;
					}
					else{
						$this->_error = "Error:" . $data . "|**| command:";
						return false;
					}
				}
				else{
					$this->_error = "Error:" . socket_strerror(socket_last_error());
					return false;
				}
			}
			else{
				$this->_error = "Error:" . socket_strerror(socket_last_error());
				return false;
			}
		}catch(Exception $e) {
			$this->_error = "Error:" . $e->getMessage();
		}
	}

	protected function sendCommandSecurity($command, $code) {
		if($this->_debug)  echo '<br />Send command:' . $command . ' ('.$code.'): <br />';
		try {
			if(fwrite($this->_socket, $command)){
				//当邮件内容分多次发送时，没有$code，服务器没有返回
				if(empty($code))  {
					return true;
				}
				//读取服务器返回
				$data = trim(fread($this->_socket, 1024));
				if($this->_debug) echo 'response:' . $data . '<br />';

				if($data) {
					$pattern = "/^".$code."+?/";
					if(preg_match($pattern, $data)) {
						return true;
					}
					else{
						$this->_error = "Error:" . $data . "|**| command:";
						return false;
					}
				}
				else{
					return false;
				}
			}
			else{
				$this->_error = "Error: " . $command . " send failed";
				return false;
			}
		}catch(Exception $e) {
			$this->_error = "Error:" . $e->getMessage();
		}
	}

	protected function encode_file($fileArr,$separator){
		$file=$fileArr[0];
		$filename=$fileArr[1];
		$mimetype=$fileArr[2];
		if(!file_exists($file)) {
			$this->_error ="file " . $file . " dose not exist";
			return false;
		}

		$file_cont = file_get_contents($file);

		$cont='';
		$cont .= "\r\n--" . $separator . "\r\n";
		$cont .= "Content-Type: " . $mimetype . '; name="=?UTF-8?B?' . base64_encode( $filename ) . '?="' . "\r\n";
		$cont .= "Content-Transfer-Encoding: base64\r\n";
		$cont .= 'Content-Disposition: attachment; filename="=?UTF-8?B?' . base64_encode( $filename ) . '?="' . "\r\n";
		$cont .= "\r\n";
		$cont .= chunk_split(base64_encode($file_cont));
		$cont .= "\r\n--" . $separator . "\r\n";
		return $cont;
	}

}

?>