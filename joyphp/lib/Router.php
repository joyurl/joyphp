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

use think\exception\HttpException;

class Route
{
    // ·�ɹ���
    private static $rules = [
        'get'     => [],
        'post'    => [],
        'put'     => [],
        'delete'  => [],
        'patch'   => [],
        'head'    => [],
        'options' => [],
        '*'       => [],
        'alias'   => [],
        'domain'  => [],
        'pattern' => [],
        'name'    => [],
    ];

    // REST·�ɲ�����������
    private static $rest = [
        'index'  => ['get', '', 'index'],
        'create' => ['get', '/create', 'create'],
        'edit'   => ['get', '/:id/edit', 'edit'],
        'read'   => ['get', '/:id', 'read'],
        'save'   => ['post', '', 'save'],
        'update' => ['put', '/:id', 'update'],
        'delete' => ['delete', '/:id', 'delete'],
    ];

    // ��ͬ�������͵ķ���ǰ׺
    private static $methodPrefix = [
        'get'    => 'get',
        'post'   => 'post',
        'put'    => 'put',
        'delete' => 'delete',
        'patch'  => 'patch',
    ];

    // ������
    private static $subDomain = '';
    // ������
    private static $bind = [];
    // ��ǰ������Ϣ
    private static $group = [];
    // ��ǰ��������
    private static $domainBind;
    private static $domainRule;
    // ��ǰ����
    private static $domain;
    // ��ǰ·��ִ�й����еĲ���
    private static $option = [];

    /**
     * ע���������
     * @access public
     * @param string|array  $name ������
     * @param string        $rule ��������
     * @return void
     */
    public static function pattern($name = null, $rule = '')
    {
        if (is_array($name)) {
            self::$rules['pattern'] = array_merge(self::$rules['pattern'], $name);
        } else {
            self::$rules['pattern'][$name] = $rule;
        }
    }

    /**
     * ע���������������
     * @access public
     * @param string|array  $domain ������
     * @param mixed         $rule ·�ɹ���
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function domain($domain, $rule = '', $option = [], $pattern = [])
    {
        if (is_array($domain)) {
            foreach ($domain as $key => $item) {
                self::domain($key, $item, $option, $pattern);
            }
        } elseif ($rule instanceof \Closure) {
            // ִ�бհ�
            self::setDomain($domain);
            call_user_func_array($rule, []);
            self::setDomain(null);
        } elseif (is_array($rule)) {
            self::setDomain($domain);
            self::group('', function () use ($rule) {
                // ��̬ע��������·�ɹ���
                self::registerRules($rule);
            }, $option, $pattern);
            self::setDomain(null);
        } else {
            self::$rules['domain'][$domain]['[bind]'] = [$rule, $option, $pattern];
        }
    }

    private static function setDomain($domain)
    {
        self::$domain = $domain;
    }

    /**
     * ����·�ɰ�
     * @access public
     * @param mixed     $bind ����Ϣ
     * @param string    $type ������ Ĭ��Ϊmodule ֧�� namespace class controller
     * @return mixed
     */
    public static function bind($bind, $type = 'module')
    {
        self::$bind = ['type' => $type, $type => $bind];
    }

    /**
     * ���û��߻�ȡ·�ɱ�ʶ
     * @access public
     * @param string|array     $name ·��������ʶ �����ʾ��������
     * @param array            $value ·�ɵ�ַ��������Ϣ
     * @return array
     */
    public static function name($name = '', $value = null)
    {
        if (is_array($name)) {
            return self::$rules['name'] = $name;
        } elseif ('' === $name) {
            return self::$rules['name'];
        } elseif (!is_null($value)) {
            self::$rules['name'][strtolower($name)][] = $value;
        } else {
            $name = strtolower($name);
            return isset(self::$rules['name'][$name]) ? self::$rules['name'][$name] : null;
        }
    }

    /**
     * ��ȡ·�ɰ�
     * @access public
     * @param string    $type ������
     * @return mixed
     */
    public static function getBind($type)
    {
        return isset(self::$bind[$type]) ? self::$bind[$type] : null;
    }

    /**
     * ���������ļ���·�ɹ���
     * @access public
     * @param array     $rule ·�ɹ���
     * @param string    $type ��������
     * @return void
     */
    public static function import(array $rule, $type = '*')
    {
        // �����������
        if (isset($rule['__domain__'])) {
            self::domain($rule['__domain__']);
            unset($rule['__domain__']);
        }

        // ����������
        if (isset($rule['__pattern__'])) {
            self::pattern($rule['__pattern__']);
            unset($rule['__pattern__']);
        }

        // ���·�ɱ���
        if (isset($rule['__alias__'])) {
            self::alias($rule['__alias__']);
            unset($rule['__alias__']);
        }

        // �����Դ·��
        if (isset($rule['__rest__'])) {
            self::resource($rule['__rest__']);
            unset($rule['__rest__']);
        }

        self::registerRules($rule, strtolower($type));
    }

    // ����ע��·��
    protected static function registerRules($rules, $type = '*')
    {
        foreach ($rules as $key => $val) {
            if (is_numeric($key)) {
                $key = array_shift($val);
            }
            if (empty($val)) {
                continue;
            }
            if (is_string($key) && 0 === strpos($key, '[')) {
                $key = substr($key, 1, -1);
                self::group($key, $val);
            } elseif (is_array($val)) {
                self::setRule($key, $val[0], $type, $val[1], isset($val[2]) ? $val[2] : []);
            } else {
                self::setRule($key, $val, $type);
            }
        }
    }

