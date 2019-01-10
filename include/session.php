<?PHP
/*

	include/phpuu_session.php 
	============================
	session start and check


	Modifly
	--------------
	+ 2011-02-10 $check_var name
	+ 2010-12-01 _session
	+ 2008-5-27 use SITE_DOMAIN
	+ 2007-11-15 Add COOKIE function
	+ 2007-9-16 COOKIE auth 
	+ 2007-8-2  use $varname
	+ 2007-7-22 modi
	+ 2007-7-1  Created

	e.g.
	---------------
	$SS=_session();
	$SS->login(userid,password,username);
	$SS->check(userid,password);

 */

class _session
{
	var $domain;
	var $cookieVar;
	var $loginname;

	//start session
	function _session($varname='',$domain='')
	{
		if (empty($varname))
		{
			$varname='__login_user';
		}
		$this->domain   =$domain;
		$this->cookieVar=$varname;
		$cookieTag=$this->cookieVar.'_ssid';
		$sessName =$this->cookieVar.'_name';
		if (!empty($_COOKIE[$cookieTag]))
		{
			$ss_id=$_COOKIE[$cookieTag];
			session_id($ss_id);
			session_start();
		}
		else
		{
			session_start();
			$ss_id=session_id();
			if (!empty($this->_domain))
			{
				setcookie($cookieTag, $ss_id,0,'/',$this->_domain);
			}
			else
			{
				setcookie($cookieTag, $ss_id,0,'/');
			}
		}
		$this->loginname=isset($_SESSION[$sessName]) ? $_SESSION[$sessName] : '';

	}

	//login register session
	function login($userid,$pass,$username='')
	{
		$sessUid  =$this->cookieVar;
		$sessName =$this->cookieVar.'_name';
		$sessTime =$this->cookieVar.'_time';

		$check_var=$this->cookieVar.'_'.substr(md5($userid.md5($pass)),0,27);
		$_SESSION[$sessTime] =time();
		$_SESSION[$sessUid]  =$userid;
		if (!empty($username))
		{
			$_SESSION[$sessName] =$username;
			$this->loginname=$username;
		}
		$ymd=date('Ymd');
		$_SESSION[$check_var]=''.md5($check_var.$ymd.md5($pass));
	}

	/*
	 * check session
	 * return 0 access ok; 1 not login; 2 time out; 3 access deny
	 * 
	 */
	function check($userid,$pass)
	{
		$sessUid  =$this->cookieVar;
		$sessName =$this->cookieVar.'_name';
		$sessTime =$this->cookieVar.'_time';

		$check_var=$this->cookieVar.'_'.substr(md5($userid.md5($pass)),0,27);
		$sessval1=''.md5($check_var.date('Ymd').md5($pass));
		$sessval2=''.md5($check_var.date('Ymd',time()-3600).md5($pass));
		$sessval3=''.md5($check_var.date('Ymd',time()+3600).md5($pass));
		$deny=0;
		$sessval =$_SESSION[$check_var];

		if (empty($_SESSION[$sessUid]))
		{
			$deny=1;
		}
		else if (time()-$_SESSION[$sessTime]>3600)
		{
			$deny=2;
		}
		else if(empty($_SESSION[$check_var]))
		{
			$deny=1;
		}
		else if($sessval!=$sessval1 and $sessval!=$sessval2 and $sessval!=$sessval3)
		{
			$deny=3;
		}

		if ($deny>0)
		{
			$_SESSION[$sessUid]=0;
			$_SESSION[$sessName]='';
			$_SESSION[$check_var]='';
		}
		else
		{
			$_SESSION[$sessTime] =time();
		}

		return $deny;
	}

	function getuser()
	{
		$sessUid  =$this->cookieVar;
		return isset($_SESSION[$sessUid]) ? $_SESSION[$sessUid] : '';
	}

	function setval($tag,$val)
	{
		$_SESSION[$tag]=$val;
	}

	function getval($tag='')
	{
		if (strlen($tag)>0)
		{
			return isset($_SESSION[$tag]) ? $_SESSION[$tag] : '';
		}
		return $_SESSION;
	}

	//unregister session
	function out()
	{
		$cookieTag=$this->cookieVar.'_ssid';
		setcookie($cookieTag);
		$sstag=str_replace('.','\\.',$this->cookieVar);
		
		foreach ($_SESSION as $_k =>$_v)
		{
			unset($_SESSION[$_k]);
			
		}
	}
}

?>