<?php


namespace app\api\controller;


use app\admin\model\order\Dispatch;
use app\admin\model\order\Order;
use app\common\model\Config;
use think\Controller;
use think\Request;

class WeXin extends Controller
{
    public $appid;
    public $app_secret;
    public $template_id;


    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->appid = Config::get(["name" => "appid"])->value;
        $this->app_secret = Config::get(["name" => "app_secret"])->value;
        $this->template_id = Config::get(["name" => "template_id"])->value;
    }

    public function send_msg($pflist, $order_number)
    {
        $order = Order::get(["order_number" => $order_number]);
        foreach ($pflist as $v) {
            if (\app\common\model\User::get($v)) {
                $res = $this->send_msg_to_user($v, $order);
                $is_weixin = $res ? "是" : "否";
                Dispatch::create([
                    'uid' => $v,
                    'is_start' => "否",
                    'is_weixin' => $is_weixin,
                    'order_number' => $order_number,
                    "state" => "未接单"

                ]);
            }
        }

    }

    //发送模板消息
    public function send_msg_to_user($uid, $order)
    {
        $openid = \app\admin\model\User::get(["id" => $uid])->openid;
        if (!$openid) {
            return false;
        }
        $s = new SubWechat();
        $time = date("Y-m-d H:i:s", $order->createtime);
        //$remark = "所属厂家：$order->factory\n发动机号码：$order->engine_code\n故障现象：$order->malfunction\n";
        $data = [
            "first" => "你有新的订单！",
            "keyword1" => $order->client_name . "-" . $order->client_mobile,
            "keyword2" => $time,
            "keyword3" => $order->emergency_address,
            "keyword4" => $order->malfunction,
            "keyword5" => $order->factory,
            "remark" => $order->order_remark
        ];

        $r = $s->sendmsg("", $openid, $data,\request()->domain()."/index.php");

        if (!$r) {
            return false;
        } else {
            if ($r["errcode"] != 0) {
                return false;
            }
        }
        return true;


    }

    public function get_access_token()
    {
        $r = Config::get(["name" => "access_token"]);
        if (!$r) {
            return $this->set_access_token();
        }
        if (time() > $r->extend) {
            return $this->set_access_token();
        }
        return $r->value;

    }

    protected function set_access_token()
    {

        $appid = $this->appid;
        $secret = $this->app_secret;
        $r = geturl("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$secret");
        $access_token = $r["access_token"];
        $expires_in = $r["expires_in"];
        $row = Config::get(["name" => "access_token"]);
        if ($row) {
            $row->extend = time() + $expires_in - 10;
            $row->value = $access_token;
            $row->save();
        } else {
            Config::create([
                "name" => "access_token",
                "extend" => time() + $expires_in - 10,
                "value" => $access_token
            ]);
        }
        return $access_token;


    }


    public function getOpenId($code)
    {
        $r = geturl("https://api.weixin.qq.com/sns/oauth2/access_token?appid=$this->appid&secret=$this->app_secret&code=$code&grant_type=authorization_code");
        return $r["openid"];
    }

    public static function is_weixin()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($user_agent, 'MicroMessenger') === false) {
            return false;
        } else {
            return true;
        }
    }

    public function weixin_jump()
    {
        $url = url("index/index/index","","",true);

        header("location:" . "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$this->appid&redirect_uri=$url&response_type=code&scope=snsapi_base&state=123#wechat_redirect");

    }


}