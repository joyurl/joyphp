<?php



namespace joyphp;

class Db
{
    /**
     * @var Connection[] 数据库连接实例
     */
    private static $instance = [];

    /**
     * @var int 查询次数
     */
    public static $queryTimes = 0;

    /**
     * @var int 执行次数
     */
    public static $executeTimes = 0;
    
    /*
     * 数据库驱动加载
     */
    public static function init($config = [], $reconnect = false)
    {
        if (false === $reconnect) {
            $reconnect = md5(serialize($config));
        }

        if (true === $reconnect || !isset(self::$instance[$reconnect])) {
            // 解析连接参数 支持数组和字符串
            $options = self::parseConfig($config);
            if (empty($options['type'])) {
                //throw new \InvalidArgumentException('Undefined db type');
            }
            //echo "<br>init:";print_r($options);
            $class = false !== strpos($options['type'], '\\') ?
            $options['type'] :
            '\\joyphp\\db\\' . ucwords('db_'.$options['type']);

            if (true === $reconnect) {
                $reconnect = md5(serialize($config));
            }

            self::$instance[$reconnect] = new $class($options);
        }

        return self::$instance[$reconnect];
    }

    /**
     * 数据库连接参数解析
     * @access private
     * @param  mixed $config 连接参数
     * @return array
     */
    private static function parseConfig($config)
    {
        if (empty($config)) {
            $config = Config::get('database');
        } elseif (is_string($config) && false === strpos($config, '/')) {
            $config = Config::get($config); // 支持读取配置参数
        }

        return $config;
    }

    /**
     * 调用驱动类的方法
     * @access public
     * @param  string $method 方法名
     * @param  array  $params 参数
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::init(), $method], $params);
    }
    
    
}

