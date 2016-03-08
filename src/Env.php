<?php
namespace ClientAdapter;
class Env {

    public static $env;

    public static function setCurrEnv(Env $env) {
        self::$env = $env;
    }

    public $sessUid   = 0;
    public $clientIp  = '0.0.0.0';
    public $longitude = 0.0;
    public $latitude  = 0.0;

    // 供下层应用扩展自有数据
    protected $datas = array();

    public function __get($name) {
        $getter = 'get' . ucfirst($name);
        if (!method_exists($this, $getter)) {
            return NULL;
        }
        return $this->$getter();
    }

    /**
     * checkEnv
     * 检查当前客户端环境, 是否和$envDesc描述的环境match
     * @param mixed $envDesc
     * @access private
     * @return void
     */
    public static function checkEnv($envDesc) {
        $envDesc = (array)$envDesc;
        //支持两层array结构，如果是两层array，每个array之间使用逻辑或,每个array自身使用逻辑与
        if(!is_array($envDesc[0])){
            $envDesc = array($envDesc);
        }
        foreach ($envDesc as $descs) {
            $ret = false;
            $current = true;
            foreach ($descs as $desc) {
                if (!preg_match(';^(?P<env>\w+)\s+(?P<operator>\S+)(?:\s+(?P<arg>.*))?$;', $desc, $match)) {
                    continue;
                }
                // 目前的实现, 仅支持所有条件逻辑与
                $arg = array_key_exists('arg', $match) ? $match['arg'] : '';
                $envName = $match['env'];
                $envValue = self::$env->$envName;
                $operator = $match['operator'];
                switch ($operator) {
                    case 'match':
                        if (!preg_match(strval($arg), strval($envValue))) {
                            $current = false;
                        }
                        break;
                    case 'eq' :
                        if (strval($envValue) !=trim($arg)) {
                            $current = false;
                        }
                        break;
                    case 'neq' :
                        if (strval($envValue) ==trim($arg)) {
                            $current = false;
                        }
                        break;
                    case '>=' :
                        if (floatval($envValue) <floatval($arg)) {
                            $current = false;
                        }
                        break;
                    case '<=' :
                        if (floatval($envValue) >floatval($arg)) {
                            $current = false;
                        }
                        break;
                    case '=' :
                        if (floatval($envValue) !=floatval($arg)) {
                            $current = false;
                        }
                        break;
                    case '>' :
                        if (floatval($envValue) <=floatval($arg)) {
                            $current = false;
                        }
                        break;
                    case '<' :
                        if (floatval($envValue) >=floatval($arg)) {
                            $current = false;
                        }
                    case 'v>=' :
                        if (self::versionDiff($envValue, $arg) <0) {
                            $current = false;
                        }
                        break;
                    case 'v<=' :
                        if (self::versionDiff($envValue, $arg) >0) {
                            $current = false;
                        }
                        break;
                    case 'v=' :
                        if (self::versionDiff($envValue, $arg) !==0) {
                            $current = false;
                        }
                        break;
                    case 'v>' :
                        if (self::versionDiff($envValue, $arg) <=0) {
                            $current = false;
                        }
                        break;
                    case 'v<' :
                        if (self::versionDiff($envValue, $arg) >=0) {
                            $current = false;
                        }
                        break;
                    case '<=%': # deviceId <=% 100:10  表示设备id经过运算后的对100取模小于等于10为真
                    case '>=%': # deviceId >=% 100:10  表示设备id经过运算后的对100取模大于等于10为真
                    case '>%':  # deviceId >% 100:10  表示设备id经过运算后的对100取模大于10为真
                    case '<%':  # deviceId <% 100:10  表示设备id经过运算后的对100取模小于10为真
                        if(!self::_judgeDuration($envValue, $operator, $arg)){
                            $current = false; 
                        }
                        break;
                    case '~': #不区分大小写
                        if (empty($envValue) || strpos(strtolower($envValue), strtolower($arg)) === false) {
                            $current = false;
                        }
                        break;
                    case '!~':#不区分大小写
                        if (empty($envValue) || strpos(strtolower($envValue), strtolower($arg)) != false) {
                            $current = false;
                        }
                        break;
                    case 'in':#in操作, 后面的数据以英文逗号分开: 切记中文逗号不算分隔符号
                        $arg = $arg ? trim($arg) : '';
                        $elems = explode(",", $arg);
                        $elems = array_filter(array_map('trim', $elems));
                        if(empty($envValue) || !in_array(trim(strval($envValue)), $elems, TRUE)){
                            $current = false;
                        }
                        break;
                    case 'notin':#in操作, 后面的数据以英文逗号分开: 切记中文逗号不算分隔符号
                        $arg = $arg ? trim($arg) : '';
                        $elems = explode(",", $arg);
                        $elems = array_filter(array_map('trim', $elems));
                        if(in_array(trim(strval($envValue)), $elems, TRUE)){
                            $current = false;
                        }
                        break;
                    default :
                        trigger_error('undefined operator[' .$operator .'] for client env adapter', E_USER_ERROR);
                        break;
                }
                if(!$current){
                    break;
                }
            }
            if($current){//找到一个符合的分支
                return TRUE;
            }

        }
        return $ret;
    }

    private static function _judgeDuration($envValue, $operator, $arg){
        $ret = true;
        if(empty($envValue)){
            $ret = false; 
        }
        if($ret){
            list($total, $limit) = explode(':', $arg);
            $total = intval($total);
            $limit = intval($limit);
            $res = 0;
            if (is_numeric($envValue)){
                $res = intval($envValue);
            } else {
                $res = crc32($envValue); 
            }
            if(!$res){
                $ret = false; 
            }
            $tmpRes = $res % $total;  
            if ($operator == '>=%' && $tmpRes < $limit){
                $ret = false;
            } else if ($operator == '<=%' && $tmpRes > $limit){
                $ret = false;
            } else if ($operator == '<%' && $tmpRes >= $limit){
                $ret = false;
            } else if ($operator == '>%' && $tmpRes <= $limit){
                $ret = false;
            }
        }
        return $ret;
    }


    private static function versionDiff($v1, $v2) {

        $i1 = explode('.', $v1);
        $i2 = explode('.', $v2);
        $l1 = count($i1);
        $l2 = count($i2);

        $len = max($l1, $l2);
        for ($i = 0; $i < $len; $i++) {
            $p1 = isset($i1[$i]) ? intval($i1[$i]) : 0;
            $p2 = isset($i2[$i]) ? intval($i2[$i]) : 0;

            if ($p1 > $p2) {
                return 1;
            } elseif ($p1 < $p2) {
                return -1;
            }
        }
        return 0;
    }

}