<?php
//定义模型命名空间
namespace  app\model\model;
//实例化Model类
use think\Model;
class Captcha extends Model
{
    //表名
    protected $name = 'captcha';

    //date转time格式
    public function dateToTime($date){
        return strtotime($date);
    }
}