<?php

/*
 * Mysql数据库基本操作对象 V1.0
 *
 * 2017-4-5 创建
 */

class DB 
{

	var $_conf;
	var $_dblink;   //mysql connect
	var $_sql='';
	var $_sqlnum=0;
	var $_tablename='';
	var $_addlog=0; //是否记录更新
	var $_errmsg='';
	var $_sqlwhere='';
	var $_Field=Array();
	var $_Data =Array();
	var $_DataNum=0;

	var $pageSize=20;
	var $stripSet=0;


	private  $__method_name=Array();//方法用处(简称)
	private  $__method_info=Array();//方法说明
	private  $__para_input =Array();//输入参数说明
	private  $__para_output=Array();//输出参数说明

	//对象说明
	public function self_help($conf){
		$ret=Array();
		$ret['name'] ='Mysql数据库基本操作对象';
		$ret['intro']='用于数据库的基本读写操作';
		return $ret;
	}

	//自身实例化
	public function self_init($conf){
		return new DB($conf);
	}

	public function __construct($conf)
	{
		$this->_conf=$conf;
		//print_r($this->_conf);
		//$this->_argument="test";
	}

	public function DB($conf){
		$this->__construct($conf);
	}


	public function database($dataname){
		return $this;
	}

	public function setTable($tbl){
		$this->_tablename ='';
		$this->_addlog  =0;
		$this->_Data=Array();
		$this->_Field=Array();
		$this->_DataNum=0;
		if(!empty($tbl)){
			$tb=config_table($tbl);
			if(!empty($tb)){
				$this->_tablename =$tb['tblname'];
				$this->_addlog  =intval($tb['addlog']);
			}
		}
		return $this;
	}

	public function sysTable($tbl){
		
		$this->_tablename ='';
		$this->_addlog  =0;
		$this->_tablename=$tbl;
		$this->_Data=Array();
		$this->_Field=Array();
		$this->_DataNum=0;
		if(!empty($tbl)){
			$tb=config_systable($tbl);
			if(!empty($tb)){
				$this->_tablename =$tb['tblname'];
				$this->_addlog  =intval($tb['addlog']);
			}
		}
		return $this;
	}

	/*
		@fieldArr=Array();
		[fieldname]=Array(type=>0,max=12,min=0,'uni'=0/1,preg="");
		@type=0 string ,1 num ,2 doubel 3 STR
		@preg=字符允许的规则
		@nui 是否唯一
		@必须在setTable()之后执行才有效
	*/
	function setField($fieldArr){
		if(!empty($this->_tablename)){
			$this->_Field[$this->_tablename]=$fieldArr;
		}
	}


	/*
		$data=Array();
		$data[filed]=Array($value,$type)
		  #type:0 string ,1 num ,2 doubel 3 STR

	*/
	function setData($dataArr){
		$this->_Data[$this->_DataNum]=$dataArr;
		$this->_DataNum=$this->_DataNum+1;
		//return $this;
	}

	private function conn()
	{
		//优先使用长连接
		if(function_exists("mysql_pconnect")){
			$this->_dblink=@mysql_pconnect($this->_conf['hostname'],$this->_conf['username'], $this->_conf['password'])
			OR $this->debug("mysql_pconnect error:".str_replace($this->_conf['username'].'@','',mysql_error($this->_dblink)));
		}
		else{
			$this->_dblink=@mysql_connect($this->_conf['hostname'],$this->_conf['username'], $this->_conf['password'])
			OR $this->debug("mysql_connect error:".str_replace($this->_conf['username'].'@','',mysql_error($this->_dblink)));
		}
		
		if($this->_dblink)
		{
			if (isset($this->_conf['charset']) and strlen($this->_conf['charset'])>0)
			{
				@mysql_query("SET character_set_connection=".$this->_conf['charset'].", character_set_results=".$this->_conf['charset'].", character_set_client=binary", $this->_dblink);
			}
			$res=@mysql_select_db($this->_conf['dataname'],$this->_dblink)
				OR $this->debug("mysql_select_db error:".mysql_error($this->_dblink));
			if ($res) return true;
		}

		$this->_dberr=1;
		return false;

	}

