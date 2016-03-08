<?php
require_once __DIR__ . '/vendor/autoload.php';

############################# Env本身定义了一个抽象概念, 需要根据不同的运营环境定义不同的实现. 比如, 对于客户端App
class AppEnv extends \ClientAdapter\Env {
    public $osName;
    public $appVersion;
}

############################ 客户端适配器, 本身提供了一种规则校验, 下面以我们的Feature应用场景示例
class Feature {
    protected static $features = array();

    public static function init($featuresConfig) {
        foreach ($featuresConfig as $featureName => $envDesc) {
            if (\ClientAdapter\Env::checkEnv($envDesc)) {
                self::$features[$featureName] = TRUE;
            }
        }
    }

    public static function isEnable($featureName) {
        return array_key_exists($featureName, self::$features) && self::$features[$featureName] === TRUE;
    }
}


########################## OK. 下面是应用中使用Feature的应用场景

echo "app start\n\n";

echo "prePare Env\n\n";
$appEnv = new \AppEnv();
$appEnv->sessUid    = rand(0, 10000);
$appEnv->clientIp   = long2ip(rand(0, pow(2, 32)));
$appEnv->latitude   = rand(0, 1000000) / 1000;
$appEnv->longitude  = rand(0, 1000000) / 1000;
$appEnv->osName     = rand(0, 1) ? 'ios' : 'android';
$appEnv->appVersion = rand(0, 100) / 10;

\ClientAdapter\Env::setCurrEnv($appEnv);

echo "Features init\n\n";
$featuresConfig = array(
    'support-hail' => array( # ios 5.2及以上版本; android 5.3及以上版本. 支持打招呼功能
        array(
            'osName eq ios',
            'appVersion v>= 5.2',
        ),
        array(
            'osName eq android',
            'appVersion v>= 5.3',
        ),
    ),
    'support-emotion' => array( # ios 5.3及以上版本, 10%小流量用户开启表情功能
        'osName eq ios',
        'appVersion v>= 5.3',
        'sessUid <=% 100:10',
    ),
);
Feature::init($featuresConfig);

echo "Business code is\n";
if (Feature::isEnable('support-hail')) {
    echo "\tHere is hail code\n";
}
if (Feature::isEnable('support-emotion')) {
    echo "\tHere is emotion code\n";
}

echo "\nAppEnv is:\n";
echo json_encode((array)$appEnv, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . chr(10);
