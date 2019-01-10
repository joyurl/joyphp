<?PHP



class Calendar 
{
    
    //24节气名
    public $_jieqi = []; 
    //农历设置（从2018年起的）
    protected $yinli = [];
    //阴历名称
    protected $dayStr= [];
    //默认节日
    protected $jieConf=[];
    //农历月份
    protected $yinMonthStr=array("闰","正","二","三","四","五","六","七","八","九","十","冬","腊","月");

    public function __construct()
    {
         $this->_jieqi[1]='小寒';
         $this->_jieqi[2]='大寒';
         
         $this->_jieqi[3]='立春';
         $this->_jieqi[4]='雨水';
         $this->_jieqi[5]='惊蛰';
         $this->_jieqi[6]='春分';
         $this->_jieqi[7]='清明';
         $this->_jieqi[8]='谷雨';
         
         $this->_jieqi[9]='立夏';
         $this->_jieqi[10]='小满';
         $this->_jieqi[11]='芒种';
         $this->_jieqi[12]='夏至';
         $this->_jieqi[13]='小暑';
         $this->_jieqi[14]='大暑';
         
         $this->_jieqi[15]='立秋';
         $this->_jieqi[16]='处暑';
         $this->_jieqi[17]='白露';
         $this->_jieqi[18]='秋分';
         $this->_jieqi[19]='寒露';
         $this->_jieqi[20]='霜降';
         
         $this->_jieqi[21]='立冬';
         $this->_jieqi[22]='小雪';
         $this->_jieqi[23]='大雪';
         $this->_jieqi[24]='冬至';
         
         $this->dayStr=array("null","初一","初二","初三","初四","初五","初六","初七","初八","初九","初十",
                "十一","十二","十三","十四","十五","十六","十七","十八","十九","二十",
                "廿一","廿二","廿三","廿四","廿五","廿六","廿七","廿八","廿九","三十");
         
         //·value[0]=Array('1月1日起始月数','1月1日起始日期','1月1日起始月天数','如果不是12月，则设置12月天数','12月是否为润月')
         //  value[1]=Array('润月数',...1-13月天数);
         $this->yinli[2010]=Array(Array(11,17,30,30,0),array(0,30,29,30,29,30,29  ,29,30,29,30,29,30,0));
         $this->yinli[2011]=Array(Array(11,27,29,30,0),array(0,30,29,30,30,29,30,29,29,30,29,30,29,0));
         $this->yinli[2012]=Array(Array(12,8,29,0,0),array(4,30,29,30,30,29,30,29,30,29,30,29,30,29));
         $this->yinli[2013]=Array(Array(11,20,30,29,0),array(0,30,29,30,29,30,30,29,30,29,30,29,30,0));
         $this->yinli[2014]=Array(Array(12,1,30,0,0),array(9,29,30,29,30,29,30,29,30,30,29,30,29,30));
         $this->yinli[2015]=Array(Array(11,11,29,30,0),array(0,29,30,29,29,30,29,30,30,30,29,30,29,0));
         $this->yinli[2016]=Array(Array(11,22,30,29,0),array(0,30,29,30,29,29,30,29,30,30,29,30,30,0));
         $this->yinli[2017]=Array(Array(12,4,30,0,0),array(6,29,30,29,30,29,29,30,29,30,29,30,30,30));
         $this->yinli[2018]=Array(Array(11,15,30,30,0),Array(0,29,30,29,30,29,29,30,29,30,29,30,30,0));
         $this->yinli[2019]=Array(Array(11,26,30,30,0),Array(0,30,29,30,29,30,29 ,29,30,29,29,30,30,0));
         $this->yinli[2020]=Array(Array(12,7,30,0,0),Array(4,29,30,30,30,29,30,29,29,30,29,30,29,30));

         //节假日设置(放假的在jiaSet中) ,505=>'端午节',815=>'中秋节' 国庆，春节
         $this->jieConf=Array(
             Array(101=>'元旦',214=>'情人节',308=>'妇女节',501=>'劳动节',504=>'青年节',601=>'儿童节',910=>'教师节',1001=>'国庆节',1224=>'平安夜',1225=>'圣诞节')
             ,Array(0=>'除夕',101=>'春节',115=>'元宵',505=>'端午',707=>'七夕',815=>'中秋')
        );
    }
    
    //取出已经设置农历的年的最大值和最小值
    public function get_year_set()
    {
        $arr = $this->yinli;
        $a = key($arr);
        end($arr); 
        $b = key($arr); 
        return Array($a,$b);
    }
    
