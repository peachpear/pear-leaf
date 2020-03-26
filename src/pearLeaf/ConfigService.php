<?php
namespace peachpear\pearLeaf;

/**
 * 参数配置服务类
 * Class ConfigService
 * @package peachpear\pearLeaf
 */
class ConfigService
{
    /**
     * @var string
     * 应用名称
     */
    private $appName;

    /**
     * @var string
     * 应用版本号
     */
    private $version;

    /**
     * @var string
     * 文件路径
     */
    private $filePath = "";

    /**
     * @var string
     * 文件后缀名
     */
    private $fileExt = "json";

    /**
     * @var string
     * 文件名称
     */
    private $fileName = "";

    /**
     * @var string
     * 完整路径文件名
     */
    private $fullName;

    /**
     * @var array
     * 配置参数
     */
    private $config = [];

    /**
     * @var array
     *
     */
    private $rhKey = [
        "mysql" => 1,
        "redis" => 1,
        "kafka" => 1,
        "params" => 1,
        "queue" => 1,
        "queue_log" => 1,
    ];

    /**
     * @var
     * 实例
     */
    private static $instance;

    /**
     * 类构建函数
     * ConfigService constructor.
     * @param $filePath
     * @param $fileExt
     */
    private function __construct($filePath, $fileExt)
    {
        $this->setAppName();
        $this->setVersion();
        $this->setFilePath($filePath);
        $this->setFileExtension($fileExt);
        $this->setFileName();
        $this->setFullName();
    }

    /**
     * 获取实例
     * @param string $filePath
     * @param string $fileExt
     * @return ConfigService
     */
    public static function getInstance($filePath = "/data/config/", $fileExt = "json")
    {
        if (!self::$instance) {
            self::$instance = new self($filePath, $fileExt);
        }

        return self::$instance;
    }

    /**
     * @param null $config
     */
    public function loadJson($config = null)
    {
        if (file_exists($this->getFullName())) {
            $jsonData = file_get_contents($this->fullName);
            if ($jsonData) {
                // 存在数据
                $server_config = $this->handleData(json_decode($jsonData, true), $config);

                $this->setConfig($server_config);
            }
        }
    }

    /**
     * 设置配置参数
     * @param $config
     */
    public function setConfig( $config = [] )
    {
        $this->config = $config;
    }

    /**
     * 获取配置参数
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 设置应用名称
     */
    public function setAppName()
    {
        $this->appName = defined("APP_NAME") ? APP_NAME : "demo";
    }

    /**
     * 获取应用名称
     * @return string
     */
    public function getAppName()
    {
        return $this->appName;
    }

    /**
     * 设置应用版本号
     */
    public function setVersion()
    {
        $this->version = defined("VERSION") ? VERSION : "*";
    }

    /**
     * 获取应用版本号
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * 设置文件路径
     * @param $filePath
     */
    public function setFilePath($filePath)
    {
        if ($filePath) {
            $this->filePath = $filePath;
        }
    }

    /**
     * 获取文件路径
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * 设置文件后缀名
     * @param $fileExt
     */
    public function setFileExtension($fileExt)
    {
        $this->fileExt = $fileExt;
    }

    /**
     * 获取文件后缀名
     * @return string
     */
    public function getFileExtension()
    {
        return $this->fileExt;
    }

    /**
     * 设置文件名称
     */
    public function setFileName()
    {
        if (!$this->fileName) {
            $this->fileName = $this->getAppName() . "." . $this->getFileExtension();
        }
    }

    /**
     * 获取文件名称
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * 设置完整路径文件名
     */
    public function setFullName()
    {
        $this->fullName = $this->getFilePath() . $this->getFileName();
    }

    /**
     * 获取完整路径文件名
     * @return string
     */
    public function getFullName()
    {
        return $this->fullName;
    }

