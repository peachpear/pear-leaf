在团队开发中，有些数据是比较敏感的，比如mysql密码等，这些信息最好能存在团队成员接触不到的服务器上。
但是应用怎么读取这些信息呢，所以有了这个package：旨在独立与整合应用配置参数，使项目更安全。


在入口文件index.php定义应用名称：
```
defined('APP_NAME') or define('APP_NAME', 'demo');
```

也可以进一步定义版本号：
```
defined('VERSION') or define('VERSION', '2.0.2');
```

如在Yii2应用中使用

common/config/main-local.php中定义所需要的components，并增加ConfigService配置：

```
<?php
return [
    'components' => [
        'cache' => array(
            'host' => '',
            'port' => 6379,
            "password" => "",
            'keyPrefix' => '',
        ),
        'demoDB' => [
            'dsn' => '',
            'username' => '',
            'password' => '',
        ];
        'pearDB' => [
            'dsn' => '',
            'username' => '',
            'password' => '',
        ];
        'kafkaProducer' => array(
            "metadata" => array(
                "brokerList" => "",
            ),
            "requireAck" => 0,
        ),
        'queue' => array(
            'credentials' => array(
                'host' => '',
                'port' => '',
                'login' => '',
                'password' => ''
            )
        ),
    ],
    'params' => [],
    "ConfigService" => [
        "filePath" => "/data/config/dev/",
        "fileExt" => "json",
    ]
];
```

backend/web/index.php：

```
<?php
defined('APP_NAME') or define('APP_NAME', 'demo');
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../common/config/bootstrap.php';
require __DIR__ . '/../config/bootstrap.php';

$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/main.php',
    require __DIR__ . '/../../common/config/main-local.php',
    require __DIR__ . '/../config/main.php',
    require __DIR__ . '/../config/main-local.php'
);

$configService = peachpear\pearLeaf\ConfigService::getInstance($config['ConfigService']['filePath'], $config['ConfigService']['fileExt']);
$configService->loadJson($config);
$server_config = $configService->getConfig();
    
$config = yii\helpers\ArrayHelper::merge($config, $server_config);
unset($config['ConfigService']);

(new yii\web\Application($config))->run();
```