    /**
     * ע��·�ɹ���
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param string        $type ��������
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function rule($rule, $route = '', $type = '*', $option = [], $pattern = [])
    {
        $group = self::getGroup('name');

        if (!is_null($group)) {
            // ·�ɷ���
            $option  = array_merge(self::getGroup('option'), $option);
            $pattern = array_merge(self::getGroup('pattern'), $pattern);
        }

        $type = strtolower($type);

        if (strpos($type, '|')) {
            $option['method'] = $type;
            $type             = '*';
        }
        if (is_array($rule) && empty($route)) {
            foreach ($rule as $key => $val) {
                if (is_numeric($key)) {
                    $key = array_shift($val);
                }
                if (is_array($val)) {
                    $route    = $val[0];
                    $option1  = array_merge($option, $val[1]);
                    $pattern1 = array_merge($pattern, isset($val[2]) ? $val[2] : []);
                } else {
                    $option1  = null;
                    $pattern1 = null;
                    $route    = $val;
                }
                self::setRule($key, $route, $type, !is_null($option1) ? $option1 : $option, !is_null($pattern1) ? $pattern1 : $pattern, $group);
            }
        } else {
            self::setRule($rule, $route, $type, $option, $pattern, $group);
        }

    }

    /**
     * ����·�ɹ���
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param string        $type ��������
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @param string        $group ��������
     * @return void
     */
    protected static function setRule($rule, $route, $type = '*', $option = [], $pattern = [], $group = '')
    {
        if (is_array($rule)) {
            $name = $rule[0];
            $rule = $rule[1];
        } elseif (is_string($route)) {
            $name = $route;
        }
        if (!isset($option['complete_match'])) {
            if (Config::get('route_complete_match')) {
                $option['complete_match'] = true;
            } elseif ('$' == substr($rule, -1, 1)) {
                // �Ƿ�����ƥ��
                $option['complete_match'] = true;
            }
        } elseif (empty($option['complete_match']) && '$' == substr($rule, -1, 1)) {
            // �Ƿ�����ƥ��
            $option['complete_match'] = true;
        }

        if ('$' == substr($rule, -1, 1)) {
            $rule = substr($rule, 0, -1);
        }

        if ('/' != $rule || $group) {
            $rule = trim($rule, '/');
        }
        $vars = self::parseVar($rule);
        if (isset($name)) {
            $key    = $group ? $group . ($rule ? '/' . $rule : '') : $rule;
            $suffix = isset($option['ext']) ? $option['ext'] : null;
            self::name($name, [$key, $vars, self::$domain, $suffix]);
        }
        if (isset($option['modular'])) {
            $route = $option['modular'] . '/' . $route;
        }
        if ($group) {
            if ('*' != $type) {
                $option['method'] = $type;
            }
            if (self::$domain) {
                self::$rules['domain'][self::$domain]['*'][$group]['rule'][] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            } else {
                self::$rules['*'][$group]['rule'][] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            }
        } else {
            if ('*' != $type && isset(self::$rules['*'][$rule])) {
                unset(self::$rules['*'][$rule]);
            }
            if (self::$domain) {
                self::$rules['domain'][self::$domain][$type][$rule] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            } else {
                self::$rules[$type][$rule] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            }
            if ('*' == $type) {
                // ע��·�ɿ�ݷ�ʽ
                foreach (['get', 'post', 'put', 'delete', 'patch', 'head', 'options'] as $method) {
                    if (self::$domain && !isset(self::$rules['domain'][self::$domain][$method][$rule])) {
                        self::$rules['domain'][self::$domain][$method][$rule] = true;
                    } elseif (!self::$domain && !isset(self::$rules[$method][$rule])) {
                        self::$rules[$method][$rule] = true;
                    }
                }
            }
        }
    }

    /**
     * ���õ�ǰִ�еĲ�����Ϣ
     * @access public
     * @param array    $options ������Ϣ
     * @return mixed
     */
    protected static function setOption($options = [])
    {
        self::$option[] = $options;
    }

    /**
     * ��ȡ��ǰִ�е����в�����Ϣ
     * @access public
     * @return array
     */
    public static function getOption()
    {
        return self::$option;
    }

    /**
     * ��ȡ��ǰ�ķ�����Ϣ
     * @access public
     * @param string    $type ������Ϣ���� name option pattern
     * @return mixed
     */
    public static function getGroup($type)
    {
        if (isset(self::$group[$type])) {
            return self::$group[$type];
        } else {
            return 'name' == $type ? null : [];
        }
    }

    /**
     * ���õ�ǰ��·�ɷ���
     * @access public
     * @param string    $name ��������
     * @param array     $option ����·�ɲ���
     * @param array     $pattern �����������
     * @return void
     */
    public static function setGroup($name, $option = [], $pattern = [])
    {
        self::$group['name']    = $name;
        self::$group['option']  = $option ?: [];
        self::$group['pattern'] = $pattern ?: [];
    }

