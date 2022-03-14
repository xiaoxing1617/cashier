<?php
//定义模型命名空间
namespace app\model\model;
//实例化Model类
use think\Model;

class Ad extends Model
{
    //表名
    protected $name = 'ad';

    public function getLevelName($key = "", $mode = "", $sw = false)
    {
        $arr = [
            "bannerPicture" => [
                "name" => "横幅图片",
                "list" => [
                ]
            ],
            "paymentSuccess"=>[
                "name"=>"支付完成动作",
                "list"=>[

                ]
            ]
        ];
        if ($sw) {
            return $arr;
        }
        if ($mode == "top") {
            if (!array_key_exists($key, $arr)) {
                return "未知";
            } else {
                return $arr[$mode];
            }
        }else if($mode=="two"){
            foreach ($arr as $v){
                if (array_key_exists($key, $v['list'])) {
                    return $v['list'][$mode];
                }
            }
            return "未知";
        }
    }
}