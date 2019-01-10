<?php

namespace joyphp;



class Config
{
    /**
     * @var array ���ò���
     */
    private static $config = [];
    
    /**
     * ���������ļ���PHP��ʽ��
     * @access public
     * @param  string $file  �����ļ�����֧��PHP,ini,XML,JSON���ָ�ʽ��
     * @param  string $name  �������������ü���ʾ�������ã�
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
     * �������ò��� name Ϊ������Ϊ��������
     * @access public
     * @param  string|array $name  ���ò�������֧�ֶ������� . �ŷָ
     * @param  mixed        $value ����ֵ
     * @return mixed
     */
    public static function set($name, $value = null)
    {

        // �ַ������ʾ������������
        if (is_string($name) and !empty($name)) {
            $name = strtolower($name);
            //һ������
            if (!strpos($name, '.')) {
                if(is_array($value) and isset(self::$config[$name])){
                    $value = array_change_key_case($value);
                    $value = array_merge(self::$config[$name], $value);
                }
                self::$config[$name] = $value;
            } else {
                // �������ã�����֧������ϲ���
                $keys = explode('.', $name, 2);
                self::$config[$keys[0]][$keys[1]] = $value;
            }

            return $value;
        }

        // �������ʾ��������
        if (is_array($name)) {
            $name=array_change_key_case($name);
            return self::$config = array_merge(self::$config, $name);
        }
        else if(empty($name) and is_array($value)){
            $value=array_change_key_case($value);
            return self::$config = array_merge(self::$config, $value);
        }

        // Ϊ��ֱ�ӷ�����������
        return self::$config;
    }
    
    
    /**
     * ��ȡ���ò��� Ϊ�����ȡ��������
     * @access public
     * @param  string $name ���ò�������֧�ֶ������� . �ŷָ
     * @param  string $range  ������
     * @return mixed
     */
    public static function get($name = null)
    {
        // �޲���ʱ��ȡ����
        if (empty($name) && isset(self::$config)) {
            return self::$config;
        }

        // һ������ֱ�ӷ���
        $name = strtolower($name);
        if (!strpos($name, '.')) {
            return isset(self::$config[$name]) ? self::$config[$name] : null;
        }

        // ��������
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

