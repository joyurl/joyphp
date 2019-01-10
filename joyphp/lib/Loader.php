<?php

namespace joyphp;


class Loader
{
    /**
     * @var array ʵ������
     */
    protected static $instance = [];

    /**
     * @var array ����ӳ��
     */
    protected static $map = [];
    
    /**
     * @var array �����ռ�Ŀ¼ӳ��
     */
    protected static $mapDirs = [];
    
    public static function register($autoload = null)
    {
        // ע��ϵͳ�Զ�����
        spl_autoload_register($autoload ?: '\\joyphp\\Loader::autoLoad', true, true);
        
        // ע�������ռ䶨��
        self::addMapDir ([
            'joyphp'    => LIB_PATH,
            'trait'   => TRAIT_PATH,
        ]);
    }
    
    public static function autoLoad($class)
    {

        if ($file = self::parseFile($class)) {
            
            // �� Win �����ϸ����ִ�Сд
            if (IS_WIN || pathinfo($file, PATHINFO_FILENAME) == pathinfo(realpath($file), PATHINFO_FILENAME)) {
                //echo '<br>',pathinfo($file, PATHINFO_FILENAME),':',pathinfo(realpath($file), PATHINFO_FILENAME);exit;
                return include($file);
            }
        }

        return false;
    }
    
    /**
     * ����ļ�
     * @access private
     * @param  string $class ����
     * @return bool|string
     */
    private static function parseFile($class)
    {
        // ���ӳ��
        if (!empty(self::$map[$class])) {
            return self::$map[$class];
        }
        //echo $class;
        // ���� PSR-4
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
        // �Ҳ���������ӳ��Ϊ false ������
        return self::$map[$class] = false;
    }
    
    private static function addMapDir($arr){
        if(is_array($arr)){
            self::$mapDirs = array_merge(self::$mapDirs, $arr);
        }
    }
    
}