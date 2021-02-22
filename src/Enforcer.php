<?php

namespace teamones\registerRoute;

use teamones\http\Client;

class Enforcer
{

    /**
     * @var array
     */
    protected $_instance = null;

    protected $_url = "";

    /**
     * Enforcer constructor.
     * @param string $url
     */
    public function __construct(string $url)
    {
        $this->_instance = new Client();
        $this->_url = $url;
    }

    /**
     * 异步提交到路由管理服务
     * @param array $postData
     */
    protected function registerRemote($postData = [])
    {
        try {
            $response = $this->_instance->setHost($this->_url)
                ->setBody(['data' => $postData])
                ->setMethod("POST")
                ->request();
        } catch (\Exception $e) {
        }
    }

    /**
     * 注册Teamones框架路由
     * @param $routes
     * @param string $method
     */
    public function registerTeamonesFramework($routes, $belongSystem = '', $method = '')
    {
        $updateData = [];

        if (!empty($routes) && is_array($routes)) {
            foreach ($routes as $routekey => $param) {

                $routeMethods = [];
                if (is_array($param[1]) && !empty($param[1]['method'])) {
                    $routeMethods = explode(',', $param[1]['method']);
                } else {
                    $routeMethods = ['POST'];
                }

                foreach ($routeMethods as $routeMethod) {
                    $updateData[] = [
                        "belong_system" => $belongSystem, // 所属系统
                        "record" => $routekey, // 路由记录
                        "method" => !empty($method) ? strtoupper($method) : strtoupper($routeMethod) // 请求方式
                    ];
                }
            }
        }

        $this->registerRemote($updateData);
    }

    /**
     * 注册webman框架路由
     * @param $routes
     * @param string $method
     */
    public function registerWebmanFramework($routes, $belongSystem = '', $method = '')
    {

    }
}