<?php


namespace joyphp\db;

use PDO;
use PDOStatement;
use joyphp\Db;

abstract class Database
{
    // 数据库连接参数配置
    protected $config = Array(
        // 数据库类型
        'type'            => '',
        // 服务器地址
        'hostname'        => '',
        // 数据库名
        'database'        => '',
        // 用户名
        'username'        => '',
        // 密码
        'password'        => '',
        // 端口
        'hostport'        => '',
        // 连接dsn
        'dsn'             => '',
        // 数据库连接参数
        'params'          => Array(),
        // 数据库编码默认采用utf8
        'charset'         => 'utf8',
        // 数据库表前缀
        'prefix'          => '',
        // 数据库调试模式
        'debug'           => false,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'          => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'     => false,
        // 读写分离后 主服务器数量
        'master_num'      => 1,
        // 指定从服务器序号
        'slave_no'        => '',
        // 是否严格检查字段是否存在
        'fields_strict'   => true,
        // 数据返回类型
        'result_type'     => PDO::FETCH_ASSOC,
        // 数据集返回类型
        'resultset_type'  => 'array',
        // 自动写入时间戳字段
        'auto_timestamp'  => false,
        // 时间字段取出后的默认时间格式
        'datetime_format' => 'Y-m-d H:i:s',
        // 是否需要进行SQL性能分析
        'sql_explain'     => false,
        // 是否需要断线重连
        'break_reconnect' => false,
    );
    
    /** @var PDO[] 数据库连接ID 支持多个连接 */
    protected $links = Array();

    /** @var PDO 当前连接ID */
    protected $linkID;
    protected $linkRead;
    protected $linkWrite;
    // 返回或者影响记录数
    protected $numRows = 0;
    