    /**
     * ע��·�ɷ���
     * @access public
     * @param string|array      $name �������ƻ��߲���
     * @param array|\Closure    $routes ·�ɵ�ַ
     * @param array             $option ·�ɲ���
     * @param array             $pattern ��������
     * @return void
     */
    public static function group($name, $routes, $option = [], $pattern = [])
    {
        if (is_array($name)) {
            $option = $name;
            $name   = isset($option['name']) ? $option['name'] : '';
        }
        // ����
        $currentGroup = self::getGroup('name');
        if ($currentGroup) {
            $name = $currentGroup . ($name ? '/' . ltrim($name, '/') : '');
        }
        if (!empty($name)) {
            if ($routes instanceof \Closure) {
                $currentOption  = self::getGroup('option');
                $currentPattern = self::getGroup('pattern');
                self::setGroup($name, array_merge($currentOption, $option), array_merge($currentPattern, $pattern));
                call_user_func_array($routes, []);
                self::setGroup($currentGroup, $currentOption, $currentPattern);
                if ($currentGroup != $name) {
                    self::$rules['*'][$name]['route']   = '';
                    self::$rules['*'][$name]['var']     = self::parseVar($name);
                    self::$rules['*'][$name]['option']  = $option;
                    self::$rules['*'][$name]['pattern'] = $pattern;
                }
            } else {
                $item          = [];
                $completeMatch = Config::get('route_complete_match');
                foreach ($routes as $key => $val) {
                    if (is_numeric($key)) {
                        $key = array_shift($val);
                    }
                    if (is_array($val)) {
                        $route    = $val[0];
                        $option1  = array_merge($option, isset($val[1]) ? $val[1] : []);
                        $pattern1 = array_merge($pattern, isset($val[2]) ? $val[2] : []);
                    } else {
                        $route = $val;
                    }

                    $options  = isset($option1) ? $option1 : $option;
                    $patterns = isset($pattern1) ? $pattern1 : $pattern;
                    if ('$' == substr($key, -1, 1)) {
                        // �Ƿ�����ƥ��
                        $options['complete_match'] = true;
                        $key                       = substr($key, 0, -1);
                    } elseif ($completeMatch) {
                        $options['complete_match'] = true;
                    }
                    $key    = trim($key, '/');
                    $vars   = self::parseVar($key);
                    $item[] = ['rule' => $key, 'route' => $route, 'var' => $vars, 'option' => $options, 'pattern' => $patterns];
                    // ����·�ɱ�ʶ
                    $suffix = isset($options['ext']) ? $options['ext'] : null;
                    self::name($route, [$name . ($key ? '/' . $key : ''), $vars, self::$domain, $suffix]);
                }
                self::$rules['*'][$name] = ['rule' => $item, 'route' => '', 'var' => [], 'option' => $option, 'pattern' => $pattern];
            }

            foreach (['get', 'post', 'put', 'delete', 'patch', 'head', 'options'] as $method) {
                if (!isset(self::$rules[$method][$name])) {
                    self::$rules[$method][$name] = true;
                } elseif (is_array(self::$rules[$method][$name])) {
                    self::$rules[$method][$name] = array_merge(self::$rules['*'][$name], self::$rules[$method][$name]);
                }
            }

        } elseif ($routes instanceof \Closure) {
            // �հ�ע��
            $currentOption  = self::getGroup('option');
            $currentPattern = self::getGroup('pattern');
            self::setGroup('', array_merge($currentOption, $option), array_merge($currentPattern, $pattern));
            call_user_func_array($routes, []);
            self::setGroup($currentGroup, $currentOption, $currentPattern);
        } else {
            // ����ע��·��
            self::rule($routes, '', '*', $option, $pattern);
        }
    }

