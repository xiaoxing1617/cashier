<?php
//定义模型命名空间
namespace  app\model\model;
//实例化Model类
use think\Model;
class IllegalRecord extends Model
{
    //表名
    protected $name = 'illegal_record';

    public function getModeName($mode="",$sw=false){
        $arr = [
            "0" => "低质灌水",
            "1" => "暴恐违禁",
            "2" => "文本色情",
            "3" => "政治敏感",
            "4" => "恶意推广",
            "5" => "低俗辱骂",
            "6" => "恶意联系方式推广",
            "7" => "恶意软文推广"
        ];
        if($sw){
            return $arr;
        }
        if ($mode == "") {
            $mode = $this->mode;
        }
        if (!array_key_exists($mode, $arr)) {
            return "未知";
        } else {
            return $arr[$mode];
        }
    }
    public function getSourceName($source="",$sw=false){
        $arr = [
            "1" => "支付商品名称"
        ];
        if($sw){
            return $arr;
        }
        if ($source == "") {
            $source = $this->source;
        }
        if (!array_key_exists($source, $arr)) {
            return "未知";
        } else {
            return $arr[$source];
        }
    }
}