	//执行SQL，返回执行原始结果
	public function query($sql,$skiplength=0){
		
		$this->__method_name['query']="执行SQL";
		$this->__method_info['query']="用于执行SQL，未连接时自动连接，过期时关闭重连";
		$this->__para_input['query'] ="@sql(String):需要执行的SQL语句";
		$this->__para_output['query']="原始执行结果或false";

		//为空或过短不执行
		if (strlen($str)<4){
			return false;
		}

		//过长的不执行
		if($skiplength>0 and strlen($str)>$skiplength){
			return false;
		}

		if(!$this->_dblink){
			$this->conn();
		}
		else if (!mysql_ping($this->_dblink))
		{
			$this->close();
			$this->conn();
		}

		$this->_sql=$str;
		$result=@mysql_query($str,$this->_dblink) OR $this->debug("mysql_query error:".mysql_error(),$SQL);
		return $result;
	}

	/*
	单条记录查询：用于查询单条记录，所有queryArr条件需同时满足，不支持定义WHERE、GROUP或ORDER
	 * @ret:一维数组
	*/
	public function find($queryArr){
		$this->__method_name['find']="单条记录查询";
		$this->__method_info['find']="用于查询单条记录，所有queryArr条件需同时满足，不支持定义WHERE、GROUP或ORDER";
		$this->__para_input['find'] ="@queryArr(Array): key=field,value=查询值";
		$this->__para_output['find']="一维数组";
		if(empty($this->_tablename)){
			$this->_errmsg="table is empty!";
			return false;
		}
		if(empty($this->_Field) or empty($this->_Field[$this->_tablename])){
			$this->_errmsg="Field is empty!";
			return false;
		}

		$sqlwhere='';
		foreach($queryArr as $field=>$value)
		{
			if(!isset($this->_Field[$this->_tablename][$field])){
				$this->_errmsg=" field Inconsistent!";
				return false;
			}
			$fieldtype=$this->_Field[$this->_tablename][$field][0];
			if(!isset($fieldtype) or !in_array($fieldtype,array(1,2,3))) $fieldtype=0;

			$sqlwhere=$sqlwhere.' AND '.$field.'='.$this->getsql($value,intval($fieldtype));
		}
		if(empty($sqlwhere)){
			$this->_errmsg="where is empty!";
			return false;
		}
		$sqlwhere=substr($sqlwhere,4);
		$sql="SELECT * FROM ".$this->_tablename." WHERE ".$sqlwhere." LIMIT 1";
		$result=$this->query($sql);
		if($result){
			$arr=@mysql_fetch_array($result,1);
			$this->check_query_quote($arr);
			$this->free($result);
			return $arr;
		}
		return Array();
	}

	/* 本方法返回二维数组 */
	public function select($queryArr=Array()){
		$this->__method_name['find'] ="复合及分页查询";
		$this->__method_info['select']="用于分页查询或较复杂的查询，可自定义WHERE、GROUP、ORDER及查询字段，可使用UNION、JOIN等语法";
		$this->__para_input['select'] =
		Array(
			"field"=>Array('name'=>'field','title'=>'查询字段','type'=>'str','null'=>0,'preg'=>'','remark'=>'需要查询的字段，若为空则select * ')
			,"where"=>Array('name'=>'where','title'=>'查询条件','type'=>'str','null'=>0,'preg'=>'','remark'=>'查询的条件，若为空则查询所有')
			,"group"=>Array('name'=>'group','title'=>'查询分组','type'=>'str','null'=>0,'preg'=>'','remark'=>'查询的分组，若为空则不分组')
			,"order"=>Array('name'=>'order','title'=>'排序设置','type'=>'str','null'=>0,'preg'=>'','remark'=>'查询的分组，若为空则不设置排序')
		);
		$this->__para_output['select']="二维数组";

		$sql="SELECT ".$queryArr['field']." FROM ".$this->_tablename." ".$queryArr['where']." ".$queryArr['group']." ".$queryArr['order'];
		$result=$this->query($sql);
		if($result){
			$arr = array();
			while ($row = mysql_fetch_array($result,1))
			{
				$this->check_query_quote($row);
				$arr[] = $row;
			}
			$this->free($result);
			return $arr;
		}
		return Array();
	}