    /**
     * ע��·��
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function any($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, '*', $option, $pattern);
    }

    /**
     * ע��GET·��
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function get($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'GET', $option, $pattern);
    }

    /**
     * ע��POST·��
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function post($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'POST', $option, $pattern);
    }

    /**
     * ע��PUT·��
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function put($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'PUT', $option, $pattern);
    }

    /**
     * ע��DELETE·��
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function delete($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'DELETE', $option, $pattern);
    }

    /**
     * ע��PATCH·��
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function patch($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'PATCH', $option, $pattern);
    }

    /**
     * ע����Դ·��
     * @access public
     * @param string|array  $rule ·�ɹ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @param array         $pattern ��������
     * @return void
     */
    public static function resource($rule, $route = '', $option = [], $pattern = [])
    {
        if (is_array($rule)) {
            foreach ($rule as $key => $val) {
                if (is_array($val)) {
                    list($val, $option, $pattern) = array_pad($val, 3, []);
                }
                self::resource($key, $val, $option, $pattern);
            }
        } else {
            if (strpos($rule, '.')) {
                // ע��Ƕ����Դ·��
                $array = explode('.', $rule);
                $last  = array_pop($array);
                $item  = [];
                foreach ($array as $val) {
                    $item[] = $val . '/:' . (isset($option['var'][$val]) ? $option['var'][$val] : $val . '_id');
                }
                $rule = implode('/', $item) . '/' . $last;
            }
            // ע����Դ·��
            foreach (self::$rest as $key => $val) {
                if ((isset($option['only']) && !in_array($key, $option['only']))
                    || (isset($option['except']) && in_array($key, $option['except']))) {
                    continue;
                }
                if (isset($last) && strpos($val[1], ':id') && isset($option['var'][$last])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$last], $val[1]);
                } elseif (strpos($val[1], ':id') && isset($option['var'][$rule])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$rule], $val[1]);
                }
                $item           = ltrim($rule . $val[1], '/');
                $option['rest'] = $key;
                self::rule($item . '$', $route . '/' . $val[2], $val[0], $option, $pattern);
            }
        }
    }

    /**
     * ע�������·�� ����������Ӧ��ͬ�������׺
     * @access public
     * @param string    $rule ·�ɹ���
     * @param string    $route ·�ɵ�ַ
     * @param array     $option ·�ɲ���
     * @param array     $pattern ��������
     * @return void
     */
    public static function controller($rule, $route = '', $option = [], $pattern = [])
    {
        foreach (self::$methodPrefix as $type => $val) {
            self::$type($rule . '/:action', $route . '/' . $val . ':action', $option, $pattern);
        }
    }

    /**
     * ע�����·��
     * @access public
     * @param string|array  $rule ·�ɱ���
     * @param string        $route ·�ɵ�ַ
     * @param array         $option ·�ɲ���
     * @return void
     */
    public static function alias($rule = null, $route = '', $option = [])
    {
        if (is_array($rule)) {
            self::$rules['alias'] = array_merge(self::$rules['alias'], $rule);
        } else {
            self::$rules['alias'][$rule] = $option ? [$route, $option] : $route;
        }
    }

    /**
     * ���ò�ͬ������������ķ���ǰ׺
     * @access public
     * @param string    $method ��������
     * @param string    $prefix ����ǰ׺
     * @return void
     */
    public static function setMethodPrefix($method, $prefix = '')
    {
        if (is_array($method)) {
            self::$methodPrefix = array_merge(self::$methodPrefix, array_change_key_case($method));
        } else {
            self::$methodPrefix[strtolower($method)] = $prefix;
        }
    }

    /**
     * rest����������޸�
     * @access public
     * @param string|array  $name ��������
     * @param array|bool    $resource ��Դ
     * @return void
     */
    public static function rest($name, $resource = [])
    {
        if (is_array($name)) {
            self::$rest = $resource ? $name : array_merge(self::$rest, $name);
        } else {
            self::$rest[$name] = $resource;
        }
    }

    /**
     * ע��δƥ��·�ɹ����Ĵ���
     * @access public
     * @param string    $route ·�ɵ�ַ
     * @param string    $method ��������
     * @param array     $option ·�ɲ���
     * @return void
     */
    public static function miss($route, $method = '*', $option = [])
    {
        self::rule('__miss__', $route, $method, $option, []);
    }

    /**
     * ע��һ���Զ�������URL·��
     * @access public
     * @param string    $route ·�ɵ�ַ
     * @return void
     */
    public static function auto($route)
    {
        self::rule('__auto__', $route, '*', [], []);
    }

    /**
     * ��ȡ������������·�ɶ���
     * @access public
     * @param mixed $rules �������ͻ���·�ɶ�������
     * @return array
     */
    public static function rules($rules = '')
    {
        if (is_array($rules)) {
            self::$rules = $rules;
        } elseif ($rules) {
            return true === $rules ? self::$rules : self::$rules[strtolower($rules)];
        } else {
            $rules = self::$rules;
            unset($rules['pattern'], $rules['alias'], $rules['domain'], $rules['name']);
            return $rules;
        }
    }

    /**
     * �������������
     * @access public
     * @param Request   $request Request�������
     * @param array     $currentRules ��ǰ·�ɹ���
     * @param string    $method ��������
     * @return void
     */
    public static function checkDomain($request, &$currentRules, $method = 'get')
    {
        // ��������
        $rules = self::$rules['domain'];
        // �������������� ֧�ֶ�������������
        if (!empty($rules)) {
            $host = $request->host(true);
            if (isset($rules[$host])) {
                // ������������IP����
                $item = $rules[$host];
            } else {
                $rootDomain = Config::get('url_domain_root');
                if ($rootDomain) {
                    // ���������� ���� thinkphp.cn 163.com.cn ����ǹ��Ҽ����� com.cn net.cn ֮���������Ҫ����
                    $domain = explode('.', rtrim(stristr($host, $rootDomain, true), '.'));
                } else {
                    $domain = explode('.', $host, -2);
                }
                // ����������
                if (!empty($domain)) {
                    // ��ǰ������
                    $subDomain       = implode('.', $domain);
                    self::$subDomain = $subDomain;
                    $domain2         = array_pop($domain);
                    if ($domain) {
                        // ������������
                        $domain3 = array_pop($domain);
                    }
                    if ($subDomain && isset($rules[$subDomain])) {
                        // ����������
                        $item = $rules[$subDomain];
                    } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                        // ����������
                        $item      = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    } elseif (isset($rules['*']) && !empty($domain2)) {
                        // ����������
                        if ('www' != $domain2) {
                            $item      = $rules['*'];
                            $panDomain = $domain2;
                        }
                    }
                }
            }
            if (!empty($item)) {
                if (isset($panDomain)) {
                    // ���浱ǰ������
                    $request->route(['__domain__' => $panDomain]);
                }
                if (isset($item['[bind]'])) {
                    // �����������������
                    list($rule, $option, $pattern) = $item['[bind]'];
                    if (!empty($option['https']) && !$request->isSsl()) {
                        // https���
                        throw new HttpException(404, 'must use https request:' . $host);
                    }

                    if (strpos($rule, '?')) {
                        // ������������
                        $array  = parse_url($rule);
                        $result = $array['path'];
                        parse_str($array['query'], $params);
                        if (isset($panDomain)) {
                            $pos = array_search('*', $params);
                            if (false !== $pos) {
                                // ��������Ϊ����
                                $params[$pos] = $panDomain;
                            }
                        }
                        $_GET = array_merge($_GET, $params);
                    } else {
                        $result = $rule;
                    }

                    if (0 === strpos($result, '\\')) {
                        // �󶨵������ռ� ���� \app\index\behavior
                        self::$bind = ['type' => 'namespace', 'namespace' => $result];
                    } elseif (0 === strpos($result, '@')) {
                        // �󶨵��� ���� @app\index\controller\User
                        self::$bind = ['type' => 'class', 'class' => substr($result, 1)];
                    } else {
                        // �󶨵�ģ��/������ ���� index/user
                        self::$bind = ['type' => 'module', 'module' => $result];
                    }
                    self::$domainBind = true;
                } else {
                    self::$domainRule = $item;
                    $currentRules     = isset($item[$method]) ? $item[$method] : $item['*'];
                }
            }
        }
    }

    /**
     * ���URL·��
     * @access public
     * @param Request   $request Request�������
     * @param string    $url URL��ַ
     * @param string    $depr URL�ָ���
     * @param bool      $checkDomain �Ƿ�����������
     * @return false|array
     */
    public static function check($request, $url, $depr = '/', $checkDomain = false)
    {
        // �ָ����滻 ȷ��·�ɶ���ʹ��ͳһ�ķָ���
        $url = str_replace($depr, '|', $url);

        if (isset(self::$rules['alias'][$url]) || isset(self::$rules['alias'][strstr($url, '|', true)])) {
            // ���·�ɱ���
            $result = self::checkRouteAlias($request, $url, $depr);
            if (false !== $result) {
                return $result;
            }
        }
        $method = strtolower($request->method());
        // ��ȡ��ǰ�������͵�·�ɹ���
        $rules = isset(self::$rules[$method]) ? self::$rules[$method] : [];
        // �����������
        if ($checkDomain) {
            self::checkDomain($request, $rules, $method);
        }
        // ���URL��
        $return = self::checkUrlBind($url, $rules, $depr);
        if (false !== $return) {
            return $return;
        }
        if ('|' != $url) {
            $url = rtrim($url, '|');
        }
        $item = str_replace('|', '/', $url);
        if (isset($rules[$item])) {
            // ��̬·�ɹ�����
            $rule = $rules[$item];
            if (true === $rule) {
                $rule = self::getRouteExpress($item);
            }
            if (!empty($rule['route']) && self::checkOption($rule['option'], $request)) {
                self::setOption($rule['option']);
                return self::parseRule($item, $rule['route'], $url, $rule['option']);
            }
        }

        // ·�ɹ�����
        if (!empty($rules)) {
            return self::checkRoute($request, $rules, $url, $depr);
        }
        return false;
    }

    private static function getRouteExpress($key)
    {
        return self::$domainRule ? self::$domainRule['*'][$key] : self::$rules['*'][$key];
    }

    /**
     * ���·�ɹ���
     * @access private
     * @param Request   $request
     * @param array     $rules ·�ɹ���
     * @param string    $url URL��ַ
     * @param string    $depr URL�ָ��
     * @param string    $group ·�ɷ�����
     * @param array     $options ·�ɲ��������飩
     * @return mixed
     */
    private static function checkRoute($request, $rules, $url, $depr = '/', $group = '', $options = [])
    {
        foreach ($rules as $key => $item) {
            if (true === $item) {
                $item = self::getRouteExpress($key);
            }
            if (!isset($item['rule'])) {
                continue;
            }
            $rule    = $item['rule'];
            $route   = $item['route'];
            $vars    = $item['var'];
            $option  = $item['option'];
            $pattern = $item['pattern'];

            // ��������Ч��
            if (!self::checkOption($option, $request)) {
                continue;
            }

            if (isset($option['ext'])) {
                // ·��ext���� ������ϵͳ���õ�URLα��̬��׺����
                $url = preg_replace('/\.' . $request->ext() . '$/i', '', $url);
            }

            if (is_array($rule)) {
                // ����·��
                $pos = strpos(str_replace('<', ':', $key), ':');
                if (false !== $pos) {
                    $str = substr($key, 0, $pos);
                } else {
                    $str = $key;
                }
                if (is_string($str) && $str && 0 !== stripos(str_replace('|', '/', $url), $str)) {
                    continue;
                }
                self::setOption($option);
                $result = self::checkRoute($request, $rule, $url, $depr, $key, $option);
                if (false !== $result) {
                    return $result;
                }
            } elseif ($route) {
                if ('__miss__' == $rule || '__auto__' == $rule) {
                    // ָ������·��
                    $var    = trim($rule, '__');
                    ${$var} = $item;
                    continue;
                }
                if ($group) {
                    $rule = $group . ($rule ? '/' . ltrim($rule, '/') : '');
                }

                self::setOption($option);
                if (isset($options['bind_model']) && isset($option['bind_model'])) {
                    $option['bind_model'] = array_merge($options['bind_model'], $option['bind_model']);
                }
                $result = self::checkRule($rule, $route, $url, $pattern, $option, $depr);
                if (false !== $result) {
                    return $result;
                }
            }
        }
        if (isset($auto)) {
            // �Զ�����URL��ַ
            return self::parseUrl($auto['route'] . '/' . $url, $depr);
        } elseif (isset($miss)) {
            // δƥ������·�ɵ�·�ɹ�����
            return self::parseRule('', $miss['route'], $url, $miss['option']);
        }
        return false;
    }

    /**
     * ���·�ɱ���
     * @access private
     * @param Request   $request
     * @param string    $url URL��ַ
     * @param string    $depr URL�ָ���
     * @return mixed
     */
    private static function checkRouteAlias($request, $url, $depr)
    {
        $array = explode('|', $url);
        $alias = array_shift($array);
        $item  = self::$rules['alias'][$alias];

        if (is_array($item)) {
            list($rule, $option) = $item;
            $action              = $array[0];
            if (isset($option['allow']) && !in_array($action, explode(',', $option['allow']))) {
                // �������
                return false;
            } elseif (isset($option['except']) && in_array($action, explode(',', $option['except']))) {
                // �ų�����
                return false;
            }
            if (isset($option['method'][$action])) {
                $option['method'] = $option['method'][$action];
            }
        } else {
            $rule = $item;
        }
        $bind = implode('|', $array);
        // ������Ч�Լ��
        if (isset($option) && !self::checkOption($option, $request)) {
            // ·�ɲ�ƥ��
            return false;
        } elseif (0 === strpos($rule, '\\')) {
            // ·�ɵ���
            return self::bindToClass($bind, substr($rule, 1), $depr);
        } elseif (0 === strpos($rule, '@')) {
            // ·�ɵ���������
            return self::bindToController($bind, substr($rule, 1), $depr);
        } else {
            // ·�ɵ�ģ��/������
            return self::bindToModule($bind, $rule, $depr);
        }
    }

    /**
     * ���URL��
     * @access private
     * @param string    $url URL��ַ
     * @param array     $rules ·�ɹ���
     * @param string    $depr URL�ָ���
     * @return mixed
     */
    private static function checkUrlBind(&$url, &$rules, $depr = '/')
    {
        if (!empty(self::$bind)) {
            $type = self::$bind['type'];
            $bind = self::$bind[$type];
            // ��¼����Ϣ
            App::$debug && Log::record('[ BIND ] ' . var_export($bind, true), 'info');
            // �����URL�� ����а󶨼��
            switch ($type) {
                case 'class':
                    // �󶨵���
                    return self::bindToClass($url, $bind, $depr);
                case 'controller':
                    // �󶨵���������
                    return self::bindToController($url, $bind, $depr);
                case 'namespace':
                    // �󶨵������ռ�
                    return self::bindToNamespace($url, $bind, $depr);
            }
        }
        return false;
    }

    /**
     * �󶨵���
     * @access public
     * @param string    $url URL��ַ
     * @param string    $class �������������ռ䣩
     * @param string    $depr URL�ָ���
     * @return array
     */
    public static function bindToClass($url, $class, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'method', 'method' => [$class, $action], 'var' => []];
    }

    /**
     * �󶨵������ռ�
     * @access public
     * @param string    $url URL��ַ
     * @param string    $namespace �����ռ�
     * @param string    $depr URL�ָ���
     * @return array
     */
    public static function bindToNamespace($url, $namespace, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : Config::get('default_controller');
        $method = !empty($array[1]) ? $array[1] : Config::get('default_action');
        if (!empty($array[2])) {
            self::parseUrlParams($array[2]);
        }
        return ['type' => 'method', 'method' => [$namespace . '\\' . Loader::parseName($class, 1), $method], 'var' => []];
    }

    /**
     * �󶨵���������
     * @access public
     * @param string    $url URL��ַ
     * @param string    $controller �������� ��֧�ִ�ģ���� index/user ��
     * @param string    $depr URL�ָ���
     * @return array
     */
    public static function bindToController($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'controller', 'controller' => $controller . '/' . $action, 'var' => []];
    }

    /**
     * �󶨵�ģ��/������
     * @access public
     * @param string    $url URL��ַ
     * @param string    $controller �������������������ռ䣩
     * @param string    $depr URL�ָ���
     * @return array
     */
    public static function bindToModule($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'module', 'module' => $controller . '/' . $action];
    }

    /**
     * ·�ɲ�����Ч�Լ��
     * @access private
     * @param array     $option ·�ɲ���
     * @param Request   $request Request����
     * @return bool
     */
    private static function checkOption($option, $request)
    {
        if ((isset($option['method']) && is_string($option['method']) && false === stripos($option['method'], $request->method()))
            || (isset($option['ajax']) && $option['ajax'] && !$request->isAjax()) // Ajax���
             || (isset($option['ajax']) && !$option['ajax'] && $request->isAjax()) // ��Ajax���
             || (isset($option['pjax']) && $option['pjax'] && !$request->isPjax()) // Pjax���
             || (isset($option['pjax']) && !$option['pjax'] && $request->isPjax()) // ��Pjax���
             || (isset($option['ext']) && false === stripos('|' . $option['ext'] . '|', '|' . $request->ext() . '|')) // α��̬��׺���
             || (isset($option['deny_ext']) && false !== stripos('|' . $option['deny_ext'] . '|', '|' . $request->ext() . '|'))
            || (isset($option['domain']) && !in_array($option['domain'], [$_SERVER['HTTP_HOST'], self::$subDomain])) // �������
             || (isset($option['https']) && $option['https'] && !$request->isSsl()) // https���
             || (isset($option['https']) && !$option['https'] && $request->isSsl()) // https���
             || (!empty($option['before_behavior']) && false === Hook::exec($option['before_behavior'])) // ��Ϊ���
             || (!empty($option['callback']) && is_callable($option['callback']) && false === call_user_func($option['callback'])) // �Զ�����
        ) {
            return false;
        }
        return true;
    }

    /**
     * ���·�ɹ���
     * @access private
     * @param string    $rule ·�ɹ���
     * @param string    $route ·�ɵ�ַ
     * @param string    $url URL��ַ
     * @param array     $pattern ��������
     * @param array     $option ·�ɲ���
     * @param string    $depr URL�ָ�����ȫ�֣�
     * @return array|false
     */
    private static function checkRule($rule, $route, $url, $pattern, $option, $depr)
    {
        // �������������
        if (isset($pattern['__url__']) && !preg_match(0 === strpos($pattern['__url__'], '/') ? $pattern['__url__'] : '/^' . $pattern['__url__'] . '/', str_replace('|', $depr, $url))) {
            return false;
        }
        // ���·�ɵĲ����ָ���
        if (isset($option['param_depr'])) {
            $url = str_replace(['|', $option['param_depr']], [$depr, '|'], $url);
        }

        $len1 = substr_count($url, '|');
        $len2 = substr_count($rule, '/');
        // ��������Ƿ�ϲ�
        $merge = !empty($option['merge_extra_vars']);
        if ($merge && $len1 > $len2) {
            $url = str_replace('|', $depr, $url);
            $url = implode('|', explode($depr, $url, $len2 + 1));
        }

        if ($len1 >= $len2 || strpos($rule, '[')) {
            if (!empty($option['complete_match'])) {
                // ����ƥ��
                if (!$merge && $len1 != $len2 && (false === strpos($rule, '[') || $len1 > $len2 || $len1 < $len2 - substr_count($rule, '['))) {
                    return false;
                }
            }
            $pattern = array_merge(self::$rules['pattern'], $pattern);
            if (false !== $match = self::match($url, $rule, $pattern)) {
                // ƥ�䵽·�ɹ���
                return self::parseRule($rule, $route, $url, $option, $match);
            }
        }
        return false;
    }

    /**
     * ����ģ���URL��ַ [ģ��/������/����?]����1=ֵ1&����2=ֵ2...
     * @access public
     * @param string    $url URL��ַ
     * @param string    $depr URL�ָ���
     * @param bool      $autoSearch �Ƿ��Զ��������������
     * @return array
     */
    public static function parseUrl($url, $depr = '/', $autoSearch = false)
    {

        if (isset(self::$bind['module'])) {
            $bind = str_replace('/', $depr, self::$bind['module']);
            // �����ģ��/��������
            $url = $bind . ('.' != substr($bind, -1) ? $depr : '') . ltrim($url, $depr);
        }
        $url              = str_replace($depr, '|', $url);
        list($path, $var) = self::parseUrlPath($url);
        $route            = [null, null, null];
        if (isset($path)) {
            // ����ģ��
            $module = Config::get('app_multi_module') ? array_shift($path) : null;
            if ($autoSearch) {
                // �Զ�����������
                $dir    = APP_PATH . ($module ? $module . DS : '') . Config::get('url_controller_layer');
                $suffix = App::$suffix || Config::get('controller_suffix') ? ucfirst(Config::get('url_controller_layer')) : '';
                $item   = [];
                $find   = false;
                foreach ($path as $val) {
                    $item[] = $val;
                    $file   = $dir . DS . str_replace('.', DS, $val) . $suffix . EXT;
                    $file   = pathinfo($file, PATHINFO_DIRNAME) . DS . Loader::parseName(pathinfo($file, PATHINFO_FILENAME), 1) . EXT;
                    if (is_file($file)) {
                        $find = true;
                        break;
                    } else {
                        $dir .= DS . Loader::parseName($val);
                    }
                }
                if ($find) {
                    $controller = implode('.', $item);
                    $path       = array_slice($path, count($item));
                } else {
                    $controller = array_shift($path);
                }
            } else {
                // ����������
                $controller = !empty($path) ? array_shift($path) : null;
            }
            // ��������
            $action = !empty($path) ? array_shift($path) : null;
            // �����������
            self::parseUrlParams(empty($path) ? '' : implode('|', $path));
            // ��װ·��
            $route = [$module, $controller, $action];
            // ����ַ�Ƿ񱻶����·��
            $name  = strtolower($module . '/' . Loader::parseName($controller, 1) . '/' . $action);
            $name2 = '';
            if (empty($module) || isset($bind) && $module == $bind) {
                $name2 = strtolower(Loader::parseName($controller, 1) . '/' . $action);
            }

            if (isset(self::$rules['name'][$name]) || isset(self::$rules['name'][$name2])) {
                throw new HttpException(404, 'invalid request:' . str_replace('|', $depr, $url));
            }
        }
        return ['type' => 'module', 'module' => $route];
    }

    /**
     * ����URL��pathinfo�����ͱ���
     * @access private
     * @param string    $url URL��ַ
     * @return array
     */
    private static function parseUrlPath($url)
    {
        // �ָ����滻 ȷ��·�ɶ���ʹ��ͳһ�ķָ���
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');
        $var = [];
        if (false !== strpos($url, '?')) {
            // [ģ��/������/����?]����1=ֵ1&����2=ֵ2...
            $info = parse_url($url);
            $path = explode('/', $info['path']);
            parse_str($info['query'], $var);
        } elseif (strpos($url, '/')) {
            // [ģ��/������/����]
            $path = explode('/', $url);
        } else {
            $path = [$url];
        }
        return [$path, $var];
    }

    /**
     * ���URL�͹���·���Ƿ�ƥ��
     * @access private
     * @param string    $url URL��ַ
     * @param string    $rule ·�ɹ���
     * @param array     $pattern ��������
     * @return array|false
     */
    private static function match($url, $rule, $pattern)
    {
        $m2 = explode('/', $rule);
        $m1 = explode('|', $url);

        $var = [];
        foreach ($m2 as $key => $val) {
            // val�ж����˶������ <id><name>
            if (false !== strpos($val, '<') && preg_match_all('/<(\w+(\??))>/', $val, $matches)) {
                $value   = [];
                $replace = [];
                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {
                        $name      = substr($name, 0, -1);
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')?';
                    } else {
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')';
                    }
                    $value[] = $name;
                }
                $val = str_replace($matches[0], $replace, $val);
                if (preg_match('/^' . $val . '$/', isset($m1[$key]) ? $m1[$key] : '', $match)) {
                    array_shift($match);
                    foreach ($value as $k => $name) {
                        if (isset($match[$k])) {
                            $var[$name] = $match[$k];
                        }
                    }
                    continue;
                } else {
                    return false;
                }
            }

            if (0 === strpos($val, '[:')) {
                // ��ѡ����
                $val      = substr($val, 1, -1);
                $optional = true;
            } else {
                $optional = false;
            }
            if (0 === strpos($val, ':')) {
                // URL����
                $name = substr($val, 1);
                if (!$optional && !isset($m1[$key])) {
                    return false;
                }
                if (isset($m1[$key]) && isset($pattern[$name])) {
                    // ����������
                    if ($pattern[$name] instanceof \Closure) {
                        $result = call_user_func_array($pattern[$name], [$m1[$key]]);
                        if (false === $result) {
                            return false;
                        }
                    } elseif (!preg_match(0 === strpos($pattern[$name], '/') ? $pattern[$name] : '/^' . $pattern[$name] . '$/', $m1[$key])) {
                        return false;
                    }
                }
                $var[$name] = isset($m1[$key]) ? $m1[$key] : '';
            } elseif (!isset($m1[$key]) || 0 !== strcasecmp($val, $m1[$key])) {
                return false;
            }
        }
        // �ɹ�ƥ��󷵻�URL�еĶ�̬��������
        return $var;
    }

    /**
     * ��������·��
     * @access private
     * @param string    $rule ·�ɹ���
     * @param string    $route ·�ɵ�ַ
     * @param string    $pathinfo URL��ַ
     * @param array     $option ·�ɲ���
     * @param array     $matches ƥ��ı���
     * @return array
     */
    private static function parseRule($rule, $route, $pathinfo, $option = [], $matches = [])
    {
        $request = Request::instance();
        // ����·�ɹ���
        if ($rule) {
            $rule = explode('/', $rule);
            // ��ȡURL��ַ�еĲ���
            $paths = explode('|', $pathinfo);
            foreach ($rule as $item) {
                $fun = '';
                if (0 === strpos($item, '[:')) {
                    $item = substr($item, 1, -1);
                }
                if (0 === strpos($item, ':')) {
                    $var           = substr($item, 1);
                    $matches[$var] = array_shift($paths);
                } else {
                    // ����URL�еľ�̬����
                    array_shift($paths);
                }
            }
        } else {
            $paths = explode('|', $pathinfo);
        }

        // ��ȡ·�ɵ�ַ����
        if (is_string($route) && isset($option['prefix'])) {
            // ·�ɵ�ַǰ׺
            $route = $option['prefix'] . $route;
        }
        // �滻·�ɵ�ַ�еı���
        if (is_string($route) && !empty($matches)) {
            foreach ($matches as $key => $val) {
                if (false !== strpos($route, ':' . $key)) {
                    $route = str_replace(':' . $key, $val, $route);
                }
            }
        }

        // ��ģ������
        if (isset($option['bind_model'])) {
            $bind = [];
            foreach ($option['bind_model'] as $key => $val) {
                if ($val instanceof \Closure) {
                    $result = call_user_func_array($val, [$matches]);
                } else {
                    if (is_array($val)) {
                        $fields    = explode('&', $val[1]);
                        $model     = $val[0];
                        $exception = isset($val[2]) ? $val[2] : true;
                    } else {
                        $fields    = ['id'];
                        $model     = $val;
                        $exception = true;
                    }
                    $where = [];
                    $match = true;
                    foreach ($fields as $field) {
                        if (!isset($matches[$field])) {
                            $match = false;
                            break;
                        } else {
                            $where[$field] = $matches[$field];
                        }
                    }
                    if ($match) {
                        $query  = strpos($model, '\\') ? $model::where($where) : Loader::model($model)->where($where);
                        $result = $query->failException($exception)->find();
                    }
                }
                if (!empty($result)) {
                    $bind[$key] = $result;
                }
            }
            $request->bind($bind);
        }

        if (!empty($option['response'])) {
            Hook::add('response_send', $option['response']);
        }

        // �����������
        self::parseUrlParams(empty($paths) ? '' : implode('|', $paths), $matches);
        // ��¼ƥ���·����Ϣ
        $request->routeInfo(['rule' => $rule, 'route' => $route, 'option' => $option, 'var' => $matches]);

        // ���·��after��Ϊ
        if (!empty($option['after_behavior'])) {
            if ($option['after_behavior'] instanceof \Closure) {
                $result = call_user_func_array($option['after_behavior'], []);
            } else {
                foreach ((array) $option['after_behavior'] as $behavior) {
                    $result = Hook::exec($behavior, '');
                    if (!is_null($result)) {
                        break;
                    }
                }
            }
            // ·�ɹ����ض���
            if ($result instanceof Response) {
                return ['type' => 'response', 'response' => $result];
            } elseif (is_array($result)) {
                return $result;
            }
        }

        if ($route instanceof \Closure) {
            // ִ�бհ�
            $result = ['type' => 'function', 'function' => $route];
        } elseif (0 === strpos($route, '/') || strpos($route, '://')) {
            // ·�ɵ��ض����ַ
            $result = ['type' => 'redirect', 'url' => $route, 'status' => isset($option['status']) ? $option['status'] : 301];
        } elseif (false !== strpos($route, '\\')) {
            // ·�ɵ�����
            list($path, $var) = self::parseUrlPath($route);
            $route            = str_replace('/', '@', implode('/', $path));
            $method           = strpos($route, '@') ? explode('@', $route) : $route;
            $result           = ['type' => 'method', 'method' => $method, 'var' => $var];
        } elseif (0 === strpos($route, '@')) {
            // ·�ɵ�������
            $route             = substr($route, 1);
            list($route, $var) = self::parseUrlPath($route);
            $result            = ['type' => 'controller', 'controller' => implode('/', $route), 'var' => $var];
            $request->action(array_pop($route));
            $request->controller($route ? array_pop($route) : Config::get('default_controller'));
            $request->module($route ? array_pop($route) : Config::get('default_module'));
            App::$modulePath = APP_PATH . (Config::get('app_multi_module') ? $request->module() . DS : '');
        } else {
            // ·�ɵ�ģ��/������/����
            $result = self::parseModule($route, isset($option['convert']) ? $option['convert'] : false);
        }
        // �������󻺴�
        if ($request->isGet() && isset($option['cache'])) {
            $cache = $option['cache'];
            if (is_array($cache)) {
                list($key, $expire, $tag) = array_pad($cache, 3, null);
            } else {
                $key    = str_replace('|', '/', $pathinfo);
                $expire = $cache;
                $tag    = null;
            }
            $request->cache($key, $expire, $tag);
        }
        return $result;
    }

    /**
     * ����URL��ַΪ ģ��/������/����
     * @access private
     * @param string    $url URL��ַ
     * @param bool      $convert �Ƿ��Զ�ת��URL��ַ
     * @return array
     */
    private static function parseModule($url, $convert = false)
    {
        list($path, $var) = self::parseUrlPath($url);
        $action           = array_pop($path);
        $controller       = !empty($path) ? array_pop($path) : null;
        $module           = Config::get('app_multi_module') && !empty($path) ? array_pop($path) : null;
        $method           = Request::instance()->method();
        if (Config::get('use_action_prefix') && !empty(self::$methodPrefix[$method])) {
            // ��������ǰ׺֧��
            $action = 0 !== strpos($action, self::$methodPrefix[$method]) ? self::$methodPrefix[$method] . $action : $action;
        }
        // ���õ�ǰ�����·�ɱ���
        Request::instance()->route($var);
        // ·�ɵ�ģ��/������/����
        return ['type' => 'module', 'module' => [$module, $controller, $action], 'convert' => $convert];
    }

    /**
     * ����URL��ַ�еĲ���Request����
     * @access private
     * @param string    $url ·�ɹ���
     * @param array     $var ����
     * @return void
     */
    private static function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if (Config::get('url_param_type')) {
                $var += explode('|', $url);
            } else {
                preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
        }
        // ���õ�ǰ����Ĳ���
        Request::instance()->route($var);
    }

    // ����·�ɹ����еı���
    private static function parseVar($rule)
    {
        // ��ȡ·�ɹ����еı���
        $var = [];
        foreach (explode('/', $rule) as $val) {
            $optional = false;
            if (false !== strpos($val, '<') && preg_match_all('/<(\w+(\??))>/', $val, $matches)) {
                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {
                        $name     = substr($name, 0, -1);
                        $optional = true;
                    } else {
                        $optional = false;
                    }
                    $var[$name] = $optional ? 2 : 1;
                }
            }

            if (0 === strpos($val, '[:')) {
                // ��ѡ����
                $optional = true;
                $val      = substr($val, 1, -1);
            }
            if (0 === strpos($val, ':')) {
                // URL����
                $name       = substr($val, 1);
                $var[$name] = $optional ? 2 : 1;
            }
        }
        return $var;
    }
}
