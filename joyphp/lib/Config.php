<?php

namespace joyphp;



class Config
{
    /**
     * @var array 配置参数
     */
    private static $config = [];
    
    /**
     * 加载配置文件（PHP格式）
     * @access public
     * @param  string $file  配置文件名（支持PHP,ini,XML,JSON四种格式）
     * @param  string $name  配置名（如设置即表示二级配置）
     * @return mixed
     * 
     */
    public static function load($file, $name = '')
    {

        if (is_file($file)) {
            $name = strtolower($name);
            $type = pathinfo($file, PATHINFO_EXTENSION);

            if ('php' == $type) {
                return self::set($name , include $file);
            }
            else if('xml' == $type ){
                return self::set($name , self::loadXml($file));
            }
            else if('ini' == $type){
                return self::set($name , self::loadIni($file));
            }
            else if('json' == $type){
                return self::set($name , self::loadJson($file));
            }
            else {
                return null;
            }
            //return self::parse($file, $type, $name);
        }

        return self::$config;
    }

    /**
     * 设置配置参数 name 为数组则为批量设置
     * @access public
     * @param  string|array $name  配置参数名（支持二级配置 . 号分割）
     * @param  mixed        $value 配置值
     * @return mixed
     */
    public static function set($name, $value = null)
    {

        // 字符串则表示单个配置设置
        if (is_string($name) and !empty($name)) {
            $name = strtolower($name);
            //一级配置
            if (!strpos($name, '.')) {
                if(is_array($value) and isset(self::$config[$name])){
                    $value = array_change_key_case($value);
                    $value = array_merge(self::$config[$name], $value);
                }
                self::$config[$name] = $value;
            } else {
                // 二级配置（不再支持数组合并）
                $keys = explode('.', $name, 2);
                self::$config[$keys[0]][$keys[1]] = $value;
            }

            return $value;
        }

        // 数组则表示批量设置
        if (is_array($name)) {
            $name=array_change_key_case($name);
            return self::$config = array_merge(self::$config, $name);
        }
        else if(empty($name) and is_array($value)){
            $value=array_change_key_case($value);
            return self::$config = array_merge(self::$config, $value);
        }

        // 为空直接返回已有配置
        return self::$config;
    }
    
    
    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param  string $name 配置参数名（支持二级配置 . 号分割）
     * @param  string $range  作用域
     * @return mixed
     */
    public static function get($name = null)
    {
        // 无参数时获取所有
        if (empty($name) && isset(self::$config)) {
            return self::$config;
        }

        // 一级配置直接返回
        $name = strtolower($name);
        if (!strpos($name, '.')) {
            return isset(self::$config[$name]) ? self::$config[$name] : null;
        }

        // 二级配置
        $keys    = explode('.', $name, 2);
        $keys[0] = strtolower($keys[0]);


        return isset(self::$config[$keys[0]][$keys[1]]) ?
            self::$config[$keys[0]][$keys[1]] :
            null;
    }

    public static function loadXml($config){
        if (is_file($config)) {
            $content = simplexml_load_file($config);
        } else {
            $content = simplexml_load_string($config);
        }
        $result = (array) $content;
        foreach ($result as $key => $val) {
            if (is_object($val)) {
                $result[$key] = (array) $val;
            }
        }
        return $result;
    }

    public static function loadJson($config){
        if (is_file($config)) {
            $config = file_get_contents($config);
        }
        $result = json_decode($config, true);
        return $result;
    }

    public static function loadIni($config){
        if (is_file($config)) {
            return parse_ini_file($config, true);
        } else {
            //parse_ini_string: PHP 5 >= 5.3.0
            return parse_ini_string($config, true);
        }
    }
    
}