    /**
     * @param $config    服务器上的配置参数
     * @param null $oldConfig  项目配置参数
     * @return array|null
     */
    private function handleData($config, $oldConfig = null)
    {
        if (!$config) {
            return null;
        }

        // 排序
        foreach ($config as $k => $v )
        {
            foreach ($v as $kk => $vv )
            {
                // 针对版本排序，*版本优先，其他具体版本维持现状
                uasort($vv, array($this, "versionSort"));
                // 重新索引键名，array_merge 数组键名以 0 开始进行重新索引
                $config[$k][$kk] = array_merge($vv);
            }
        }

        $returnConfig = [];
        foreach ($config as $key => $value)
        {
            if ($key == $this->getParamsKey()) {
                $key = "params";
            }

            if (isset($this->rhKey[$key])) {
                foreach ($value as $kk => $vv)
                {
                    foreach ($vv as $kkk => $vvv)
                    {
                        // 检查version版本号，要么为*,要么是项目配置的版本号，如果不是，跳过本次循环,
                        if (!$this->matchVersion($vvv)){
                            continue;
                        }

                        if ($key == "mysql") {
                            $mysqlKey = $this->getMysqlKey($vvv);
                            // 项目配置参数中无此数据库参数则跳过本次循环
                            if (!isset($oldConfig["components"][$mysqlKey])) {
                                continue;
                            }

                            if (strpos($kk, "master") !== false) {
                                $returnConfig["components"][$mysqlKey] = [
                                    "dsn" => $this->getDSN($vvv),
                                    "username" => $vvv["username"],
                                    "password" => $vvv["password"],
                                ];
                            } elseif (strpos($kk, "slave") !== false) {
                                $returnConfig["components"][$mysqlKey]["slaves"][] = [
                                    "dsn" => $this->getDSN($vvv),
                                    "username" => $vvv["username"],
                                    "password" => $vvv["password"],
                                ];
                            }
                        } elseif ($key == "redis") {
                            if (!isset($oldConfig["components"][$kk])) {
                                continue;
                            }

                            $returnConfig["components"][$kk] = [
                                'host' => $vvv["host"],
                                'port' => $vvv["port"],
                                "password" => empty($vvv["password"]) ? "" : trim($vvv["password"])
                            ];
                            if (isset($vvv["database"])) {
                                $returnConfig["components"][$kk]["database"] = $vvv["database"];
                            }
                            if (isset($vvv["keyPrefix"])) {
                                $returnConfig["components"][$kk]["keyPrefix"] = $vvv["keyPrefix"];
                            }
                        } elseif ($key == "kafka") {
                            if (!isset($oldConfig["components"]["kafkaProducer"])) {
                                continue;
                            }

                            $returnConfig["components"]["kafkaProducer"]["metadata"]["brokerList"] = $this->getKafkaBrokerList($vvv["brokerList"]);
                        } elseif (strpos($key, "queue") !== false) {
                            if (!isset($oldConfig["components"][$key])) {
                                continue;
                            }

                            $returnConfig["components"][$key]["credentials"] = [
                                'host' => $vvv["host"],
                                'port' => $vvv["port"],
                                'login' => $vvv["login"],
                                'password' => $vvv["password"],
                            ];
                        } elseif ($key == "params") {
                            if (isset($vvv["version"])) {
                                unset($vvv["version"]);
                            }

                            if (isset($returnConfig["params"])) {
                                $returnConfig["params"] = $vvv + $returnConfig["params"];
                            } else {
                                $returnConfig["params"] = $vvv;
                            }
                        }
                    }
                }
            }
        }

        return $returnConfig;
    }

    /**
     * 针对version倒序，*版本优先，其他具体版本维持现状
     * @param $a
     * @param $b
     * @return int
     */
    private function versionSort($a, $b)
    {
        if (!isset($a["version"]) || $a["version"][0] == "*") {
            return -1;  // $a排到$b前
        } else if (!isset($b["version"]) || $b["version"][0] == "*") {
            return 1;  // $b排到$a前
        } else {
            return 0;  // 维持现状
        }
    }

    /**
     * 获取应用params名
     */
    private function getParamsKey()
    {
        return $this->getAppName()."-params";
    }

    /**
     * 匹配版本
     * @param array $array
     * @return bool
     */
    private function matchVersion(array $array)
    {
        if (!isset($array["version"])) {
            return true;
        }

        if (is_array($array["version"])) {
            foreach ($array["version"] as $version)
            {
                if ($version == $this->getVersion() || $version == "*") {
                    return true;
                }
            }
        } elseif (is_string($array["version"])) {
            if ($array["version"] == $this->getVersion() || $array["version"] == "*") {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $array
     * @return string
     */
    private function getMysqlKey(array $array)
    {
        if (empty($array["db"])) {
            return "";
        }

        return $array["db"] ."DB";
    }

    /**
     * 获取数据源名称
     * @param array $array
     * @return string
     */
    private function getDSN(array $array)
    {
        if (empty($array["host"]) || empty($array["port"]) || empty($array["db"])) {
            return "";
        }

        return "mysql:host=" .$array["host"] .";port=".$array["port"] .";dbname=" .$array["db"];
    }

    /**
     * 获取Kafka的brokerList
     * @param array $array
     * @return string
     */
    private function getKafkaBrokerList(array $array)
    {
        $return = "";
        foreach ($array as $value)
        {
            $return .= $value["host"].":".$value["port"].",";
        }

        return rtrim($return, ",");
    }
}