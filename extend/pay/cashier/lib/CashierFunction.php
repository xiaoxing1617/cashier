<?php
// +----------------------------------------------------------------------
// | 星益云 [ Remain true to our original aspiration ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018-至今 http://www.96xy.cn/ All rights reserved.
// +----------------------------------------------------------------------
// | 星益云聚合收银台系统 - 函数类库
// +----------------------------------------------------------------------

class CashierFunction{
    /**
     * 把数组所有元素拼接成字符串
     * @param $params 需要拼接的数组
     * @param $switch 是否对字符串做urlencode编码
     * @param $arr 要剔除的字段，不剔除则不传即可
     */
    public function createLinkstring($params, $switch = false, $arr = [])
    {
        $arg = "";
        foreach ($params as $key => $val) {
            if (!in_array($key, $arr)) {
                if ($switch) {
                    $arg .= $key . "=" . urlencode($val) . "&";
                } else {
                    $arg .= $key . "=" . $val . "&";
                }
            }
        }
//去掉最后一个&字符
        $arg = substr($arg, 0, -1);

        return $arg;
    }

    /**
     * 除去数组中的空值和指定签名参数
     * @param $params 签名参数组
     */
    public function paraFilter($params)
    {
        $para_filter = array();
        foreach ($params as $key => $val) {
            if ($key == "sign" || $key == "sign_type" || $val == "") continue;
            else $para_filter[$key] = $params[$key];
        }
        return $para_filter;
    }

    /**
     * 对数组进行排序
     * @param $params 排序前的数组
     */
    public function argSort($params)
    {
        ksort($params);
        reset($params);
        return $params;
    }

    /**
     * 生成Sign签名
     * @param $str 值
     * @param $type 加密方式
     */
    public function generateSign($str, $type)
    {
        $return = "";
        switch ($type) {
            case "md5":
                $return = md5($str);
                break;
        }
        return $return;
    }

    /**
     * 验证Sign签名
     * @param $params 参数数组
     * @param $sign sing值
     * @param $sign_type 签名方式
     */
    public function checkSign($params, $sign, $sign_type = "md5")
    {
//除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($params);

//对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);

//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort, false);
//验证时间戳
        $time = $para_sort['time'];
//左右误差不超过1分钟
        if (time() - $time < -30 or time() - $time > 30) {
            return false;
        }

//验签
        if ($sign_type == "") {
            $sign_type = "md5";
        }
        $check = false;
        if ($this->generateSign($prestr, $sign_type) == $sign) {
            return true;
        }
        return $check;
    }

    /**
     * 【外层 - 验签方法】
     * @param $params 参数
     * @param $identity 是否要商户身份验证
     * @param $user 商户信息
     * return 签名结果
     */
    public function check($params, $identity = false, $user = [])
    {
        if ($identity) {
            if (!array_key_exists('uid', $params)) {
                return ['code' => 1004, 'msg' => '商户UID参数不能为空'];
            }
            $params['key'] = $user['key'];
        }
        if (!array_key_exists('time', $params) or $params['time'] == "") {
            return ['code' => 1005, 'msg' => 'time时间戳不能为空'];
        }
        if (!array_key_exists('sign', $params) or $params['sign'] == "") {
            return ['code' => 1002, 'msg' => 'sign值不能为空'];
        }
        $sign = $params['sign'];
        $sign_type = "";
        if (array_key_exists('sign_type', $params)) {
            $sign_type = $params['sign_type'];
        }
        if (!$this->checkSign($params, $sign, $sign_type)) {
            return ['code' => 1003, 'msg' => '签名错误'];
        }
        return ['code' => 0, 'msg' => "正确"];
    }

    /**
     * 【外层 - 生成签名】
     * @param $params 参数
     * @param $identity 是否把商户信息写入
     * @param $user 商户信息
     * return 签名结果
     */
    public function createSign($params = [], $identity = false, $config = [])
    {
        if (empty($params) or empty($config)) {
            return "";
        }
        if ($identity) {
            $params['uid'] = $config['uid'];
            $params['key'] = $config['key'];
        }
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($params);
        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort, false);
        //生成sign
        $sign_type = "";
        if (array_key_exists('sign_type', $config)) {
            $sign_type = $config['sign_type'];
        }
        $mysign = $this->generateSign($prestr, $sign_type);

        return $mysign;
    }

    /**
     * 【生成参数字符串】
     * @param $params 参数
     * @param $identity 是否把商户信息写入
     * @param $user 商户信息
     * @param $sw 是否对字符串做urlencode编码
     * return 签名结果
     */
    public function createUrlStr($params = [], $identity = false, $config = [], $arr = [],$sw=false)
    {
        if (empty($params) or empty($config)) {
            return "";
        }
        if ($identity) {
            $params['uid'] = $config['uid'];
            $params['key'] = $config['key'];
        }
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($params);
        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);
        $para_sort['sign'] = $this->createSign($params, $identity, $config);
        $sign_type = "";
        if (array_key_exists('sign_type', $config)) {
            $sign_type = $config['sign_type'];
        }
        $para_sort['sign_type'] = trim($sign_type);
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort, $sw, $arr);

        return $prestr;
    }
}