<?php


namespace joyphp;


class Cookie
{

    //@inistat: false 未初始化 ; true  已经初始化
    protected static $inistat   = false;

    protected static $instance  = null;
    

    //cookie配置
    protected static $config = [

        'path'      => '/'
        ,'expire'         => 0
        //定义位置
        ,'domain'         => ''
        ,'secure'         => false
        ,'httponly'       => false
    ];


    public function __construct($config = []){
        self::init($config);
        self::$instance = $this;
    }

    public function init($config = [])
    {
        self::$config = array_merge(self::$config, $config);

        if (!empty(self::$config['httponly'])) {
            ini_set('session.cookie_httponly', 1);
        }

        self::$inistat = true;
    }

    //赋值
    public function __set($name , $value)
    {
        if($value == null){
            return self:del($name);
        }
        return self::set($name , $value);
    }

    //读取内容
    public function __get($name)
    {
        return self::get($name);
    }

    //返回配置信息
    public static function config($key)
    {
        if(!empty($key)) {
            return self::$config[$key];
        }
        return  self::$config;
    }

    /**
     * 初始化
     * @param array $config 参数
     * @return cookie
     */
    public static function instance($config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    /**
     * 赋值
     * @param string $name 名称
     * @param string $value 值，只允许数字、字符串、对象、数组
     * @param string $expire 有效期，若为null则使用默认设置
     * @return str|array|object 
     */
    public static function set($name, $value = '',$expire = null)
    {
        (false === self::$inistat) && self::init();
        $conf = self::$config;
        
        $valueType=strtolower(gettype($value));
        // 设置 cookie
        if (in_array($valueType,Array('array','object'))) {
            if(function_exists('json_encode_')){
                array_walk_recursive($value, 'self::jsonFormats', 'encode');
                $value = json_encode($value);
            }
            else {
                $value = serialize($value);
            }
        }
        else if(in_array($valueType,Array('string','integer','boolean','double','float') ) ){
            $value = strval($value);
        }
        else {
            return false;
        }
        if(is_null($expire)){
            $expire = !empty($conf['expire']) ?  $_SERVER['REQUEST_TIME'] + intval($conf['expire']) : 0;
            
        }
        else{
            $expire = intval($expire);
        }

        setcookie($name, $value, $expire, $conf['path'], $conf['domain'], $conf['secure'], $conf['httponly']);
        $_COOKIE[$name] = $value;
        return true;
    }

    /**
     * 取值
     * @param string $name 名称
     * @param string $type 定义返回值类型：null 原始值; | false :对象;| true 数组
     * @return str|array|object 
     */
    public static function get($name = '', $type = null)
    {
        (false === self::$inistat) && self::init();

        if('' == $name){
            return $_COOKIE;
        }
        if(!isset($_COOKIE[$name])){
            return null;
        }

        $value = $string = $_COOKIE[$name];
        if(!is_null($type)){
            if(false !== $type) $type = true;
            if(function_exists('json_encode_')){
                $value = json_decode($string,$type);
                if(!is_null($value)){
                    array_walk_recursive($value, 'self::jsonFormats', 'decode');
                }
            }
            else{
                $value = unserialize($string);
                if(false === $value ){
                    $value = null;
                }
            }
            
            if(is_null($value) and $type==true){
                $value =Array($string);
            }
            else if(is_null($value)){
                $value =(object) $string;
            }
        }

        return $value;
    }

    /**
     * 清除指定值 
     * @param string $name 名称
     * @return bool
     */
    public static function del($name = ''){
        (false === self::$inistat) && self::init();
        if('' == $name or is_null($name)) return false;

        if(empty($_COOKIE)) {
            return true;
        }
        if(!isset($_COOKIE[$name])) {
            return true;
        }
        
        $conf = self::$config;
        $t=$_SERVER['REQUEST_TIME'] - 3600;
        if(setcookie($name, '', $t, $conf['path'], $conf['domain'], $conf['secure'], $conf['httponly']) ){
            unset($_COOKIE[$name]);
            return true;
        }
        return false;
    }

    /**
     * 清除所有值 
     * @param string $name 名称
     * @param string $prefix 清除批定前缀的值 
     * @return bool
     */
    public static function clear($prefix = null)
    {
        (false === self::$inistat) && self::init;

        if(empty($_COOKIE)) return true;
        $conf = self::$config;

        if($prefix){
            $prefix =strval($prefix);
        }
        $t=$_SERVER['REQUEST_TIME'] - 3600;
        foreach ($_COOKIE as $_key => $_val ){

            if(!empty($prefix) and 0 !== strpos($_key, $prefix)){
                continue;
            }

            $ret=setcookie($_key, '', $t, $conf['path'], $conf['domain'], $conf['secure'], $conf['httponly']);

            if(!$ret){
                return false;
            }
            unset($_COOKIE[$_key]);
        }
        return true;

    }

    //判断是否存在
    public static function exists($name){
        
        (false === self::$inistat) && self::init();
        return isset($_COOKIE[$name]);
    }

    public static function jsonFormats(&$val, $key, $type = 'encode')
    {
        if (!empty($val) && true !== $val) {
            $val = 'decode' == $type ? urldecode($val) : urlencode($val);
        }
    }
    
}

