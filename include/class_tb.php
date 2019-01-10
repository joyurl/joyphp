<?PHP

/*
	数据表内容管理对象
	=========================

	用于管理数据表模型
*/


if(!defined('__CLASS__TB__')){
  define('__CLASS__TB__', 1);

class TB
{

	var $_conf;
	var $DB; 


	private  $__method_name=Array();//方法用处(简称)
	private  $__method_info=Array();//方法说明
	private  $__para_input =Array();//输入参数说明
	private  $__para_output=Array();//输出参数说明

	public function __construct($DB)
	{
		$this->DB=$DB;
		//$this->_argument="test";
	}

	public function TB($DB){
		$this->__construct($DB);
	}

	//自身实例化
	public function self_init($conf){
		return new TB($conf);
	}

	//对象说明
	public function self_help($conf){
		$ret=Array();
		$ret['name'] ='数据表管理对象';
		$ret['intro']='用于管理数据表，定义数据表单编辑形式、写入数据格式检查，以及查询列表的显示形式';
		return $ret;
	}

	//
	public function menu_get(){
		
	}
	
	//处理搜索项（通用）
	//+ 2017-8-17创建；2017-8-25 完善搜索类型设置
	//@返回SQL 
	protected function getSearchSQL(&$_key,$_f,$findArr){

		//@$findArr=Array();定义搜索项
		//Array("type:类型","title:显示名称","field:搜索的字段名称(有附表时为附表字段)","tbl:附表表名(真实名称)","set:(type=15)搜索设置或(type=2|3|12)第二字段(type=16设置字段)或(其它)主附表关联字段名")
		//运算符：数值(! | & > < ) 字符(! | = /A/)
		//匹配符：%(0到多个任意字符),_(一个任意字符)

		//type类型
		//0 ?== 等于（限数值型，适用于可以搜索多个值的情况，默认按等于进行搜索。支持运算符搜索：用A|B搜索两个值;用A&B搜索A到B之间的值;前加!A搜索不等于A的值;>A大于或等于A;<A小于或等于A）
		//1 === 绝对等于，不支持运算符搜索，适用于只需要搜索一个值的情况（比如流水号，会员编号）
		//2 ==: 双字段AND搜索（限数值型，限搜索主表内字段），不能关联附表搜索： table 值不使用，set为第二个值的搜索字段
		//3 ==@ 双字段AND搜索（限数值型，限搜索主表内字段），不能关联附表搜索： table 值不使用，set为第二个值的搜索字段
			   //3为保留项，不建议使用(关闭)
		//10 ?≈ 自定义模糊搜索(适用内容较短的字符型和数值型，%匹配0至多个任意字符，_匹配1个任意字符，无关键字则搜索绝对相等的值) 
		//      支持运算符搜索：或在前加!搜索反向匹配值;或用|搜索两个值中任一个;或前加=进行二进制搜索；或前后加 /关键字/ 使用正则表达式搜索(若要搜索_,!,=,%,/字符，则需要使用正则表达式规则)
		//12 ＝: 双字段AND搜索（适用于字串型） 不能关联附表搜索，不支付运算符或匹配符： table 值不使用，set为第二个值的搜索字段（可用:设置长度）
		//13 ≈  单字段包含搜索(搜索内容包含输入值的记录，适用于对较长的字符进行搜索，比如文章内容、备注等) 

		//15 ≌ 智能单字段搜索（根据值不同而搜索对应的字段），对field自定义规则模糊LIKE搜索(但不支持运算符搜索)，不能关联附表搜索。
		//        若有set设置限搜索主表且值为绝对等于，按输入值匹配set设置进行搜索， set格式为： 
		//        (field=>'user_name')set=Array(array("/^[1-9][0-9]{0,9}\$/i","user_id",1),array("/^1[3-9][0-9]{9}\$/i","user_mobile",0));
		//16 ∽ 智能多字段OR搜索(LIKE)（适合于字符串，不能关联附表搜索，可用匹配符，不支持运算符搜索）,set格式set=Array("字段2","字段3","字段n")

		$_sql='';
		//自动校正（不允许搜索引号，建议输入框设置最大长度maxlength=50）
		$_key=trim($_key);
		$_key=preg_replace('/(\"|\')/i','',$_key);

		$_k=trim($_key);
		if(!empty($_k) and !empty($_f) and isset($findArr[$_f])){

			$_tmp='';
			$fArr=$findArr[$_f];
			$_type =$fArr['type'];
			$_field=preg_replace('/[^-_0-9a-z]/i','',$fArr['field']);
			$_field2='';
			//$fArr[3]=preg_replace('/[^-_0-9a-z]/i','',trim($fArr[3]));
			//$_fname=isset($fArr[5]) ? preg_replace('/[^-_0-9a-z]/i','',trim($fArr[5])):'';
			$_tablename=isset($fArr['tbl']) ? preg_replace('/[^-_0-9a-z]/i','',trim($fArr['tbl'])):'';
			if(empty($_field)) return $_sql;
			if($_type==0) {

				if(preg_match('/\|/i',$_k)){
					$tmp=explode("|",$_k);
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp." $_field IN (".$this->formatNum($tmp[0],1).", ".($this->formatNum($tmp[1],1)).")";
					}
				}
				else if(preg_match('/&/i',$_k)){
					$_k=preg_replace('/(\||!|<|>)/i','',$_k);
					$tmp=explode("&",$_k);
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp." $_field >= ".$this->formatNum($tmp[0],1)." and $_field <= ".($this->formatNum($tmp[1],1))."";
					}
				}
				else if(substr($_k,0,1)=='!'){
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field != ".($this->formatNum($_k,1));
				}
				else if(substr($_k,0,1)=='>'){
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field > ".($this->formatNum($_k,1));
				}
				else if(substr($_k,0,1)=='<'){
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field < ".($this->formatNum($_k,1));
				}


				if(empty($_tmp)){
					$_tmp=$_tmp."  $_field = ".($this->formatNum($_k,1));
				}
			}
			else if($_type==1) $_tmp=$_tmp." $_field = ".($this->formatNum($_k,1));
			else if($_type==2) {
				//此类型不能搜索两个表
				$_tablename='';
				if(isset($fArr['set'])){
					if(!is_string($fArr['set'])) $fArr['set']='';
					$_field2=preg_replace('/[^-_0-9a-z]/i','',trim($fArr['set']));
				}

				$tmp=explode("@",$_k);
				if(strlen($tmp[0])>0){
					$_tmp=$_tmp." $_field = ".($this->formatNum($tmp[0],1));
					if(strlen($tmp[1])>0 and !empty($_field2)){
						$_tmp=$_tmp." and $_field2 = ".($this->formatNum($tmp[1],1))."";
					}
				}
			}
			else if($_type==10) {
				if(substr($_k,0,1)=='!' and strlen($_k)>1)
				{
					$_k=substr($_k,1);
					$_tmp=$_tmp." $_field NOT LIKE ".$this->DB->getsql($_k);
				}
				else if(substr($_k,0,1)=='=' and strlen($_k)>1)
				{
					$_k=substr($_k,1);
					$_tmp=$_tmp." BINARY($_field) LIKE ".$this->DB->getsql($_k);
				}
				else if(substr($_k,0,1)=='/' and substr($_k,-1)=='/' and strlen($_k)>2)
				{
					$_k=substr($_k,1,strlen($_k)-2);
					//REGEXP or RLIKE
					$_tmp=$_tmp." $_field REGEXP ".$this->DB->getsql($_k);
				}
				else if(preg_match('/\|/i',$_k)){
					$tmp=explode("|",$_k);
					if(strlen($tmp[1])>0){
						$_tmp=$_tmp."  ($_field LIKE ".$this->DB->getsql($tmp[0])." OR $_field LIKE ".$this->DB->getsql($tmp[1]).")";
					}
					else{
						$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($tmp[0]);
					}
				}
				else{
					$_k=str_replace('/','',$_k);
					$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($_k);
				}
			}
			else if($_type==12){
				
				//此类型不能搜索两个表
				$_tablename='';
				if(isset($fArr['set'])){
					if(!is_string($fArr['set'])) $fArr['set']='';
					$_field2=preg_replace('/[^-_0-9a-z]/i','',trim($fArr['set']));
				}

				$tmp=explode(":",$_k);
				if(strlen($tmp[0])>0){
					$_tmp=$_tmp." $_field = ".($this->DB->getsql($tmp[0]));
					if(strlen($tmp[1])>0 and !empty($_field2)){
						$_tmp=$_tmp." and $_field2 = ".($this->DB->getsql($tmp[1]))."";
					}
				}
			}
			else if($_type==13) {
				$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql('%'.$_k.'%');
			}
			else if($_type==15){
				//此类型不能搜索两个表
				$_tablename='';
				if(isset($fArr['set']) and is_array($fArr['set']))
				{
					foreach($fArr['set'] as $arr){
						$arr[1]=preg_replace('/[^-_0-9a-z]/i','',trim($arr[1]));
						$valuetype = ($arr[2]==1?1:0);
						if(empty($arr[1])) continue;
						if(@preg_match($arr[0],$_k)){
							$_tmp=$_tmp." AND {$arr[1]} = ".$this->DB->getsql($_k,$valuetype);
							return $_tmp;
							//break;
						}
					}
				}
				//默认
				$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($_k);
			}
			else if($_type==16){
				
				$_tablename='';
				if(isset($fArr['set']) and is_array($fArr['set'])){
					foreach($fArr['set'] as $_find){
						if(empty($_find) or !is_string($_find)) continue;
						$_find=preg_replace('/[^-_0-9a-z]/i','',trim($_find));
						if(empty($_find)) continue;
						$_tmp=$_tmp." OR  {$_find} LIKE ".$this->DB->getsql($_k);
					}
				}
				if(!empty($_tmp)){
					$_tmp=$_tmp." ($_field LIKE ".$this->DB->getsql($_k).substr($_tmp,4).")";
				}
				//默认
				else{
					$_tmp=$_tmp." $_field LIKE ".$this->DB->getsql($_k);
				}
			}

			if(!empty($_tmp)){
				if(isset($fArr['set'])){
					if(!is_string($fArr['set'])) $fArr['set']='';
					$_field2=preg_replace('/[^-_0-9a-z]/i','',trim($fArr['set']));
				}
				if(!empty($_tablename) and !empty($_field2)){
					$_sql=$_sql." AND {$_field2} IN (select  {$_field2} from $_tablename where $_tmp )";
				}
				else{
					$_sql=$_sql." AND $_tmp ";
				}
			}
		}
		return $_sql;
	}

	
	//处理分类项
	//@sortArr[]: Array("name"=>"变量名","field"=>"默认查询字段","type"=>"类型0|1|2(字符|整数|小数)","set"=>Array(选择项设置));
	//@set[] =Array("title"=>"@表单显示内容","value"=>"表单value值","fieldset"=>'查询字段，为空则使用field',"query"=>"对应查询值，为空时则直接使用value值进行查询，支持用|查询2-6个值");
	protected function getSortSQL($form,$sortArr){

		if(!is_array($sortArr)) return '';
		$sql='';
		foreach($sortArr as $conf){

			$_field=preg_replace('/[^-_0-9a-z]/i','',$conf['field']);
			$_type=$conf['type'];
			if(!in_array($_type,Array(1,2))) $_type=0;
			//没有输入值
			if(!isset($form[$conf['name']]) or strlen($form[$conf['name']])==0) continue;

			$_tmp='';//当前分类框
			$_value=trim($form($conf['name']));
			foreach($conf['set'] as $sets){

				if($sets['value']!=$_value) continue;

				$_fieldset='';
				if(isset($sets['fieldset'])) $_fieldset=preg_replace('/[^-_0-9a-z]/i','',$sets['fieldset']);
				$_fieldset=(!empty($_fieldset)?$_fieldset:$_field);
				if(empty($_fieldset)) break;
				$_tmp=$_tmp." ".$_fieldset;
				$_query=(isset($sets['query'])?trim($sets['query']):"");
				if(!empty($sets['query'])){
					//支持搜索多个值
					if(preg_match("/\|/i",$sets['query'])){
						$tmpstr='';
						$rows=explode("|",$sets['query']);
						$n=0;
						foreach($rows as $_v){
							$_v=trim($_v);
							if(strlen($_v)>0){
								$tmpstr=$tmpstr.",".$this->DB->getsql($_v,$_type);
							}
							$n++;
							if($n>=6) break;
						}
						if(!empty($tmpstr)){
							$tmpstr=substr($tmpstr,1);
							$_tmp=$_tmp." IN (".$tmpstr.")";
						}
					}
					else if(strlen($_query)>0){
						$_tmp=$_tmp." = ".$this->DB->getsql($_query,$_type); //直接使用
					}
				}
				else{
					$_tmp=$_tmp." = ".$this->DB->getsql($_value,$_type); //直接使用
				}
				break;

			}

			if(!empty($_tmp)){
				$sql=$sql." and ".$_tmp;
			}

		}
		return $sql;

	}

	//处理数值用于写入数据表（可能是整数，可能是小数）
	protected function formatNum($value,$formart=0){
		$value=preg_replace('/\s/','',$value);
		$value=str_replace(',','',trim($value));
		if(!preg_match('/^(-[0-9]|[0-9])[0-9]*(\.[0-9]+)?$/',$value)) $value='';
		if($formart) $value=0+$value;
		return $value;
	}

} //END class

}//END __CLASS__TB__
?>