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

use think\response\Json as JsonResponse;
use think\response\Jsonp as JsonpResponse;
use think\response\Redirect as RedirectResponse;
use think\response\View as ViewResponse;
use think\response\Xml as XmlResponse;

class Response
{
    // ԭʼ����
    protected $data;

    // ��ǰ��contentType
    protected $contentType = 'text/html';

    // �ַ���
    protected $charset = 'utf-8';

    //״̬
    protected $code = 200;

    // �������
    protected $options = [];
    // header����
    protected $header = [];

    protected $content = null;

    /**
     * ���캯��
     * @access   public
     * @param mixed $data    �������
     * @param int   $code
     * @param array $header
     * @param array $options �������
     */
    public function __construct($data = '', $code = 200, array $header = [], $options = [])
    {
        $this->data($data);
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->contentType($this->contentType, $this->charset);
        $this->header = array_merge($this->header, $header);
        $this->code   = $code;
    }

    /**
     * ����Response����
     * @access public
     * @param mixed  $data    �������
     * @param string $type    �������
     * @param int    $code
     * @param array  $header
     * @param array  $options �������
     * @return Response|JsonResponse|ViewResponse|XmlResponse|RedirectResponse|JsonpResponse
     */
    public static function create($data = '', $type = '', $code = 200, array $header = [], $options = [])
    {
        $class = false !== strpos($type, '\\') ? $type : '\\think\\response\\' . ucfirst(strtolower($type));
        if (class_exists($class)) {
            $response = new $class($data, $code, $header, $options);
        } else {
            $response = new static($data, $code, $header, $options);
        }

        return $response;
    }

    /**
     * �������ݵ��ͻ���
     * @access public
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function send()
    {
        // ����response_send
        Hook::listen('response_send', $this);

        // �����������
        $data = $this->getContent();

        // Trace����ע��
        if (Env::get('app_trace', Config::get('app_trace'))) {
            Debug::inject($this, $data);
        }

        if (200 == $this->code) {
            $cache = Request::instance()->getCache();
            if ($cache) {
                $this->header['Cache-Control'] = 'max-age=' . $cache[1] . ',must-revalidate';
                $this->header['Last-Modified'] = gmdate('D, d M Y H:i:s') . ' GMT';
                $this->header['Expires']       = gmdate('D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] + $cache[1]) . ' GMT';
                Cache::tag($cache[2])->set($cache[0], [$data, $this->header], $cache[1]);
            }
        }

        if (!headers_sent() && !empty($this->header)) {
            // ����״̬��
            http_response_code($this->code);
            // ����ͷ����Ϣ
            foreach ($this->header as $name => $val) {
                if (is_null($val)) {
                    header($name);
                } else {
                    header($name . ':' . $val);
                }
            }
        }

        echo $data;

        if (function_exists('fastcgi_finish_request')) {
            // ���ҳ����Ӧ
            fastcgi_finish_request();
        }

        // ����response_end
        Hook::listen('response_end', $this);

        // ��յ���������Ч������
        if (!($this instanceof RedirectResponse)) {
            Session::flush();
        }
    }

    /**
     * ��������
     * @access protected
     * @param mixed $data Ҫ���������
     * @return mixed
     */
    protected function output($data)
    {
        return $data;
    }

    /**
     * ����Ĳ���
     * @access public
     * @param mixed $options �������
     * @return $this
     */
    public function options($options = [])
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * �����������
     * @access public
     * @param mixed $data �������
     * @return $this
     */
    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * ������Ӧͷ
     * @access public
     * @param string|array $name  ������
     * @param string       $value ����ֵ
     * @return $this
     */
    public function header($name, $value = null)
    {
        if (is_array($name)) {
            $this->header = array_merge($this->header, $name);
        } else {
            $this->header[$name] = $value;
        }
        return $this;
    }

    /**
     * ����ҳ���������
     * @param $content
     * @return $this
     */
    public function content($content)
    {
        if (null !== $content && !is_string($content) && !is_numeric($content) && !is_callable([
            $content,
            '__toString',
        ])
        ) {
            throw new \InvalidArgumentException(sprintf('variable type error�� %s', gettype($content)));
        }

        $this->content = (string) $content;

        return $this;
    }

    /**
     * ����HTTP״̬
     * @param integer $code ״̬��
     * @return $this
     */
    public function code($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * LastModified
     * @param string $time
     * @return $this
     */
    public function lastModified($time)
    {
        $this->header['Last-Modified'] = $time;
        return $this;
    }

    /**
     * Expires
     * @param string $time
     * @return $this
     */
    public function expires($time)
    {
        $this->header['Expires'] = $time;
        return $this;
    }

    /**
     * ETag
     * @param string $eTag
     * @return $this
     */
    public function eTag($eTag)
    {
        $this->header['ETag'] = $eTag;
        return $this;
    }

    /**
     * ҳ�滺�����
     * @param string $cache ״̬��
     * @return $this
     */
    public function cacheControl($cache)
    {
        $this->header['Cache-control'] = $cache;
        return $this;
    }

    /**
     * ҳ���������
     * @param string $contentType �������
     * @param string $charset     �������
     * @return $this
     */
    public function contentType($contentType, $charset = 'utf-8')
    {
        $this->header['Content-Type'] = $contentType . '; charset=' . $charset;
        return $this;
    }

    /**
     * ��ȡͷ����Ϣ
     * @param string $name ͷ������
     * @return mixed
     */
    public function getHeader($name = '')
    {
        if (!empty($name)) {
            return isset($this->header[$name]) ? $this->header[$name] : null;
        } else {
            return $this->header;
        }
    }

    /**
     * ��ȡԭʼ����
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * ��ȡ�������
     * @return mixed
     */
    public function getContent()
    {
        if (null == $this->content) {
            $content = $this->output($this->data);

            if (null !== $content && !is_string($content) && !is_numeric($content) && !is_callable([
                $content,
                '__toString',
            ])
            ) {
                throw new \InvalidArgumentException(sprintf('variable type error�� %s', gettype($content)));
            }

            $this->content = (string) $content;
        }
        return $this->content;
    }

    /**
     * ��ȡ״̬��
     * @return integer
     */
    public function getCode()
    {
        return $this->code;
    }
}