	/*
		每次插入多条数据（但只执行一次SQL），校验_Field与_Data的一致性
	 */ 
	public function insert(){
		
		if(empty($this->_tablename)){
			$this->_errmsg="table is empty!";
			return false;
		}
		if(empty($this->_Field) or empty($this->_Field[$this->_tablename])){
			$this->_errmsg="Field is empty!";
			return false;
		}
		if(empty($this->_Data)){
			$this->_errmsg="Data is empty!";
			return false;
		}
		$f='';
		$fieldSet=Array();
		$n=0;
		foreach($this->_Field[$this->_tablename] as $field=>$arr)
		{
			$f=$f.','.$field;
			$fieldSet[$n]=$field;
			$n++;
		}
		$sql='';
		foreach($this->_Data as $arr)
		{
			$v='';
			$i=0;
			foreach($arr as $field=>$value)
			{
				if($fieldSet[$i]=!$field){
					$this->_errmsg=" field Inconsistent!";
					return false;
				}
				$fieldtype=$this->_Field[$this->_tablename][$field][0];
				if(!isset($fieldtype) or !in_array($fieldtype,array(1,2,3))) $fieldtype=0;
				$v=$v.','.$this->getsql($value,intval($fieldtype));
				$i++;
			}
			if($i!=$n){
				$this->_errmsg=" field Inconsistent!";
				return false;
			}
			if(strlen($v)>1){
				$v=", (".substr($v,1).")";
			}
			$sql=$sql.$v;
		}
		if (strlen($f)>1 and strlen($sql)>1){
			$sql="INSERT INTO ".$this->_tablename." (".substr($f,1).") VALUES ".substr($sql,1)."";
			$ret=$this->query($sql);
			if($ret){
				usleep(5000);
				if($this->_addlog){
					$this->table_log();
				}
				return true;
			}
			else{
				$this->_errmsg="sql error:".$this->err_msg();
				return false;
			}
		}
		$this->_errmsg="data is empty!";
		return false;
	}

	//每次只处理一条数据，不校验_Field与_Data的一致性
	public function update($whereFieldArr,$limit=0){
		$limit=intval($limit);
		if($limit>10000) $limit=10000;
		if(empty($this->_tablename)){
			$this->_errmsg="table is empty!";
			return false;
		}
		if(empty($whereFieldArr) or !is_array($whereFieldArr)){
			$this->_errmsg="where is empty!";
			return false;
		}
		if(empty($this->_Field) or empty($this->_Field[$this->_tablename])){
			$this->_errmsg="Field is empty!";
			return false;
		}
		if(empty($this->_Data)){
			$this->_errmsg="Data is empty!";
			return false;
		}

		$sql='';
		$sqlwhere='';
		foreach($this->_Data as $k =>$arr)
		{
			$i=0;
			foreach($arr as $field=>$value)
			{
				if(!isset($this->_Field[$this->_tablename][$field])){
					$this->_errmsg=" field Inconsistent!";
					return false;
				}
				$fieldtype=$this->_Field[$this->_tablename][$field][0];
				if(!isset($fieldtype) or !in_array($fieldtype,array(1,2,3))) $fieldtype=0;
				$i++;
				if(in_array($field,$whereFieldArr)){
					$sqlwhere=$sqlwhere.' AND  '.$field.'='.$this->getsql($value,intval($fieldtype));
					continue;
				}
				$sql=$sql.','.$field.'='.$this->getsql($value,intval($fieldtype));
			}
			//只处理一条
			break;
		}
		if(empty($sqlwhere)){
			$this->_errmsg="where is empty!";
			return false;
		}
		$sqlwhere=substr($sqlwhere,4);
		if(strlen($sql)>1){
			$sql="UPDATE ".$this->_tablename." SET  ".substr($sql,1)." WHERE ".$sqlwhere.($limit>0?" LIMIT $limit":"");
			$ret=$this->query($sql);
			if($ret){
				usleep(5000);
				if($this->_addlog){
					$this->table_log();
				}
				return true;
			}
			else{
				$this->_errmsg="sql error:".$this->err_msg();
				return false;
			}
		}

		$this->_errmsg="data is empty!";
		return false;
	}

