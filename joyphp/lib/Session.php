<?php

namespace joyphp;



class Session
{

    protected static $instance  = null;
    protected static $inistat   = null;

    protected static $prefix     = '';

    //Session配置
    protected static $config = [
        //驱动方式 支持redis memcache，为空时为PHP自带的文件方式保存session
        'driver'         => '', 
        //定义session_id变量名称，默认为PHPSESSID
        'var_session_id' => '',
        //定义session保存位置
        'save_path'      => '',
        'expire'         => 0,
        // 是否在需要时自动开启 SESSION，如果不为true，则需要先使用session::start(); 后session才能正常工作。
        'auto_start'     => true
    ];

    public function __construct($config = [])
    {
        //先初始化
        if (is_null(self::$inistat)) {
            self::init($config);
        }
        //未启动时则启动
        if (false === self::$inistat) {
            (true !== self::status()) && session_start();
            self::$inistat = true;
        }

    }

    /**
     * 初始化
     * @access public
     * @param array $options 参数
     * @return \think\Request
     */
    public static function instance($config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    //session.use_cookies=0时，需要设置use_trans_sid=1,use_only_cookies=0
    public static function init(array $config = [])
    {
        self::$config = array_merge(self::$config, $config);
        $config=self::$config;

        $sess_name=session_name();

        //是否需要自动启动
        $startset=false;
        if (!empty($config['auto_start'])) {
            $startset = true;
        }
        $name = (!empty($config['var_session_id']) ? $config['var_session_id'] : $sess_name);
 
        //使用配置中定义的固定session_id
        if (isset($config['id']) && !empty($config['id']) && preg_match('/^[-0-9a-z,]+$/i',$config['id'])) {
            session_id($config['id']);
        }
        //从GET/POST或Cookie中取得指定name的session_id,  use_trans_sid=0或1均可使用
        else if (!empty($name) && isset($_REQUEST[$name]) && preg_match('/^[-0-9a-z,]+$/i',$_REQUEST[$name]) && $name!=$sess_name ) 
        {
            session_id($_REQUEST[$name]);
        }


        //在需要时更改
        if (isset($config['var_session_id']) && preg_match('/^[-_0-9a-z]+$/i',$config['var_session_id'])
            && $config['var_session_id']!=$sess_name ) {
            session_name($config['var_session_id']);
        }

        //设置path（仅对文件模式有效），可以将不同的网站设置不同的path
        if (!empty($config['save_path'])) {
            session_save_path($config['save_path']);
        }

        //设置session生命周期
        if (isset($config['expire']) and function_exists("ini_set")) {
            ini_set('session.gc_maxlifetime', $config['expire']);
            ini_set('session.cookie_lifetime', $config['expire']);
        }


        if(!empty($config['driver'])){
            $class = false !== strpos($config['driver'], '\\') ? $config['driver'] : '\\joyphp\\session\\' . ucwords($config['driver']);

            if (!class_exists($class) || !session_set_save_handler(new $class($config))) {
                //die("Error class: ".$class);
                $startset = null;
                throw new \Exception('error session handler:' . $class, $class);
            }
        }

        //echo "sessionname=",$name,",session_name=",$sess_name,"<br>";
        //自动启动
        if (true === $startset) {
            (true !== self::status()) && session_start();
            self::$inistat = true;
        } else if(false === $startset) {
            self::$inistat = false;
        }
        //echo ";auto=",$startset,"<br>";
    }

    //自动初始化
    private static function init_start(){
        if (is_null(self::$inistat)) {
            self::init();
        }
    }

    //检查sessions状态，
    public static function status(){
        //session_status()需要PHP >= 5.4.0
        if(function_exists("session_status"))
        {
            return (PHP_SESSION_ACTIVE === session_status() ? true : false);
        }
        else{
            return (ini_get('session.auto_start')? true : false);
        }
    }

    //手动始化并启动
    public static function start(array $config = []){
        //先初始化
        if (is_null(self::$inistat)) {
            self::init($config);
        }

        //未启动时则启动
        if (false === self::$inistat) {
            (true !== self::status()) && session_start();
            self::$inistat = true;
        }
    }

    public static function set($name, $value = '')
    {
        empty(self::$inistat) && self::init_start();
        if(true !== self::$inistat){
            return ;
        }
        if (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            $_SESSION[$name1][$name2] = $value;
        } else {
            $_SESSION[$name] = $value;
        }
    }

    public static function get($name = '')
    {
        empty(self::$inistat) && self::init_start();
        if(true !== self::$inistat){
            return ;
        }

        if('' == $name){
            return $_SESSION;
        }

        $tmp = $_SESSION;
        if (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            $value = isset($tmp[$name1][$name2]) ? $tmp[$name1][$name2] : null;
        } else {
            $value = isset($tmp[$name]) ? $tmp[$name] : null;
        }
    }

    public static function clear($name = null)
    {
        empty(self::$inistat) && self::init_start();
        if(true !== self::$inistat){
            return false;
        }

        if ($name) {
            unset($_SESSION[$name]);
        } else {
            $_SESSION = [];
        }
    }

    public static function exists($name, $prefix = null){
        empty(self::$inistat) && self::init_start();
        if(true !== self::$inistat){
            return false;
        }
        if (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            $value = isset($_SESSION[$name1][$name2]) ? true : false;
        } else {
            $value = isset($_SESSION[$name]) ? true : false;
        }
        return $value;
    }


    public static function iniget($name){
        if($name=='session_name'){
            return session_name();
        }
        else if($name=='session_id'){
            return session_id();
        }
        else if($name=='session_status'){
            return session_status();
        }
        else if($name=='session_save_path'){
            return session_save_path();
        }
        else if($name=='driver'){
            return !empty(self::$config['driver']) ? self::$config['driver'] : 'default';
        }
        else if(function_exists("ini_get")){
            return ini_get("session.".$name);
        }
        return null;
    }


    public static function destroy()
    {
        if(true !== self::$inistat){
            return ;
        }

        if (!empty($_SESSION)) {
            $_SESSION = [];
        }
        session_unset();
        session_destroy();
        self::$inistat = null;
    }

}
