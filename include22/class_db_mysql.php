<?php

/*
 * Mysql���ݿ������������ V1.0
 *
 * 2017-4-5 ����
 */

class DB 
{

	var $_conf;
	var $_dblink;   //mysql connect
	var $_sql='';
	var $_sqlnum=0;
	var $_tablename='';
	var $_addlog=0; //�Ƿ��¼����
	var $_errmsg='';
	var $_sqlwhere='';
	var $_Field=Array();
	var $_Data =Array();
	var $_DataNum=0;

	var $pageSize=20;
	var $stripSet=0;


	private  $__method_name=Array();//�����ô�(���)
	private  $__method_info=Array();//����˵��
	private  $__para_input =Array();//�������˵��
	private  $__para_output=Array();//�������˵��

	//����˵��
	public function self_help($conf){
		$ret=Array();
		$ret['name'] ='Mysql���ݿ������������';
		$ret['intro']='�������ݿ�Ļ�����д����';
		return $ret;
	}

	//����ʵ����
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
		@preg=�ַ�����Ĺ���
		@nui �Ƿ�Ψһ
		@������setTable()֮��ִ�в���Ч
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
		//����ʹ�ó�����
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

	//ִ��SQL������ִ��ԭʼ���
	public function query($sql,$skiplength=0){
		
		$this->__method_name['query']="ִ��SQL";
		$this->__method_info['query']="����ִ��SQL��δ����ʱ�Զ����ӣ�����ʱ�ر�����";
		$this->__para_input['query'] ="@sql(String):��Ҫִ�е�SQL���";
		$this->__para_output['query']="ԭʼִ�н����false";

		//Ϊ�ջ���̲�ִ��
		if (strlen($str)<4){
			return false;
		}

		//�����Ĳ�ִ��
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
	������¼��ѯ�����ڲ�ѯ������¼������queryArr������ͬʱ���㣬��֧�ֶ���WHERE��GROUP��ORDER
	 * @ret:һά����
	*/
	public function find($queryArr){
		$this->__method_name['find']="������¼��ѯ";
		$this->__method_info['find']="���ڲ�ѯ������¼������queryArr������ͬʱ���㣬��֧�ֶ���WHERE��GROUP��ORDER";
		$this->__para_input['find'] ="@queryArr(Array): key=field,value=��ѯֵ";
		$this->__para_output['find']="һά����";
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

	/* ���������ض�ά���� */
	public function select($queryArr=Array()){
		$this->__method_name['find'] ="���ϼ���ҳ��ѯ";
		$this->__method_info['select']="���ڷ�ҳ��ѯ��ϸ��ӵĲ�ѯ�����Զ���WHERE��GROUP��ORDER����ѯ�ֶΣ���ʹ��UNION��JOIN���﷨";
		$this->__para_input['select'] =
		Array(
			"field"=>Array('name'=>'field','title'=>'��ѯ�ֶ�','type'=>'str','null'=>0,'preg'=>'','remark'=>'��Ҫ��ѯ���ֶΣ���Ϊ����select * ')
			,"where"=>Array('name'=>'where','title'=>'��ѯ����','type'=>'str','null'=>0,'preg'=>'','remark'=>'��ѯ����������Ϊ�����ѯ����')
			,"group"=>Array('name'=>'group','title'=>'��ѯ����','type'=>'str','null'=>0,'preg'=>'','remark'=>'��ѯ�ķ��飬��Ϊ���򲻷���')
			,"order"=>Array('name'=>'order','title'=>'��������','type'=>'str','null'=>0,'preg'=>'','remark'=>'��ѯ�ķ��飬��Ϊ������������')
		);
		$this->__para_output['select']="��ά����";

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
		ÿ�β���������ݣ���ִֻ��һ��SQL����У��_Field��_Data��һ����
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

	//ÿ��ֻ����һ�����ݣ���У��_Field��_Data��һ����
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
			//ֻ����һ��
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

	//����ͬʱɾ������_Data��ָ�������ݣ���ִֻ��һ��SQL������У��_Field��_Data��һ����
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

	//������ֵ����д�����ݱ�
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
		$this->__method_info['table_log']="��¼���ݸ���";
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