    //取得年的干支
    public function get_year_info(&$year){
        
         $gan_base=7; //1970年 庚戌年
         $zhi_base =11; //1970年 庚戌年
         //天干
         $gan_set=array("null","甲","乙","丙","丁","戊","己","庚","辛","壬","癸");
          //地支
         $zhi_set=array("null","子（鼠）","丑（牛）","寅（虎）","卯（兔）","辰（龙）",
             "巳（蛇）","午（马）","未（羊）","申（猴）","酉（鸡）","戌（狗）","亥（猪）");

         $year=intval($year);
         if($year<1970) $year=1970 ;
         if($year>2200) $year=2200 ;
         $gan=($year-1970)%10+$gan_base;
         if($gan>10 ) $gan=$gan-10;
         $zhi=($year-1970)%12+$zhi_base;
         if($zhi>12 ) $zhi=$zhi-12;

         return $gan_set[$gan].$zhi_set[$zhi];
    }
    

    /*
     * @year = 公历年 @month= 公历月 
     * @jiaSet[月][日]: 设置节假日或换休的上班日=Array(状态{0-一般节日 1 周末上班,2-放假,3 放假且高速免费},名称);
     * @jieSet[月][日]: 设置24节气日期，值为所属编号
     */ 
    public function getMonthData($year,$month,$jiaSet=[],$jieSet=[]){
        $ret=[];
        $year=intval($year);
        if($year<1970) $year=1970 ;
        if($year>2200) $year=2200 ;
        $month=intval($month);
        if($month<1 or $month>12) {
            $month=1;
        }
        $daytime    =strtotime($year.'-'.$month.'-01 12:00:00');
        $first =0+date('w',$daytime);//当月1日是星期几
        if($first == 0) 
        {
            $first=7;
        }
        $first=$first-1;
        //1日前空几格(第一天为周一计算)
        $ret[0] = Array('day'=>$first,'yinli','jia'=>0);
        
        $datecount=1+date('z',$daytime);//当月1日是当年第N天哦年
        $daynum = date('t',$daytime); //当月天数
        
        $ymonth=0;
        $yinli_month=0;
        //计算第一天的阴历
        if(isset($this->yinli[$year]))
        {
            $yinli_month=$yinli_month+1;
            $yinliset=$this->yinli[$year][0];
            //上一年还有多少天
            $last_year_day=1+$yinliset[2]+$yinliset[3]-$yinliset[1];
            
            //第一个月直接使用初始值
            if($month==1){
                $ymonth= $yinliset[0];
                $yday  = $yinliset[1];
                $ymax  = $yinliset[2];
            }
            else{
                //仍是上一年(2月才会有这种情况)
                if($datecount<=$last_year_day){
                    $ymonth=12;
                    $ymax = isset($yinliset[3]) ? $yinliset[3] : $yinliset[2];
                    $yday = $ymax-($last_year_day-$datecount);
                }
                else{
                    //
                    $datecount=$datecount-$last_year_day;
                    for($j=1;$j<13;$j++){
                        $max=$this->yinli[$year][1][$j];
                        if($datecount<=$max){
                            $ymonth=$j;
                            $ymax=$max;
                            $yday=$datecount;
                            break;
                        }
                        $datecount=$datecount-$max;
                    }
                }
            }
        }
        if($month==1){
            //echo "$datecount <= $last_year_day  <br> $ymonth, $yday, Max= $ymax";
            //exit;
        }
        $nowday=$yday-1;
        $runyue=0;
        for ($i=1 ; $i<=$daynum; $i++)
        {
            $yinday='';
            //计算农历
            if($yinli_month){
                
                $nowday=$nowday+1;
                
                if($nowday>$ymax){
                    $nowday=1;
                    $ymonth=$ymonth+1;

                    //跨年后设置为一月
                    if($month>1 and $this->yinli[$year][1][0] >0 and $ymonth>13 or $ymonth>12){
                        $ymonth=1;
                    }
                    //更新下一个月的$ymax值
                    if($month==1){
                        if($ymonth==12) {
                            $ymax=$this->yinli[$year][0][3];
                        }
                        else if($ymonth==1){
                            $ymax=$this->yinli[$year][1][1];
                        }
                    }
                    else{
                        $ymax=$this->yinli[$year][1][$ymonth];
                    }
                }
                //echo "<br> ym=  $ymonth ;sm=$showMonth ; yd=$nowday"; 
                //当前农历月份 $month>2是假设没有润一月的情况
                if($this->yinli[$year][1][0] > 0 and $ymonth>$this->yinli[$year][1][0] and $month>2){
                    $showMonth=$ymonth-1;
                }
                else{
                    $showMonth=$ymonth;
                }
                
                //echo "<br> ym=  $ymonth ;sm=$showMonth  ; yd=$nowday"; exit;
                
                if($nowday==1){
                    $yinday='';
                    //echo "<br> ym=  $ymonth  ; yd=$nowday"; exit;
                    //上年显示润月
                    if($month==1 and $ymonth==12 and $this->yinli[$year][0][4]>0){
                        $yinday=$this->yinMonthStr[0].$yinday;
                        $runyue=1;
                    }
                    //当年显示润月
                    else if($this->yinli[$year][1][0]>0 and $this->yinli[$year][1][0]+1==$ymonth){
                        $yinday=$this->yinMonthStr[0].$yinday;
                        $runyue=1;
                    }
                    
                    $yinday=$yinday.$this->yinMonthStr[$showMonth].$this->yinMonthStr[13];
                }
                else{
                    $yinday=$this->dayStr[$nowday];
                }
            }
            $tmp =strtotime($year.'-'.$month.'-'.$i.' 12:00:00');
            $week=0+date('w',$tmp);
            $is_xiu=0;
            $is_work=0;
            $_jie='';
            //参数设置节日
            if(isset($jiaSet[$month]) and isset($jiaSet[$month][$i])){
                $jiaArr=$jiaSet[$month][$i];
                if($jiaArr[0]>=2) $is_xiu=1;
                if($jiaArr[0]==1) $is_work=1;
                else if($jiaArr[0]==3) $is_work=2;
                if(!empty($jiaArr[1])) $_jie=$jiaArr[1];
            }
            else if ($week==0 or $week==6)
            {
                $is_xiu=1;
            }
            //参数未设置时调用默认的节日
            if(empty($_jie)){
                $_jie0=$month*100+$i;
                $_jie1=$showMonth*100+$nowday;
                if(isset($this->jieConf[0][$_jie0])){
                    $_jie=$this->jieConf[0][$_jie0];
                }
                if(isset($this->jieConf[1][$_jie1]) and empty($runyue)){
                    $_jie=$_jie.(!empty($_jie)?'/':'').$this->jieConf[1][$_jie1];
                }
            }
            //除夕
            if(empty($_jie) and $showMonth==12 and $nowday==$ymax and $month<3){
                //die("eee");
                $_jie=$this->jieConf[1][0];
            }
            //24节气
            if(isset($jieSet[$month][$i])){
                $_jie=$_jie.(!empty($_jie)?'/':'').$this->_jieqi[$jieSet[$month][$i]];
            }
            //@ret : day -日期 ; yinli 农历 ; jie节假日;xiu放假;week 星期几 work: 1 周末上班 2 高速免费
            $ret[$i]=Array('day'=>$i,'yinli'=>$yinday,'jie'=>$_jie,'xiu'=>$is_xiu,'week'=>$week,'work'=>$is_work);
        }
        return $ret;
    }
}

