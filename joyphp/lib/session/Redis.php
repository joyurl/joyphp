<?php

namespace joyphp\session;

/*
public bool close ( void )
public string create_sid ( void )
public bool destroy ( string $session_id )
public int gc ( int $maxlifetime )
public bool open ( string $save_path , string $session_name )
public string read ( string $session_id )
public bool write ( string $session_id , string $session_data )
*/

use \SessionHandler;

class redis extends SessionHandler
{
    //var @handler 状态：null 未连接 ； true 已连接 ；false 不可用
    protected $handler = null;
    protected $config  = [
        'host'       => '127.0.0.1', // redis主机
        'port'       => 6379, // redis端口
        'password'   => 'red12345', // 密码
        'select'     => 0, // 操作库
        'expire'     => 0, // 有效期(秒)
        'timeout'    => 0, // 超时时间(秒)
        'pconnect'   => true, // 是否长连接
    ];

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    //PHP >= 5.5.1
    public function create_sid(){
        $str='1234567890abcdefghijkmnpqrstuvwxy';
        $y=date('Y');
        $y=$y%2000%20+0;
        $m=date('n')+10;
        $d=date('j')+0;
        $h=0+intval(date('H'));
        $i=0+intval(intval(date('i'))/2);
        $s=0+intval(intval(date('s'))/2);
        $id=substr($str,$y,1);
        $id=$id.substr($str,$m,1);
        $id=$id.substr($str,$d,1);
        $id=$id.substr($str,$h,1);
        $id=$id.substr($str,$i,1);
        $id=$id.substr($str,$s,1);
        for($x=0;$x<10;$x++){
            $j=rand(0,32);
            $id=$id.substr($str,$j,1);
        }
        return $id;
    }

    public function open($savePath, $sessName)
    {
        // 检测php环境
        if (!extension_loaded('redis')) {
            $this->handler=false;
            throw new \Exception('not support redis');
        }

        $this->handler = new \Redis();
        // 建立连接
        if($this->config['pconnect']){
            $conn=$this->handler->pconnect($this->config['host'], $this->config['port'], $this->config['timeout']);
        }
        else{
            $conn=$this->handler->connect($this->config['host'], $this->config['port'], $this->config['timeout']);
        }
        if(!$conn){
            $this->handler=false;
        }
        if ($this->handler && '' != $this->config['password']) { $this->handler->auth($this->config['password']);}

        if ($this->handler && 0 != $this->config['select']) { $this->handler->select($this->config['select']); }

        return true;
    }


    public function close()
    {
        $this->gc(ini_get('session.gc_maxlifetime'));
        $this->handler && $this->handler->close();
        $this->handler = null;
        return true;
    }

    public function read($session_id)
    {
        //echo "$session_id<br />";
        //echo $this->handler->get($session_id);
        if(false === $this->handler) return null;
        return (string) $this->handler->get($session_id);
    }

    public function write($session_id, $session_data)
    {
        if(false === $this->handler) return false;
        if ($this->config['expire'] > 0) {
            $res=$this->handler->setex($session_id, $this->config['expire'], $session_data);
        } else {
            $res=$this->handler->set($session_id, $session_data);
        }
        return $res;
    }

    public function destroy($session_id)
    {
        if(false === $this->handler) return false;
        return $this->handler->delete($session_id) > 0;
    }

    public function gc($sessMaxLifeTime)
    {
        return true;
    }

}
