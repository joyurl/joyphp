<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

class Request
{
    /**
     * @var object ����ʵ��
     */
    protected static $instance;

    protected $method;
    /**
     * @var string ��������Э��Ͷ˿ڣ�
     */
    protected $domain;

    /**
     * @var string URL��ַ
     */
    protected $url;

    /**
     * @var string ����URL
     */
    protected $baseUrl;

    /**
     * @var string ��ǰִ�е��ļ�
     */
    protected $baseFile;

    /**
     * @var string ���ʵ�ROOT��ַ
     */
    protected $root;

    /**
     * @var string pathinfo
     */
    protected $pathinfo;

    /**
     * @var string pathinfo��������׺��
     */
    protected $path;

    /**
     * @var array ��ǰ·����Ϣ
     */
    protected $routeInfo = [];

    /**
     * @var array ��������
     */
    protected $env;

    /**
     * @var array ��ǰ������Ϣ
     */
    protected $dispatch = [];
    protected $module;
    protected $controller;
    protected $action;
    // ��ǰ���Լ�
    protected $langset;

    /**
     * @var array �������
     */
    protected $param   = [];
    protected $get     = [];
    protected $post    = [];
    protected $request = [];
    protected $route   = [];
    protected $put;
    protected $session = [];
    protected $file    = [];
    protected $cookie  = [];
    protected $server  = [];
    protected $header  = [];

    /**
     * @var array ��Դ����
     */
    protected $mimeType = [
        'xml'   => 'application/xml,text/xml,application/x-xml',
        'json'  => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js'    => 'text/javascript,application/javascript,application/x-javascript',
        'css'   => 'text/css',
        'rss'   => 'application/rss+xml',
        'yaml'  => 'application/x-yaml,text/yaml',
        'atom'  => 'application/atom+xml',
        'pdf'   => 'application/pdf',
        'text'  => 'text/plain',
        'image' => 'image/png,image/jpg,image/jpeg,image/pjpeg,image/gif,image/webp,image/*',
        'csv'   => 'text/csv',
        'html'  => 'text/html,application/xhtml+xml,*/*',
    ];

    protected $content;

    // ȫ�ֹ��˹���
    protected $filter;
    // Hook��չ����
    protected static $hook = [];
    // �󶨵�����
    protected $bind = [];
    // php://input
    protected $input;
    // ���󻺴�
    protected $cache;
    // �����Ƿ���
    protected $isCheckCache;

    /**
     * ���캯��
     * @access protected
     * @param array $options ����
     */
    protected function __construct($options = [])
    {
        foreach ($options as $name => $item) {
            if (property_exists($this, $name)) {
                $this->$name = $item;
            }
        }
        if (is_null($this->filter)) {
            $this->filter = Config::get('default_filter');
        }

        // ���� php://input
        $this->input = file_get_contents('php://input');
    }

    public function __call($method, $args)
    {
        if (array_key_exists($method, self::$hook)) {
            array_unshift($args, $this);
            return call_user_func_array(self::$hook[$method], $args);
        } else {
            throw new Exception('method not exists:' . __CLASS__ . '->' . $method);
        }
    }

    /**
     * Hook ����ע��
     * @access public
     * @param string|array  $method ������
     * @param mixed         $callback callable
     * @return void
     */
    public static function hook($method, $callback = null)
    {
        if (is_array($method)) {
            self::$hook = array_merge(self::$hook, $method);
        } else {
            self::$hook[$method] = $callback;
        }
    }

