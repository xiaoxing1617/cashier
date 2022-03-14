<?php

namespace app\login\controller;

use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;
use think\Validate;
use think\Session;
use app\index\controller\Index;



use app\model\model\User;

//引入User模型
use app\model\model\Pay;

//引入Pay模型
use app\model\model\Order;

//引入Order模型
use app\model\model\Core;

//引入Core模型
use app\model\model\Captcha;

//引入Captcha模型
use app\model\model\Login;

//引入Login模型
class Qqlogin extends Controller
{
    //QQ快捷登录
    public function index(Request $request)
    {
        if (isLogin('user')) {
            exit("<script language='javascript'>window.location.href='/user';</script>");
        }

        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if($core['veloce_qqlogin']!=1){
            exit("<script language='javascript'>alert('抱歉，暂未开放QQ快捷登录方式！');window.location.href='/login';</script>");
        }
        $this->assign('title', 'QQ快捷登录');  //输出变量

        $this->assign('core', $core);  //输出变量
        return $this->fetch('qqlogin/index');  //进入模板
    }
    public function getqr(){
        if(strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST'])===false){
            return json_encode(array('code'=>-1,'msg'=>'非法操作'));
        }

        $url='https://ssl.ptlogin2.qq.com/ptqrshow?appid=716027609&e=2&l=M&s=4&d=72&v=4&t=0.5409099'.time().'&daid=383&pt_3rd_aid=101487368';
        $arr=$this->get_curl($url,0,'https://xui.ptlogin2.qq.com/cgi-bin/xlogin?appid=716027609&daid=383&style=33&theme=2&login_text=%E6%8E%88%E6%9D%83%E5%B9%B6%E7%99%BB%E5%BD%95&hide_title_bar=1&hide_border=1&target=self&s_url=https%3A%2F%2Fgraph.qq.com%2Foauth2.0%2Flogin_jump&pt_3rd_aid=101487368&pt_feedback_link=https%3A%2F%2Fsupport.qq.com%2Fproducts%2F77942%3FcustomInfo%3Dwww.qq.com.appid101487368',0,1,0,0,1);
        preg_match('/qrsig=(.*?);/',$arr['header'],$match);
        if($qrsig=$match[1]) {
            return json_encode(array('code' => 0, 'qrsig' => $qrsig, 'data' => base64_encode($arr['body'])));
        }else{
            return json_encode(array('code' => 1, 'msg' => '二维码获取失败'));
        }
    }

