<?php

namespace joyphp;


class Loader
{
    /**
     * @var array 实例数组
     */
    protected static $instance = [];

    /**
     * @var array 类名映射
     */
    protected static $map = [];
    
    /**
     * @var array 命名空间目录映射
     */
    protected static $mapDirs = [];
    
    public static function register($autoload = null)
    {
        // 注册系统自动加载
        spl_autoload_register($autoload ?: '\\joyphp\\Loader::autoLoad', true, true);
        
        // 注册命名空间定义
        self::addMapDir ([
            'joyphp'    => LIB_PATH,
            'trait'   => TRAIT_PATH,
        ]);
    }
    
    public static function autoLoad($class)
    {

        if ($file = self::parseFile($class)) {
            
            // 非 Win 环境严格区分大小写
            if (IS_WIN || pathinfo($file, PATHINFO_FILENAME) == pathinfo(realpath($file), PATHINFO_FILENAME)) {
                //echo '<br>',pathinfo($file, PATHINFO_FILENAME),':',pathinfo(realpath($file), PATHINFO_FILENAME);exit;
                return include($file);
            }
        }

        return false;
    }
    
    /**
     * 检查文件
     * @access private
     * @param  string $class 类名
     * @return bool|string
     */
    private static function parseFile($class)
    {
        // 类库映射
        if (!empty(self::$map[$class])) {
            return self::$map[$class];
        }
        //echo $class;
        // 查找 PSR-4
        $class=strtr($class, '\\', DS);
        $tmp=explode(DS,$class);
        $first=$tmp[0];
        $length=strlen($first);
        if(isset(self::$mapDirs[$first])){
            $file=self::$mapDirs[$first].substr($class, $length+1). EXT;
            if (is_file($file)) {
                return $file;
            }
        }
        // 找不到则设置映射为 false 并返回
        return self::$map[$class] = false;
    }
    
    private static function addMapDir($arr){
        if(is_array($arr)){
            self::$mapDirs = array_merge(self::$mapDirs, $arr);
        }
    }
    
}