    /**
     * ��ʼ��
     * @access public
     * @param array $options ����
     * @return \think\Request
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }

    /**
     * ����һ��URL����
     * @access public
     * @param string    $uri URL��ַ
     * @param string    $method ��������
     * @param array     $params �������
     * @param array     $cookie
     * @param array     $files
     * @param array     $server
     * @param string    $content
     * @return \think\Request
     */
    public static function create($uri, $method = 'GET', $params = [], $cookie = [], $files = [], $server = [], $content = null)
    {
        $server['PATH_INFO']      = '';
        $server['REQUEST_METHOD'] = strtoupper($method);
        $info                     = parse_url($uri);
        if (isset($info['host'])) {
            $server['SERVER_NAME'] = $info['host'];
            $server['HTTP_HOST']   = $info['host'];
        }
        if (isset($info['scheme'])) {
            if ('https' === $info['scheme']) {
                $server['HTTPS']       = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }
        if (isset($info['port'])) {
            $server['SERVER_PORT'] = $info['port'];
            $server['HTTP_HOST']   = $server['HTTP_HOST'] . ':' . $info['port'];
        }
        if (isset($info['user'])) {
            $server['PHP_AUTH_USER'] = $info['user'];
        }
        if (isset($info['pass'])) {
            $server['PHP_AUTH_PW'] = $info['pass'];
        }
        if (!isset($info['path'])) {
            $info['path'] = '/';
        }
        $options                      = [];
        $options[strtolower($method)] = $params;
        $queryString                  = '';
        if (isset($info['query'])) {
            parse_str(html_entity_decode($info['query']), $query);
            if (!empty($params)) {
                $params      = array_replace($query, $params);
                $queryString = http_build_query($params, '', '&');
            } else {
                $params      = $query;
                $queryString = $info['query'];
            }
        } elseif (!empty($params)) {
            $queryString = http_build_query($params, '', '&');
        }
        if ($queryString) {
            parse_str($queryString, $get);
            $options['get'] = isset($options['get']) ? array_merge($get, $options['get']) : $get;
        }

        $server['REQUEST_URI']  = $info['path'] . ('' !== $queryString ? '?' . $queryString : '');
        $server['QUERY_STRING'] = $queryString;
        $options['cookie']      = $cookie;
        $options['param']       = $params;
        $options['file']        = $files;
        $options['server']      = $server;
        $options['url']         = $server['REQUEST_URI'];
        $options['baseUrl']     = $info['path'];
        $options['pathinfo']    = '/' == $info['path'] ? '/' : ltrim($info['path'], '/');
        $options['method']      = $server['REQUEST_METHOD'];
        $options['domain']      = isset($info['scheme']) ? $info['scheme'] . '://' . $server['HTTP_HOST'] : '';
        $options['content']     = $content;
        self::$instance         = new self($options);
        return self::$instance;
    }

    /**
     * ���û��ȡ��ǰ����Э�������
     * @access public
     * @param string $domain ����
     * @return string
     */
    public function domain($domain = null)
    {
        if (!is_null($domain)) {
            $this->domain = $domain;
            return $this;
        } elseif (!$this->domain) {
            $this->domain = $this->scheme() . '://' . $this->host();
        }
        return $this->domain;
    }

    /**
     * ���û��ȡ��ǰ����URL ����QUERY_STRING
     * @access public
     * @param string|true $url URL��ַ true ��������ȡ
     * @return string
     */
    public function url($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->url = $url;
            return $this;
        } elseif (!$this->url) {
            if (IS_CLI) {
                $this->url = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
                $this->url = $_SERVER['HTTP_X_REWRITE_URL'];
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $this->url = $_SERVER['REQUEST_URI'];
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
                $this->url = $_SERVER['ORIG_PATH_INFO'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
            } else {
                $this->url = '';
            }
        }
        return true === $url ? $this->domain() . $this->url : $this->url;
    }

    /**
     * ���û��ȡ��ǰURL ����QUERY_STRING
     * @access public
     * @param string $url URL��ַ
     * @return string
     */
    public function baseUrl($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->baseUrl = $url;
            return $this;
        } elseif (!$this->baseUrl) {
            $str           = $this->url();
            $this->baseUrl = strpos($str, '?') ? strstr($str, '?', true) : $str;
        }
        return true === $url ? $this->domain() . $this->baseUrl : $this->baseUrl;
    }