    private function get_curl($url,$post=0,$referer=0,$cookie=0,$header=0,$ua=0,$nobaody=0,$split=0){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $httpheader[] = "Accept: application/json";
        $httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Connection: close";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        if($post){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if($header){
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
        }
        if($cookie){
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if($referer){
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
        if($ua){
            curl_setopt($ch, CURLOPT_USERAGENT,$ua);
        }else{
            curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36');
        }
        if($nobaody){
            curl_setopt($ch, CURLOPT_NOBODY,1);

        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $ret = curl_exec($ch);
        if ($split) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($ret, 0, $headerSize);
            $body = substr($ret, $headerSize);
            $ret=array();
            $ret['header']=$header;
            $ret['body']=$body;
        }
        curl_close($ch);
        return $ret;
    }
    private function getqrtoken($qrsig){
        $len = strlen($qrsig);
        $hash = 0;
        for($i = 0; $i < $len; $i++){
            $hash += (($hash << 5) & 2147483647) + ord($qrsig[$i]) & 2147483647;
            $hash &= 2147483647;
        }
        return $hash & 2147483647;
    }
    private function isqrsig($qrsig,$sig="星益云"){
        if(strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST'])===false){
            return array('code'=>-1,'msg'=>'非法操作');
        }
        if(empty($qrsig)){
            return array('code'=>-1,'msg'=>'qrsig不能为空');
        }
        $url='https://ssl.ptlogin2.qq.com/ptqrlogin?u1=https%3A%2F%2Fgraph.qq.com%2Foauth2.0%2Flogin_jump&ptqrtoken='.$this->getqrtoken($qrsig).'&ptredirect=0&h=1&t=1&g=1&from_ui=1&ptlang=2052&action=0-0-'.time().'0000&js_ver=21020514&js_type=1&login_sig='.$sig.'&pt_uistyle=40&aid=716027609&daid=383&pt_3rd_aid=101487368&';
        $ret = $this->get_curl($url,0,$url,'qrsig='.$qrsig.'; ',1);
        if(preg_match("/ptuiCB\('(.*?)'\)/", $ret, $arr)){
            $r=explode("','",str_replace("', '","','",$arr[1]));
            if($r[0]==0){
                preg_match('/uin=(\d+)&/',$ret,$uin);
                $uin=$uin[1];
                return array('code'=>0,'uin'=>$uin,'nickname'=>$r[5]);
            }elseif($r[0]==65){
                return array('code'=>4,'msg'=>'二维码已失效！');
            }elseif($r[0]==66){
                return array('code'=>2,'msg'=>'请打开手机QQ扫描二维码...');
            }elseif($r[0]==67){
                return array('code'=>3,'msg'=>'正在验证二维码...');
            }else{
                return array('saveOK'=>6,'msg'=>$r[4]);
            }
        }else{
            return array('saveOK'=>6,'msg'=>$ret);
        }
    }
    //验证身份
    public function islogin(Request $request){
        if(strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST'])===false){
            return json_encode(array('code'=>-1,'msg'=>'非法操作'));
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if($core['veloce_qqlogin']!=1){
            return json_encode(array("code"=>-1,"msg"=>"抱歉，暂未开放QQ快捷登录方式！"));
        }
        $postArray = $request->post();
        if (!array_key_exists('qrsig', $postArray)) {
            return json_encode(array("code"=>-1,"msg"=>"qrsig不能为空"));
        } else {
            $qrsig = $postArray['qrsig'];
        }
        $arr = $this->isqrsig($qrsig);
        if($arr['code']!=0){
            return json_encode($arr);
        }
        //======身份验证成功=====
        $qq = $arr['uin'];
        $user = User::where("veloce_qq",$qq)->find();
        if(!$user){
            return json_encode(array("code"=>-1,"msg"=>"该QQ未绑定商户！","url"=>"/register"));
        }
        $arr = isUser(['account'=>$user['account'],'password'=>$user['password'],"ismd5"=>""]);
        if($arr['code']!=0){
            return json_encode(['code' => 1, 'msg' => $arr['msg']]);
        }else{
            return json_encode(['code' => 0, 'msg' => '登录成功',"url"=>"/user"]);
        }
    }
    //更换绑定 - 页面
    public function oper(Request $request)
    {
        if (!isLogin('user')) {
            exit("<script language='javascript'>window.location.href='/login';</script>");
        }
        $user = User::where('uid', getUserUid())->find();  //获取商户信息
        if (!$user) {
            exit("<script language='javascript'>alert('商户不存在或被删除');window.location.href='/user';</script>");
        }

        $get = $request->get();
        if(array_key_exists("relieve",$get)){
            if($user['veloce_qq']==NULL or trim($user['veloce_qq'])==""){
                exit("<script language='javascript'>alert('商户未绑定QQ，无法解除绑定');window.location.href='/user';</script>");
            }
            $this->assign('title', '解除绑定');  //输出变量
            $this->assign('mode', 'relieve');  //输出变量
        }else{
            $this->assign('mode', 'replace');  //输出变量
            $this->assign('title', '更换绑定');  //输出变量
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');

        $this->assign('core', $core);  //输出变量
        return $this->fetch('qqlogin/oper');  //进入模板
    }
    //更换绑定 - 业务逻辑
    public function oper_handle(Request $request){
        if(strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST'])===false){
            return json_encode(array('code'=>-1,'msg'=>'非法操作'));
        }
        if (!isLogin('user')) {
            return json_encode(array("code"=>-1,"msg"=>"请先登录商户","url"=>"/login"));
        }
        $user = User::where('uid', getUserUid())->find();  //获取商户信息
        if (!$user) {
            return array("code" => -1, "msg" => "商户不存在或被删除","url"=>"/login");
        }

        $postArray = $request->post();
        if (!array_key_exists('qrsig', $postArray)) {
            return json_encode(array("code"=>-1,"msg"=>"qrsig不能为空"));
        } else {
            $qrsig = $postArray['qrsig'];
        }
        if (!array_key_exists('mode', $postArray)) {
            return json_encode(array("code"=>-1,"msg"=>"请选择操作方式"));
        } else {
            $mode = $postArray['mode'];
        }
        $arr = $this->isqrsig($qrsig);
        if($arr['code']!=0){
            return json_encode($arr);
        }
        $qq = $arr['uin'];

        if($mode == "replace") {
            if ($qq == $user['veloce_qq']) {
                return json_encode(array("code" => -1, "msg" => "该QQ与您当前已绑定的QQ一致，无需更换！", "url" => "/user"));
            }
            $temp = User::where("veloce_qq", $qq)->find();
            if ($temp) {
                return json_encode(array("code" => -1, "msg" => "该QQ已与其他商户绑定，请先解除绑定！", "url" => "/user"));
            }
            $temp = User::getById($user['id']);
            $temp->veloce_qq = $qq;
            if ($temp->save() !== false) {
                return json_encode(['code' => 0, 'msg' => "更换成功", "url" => "/user"]);
            } else {
                return json_encode(['code' => -1, 'msg' => "更换失败", "url" => "/user"]);
            }
        }else if($mode=="relieve"){
            if ($qq != $user['veloce_qq']) {
                return json_encode(array("code" => -1, "msg" => "该QQ与您当前已绑定的QQ不一致，无法解除绑定！", "url" => "/user"));
            }
            $temp = User::getById($user['id']);
            $temp->veloce_qq = NULL;
            if ($temp->save() !== false) {
                return json_encode(['code' => 0, 'msg' => "解绑成功", "url" => "/user"]);
            } else {
                return json_encode(['code' => -1, 'msg' => "解绑失败", "url" => "/user"]);
            }
        }else{
            return json_encode(array("code"=>-1,"msg"=>"非法的操作方式"));
        }
    }
}