//============================

/*


$mten=array("null","甲","乙","丙","丁","戊","己","庚","辛","壬","癸");
//农历地支
$mtwelve=array("null","子（鼠）","丑（牛）","寅（虎）","卯（兔）","辰（龙）",
			   "巳（蛇）","午（马）","未（羊）","申（猴）","酉（鸡）","戌（狗）","亥（猪）");
$yearSet=Array(
2011=>array(0,30,29,30,30,29,30,29,29,30,29,30,29,0,8,4),
2012=>array(4,30,29,30,30,29,30,29,30,29,30,29,30,29,9,5),
2013=>array(0,30,29,30,29,30,30,29,30,29,30,29,30,0,10,6),
2014=>array(9,29,30,29,30,29,30,29,30,30,29,30,29,30,1,7),
2015=>array(0,29,30,29,29,30,29,30,30,30,29,30,29,0,2,8),
2016=>array(0,30,29,30,29,29,30,29,30,30,29,30,30,0,3,9),
2017=>array(6,29,30,29,30,29,29,30,29,30,29,30,30,30,4,10),
2018=>array(0,29,30,29,30,29,29,30,29,30,29,30,30,0,5,11),
2019=>array(0,30,29,30,29,30,29,29,30,29,29,30,30,0,6,12),
2020=>array(4,29,30,30,30,29,30,29,29,30,29,30,29,30,7,1)
);

function getMonthShow($year,$month)
{
	global $jiaSet,$yearSet; 
	if($year<2012) $year=2012;

	//农历月份
	$mStr=array("闰","正","二","三","四","五","六",
				  "七","八","九","十","冬","腊","月");
	//农历日
	$dayStr=array("null","初一","初二","初三","初四","初五","初六","初七","初八","初九","初十",
				"十一","十二","十三","十四","十五","十六","十七","十八","十九","二十",
				"廿一","廿二","廿三","廿四","廿五","廿六","廿七","廿八","廿九","三十");


	//2012-01-01(2011.12.8)
	$mmth=12;
	$mday=8;
	$myear=2011;
	
	$total=0;
	$mtotal=0;
	for ($y=2012; $y<$year; $y++)
	{
		$total+=365;
		if ($y%4==0) $total ++;
	}

	$dt=strtotime($year.'-'.$month.'-01 12:00:00');

	//计算1日的农历日期
	$dnum=1+date('z',$dt);//当年第N天
	$dnum=$total+$dnum; //2012.1.1的第N天

	$n=0;
	//计算农历日期
	for ($j=2011; ($j<=$year and $dnum>1); $j++)
	{
		$nls=$yearSet[$year];
		$myear=$j;
		foreach ($nls as $m => $arr)
		{
			if ($m<1 or $m>13)
			{
				continue;
			}

			if($j==2011) {
				if($m<12) continue;

				for ($i=8; $i<=$yearSet[$j][$m]; $i++)
				{
					$n++;
					$mday=$i;
					if ($n>=$dnum)
					{
						break 3;
					}
				}
			}
			else
			{
				for ($i=1; $i<=$yearSet[$j][$m]; $i++)
				{
					$n++;
					$mmth=$m;
					$mday=$i;
					if ($n>=$dnum)
					{
						break 3;
					}
				}
			}
		}
	}

	//return "($dnum) $myear _ $mmth _ $mday";
	for ($i=2012; $i<=$year; $i++)
	{
		$n++;
		if($n>=$dnum) break;
	}
	
	

	$dw=date('w',$dt)-1;//当月1日是星期几
	if($dw==-1) $dw=6;
	$daymax=date('t',$dt); //当月天数
	$html='
<table border="0" cellspacing="1" cellpadding="0" class="klrili">
	<tr align="center">
	<th width="14%"><b>一</b></th>
	<th width="14%"><b>二</b></th>
	<th width="14%"><b>三</b></th>
	<th width="14%"><b>四</b></th>
	<th width="14%"><b>五</b></th>
	<th width="14%"><b>六</b></th>
	<th width="14%"><b>日</b></th>
	</tr>
	<tr align="center">';

	for ($i=0; $i<$dw ; $i++)
	{
		$html=$html.'
	<td></td>';
	}

	$mday=$mday-1;
	for ($d=1; $d<=$daymax; $d++)
	{
		$mday=$mday+1;
		//echo "$myear _ $mmth : ".$yearSet[$myear][$mmth];exit;
		//跨月了
		if ($mday>$yearSet[$myear][$mmth])
		{
			$mmth=$mmth+1;
			$mday=1;
			//跨年了
			if($yearSet[$myear][$mmth]<28){
				$myear=$myear+1;
				$mmth=1;
			}
		}

		
		$styled='';
		if($i%7==5 or $i%7==6) $styled=' style="color:red;"';
		$classname='';
		$nl='';
		if($mday==1){
			$tag=$mmth;
			if($yearSet[$myear][0]>0 and $mmth>$yearSet[$myear][0]){
				$tag=$tag-1;
			}
			$nl=$mStr[$tag].$mStr[13];
			if($tag=$mmth-1 and $tag==$yearSet[$myear][0]) $nl=$mStr[0].$nl;
		}
		else
		{
			$nl=$dayStr[$mday];
		}
		$arr=Array();
		if (isset($jiaSet[$year][$month][$d]))
		{
			$arr=$jiaSet[$year][$month][$d];
			if (!empty($arr[1]))
			{
				$nl=$arr[1];
				if($arr[0]==3 or $arr[0]==1) {
					$nl='<font color="red">'.$nl.'</font>';
				}
			}
			if($arr[0]==1) {
				$styled=' style="color:red;"';
				$classname=' class="jia"';
			}
			else if ($arr[0]==2) 
			{
				$styled=' style="color:#0000EE;"';
				$classname=' class="ban"';
			}
			else if (strlen($arr[2])>1)
			{
				$classname=' class="jie"';
			}
		}
		$str='<b'.$styled.'>'.$d.'</b><br />'.$nl.'';
		if (strlen($arr[2])>1)
		{
			$str='<a href="'.$arr[2].'"'.(strlen($arr[3]) ?' title="'.$arr[3].'"':'').'><div>'.$str.'</div></a>';
		}

		$html=$html.'
	<td'.$classname.'>'.$str.'</td>';

		$i++;
		if ($i%7==0 and $d<$daymax)
		{
			$html=$html.'</tr>
	<tr align="center">';
		}
	}

	$ds=$i%7;
	for ($j=$ds; ($ds>0 and $j<7); $j++)
	{
		$html=$html.'
	<td></td>';
	}


	$html=$html.'</tr></table>';
	return $html;
}

*/


?>