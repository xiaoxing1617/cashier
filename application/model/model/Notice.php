<?php
//定义模型命名空间
namespace  app\model\model;
//实例化Model类
use think\Model;
class Notice extends Model
{
    //表名
    protected $name = 'notice';

    public function getGroupName($val="",$list=false){
        $arr = array(
            'user'=>  "商户公告"
        );
        if($list){
            return $arr;
        }
        if($val==""){
            $val = $this->group;
        }
        if(isset($arr[$val])){
            return $arr[$val];
        }else{
            return "未知";
        }
    }
}