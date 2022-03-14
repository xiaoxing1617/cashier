<?php
//定义模型命名空间
namespace app\model\model;
//实例化Model类
use think\Model;

class Core extends Model
{
    //表名
    protected $name = 'core';

    public function getCaptchaSwitch($sw = null, $all = false)
    {
        $arr = ["admin_login", "user_login", "user_register"];
        if ($all) {
            return $arr;
        }
        $val = explode("|", Core::getByName('captcha_switch')['value1']);
        if (in_array($sw, $val)) {
            return true;
        } else {
            return false;
        }
    }

}