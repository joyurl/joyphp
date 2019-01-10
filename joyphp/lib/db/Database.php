<?php


namespace joyphp\db;

use PDO;
use PDOStatement;
use joyphp\Db;

abstract class Database
{
    // ���ݿ����Ӳ�������
    protected $config = Array(
        // ���ݿ�����
        'type'            => '',
        // ��������ַ
        'hostname'        => '',
        // ���ݿ���
        'database'        => '',
        // �û���
        'username'        => '',
        // ����
        'password'        => '',
        // �˿�
        'hostport'        => '',
        // ����dsn
        'dsn'             => '',
        // ���ݿ����Ӳ���
        'params'          => Array(),
        // ���ݿ����Ĭ�ϲ���utf8
        'charset'         => 'utf8',
        // ���ݿ��ǰ׺
        'prefix'          => '',
        // ���ݿ����ģʽ
        'debug'           => false,
        // ���ݿⲿ��ʽ:0 ����ʽ(��һ������),1 �ֲ�ʽ(���ӷ�����)
        'deploy'          => 0,
        // ���ݿ��д�Ƿ���� ����ʽ��Ч
        'rw_separate'     => false,
        // ��д����� ������������
        'master_num'      => 1,
        // ָ���ӷ��������
        'slave_no'        => '',
        // �Ƿ��ϸ����ֶ��Ƿ����
        'fields_strict'   => true,
        // ���ݷ�������
        'result_type'     => PDO::FETCH_ASSOC,
        // ���ݼ���������
        'resultset_type'  => 'array',
        // �Զ�д��ʱ����ֶ�
        'auto_timestamp'  => false,
        // ʱ���ֶ�ȡ�����Ĭ��ʱ���ʽ
        'datetime_format' => 'Y-m-d H:i:s',
        // �Ƿ���Ҫ����SQL���ܷ���
        'sql_explain'     => false,
        // �Ƿ���Ҫ��������
        'break_reconnect' => false,
    );
    
    /** @var PDO[] ���ݿ�����ID ֧�ֶ������ */
    protected $links = Array();

    /** @var PDO ��ǰ����ID */
    protected $linkID;
    protected $linkRead;
    protected $linkWrite;
    // ���ػ���Ӱ���¼��
    protected $numRows = 0;
    
    // PDO���Ӳ���
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        //PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'latin1\'',
         
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];
    
    // �󶨲���
    protected $bind = Array();

    private  $__method_info=Array("name"=>"��������","intro"=>"����˵��");
    private  $__para_input =Array();//�������˵��
    private  $__para_output=Array();//�������˵��

    /**
     * ���캯�� ��ȡ���ݿ�������Ϣ
     * @access public
     * @param array $config ���ݿ���������
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }
    
    /**
     * ����pdo���ӵ�dsn��Ϣ
     * @access protected
     * @param array $config ������Ϣ
     * @return string
     */
    abstract protected function parseDsn($config);
    
    /**
     * �������ݿⷽ��
     * @access public
     * @param array         $config ���Ӳ���
     * @param integer       $linkNum �������
     * @param array|bool    $autoConnection �Ƿ��Զ����������ݿ⣨���ڷֲ�ʽ��
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
            // ���Ӳ���
            if (isset($config['params']) && is_array($config['params'])) {
                $params = $config['params'] + $this->params;
            } else {
                $params = $this->params;
            }
            // ���ݷ�������
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
                    // ��¼���ݿ�������Ϣ
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
     * ��ʼ�����ݿ�����
     * @access protected
     * @param boolean $master �Ƿ���������
     * @return void
     */
     
    protected function init($master = true)
    {
        if (!empty($this->config['deploy'])) {
            // ���÷ֲ�ʽ���ݿ�
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
            // Ĭ�ϵ����ݿ�
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

        // ��¼SQL���
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$queryTimes++;
        try {
            // ���Կ�ʼ
            //$this->debug(true);

            // �ͷ�ǰ�εĲ�ѯ���
            if (!empty($this->PDOStatement)) {
                $this->free();
            }
            // Ԥ����
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            /*
            // �Ƿ�Ϊ�洢���̵���
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // ������
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            */
            // ִ�в�ѯ
            $this->PDOStatement->execute();
            // ���Խ���
            //$this->debug(false);
            // ���ؽ����
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
     * ִ�����
     * @access public
     * @param string        $sql sqlָ��
     * @param array         $bind ������
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

        // ��¼SQL���
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$executeTimes++;
        try {
            // ���Կ�ʼ
            $this->debug(true);

            //�ͷ�ǰ�εĲ�ѯ���
            if (!empty($this->PDOStatement) && $this->PDOStatement->queryString != $sql) {
                $this->free();
            }
            // Ԥ����
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            /*
            // �Ƿ�Ϊ�洢���̵���
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // ������
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            */
            // ִ�����
            $this->PDOStatement->execute();
            // ���Խ���
            $this->debug(false);

            $this->numRows = $this->PDOStatement->rowCount();
            return $this->numRows;
        }
        catch (Exception $e) {
                throw $e;
            }
    }

    
    /**
     * �ͷŲ�ѯ���
     * @access public
     */
    public function free()
    {
        $this->PDOStatement = null;
    }
    
    /**
     * ������ݼ�����
     * @access protected
     * @param bool   $pdo �Ƿ񷵻�PDOStatement
     * @param bool   $procedure �Ƿ�洢����
     * @return PDOStatement|array
     */
    protected function getResult($pdo = false, $procedure = false)
    {
        if ($pdo) {
            // ����PDOStatement������
            return $this->PDOStatement;
        }
        if ($procedure) {
            // �洢���̷��ؽ��
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
            // ���Ӳ���
            if (isset($config['params']) && is_array($config['params'])) {
                $params = $config['params'] + $this->params;
            } else {
                $params = $this->params;
            }
            // ��¼��ǰ�ֶ����Դ�Сд����
            $this->attrCase = $params[PDO::ATTR_CASE];

            // ���ݷ�������
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
                    // ��¼���ݿ�������Ϣ
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
     * ִ�в�ѯ �������ݼ�
     * @access public
     * @param string        $sql sqlָ��
     * @param array         $bind ������
     * @param bool          $master �Ƿ�����������������
     * @param bool          $pdo �Ƿ񷵻�PDO����
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

        // ��¼SQL���
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$queryTimes++;
        try {
            // ���Կ�ʼ
            $this->debug(true);

            // �ͷ�ǰ�εĲ�ѯ���
            if (!empty($this->PDOStatement)) {
                $this->free();
            }
            // Ԥ����
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            // �Ƿ�Ϊ�洢���̵���
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // ������
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            // ִ�в�ѯ
            $this->PDOStatement->execute();
            // ���Խ���
            $this->debug(false);
            // ���ؽ����
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
     * ִ�����
     * @access public
     * @param string        $sql sqlָ��
     * @param array         $bind ������
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

        // ��¼SQL���
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        Db::$executeTimes++;
        try {
            // ���Կ�ʼ
            $this->debug(true);

            //�ͷ�ǰ�εĲ�ѯ���
            if (!empty($this->PDOStatement) && $this->PDOStatement->queryString != $sql) {
                $this->free();
            }
            // Ԥ����
            if (empty($this->PDOStatement)) {
                $this->PDOStatement = $this->linkID->prepare($sql);
            }
            // �Ƿ�Ϊ�洢���̵���
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // ������
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }
            // ִ�����
            $this->PDOStatement->execute();
            // ���Խ���
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