    // PDO连接参数
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        //PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'latin1\'',
         
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];
    
    // 绑定参数
    protected $bind = Array();

    private  $__method_info=Array("name"=>"方法名称","intro"=>"作用说明");
    private  $__para_input =Array();//输入参数说明
    private  $__para_output=Array();//输出参数说明

    /**
     * 构造函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }
    
    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    abstract protected function parseDsn($config);
    
    /**
     * 连接数据库方法
     * @access public
     * @param array         $config 连接参数
     * @param integer       $linkNum 连接序号
     * @param array|bool    $autoConnection 是否自动连接主数据库（用于分布式）
     * @return PDO
     * @throws Exception
     */
    public function connect(array $config = Array(), $linkNum = 0, $autoConnection = false)
    {
        if (!isset($this->links[$linkNum])) {
            
            if (!$config) {
                $config = $this->config;
            } else {
                $config = array_merge($this->config, $config);
            }
            // 连接参数
            if (isset($config['params']) && is_array($config['params'])) {
                $params = $config['params'] + $this->params;
            } else {
                $params = $this->params;
            }
            // 数据返回类型
            if (isset($config['result_type'])) {
                $this->fetchType = $config['result_type'];
            }
            try {
                if (empty($config['dsn'])) {
                    $config['dsn'] = $this->parseDsn($config);
                }
                if ($config['debug']) {
                    $startTime = microtime(true);
                }
                $this->links[$linkNum] = new PDO($config['dsn'], $config['username'], $config['password'], $params);
                if ($config['debug']) {
                    // 记录数据库连接信息
                    //Log::record('[ DB ] CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn'], 'sql');
                }
            } catch (Exception $e) {
                throw $e;
                /*
                if ($autoConnection) {
                    Log::record($e->getMessage(), 'error');
                    return $this->connect($autoConnection, $linkNum);
                } else {
                    throw $e;
                }
                */
            }
        }
        return $this->links[$linkNum];
    }
    
    /**
     * 初始化数据库连接
     * @access protected
     * @param boolean $master 是否主服务器
     * @return void
     */
     
    protected function init($master = true)
    {
        if (!empty($this->config['deploy'])) {
            // 采用分布式数据库
            if ($master || $this->transTimes) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }
                $this->linkID = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }
                $this->linkID = $this->linkRead;
            }
        } elseif (!$this->linkID) {
            // 默认单数据库
            $this->linkID = $this->connect();
        }
    }
    
    /*
     *public 
     */
    public function query($sql, $bind = Array(), $master = false, $pdo = false)
    {
        $this->init($master);
        if (!$this->linkID) {
            return false;
        }

        // 记录SQL语句
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$queryTimes++;
        try {
            // 调试开始
            //$this->debug(true);

            // 释放前次的查询结果
            if (!empty($this->PDOStatement)) {
                $this->free();
            }
            // 预处理
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            /*
            // 是否为存储过程调用
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // 参数绑定
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            */
            // 执行查询
            $this->PDOStatement->execute();
            // 调试结束
            //$this->debug(false);
            // 返回结果集
            return $this->getResult($pdo, $procedure);
        }
        catch (Exception $e) {
                throw $e;
            }
        /*
        catch (\PDOException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw new PDOException($e, $this->config, $this->getLastsql());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw $e;
        }
        */
    }

    /**
     * 执行语句
     * @access public
     * @param string        $sql sql指令
     * @param array         $bind 参数绑定
     * @return int
     * @throws PDOException
     * @throws \Exception
     */
    public function execute($sql, $bind = Array())
    {
        $this->init(true);
        if (!$this->linkID) {
            return false;
        }

        // 记录SQL语句
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$executeTimes++;
        try {
            // 调试开始
            $this->debug(true);

            //释放前次的查询结果
            if (!empty($this->PDOStatement) && $this->PDOStatement->queryString != $sql) {
                $this->free();
            }
            // 预处理
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            /*
            // 是否为存储过程调用
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // 参数绑定
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            */
            // 执行语句
            $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);

            $this->numRows = $this->PDOStatement->rowCount();
            return $this->numRows;
        }
        catch (Exception $e) {
                throw $e;
            }
    }

    
    /**
     * 释放查询结果
     * @access public
     */
    public function free()
    {
        $this->PDOStatement = null;
    }
    
    /**
     * 获得数据集数组
     * @access protected
     * @param bool   $pdo 是否返回PDOStatement
     * @param bool   $procedure 是否存储过程
     * @return PDOStatement|array
     */
    protected function getResult($pdo = false, $procedure = false)
    {
        if ($pdo) {
            // 返回PDOStatement对象处理
            return $this->PDOStatement;
        }
        if ($procedure) {
            // 存储过程返回结果
            return $this->procedure();
        }
        $result        = $this->PDOStatement->fetchAll($this->fetchType);
        $this->numRows = count($result);
        return $result;
    }
    
    
    function connects(array $config = Array(), $linkNum = 0, $autoConnection = false)
    {
        if (!isset($this->links[$linkNum])) {
            if (!$config) {
                $config = $this->config;
            } else {
                $config = array_merge($this->config, $config);
            }
            // 连接参数
            if (isset($config['params']) && is_array($config['params'])) {
                $params = $config['params'] + $this->params;
            } else {
                $params = $this->params;
            }
            // 记录当前字段属性大小写设置
            $this->attrCase = $params[PDO::ATTR_CASE];

            // 数据返回类型
            if (isset($config['result_type'])) {
                $this->fetchType = $config['result_type'];
            }
            try {
                if (empty($config['dsn'])) {
                    $config['dsn'] = $this->parseDsn($config);
                }
                if ($config['debug']) {
                    $startTime = microtime(true);
                }
                $this->links[$linkNum] = new PDO($config['dsn'], $config['username'], $config['password'], $params);
                if ($config['debug']) {
                    // 记录数据库连接信息
                    Log::record('[ DB ] CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn'], 'sql');
                }
            } catch (\PDOException $e) {
                if ($autoConnection) {
                    Log::record($e->getMessage(), 'error');
                    return $this->connect($autoConnection, $linkNum);
                } else {
                    throw $e;
                }
            }
        }
        return $this->links[$linkNum];
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string        $sql sql指令
     * @param array         $bind 参数绑定
     * @param bool          $master 是否在主服务器读操作
     * @param bool          $pdo 是否返回PDO对象
     * @return mixed
     * @throws PDOException
     * @throws \Exception
     */
    function querys($sql, $bind = Array(), $master = false, $pdo = false)
    {
        $this->initConnect($master);
        if (!$this->linkID) {
            return false;
        }

        // 记录SQL语句
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$queryTimes++;
        try {
            // 调试开始
            $this->debug(true);

            // 释放前次的查询结果
            if (!empty($this->PDOStatement)) {
                $this->free();
            }
            // 预处理
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            // 是否为存储过程调用
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // 参数绑定
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            // 执行查询
            $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);
            // 返回结果集
            return $this->getResult($pdo, $procedure);
        } catch (\PDOException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw new PDOException($e, $this->config, $this->getLastsql());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw $e;
        }
    }

    /**
     * 执行语句
     * @access public
     * @param string        $sql sql指令
     * @param array         $bind 参数绑定
     * @return int
     * @throws PDOException
     * @throws \Exception
     */
    function executes($sql, $bind = Array())
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }

        // 记录SQL语句
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$executeTimes++;
        try {
            // 调试开始
            $this->debug(true);

            //释放前次的查询结果
            if (!empty($this->PDOStatement) && $this->PDOStatement->queryString != $sql) {
                $this->free();
            }
            // 预处理
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            // 是否为存储过程调用
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // 参数绑定
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            // 执行语句
            $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);

            $this->numRows = $this->PDOStatement->rowCount();
            return $this->numRows;
        } catch (\PDOException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->execute($sql, $bind);
            }
            throw new PDOException($e, $this->config, $this->getLastsql());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->execute($sql, $bind);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->execute($sql, $bind);
            }
            throw $e;
        }
    }

}
