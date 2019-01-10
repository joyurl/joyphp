<?php


include("./joyphp/extend/Calendar.php");

$Calendar=new Calendar();

$year=intval($_GET['_year']);
if(empty($year)) $year=date('Y');
if($year==2019){
    $jiaSet=Array();
    $jiaSet[1][1]=Array(1,'元旦');
    $jiaSet[2][2]=Array(0,'');
    $jiaSet[2][3]=Array(0,'');
    $jiaSet[2][4]=Array(2,'除夕');
    $jiaSet[2][5]=Array(2,'春节');
    $jiaSet[2][6]=Array(2,'');
    $jiaSet[2][7]=Array(2,'');
    $jiaSet[2][8]=Array(2,'');
    $jiaSet[2][9]=Array(2,'');
    $jiaSet[2][10]=Array(2,'');
    
    $jieSet=Array();
    $jieSet[1][5]=1;
    $jieSet[1][20]=2;
    $jieSet[2][4]=3;
    $jieSet[2][19]=4;
    $jieSet[3][6]=5;
    $jieSet[3][21]=6;
    $jieSet[4][5]=7;
    $jieSet[4][20]=8;
    $jieSet[12][7]=23;
    $jieSet[12][22]=24;
}


$name=$Calendar->get_year_info($year);
$max=$Calendar->get_year_set();
//print_r($max);
$html='';
$html=$html.'
<center>
'.$year.'年 '.$name.'
<br>
<br>
<a href="?_year='.($year-1).'">上一年</a>    　　　　  <a href="?_year='.($year+1).'">下一年</a></center>
<br>
<br>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
<tr align="center">';
for ($i=1; $i<13; $i++)
{
	$html=$html.'
 <td valign="top"><b>'.$year.'年'.$i.'月</b>';
 
	$html=$html.'
<table border="1" cellspacing="1" cellpadding="0" width="380" class="klrili">
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
    $dateSet=$Calendar->getMonthData($year,$i,$jiaSet,$jieSet);
    //echo '<pre>';print_r($dateSet);exit;
    if($dateSet[0]['day']>0){
        $html=$html.str_repeat('<td>&nbsp;</td>',$dateSet[0]['day']);
    }
    $n=0;
    for($j=1;$j<32;$j++){
        if(!isset($dateSet[$j])) {
            break;
        }
        $n++;
        $sets=$dateSet[$j];
        $jie=$sets['jie'];
        $html=$html.'<td><span'.($sets['xiu']?' style="color:red;"':'').'>'.$sets['day'].'</span><br><span style="color:gray;">'.$sets['yinli'].'</style>'.(!empty($jie)?'<br><span style="color:#0055AA;">'.$jie.'</style>':'').'</td>';
        if($sets['week']==0 and isset($dateSet[$j+1])){
            $html=$html.'</tr>
    <tr align="center">';
            $n=0;
        }
    }
    //补空位
    $html=$html.str_repeat('<td>&nbsp;</td>',7-$n);
    $html=$html.'</tr></table>
  </td>';
 
 if ($i%2==0 and $i<12)
 {
	$html=$html.'
 </tr><tr><td colspan="2" height="18"></td></tr><tr align="center">';
 }
}

$html=$html.'</tr></table>';

echo $html;