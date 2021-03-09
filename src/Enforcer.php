<?php

namespace teamones\registerRoute;

use think\facade\Db;
use teamones\http\Client;

class Enforcer
{

    /**
     * @var array
     */
    protected $_instance = null;

    protected $_url = "";

    protected $_isSelf = false;

    /**
     * Enforcer constructor.
     * @param string $url
     * @param bool $isSelf
     */
    public function __construct(string $url, $isSelf = false)
    {
        $this->_instance = new Client();
        $this->_url = $url;
        $this->_isSelf = $isSelf;
    }

    /**
     * 通过数据库直接更新记录
     * @param $data
     * @return array
     */
    protected function saveRecordByDB($data)
    {
        try {
            $dbConfig = config('thinkorm');
            $dbConfig['connections']['route_register'] = $dbConfig['connections']['mysql'];
            $dbConfig['break_reconnect'] = false;
            Db::setConfig($dbConfig);


            // 1. 组装查询条件
            // - belong_system
            // - method
            // - record
            // 上面三者联合唯一
            $filterBelongSystem = [];
            $filterMethod = [];
            $filterRecord = [];
            foreach ($data as $item) {
                if (!empty($item['belong_system']) && !in_array($item['belong_system'], $filterBelongSystem)) {
                    $filterBelongSystem[] = $item['belong_system'];
                }

                if (!empty($item['method']) && !in_array($item['method'], $filterMethod)) {
                    $filterMethod[] = $item['method'];
                }

                if (!empty($item['record']) && !in_array($item['record'], $filterRecord)) {
                    $filterRecord[] = $item['record'];
                }
            }

            $ExistRoutes = Db::connect('route_register')
                ->table('route')
                ->whereIn("belong_system", $filterBelongSystem)
                ->whereIn("method", $filterMethod)
                ->whereIn("record", $filterRecord)
                ->select();

            // 2. 查询重复记录
            $ExistRoutesMap = [];
            foreach ($ExistRoutes as $ExistRoute) {
                $keyMd5 = md5("{$ExistRoute['belong_system']}_{$ExistRoute['record']}_{$ExistRoute['method']}");
                if (!in_array($keyMd5, $ExistRoutesMap)) {
                    $ExistRoutesMap[] = $keyMd5;
                }
            }

            // 3. 写入非重复记录
            $saveData = [];
            foreach ($data as $item) {
                $keyMd5 = md5("{$item['belong_system']}_{$item['record']}_{$item['method']}");
                if (!in_array($keyMd5, $ExistRoutesMap)) {
                    $saveData[] = $item;
                }
            }

            if (empty($saveData)) {
                return [];
            }

            Db::connect('route_register')->table('route')->insertAll($saveData);

            // 处理完释放连接
            Db::connect('route_register')->close();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }

    /**
     * 异步提交到路由管理服务
     * @param array $postData
     */
    protected function registerRemote($postData = [])
    {
        if ($this->_isSelf) {
            $this->saveRecordByDB($postData);
        } else {
            try {
                $response = $this->_instance->setHost($this->_url)
                    ->setBody(['data' => $postData])
                    ->setMethod("POST")
                    ->request();
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * 注册Teamones框架路由
     * @param $routes
     * @param string $belongSystem
     * @param string $method
     */
    public function registerTeamonesFramework($routes, $belongSystem = '', $method = '')
    {
        $updateData = [];

        if (!empty($routes) && is_array($routes)) {
            foreach ($routes as $routekey => $param) {

                $routeMethods = [];
                if (is_array($param[1]) && !empty($param[1]['method'])) {
                    $routeMethods = explode('|', $param[1]['method']);
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
     * @param $routeConfig
     * @param string $belongSystem
     * @param string $method
     */
    public function registerWebmanFramework($routeConfig, $belongSystem = '', $method = '')
    {
        $handle = fopen($routeConfig, "r");//读取二进制文件时，需要将第二个参数设置成'rb'

        //通过filesize获得文件大小，将整个文件一下子读到一个字符串中
        $contents = fread($handle, filesize($routeConfig));
        fclose($handle);

        // 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'
        $matchRegexs = [
            'Route::any' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'],
            'Route::get' => ['GET'],
            'Route::post' => ['POST'],
            'Route::put' => ['PUT'],
            'Route::delete' => ['DELETE'],
            'Route::patch' => ['PATCH'],
            'Route::head' => ['HEAD'],
            'Route::options' => ['OPTIONS'],
        ];

        $routes = [];
        foreach ($matchRegexs as $key => $methods) {
            preg_match_all("/{$key}\('(.*?)',/", $contents, $m);

            if (!empty($m[1])) {
                $routes[$key] = [
                    'methods' => $methods,
                    'routes' => $m[1]
                ];
            }
        }


        $updateData = [];
        if (!empty($routes) && is_array($routes)) {
            foreach ($routes as $param) {
                foreach ($param['methods'] as $routeMethod) {
                    foreach ($param['routes'] as $route) {
                        $updateData[] = [
                            "belong_system" => $belongSystem, // 所属系统
                            "record" => $route, // 路由记录
                            "method" => !empty($method) ? strtoupper($method) : strtoupper($routeMethod) // 请求方式
                        ];
                    }
                }
            }
        }

        $this->registerRemote($updateData);
    }
}