    /**
     * ���û��ȡ��ǰִ�е��ļ� SCRIPT_NAME
     * @access public
     * @param string $file ��ǰִ�е��ļ�
     * @return string
     */
    public function baseFile($file = null)
    {
        if (!is_null($file) && true !== $file) {
            $this->baseFile = $file;
            return $this;
        } elseif (!$this->baseFile) {
            $url = '';
            if (!IS_CLI) {
                $script_name = basename($_SERVER['SCRIPT_FILENAME']);
                if (basename($_SERVER['SCRIPT_NAME']) === $script_name) {
                    $url = $_SERVER['SCRIPT_NAME'];
                } elseif (basename($_SERVER['PHP_SELF']) === $script_name) {
                    $url = $_SERVER['PHP_SELF'];
                } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $script_name) {
                    $url = $_SERVER['ORIG_SCRIPT_NAME'];
                } elseif (($pos = strpos($_SERVER['PHP_SELF'], '/' . $script_name)) !== false) {
                    $url = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $script_name;
                } elseif (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) === 0) {
                    $url = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
                }
            }
            $this->baseFile = $url;
        }
        return true === $file ? $this->domain() . $this->baseFile : $this->baseFile;
    }

    /**
     * ���û��ȡURL���ʸ���ַ
     * @access public
     * @param string $url URL��ַ
     * @return string
     */
    public function root($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->root = $url;
            return $this;
        } elseif (!$this->root) {
            $file = $this->baseFile();
            if ($file && 0 !== strpos($this->url(), $file)) {
                $file = str_replace('\\', '/', dirname($file));
            }
            $this->root = rtrim($file, '/');
        }
        return true === $url ? $this->domain() . $this->root : $this->root;
    }

    /**
     * ��ȡ��ǰ����URL��pathinfo��Ϣ����URL��׺��
     * @access public
     * @return string
     */
    public function pathinfo()
    {
        if (is_null($this->pathinfo)) {
            if (isset($_GET[Config::get('var_pathinfo')])) {
                // �ж�URL�����Ƿ��м���ģʽ����
                $_SERVER['PATH_INFO'] = $_GET[Config::get('var_pathinfo')];
                unset($_GET[Config::get('var_pathinfo')]);
            } elseif (IS_CLI) {
                // CLIģʽ�� index.php module/controller/action/params/...
                $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            }

            // ����PATHINFO��Ϣ
            if (!isset($_SERVER['PATH_INFO'])) {
                foreach (Config::get('pathinfo_fetch') as $type) {
                    if (!empty($_SERVER[$type])) {
                        $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type], $_SERVER['SCRIPT_NAME'])) ?
                        substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME'])) : $_SERVER[$type];
                        break;
                    }
                }
            }
            $this->pathinfo = empty($_SERVER['PATH_INFO']) ? '/' : ltrim($_SERVER['PATH_INFO'], '/');
        }
        return $this->pathinfo;
    }

    /**
     * ��ȡ��ǰ����URL��pathinfo��Ϣ(����URL��׺)
     * @access public
     * @return string
     */
    public function path()
    {
        if (is_null($this->path)) {
            $suffix   = Config::get('url_html_suffix');
            $pathinfo = $this->pathinfo();
            if (false === $suffix) {
                // ��ֹα��̬����
                $this->path = $pathinfo;
            } elseif ($suffix) {
                // ȥ��������URL��׺
                $this->path = preg_replace('/\.(' . ltrim($suffix, '.') . ')$/i', '', $pathinfo);
            } else {
                // �����κκ�׺����
                $this->path = preg_replace('/\.' . $this->ext() . '$/i', '', $pathinfo);
            }
        }
        return $this->path;
    }

    /**
     * ��ǰURL�ķ��ʺ�׺
     * @access public
     * @return string
     */
    public function ext()
    {
        return pathinfo($this->pathinfo(), PATHINFO_EXTENSION);
    }

    /**
     * ��ȡ��ǰ�����ʱ��
     * @access public
     * @param bool $float �Ƿ�ʹ�ø�������
     * @return integer|float
     */
    public function time($float = false)
    {
        return $float ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'];
    }

    /**
     * ��ǰ�������Դ����
     * @access public
     * @return false|string
     */
    public function type()
    {
        $accept = $this->server('HTTP_ACCEPT');
        if (empty($accept)) {
            return false;
        }

        foreach ($this->mimeType as $key => $val) {
            $array = explode(',', $val);
            foreach ($array as $k => $v) {
                if (stristr($accept, $v)) {
                    return $key;
                }
            }
        }
        return false;
    }

    /**
     * ������Դ����
     * @access public
     * @param string|array  $type ��Դ������
     * @param string        $val ��Դ����
     * @return void
     */
    public function mimeType($type, $val = '')
    {
        if (is_array($type)) {
            $this->mimeType = array_merge($this->mimeType, $type);
        } else {
            $this->mimeType[$type] = $val;
        }
    }

    /**
     * ��ǰ����������
     * @access public
     * @param bool $method  true ��ȡԭʼ��������
     * @return string
     */
    public function method($method = false)
    {
        if (true === $method) {
            // ��ȡԭʼ��������
            return IS_CLI ? 'GET' : (isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : $_SERVER['REQUEST_METHOD']);
        } elseif (!$this->method) {
            if (isset($_POST[Config::get('var_method')])) {
                $this->method = strtoupper($_POST[Config::get('var_method')]);
                $this->{$this->method}($_POST);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            } else {
                $this->method = IS_CLI ? 'GET' : (isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : $_SERVER['REQUEST_METHOD']);
            }
        }
        return $this->method;
    }

    /**
     * �Ƿ�ΪGET����
     * @access public
     * @return bool
     */
    public function isGet()
    {
        return $this->method() == 'GET';
    }

    /**
     * �Ƿ�ΪPOST����
     * @access public
     * @return bool
     */
    public function isPost()
    {
        return $this->method() == 'POST';
    }

    /**
     * �Ƿ�ΪPUT����
     * @access public
     * @return bool
     */
    public function isPut()
    {
        return $this->method() == 'PUT';
    }

    /**
     * �Ƿ�ΪDELTE����
     * @access public
     * @return bool
     */
    public function isDelete()
    {
        return $this->method() == 'DELETE';
    }

    /**
     * �Ƿ�ΪHEAD����
     * @access public
     * @return bool
     */
    public function isHead()
    {
        return $this->method() == 'HEAD';
    }

    /**
     * �Ƿ�ΪPATCH����
     * @access public
     * @return bool
     */
    public function isPatch()
    {
        return $this->method() == 'PATCH';
    }

    /**
     * �Ƿ�ΪOPTIONS����
     * @access public
     * @return bool
     */
    public function isOptions()
    {
        return $this->method() == 'OPTIONS';
    }

    /**
     * �Ƿ�Ϊcli
     * @access public
     * @return bool
     */
    public function isCli()
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * �Ƿ�Ϊcgi
     * @access public
     * @return bool
     */
    public function isCgi()
    {
        return strpos(PHP_SAPI, 'cgi') === 0;
    }

    /**
     * ��ȡ��ǰ����Ĳ���
     * @access public
     * @param string|array  $name ������
     * @param mixed         $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function param($name = '', $default = null, $filter = '')
    {
        if (empty($this->param)) {
            $method = $this->method(true);
            // �Զ���ȡ�������
            switch ($method) {
                case 'POST':
                    $vars = $this->post(false);
                    break;
                case 'PUT':
                case 'DELETE':
                case 'PATCH':
                    $vars = $this->put(false);
                    break;
                default:
                    $vars = [];
            }
            // ��ǰ���������URL��ַ�еĲ����ϲ�
            $this->param = array_merge($this->get(false), $vars, $this->route(false));
        }
        if (true === $name) {
            // ��ȡ�����ļ��ϴ���Ϣ������
            $file = $this->file();
            $data = is_array($file) ? array_merge($this->param, $file) : $this->param;
            return $this->input($data, '', $default, $filter);
        }
        return $this->input($this->param, $name, $default, $filter);
    }

    /**
     * ���û�ȡ·�ɲ���
     * @access public
     * @param string|array  $name ������
     * @param mixed         $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function route($name = '', $default = null, $filter = '')
    {
        if (is_array($name)) {
            $this->param        = [];
            return $this->route = array_merge($this->route, $name);
        }
        return $this->input($this->route, $name, $default, $filter);
    }

    /**
     * ���û�ȡGET����
     * @access public
     * @param string|array  $name ������
     * @param mixed         $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function get($name = '', $default = null, $filter = '')
    {
        if (empty($this->get)) {
            $this->get = $_GET;
        }
        if (is_array($name)) {
            $this->param      = [];
            return $this->get = array_merge($this->get, $name);
        }
        return $this->input($this->get, $name, $default, $filter);
    }

    /**
     * ���û�ȡPOST����
     * @access public
     * @param string        $name ������
     * @param mixed         $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function post($name = '', $default = null, $filter = '')
    {
        if (empty($this->post)) {
            $content = $this->input;
            if (empty($_POST) && false !== strpos($this->contentType(), 'application/json')) {
                $this->post = (array) json_decode($content, true);
            } else {
                $this->post = $_POST;
            }
        }
        if (is_array($name)) {
            $this->param       = [];
            return $this->post = array_merge($this->post, $name);
        }
        return $this->input($this->post, $name, $default, $filter);
    }

    /**
     * ���û�ȡPUT����
     * @access public
     * @param string|array      $name ������
     * @param mixed             $default Ĭ��ֵ
     * @param string|array      $filter ���˷���
     * @return mixed
     */
    public function put($name = '', $default = null, $filter = '')
    {
        if (is_null($this->put)) {
            $content = $this->input;
            if (false !== strpos($this->contentType(), 'application/json')) {
                $this->put = (array) json_decode($content, true);
            } else {
                parse_str($content, $this->put);
            }
        }
        if (is_array($name)) {
            $this->param      = [];
            return $this->put = is_null($this->put) ? $name : array_merge($this->put, $name);
        }

        return $this->input($this->put, $name, $default, $filter);
    }

    /**
     * ���û�ȡDELETE����
     * @access public
     * @param string|array      $name ������
     * @param mixed             $default Ĭ��ֵ
     * @param string|array      $filter ���˷���
     * @return mixed
     */
    public function delete($name = '', $default = null, $filter = '')
    {
        return $this->put($name, $default, $filter);
    }

    /**
     * ���û�ȡPATCH����
     * @access public
     * @param string|array      $name ������
     * @param mixed             $default Ĭ��ֵ
     * @param string|array      $filter ���˷���
     * @return mixed
     */
    public function patch($name = '', $default = null, $filter = '')
    {
        return $this->put($name, $default, $filter);
    }

    /**
     * ��ȡrequest����
     * @param string        $name ��������
     * @param string        $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function request($name = '', $default = null, $filter = '')
    {
        if (empty($this->request)) {
            $this->request = $_REQUEST;
        }
        if (is_array($name)) {
            $this->param          = [];
            return $this->request = array_merge($this->request, $name);
        }
        return $this->input($this->request, $name, $default, $filter);
    }

    /**
     * ��ȡsession����
     * @access public
     * @param string|array  $name ��������
     * @param string        $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function session($name = '', $default = null, $filter = '')
    {
        if (empty($this->session)) {
            $this->session = Session::get();
        }
        if (is_array($name)) {
            return $this->session = array_merge($this->session, $name);
        }
        return $this->input($this->session, $name, $default, $filter);
    }

    /**
     * ��ȡcookie����
     * @access public
     * @param string|array  $name ��������
     * @param string        $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function cookie($name = '', $default = null, $filter = '')
    {
        if (empty($this->cookie)) {
            $this->cookie = Cookie::get();
        }
        if (is_array($name)) {
            return $this->cookie = array_merge($this->cookie, $name);
        } elseif (!empty($name)) {
            $data = Cookie::has($name) ? Cookie::get($name) : $default;
        } else {
            $data = $this->cookie;
        }

        // ����������
        $filter = $this->getFilter($filter, $default);

        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
            reset($data);
        } else {
            $this->filterValue($data, $name, $filter);
        }
        return $data;
    }

    /**
     * ��ȡserver����
     * @access public
     * @param string|array  $name ��������
     * @param string        $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function server($name = '', $default = null, $filter = '')
    {
        if (empty($this->server)) {
            $this->server = $_SERVER;
        }
        if (is_array($name)) {
            return $this->server = array_merge($this->server, $name);
        }
        return $this->input($this->server, false === $name ? false : strtoupper($name), $default, $filter);
    }

    /**
     * ��ȡ�ϴ����ļ���Ϣ
     * @access public
     * @param string|array $name ����
     * @return null|array|\think\File
     */
    public function file($name = '')
    {
        if (empty($this->file)) {
            $this->file = isset($_FILES) ? $_FILES : [];
        }
        if (is_array($name)) {
            return $this->file = array_merge($this->file, $name);
        }
        $files = $this->file;
        if (!empty($files)) {
            // �����ϴ��ļ�
            $array = [];
            foreach ($files as $key => $file) {
                if (is_array($file['name'])) {
                    $item  = [];
                    $keys  = array_keys($file);
                    $count = count($file['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if (empty($file['tmp_name'][$i]) || !is_file($file['tmp_name'][$i])) {
                            continue;
                        }
                        $temp['key'] = $key;
                        foreach ($keys as $_key) {
                            $temp[$_key] = $file[$_key][$i];
                        }
                        $item[] = (new File($temp['tmp_name']))->setUploadInfo($temp);
                    }
                    $array[$key] = $item;
                } else {
                    if ($file instanceof File) {
                        $array[$key] = $file;
                    } else {
                        if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
                            continue;
                        }
                        $array[$key] = (new File($file['tmp_name']))->setUploadInfo($file);
                    }
                }
            }
            if (strpos($name, '.')) {
                list($name, $sub) = explode('.', $name);
            }
            if ('' === $name) {
                // ��ȡȫ���ļ�
                return $array;
            } elseif (isset($sub) && isset($array[$name][$sub])) {
                return $array[$name][$sub];
            } elseif (isset($array[$name])) {
                return $array[$name];
            }
        }
        return;
    }

    /**
     * ��ȡ��������
     * @param string|array  $name ��������
     * @param string        $default Ĭ��ֵ
     * @param string|array  $filter ���˷���
     * @return mixed
     */
    public function env($name = '', $default = null, $filter = '')
    {
        if (empty($this->env)) {
            $this->env = $_ENV;
        }
        if (is_array($name)) {
            return $this->env = array_merge($this->env, $name);
        }
        return $this->input($this->env, false === $name ? false : strtoupper($name), $default, $filter);
    }

    /**
     * ���û��߻�ȡ��ǰ��Header
     * @access public
     * @param string|array  $name header����
     * @param string        $default Ĭ��ֵ
     * @return string
     */
    public function header($name = '', $default = null)
    {
        if (empty($this->header)) {
            $header = [];
            if (function_exists('apache_request_headers') && $result = apache_request_headers()) {
                $header = $result;
            } else {
                $server = $this->server ?: $_SERVER;
                foreach ($server as $key => $val) {
                    if (0 === strpos($key, 'HTTP_')) {
                        $key          = str_replace('_', '-', strtolower(substr($key, 5)));
                        $header[$key] = $val;
                    }
                }
                if (isset($server['CONTENT_TYPE'])) {
                    $header['content-type'] = $server['CONTENT_TYPE'];
                }
                if (isset($server['CONTENT_LENGTH'])) {
                    $header['content-length'] = $server['CONTENT_LENGTH'];
                }
            }
            $this->header = array_change_key_case($header);
        }
        if (is_array($name)) {
            return $this->header = array_merge($this->header, $name);
        }
        if ('' === $name) {
            return $this->header;
        }
        $name = str_replace('_', '-', strtolower($name));
        return isset($this->header[$name]) ? $this->header[$name] : $default;
    }

    /**
     * ��ȡ���� ֧�ֹ��˺�Ĭ��ֵ
     * @param array         $data ����Դ
     * @param string|false  $name �ֶ���
     * @param mixed         $default Ĭ��ֵ
     * @param string|array  $filter ���˺���
     * @return mixed
     */
    public function input($data = [], $name = '', $default = null, $filter = '')
    {
        if (false === $name) {
            // ��ȡԭʼ����
            return $data;
        }
        $name = (string) $name;
        if ('' != $name) {
            // ����name
            if (strpos($name, '/')) {
                list($name, $type) = explode('/', $name);
            } else {
                $type = 's';
            }
            // ��.��ֳɶ�ά��������ж�
            foreach (explode('.', $name) as $val) {
                if (isset($data[$val])) {
                    $data = $data[$val];
                } else {
                    // ���������ݣ�����Ĭ��ֵ
                    return $default;
                }
            }
            if (is_object($data)) {
                return $data;
            }
        }

        // ����������
        $filter = $this->getFilter($filter, $default);

        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
            reset($data);
        } else {
            $this->filterValue($data, $name, $filter);
        }

        if (isset($type) && $data !== $default) {
            // ǿ������ת��
            $this->typeCast($data, $type);
        }
        return $data;
    }

    /**
     * ���û��ȡ��ǰ�Ĺ��˹���
     * @param mixed $filter ���˹���
     * @return mixed
     */
    public function filter($filter = null)
    {
        if (is_null($filter)) {
            return $this->filter;
        } else {
            $this->filter = $filter;
        }
    }

    protected function getFilter($filter, $default)
    {
        if (is_null($filter)) {
            $filter = [];
        } else {
            $filter = $filter ?: $this->filter;
            if (is_string($filter) && false === strpos($filter, '/')) {
                $filter = explode(',', $filter);
            } else {
                $filter = (array) $filter;
            }
        }

        $filter[] = $default;
        return $filter;
    }

    /**
     * �ݹ���˸�����ֵ
     * @param mixed     $value ��ֵ
     * @param mixed     $key ����
     * @param array     $filters ���˷���+Ĭ��ֵ
     * @return mixed
     */
    private function filterValue(&$value, $key, $filters)
    {
        $default = array_pop($filters);
        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                // ���ú������߷�������
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value)) {
                if (false !== strpos($filter, '/')) {
                    // �������
                    if (!preg_match($filter, $value)) {
                        // ƥ�䲻�ɹ�����Ĭ��ֵ
                        $value = $default;
                        break;
                    }
                } elseif (!empty($filter)) {
                    // filter����������ʱ, ��ʹ��filter_var���й���
                    // filterΪ������ֵʱ, ����filter_idȡ�ù���id
                    $value = filter_var($value, is_int($filter) ? $filter : filter_id($filter));
                    if (false === $value) {
                        $value = $default;
                        break;
                    }
                }
            }
        }
        return $this->filterExp($value);
    }

    /**
     * ���˱��еı��ʽ
     * @param string $value
     * @return void
     */
    public function filterExp(&$value)
    {
        // ���˲�ѯ�����ַ�
        if (is_string($value) && preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT LIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOT EXISTS|NOTEXISTS|EXISTS|NOT NULL|NOTNULL|NULL|BETWEEN TIME|NOT BETWEEN TIME|NOTBETWEEN TIME|NOTIN|NOT IN|IN)$/i', $value)) {
            $value .= ' ';
        }
        // TODO ������ȫ����
    }

    /**
     * ǿ������ת��
     * @param string $data
     * @param string $type
     * @return mixed
     */
    private function typeCast(&$data, $type)
    {
        switch (strtolower($type)) {
            // ����
            case 'a':
                $data = (array) $data;
                break;
            // ����
            case 'd':
                $data = (int) $data;
                break;
            // ����
            case 'f':
                $data = (float) $data;
                break;
            // ����
            case 'b':
                $data = (boolean) $data;
                break;
            // �ַ���
            case 's':
            default:
                if (is_scalar($data)) {
                    $data = (string) $data;
                } else {
                    throw new \InvalidArgumentException('variable type error��' . gettype($data));
                }
        }
    }

    /**
     * �Ƿ����ĳ���������
     * @access public
     * @param string    $name ������
     * @param string    $type ��������
     * @param bool      $checkEmpty �Ƿ����ֵ
     * @return mixed
     */
    public function has($name, $type = 'param', $checkEmpty = false)
    {
        if (empty($this->$type)) {
            $param = $this->$type();
        } else {
            $param = $this->$type;
        }
        // ��.��ֳɶ�ά��������ж�
        foreach (explode('.', $name) as $val) {
            if (isset($param[$val])) {
                $param = $param[$val];
            } else {
                return false;
            }
        }
        return ($checkEmpty && '' === $param) ? false : true;
    }

    /**
     * ��ȡָ���Ĳ���
     * @access public
     * @param string|array  $name ������
     * @param string        $type ��������
     * @return mixed
     */
    public function only($name, $type = 'param')
    {
        $param = $this->$type();
        if (is_string($name)) {
            $name = explode(',', $name);
        }
        $item = [];
        foreach ($name as $key) {
            if (isset($param[$key])) {
                $item[$key] = $param[$key];
            }
        }
        return $item;
    }

    /**
     * �ų�ָ��������ȡ
     * @access public
     * @param string|array  $name ������
     * @param string        $type ��������
     * @return mixed
     */
    public function except($name, $type = 'param')
    {
        $param = $this->$type();
        if (is_string($name)) {
            $name = explode(',', $name);
        }
        foreach ($name as $key) {
            if (isset($param[$key])) {
                unset($param[$key]);
            }
        }
        return $param;
    }

    /**
     * ��ǰ�Ƿ�ssl
     * @access public
     * @return bool
     */
    public function isSsl()
    {
        $server = array_merge($_SERVER, $this->server);
        if (isset($server['HTTPS']) && ('1' == $server['HTTPS'] || 'on' == strtolower($server['HTTPS']))) {
            return true;
        } elseif (isset($server['REQUEST_SCHEME']) && 'https' == $server['REQUEST_SCHEME']) {
            return true;
        } elseif (isset($server['SERVER_PORT']) && ('443' == $server['SERVER_PORT'])) {
            return true;
        } elseif (isset($server['HTTP_X_FORWARDED_PROTO']) && 'https' == $server['HTTP_X_FORWARDED_PROTO']) {
            return true;
        } elseif (Config::get('https_agent_name') && isset($server[Config::get('https_agent_name')])) {
            return true;
        }
        return false;
    }

    /**
     * ��ǰ�Ƿ�Ajax����
     * @access public
     * @param bool $ajax  true ��ȡԭʼajax����
     * @return bool
     */
    public function isAjax($ajax = false)
    {
        $value  = $this->server('HTTP_X_REQUESTED_WITH', '', 'strtolower');
        $result = ('xmlhttprequest' == $value) ? true : false;
        if (true === $ajax) {
            return $result;
        } else {
            return $this->param(Config::get('var_ajax')) ? true : $result;
        }
    }

    /**
     * ��ǰ�Ƿ�Pjax����
     * @access public
     * @param bool $pjax  true ��ȡԭʼpjax����
     * @return bool
     */
    public function isPjax($pjax = false)
    {
        $result = !is_null($this->server('HTTP_X_PJAX')) ? true : false;
        if (true === $pjax) {
            return $result;
        } else {
            return $this->param(Config::get('var_pjax')) ? true : $result;
        }
    }

    /**
     * ��ȡ�ͻ���IP��ַ
     * @param integer   $type �������� 0 ����IP��ַ 1 ����IPV4��ַ����
     * @param boolean   $adv �Ƿ���и߼�ģʽ��ȡ���п��ܱ�αװ��
     * @return mixed
     */
    public function ip($type = 0, $adv = true)
    {
        $type      = $type ? 1 : 0;
        static $ip = null;
        if (null !== $ip) {
            return $ip[$type];
        }

        $httpAgentIp = Config::get('http_agent_ip');

        if ($httpAgentIp && isset($_SERVER[$httpAgentIp])) {
            $ip = $_SERVER[$httpAgentIp];
        } elseif ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim(current($arr));
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP��ַ�Ϸ���֤
        $long = sprintf("%u", ip2long($ip));
        $ip   = $long ? [$ip, $long] : ['0.0.0.0', 0];
        return $ip[$type];
    }

    /**
     * ����Ƿ�ʹ���ֻ�����
     * @access public
     * @return bool
     */
    public function isMobile()
    {
        if (isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], "wap")) {
            return true;
        } elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML")) {
            return true;
        } elseif (isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])) {
            return true;
        } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * ��ǰURL��ַ�е�scheme����
     * @access public
     * @return string
     */
    public function scheme()
    {
        return $this->isSsl() ? 'https' : 'http';
    }

    /**
     * ��ǰ����URL��ַ�е�query����
     * @access public
     * @return string
     */
    public function query()
    {
        return $this->server('QUERY_STRING');
    }

    /**
     * ��ǰ�����host
     * @access public
     * @param bool $strict  true ������ȡHOST
     * @return string
     */
    public function host($strict = false)
    {
        if (isset($_SERVER['HTTP_X_REAL_HOST'])) {
            $host = $_SERVER['HTTP_X_REAL_HOST'];
        } else {
            $host = $this->server('HTTP_HOST');
        }

        return true === $strict && strpos($host, ':') ? strstr($host, ':', true) : $host;
    }

    /**
     * ��ǰ����URL��ַ�е�port����
     * @access public
     * @return integer
     */
    public function port()
    {
        return $this->server('SERVER_PORT');
    }

    /**
     * ��ǰ���� SERVER_PROTOCOL
     * @access public
     * @return integer
     */
    public function protocol()
    {
        return $this->server('SERVER_PROTOCOL');
    }

    /**
     * ��ǰ���� REMOTE_PORT
     * @access public
     * @return integer
     */
    public function remotePort()
    {
        return $this->server('REMOTE_PORT');
    }

    /**
     * ��ǰ���� HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public function contentType()
    {
        $contentType = $this->server('CONTENT_TYPE');
        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }
        return '';
    }

    /**
     * ��ȡ��ǰ�����·����Ϣ
     * @access public
     * @param array $route ·������
     * @return array
     */
    public function routeInfo($route = [])
    {
        if (!empty($route)) {
            $this->routeInfo = $route;
        } else {
            return $this->routeInfo;
        }
    }

    /**
     * ���û��߻�ȡ��ǰ����ĵ�����Ϣ
     * @access public
     * @param array  $dispatch ������Ϣ
     * @return array
     */
    public function dispatch($dispatch = null)
    {
        if (!is_null($dispatch)) {
            $this->dispatch = $dispatch;
        }
        return $this->dispatch;
    }

    /**
     * ���û��߻�ȡ��ǰ��ģ����
     * @access public
     * @param string $module ģ����
     * @return string|Request
     */
    public function module($module = null)
    {
        if (!is_null($module)) {
            $this->module = $module;
            return $this;
        } else {
            return $this->module ?: '';
        }
    }

    /**
     * ���û��߻�ȡ��ǰ�Ŀ�������
     * @access public
     * @param string $controller ��������
     * @return string|Request
     */
    public function controller($controller = null)
    {
        if (!is_null($controller)) {
            $this->controller = $controller;
            return $this;
        } else {
            return $this->controller ?: '';
        }
    }

    /**
     * ���û��߻�ȡ��ǰ�Ĳ�����
     * @access public
     * @param string $action ������
     * @return string|Request
     */
    public function action($action = null)
    {
        if (!is_null($action) && !is_bool($action)) {
            $this->action = $action;
            return $this;
        } else {
            $name = $this->action ?: '';
            return true === $action ? $name : strtolower($name);
        }
    }

    /**
     * ���û��߻�ȡ��ǰ������
     * @access public
     * @param string $lang ������
     * @return string|Request
     */
    public function langset($lang = null)
    {
        if (!is_null($lang)) {
            $this->langset = $lang;
            return $this;
        } else {
            return $this->langset ?: '';
        }
    }

    /**
     * ���û��߻�ȡ��ǰ�����content
     * @access public
     * @return string
     */
    public function getContent()
    {
        if (is_null($this->content)) {
            $this->content = $this->input;
        }
        return $this->content;
    }

    /**
     * ��ȡ��ǰ�����php://input
     * @access public
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * ������������
     * @access public
     * @param string $name ��������
     * @param mixed  $type �������ɷ���
     * @return string
     */
    public function token($name = '__token__', $type = 'md5')
    {
        $type  = is_callable($type) ? $type : 'md5';
        $token = call_user_func($type, $_SERVER['REQUEST_TIME_FLOAT']);
        if ($this->isAjax()) {
            header($name . ': ' . $token);
        }
        Session::set($name, $token);
        return $token;
    }

    /**
     * ���õ�ǰ��ַ�����󻺴�
     * @access public
     * @param string $key �����ʶ��֧�ֱ������� ������ item/:name/:id
     * @param mixed  $expire ������Ч��
     * @param array  $except �����ų�
     * @param string $tag    �����ǩ
     * @return void
     */
    public function cache($key, $expire = null, $except = [], $tag = null)
    {
        if (!is_array($except)) {
            $tag    = $except;
            $except = [];
        }

        if (false !== $key && $this->isGet() && !$this->isCheckCache) {
            // ������󻺴���
            $this->isCheckCache = true;
            if (false === $expire) {
                // �رյ�ǰ����
                return;
            }
            if ($key instanceof \Closure) {
                $key = call_user_func_array($key, [$this]);
            } elseif (true === $key) {
                foreach ($except as $rule) {
                    if (0 === stripos($this->url(), $rule)) {
                        return;
                    }
                }
                // �Զ����湦��
                $key = '__URL__';
            } elseif (strpos($key, '|')) {
                list($key, $fun) = explode('|', $key);
            }
            // ��������滻
            if (false !== strpos($key, '__')) {
                $key = str_replace(['__MODULE__', '__CONTROLLER__', '__ACTION__', '__URL__', ''], [$this->module, $this->controller, $this->action, md5($this->url(true))], $key);
            }

            if (false !== strpos($key, ':')) {
                $param = $this->param();
                foreach ($param as $item => $val) {
                    if (is_string($val) && false !== strpos($key, ':' . $item)) {
                        $key = str_replace(':' . $item, $val, $key);
                    }
                }
            } elseif (strpos($key, ']')) {
                if ('[' . $this->ext() . ']' == $key) {
                    // ����ĳ����׺������
                    $key = md5($this->url());
                } else {
                    return;
                }
            }
            if (isset($fun)) {
                $key = $fun($key);
            }

            if (strtotime($this->server('HTTP_IF_MODIFIED_SINCE')) + $expire > $_SERVER['REQUEST_TIME']) {
                // ��ȡ����
                $response = Response::create()->code(304);
                throw new \think\exception\HttpResponseException($response);
            } elseif (Cache::has($key)) {
                list($content, $header) = Cache::get($key);
                $response               = Response::create($content)->header($header);
                throw new \think\exception\HttpResponseException($response);
            } else {
                $this->cache = [$key, $expire, $tag];
            }
        }
    }

    /**
     * ��ȡ���󻺴�����
     * @access public
     * @return array
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * ���õ�ǰ����󶨵Ķ���ʵ��
     * @access public
     * @param string|array $name �󶨵Ķ����ʶ
     * @param mixed  $obj �󶨵Ķ���ʵ��
     * @return mixed
     */
    public function bind($name, $obj = null)
    {
        if (is_array($name)) {
            $this->bind = array_merge($this->bind, $name);
        } else {
            $this->bind[$name] = $obj;
        }
    }

    public function __set($name, $value)
    {
        $this->bind[$name] = $value;
    }

    public function __get($name)
    {
        return isset($this->bind[$name]) ? $this->bind[$name] : null;
    }

    public function __isset($name)
    {
        return isset($this->bind[$name]);
    }
}