	//允许同时删除多条_Data中指定的数据（但只执行一次SQL），不校验_Field与_Data的一致性
	public function del(){
		if(empty($this->_tablename)){
			$this->_errmsg="table is empty!";
			return false;
		}
		if(empty($this->_Field) or empty($this->_Field[$this->_tablename])){
			$this->_errmsg="Field is empty!";
			return false;
		}
		if(empty($this->_Data)){
			$this->_errmsg="Data is empty!";
			return false;
		}

		$sqlwhere='';
		$datanum=count($this->_Data);
		foreach($this->_Data as $k =>$arr)
		{
			$sql='';
			$n=0;
			foreach($arr as $field=>$value)
			{
				if(!isset($this->_Field[$this->_tablename][$field])){
					$this->_errmsg=" field Inconsistent!";
					return false;
				}
				$fieldtype=$this->_Field[$this->_tablename][$field][0];
				if(!isset($fieldtype) or !in_array($fieldtype,array(1,2,3))) $fieldtype=0;

				$sql=$sql.' AND '.$field.'='.$this->getsql($value,intval($fieldtype));
				$n++;
			}

			if(!empty($sql) and $datanum>1){
				if($n>1)
					$sqlwhere=$sqlwhere." OR  (".substr($sql,4).")";
				else 
					$sqlwhere=$sqlwhere." OR  ".substr($sql,4)."";
			}
			else {
				$sqlwhere=$sqlwhere.$sql;
			}
		}
		if(empty($sqlwhere)){
			$this->_errmsg="where is empty!";
			return false;
		}

		$sql="DELETE FROM  ".$this->_tablename." WHERE ".substr($sqlwhere,4);
		$ret=$this->query($sql);
		if($ret){
			usleep(5000);
			if($this->_addlog){
				$this->table_log();
			}
			return true;
		}
		else{
			$this->_errmsg="sql error:".$this->err_msg();
			return false;
		}
	}


	public function last_id(){
		$id=@mysql_insert_id($this->_dblink);
		return intval($id);
	}

	public function err_code()
	{
		return @mysql_errno($this->_dblink);
	}

	public function err_msg()
	{
		return mysql_error($this->_dblink);
	}

	//处理数值用于写入数据表
	public function formatNum($value,$formart=0){
		$value=preg_replace('/\s/','',$value);
		$value=str_replace(',','',trim($value));
		if(!preg_match('/^(-[0-9]|[0-9])[0-9]*(\.[0-9]+)?$/',$value)) $value='';
		if($formart) $value=0+$value;
		return $value;
	}
	/*
		$type:0 string ,1 num ,2 doubel 3 STR
	*/
	public function getsql($val,$type=0)
	{
		if($type==1) {
			$g='';
			if(substr($val,0,1)=='-') $g='-';
			$val=preg_replace('/\D/','',$val);
			if(strlen($val)==0) $val=0;
			return $g.$val;
		}
		else if($type==2) return doubleval($val);
		else if($type==3) return $val;
		else{
			if ($this->_dblink) return "'" . mysql_real_escape_string($val) . "'";
			else return "'" . AddSlashes($val) . "'";
		}
	}

	private function table_log(){
		$this->__method_info['table_log']="记录数据更新";
		if(!empty($this->_sql)){
			$sql="insert into (log_date,log_str) values (".$DB->getsql(date('Y-m-d H:i:s')).",".$DB->getsql($this->_sql).")";
			return $this->query($sql);
		}
		return false;
	}

	function free(&$result){
		return @mysql_free_result($result);
	}
	private function debug($string,$SQL='')
	{
		$debug=$this->_conf['debug'];
		if ($debug==4) {
			die($string.'<HR>'.$SQL);
		}
		else if ($debug==3) {
			print($string.'<HR>'.$SQL);
		}
		else if ($debug==2) {
			print('<!--'.$string.'<HR>'.$SQL.'-->');
		}
		else if ($debug==1) {
			//$code=intval($this->err_code());
			return 0;
		}
		return 0;
	}

	private function check_query_quote(&$val)
	{
		if($this->stripSet) $this->stripVar($val);
	}

	private function stripVar(&$val)
	{
		if(is_array($val)){
			foreach($val as $key => $v)
			{
				$this->stripVar($val[$key]);
			}
		}
		else if(is_string($val)){
			$val=StripSlashes($val);
		}
	}
}


?>