<?php

namespace app\index\controller;

use app\admin\model\order\Dispatch;
use app\admin\model\order\Order;
use app\api\controller\WeXin;
use fast\Random;
use think\Controller;
use think\Db;
use think\Exception;
use think\Loader;
use think\Request;
use think\Session;


class Index extends Controller
{

    public function __construct(Request $request = null)
    {


        if (\request()->action() != "login") {
            $this->is_login();;
        }

        if (\request()->isAjax() && Session::get("user")) {
            $user_id = Session::get("user")->id;
            $user = \app\common\model\User::get($user_id);
            if ($user->is_use == "冻结") {
                Session::delete("user");
                $this->error("账号已冻结，无法使用");
                exit();
            }
            Session::set("user", $user);
        }
        if (!\request()->isAjax() && Session::get("user")) {
            $user_id = Session::get("user")->id;
            $user = \app\common\model\User::get($user_id);


            if ($user->is_use == "冻结") {

                Session::delete("user");
                header("location:" . url("index/freeze/index"));
                //$this->error("账号已冻结，无法使用","freeze/index");
                exit();
            }
            Session::set("user", $user);
        }
        parent::__construct($request);
    }

    public function Index()
    {
        $this->assign("is_weixin", WeXin::is_weixin());
        $this->assign("user_id", Session::get("user")->id);

        return $this->view->fetch();
    }

    public function login()
    {
        if (\request()->isAjax()) {
            $data = [
                'username' => \request()->post("username"),
                'password' => \request()->post("password")

            ];
            $v = Loader::validate("Login");
            if (!$v->check($data)) {
                $this->error($v->getError());

            }
            $user = \app\common\model\User::get(["username" => $data['username']]);
            if (!$user) {
                $this->error("用户不存在");
            }
            if ($user->is_use == "冻结") {
                $this->error("该用户已被冻结，无法登陆！");
            }
            Session::set("user", $user);
            $this->success("登录成功");


        } else {
            if (Session::get("user")) {
                $this->success("已登录", "index", [], 2);
            }
            return view();

        }
    }

    protected function is_login()
    {
        $user = Session::get("user");
        if ($user) {
            $user1 = \app\common\model\User::get($user->id);
            Session::set("user",$user1);
            if (WeXin::is_weixin()) {
                if($user->openid!=$user1->openid){
                    Session::delete("user");
                    header("location:".url("login"));
                    die();
                }
                if (!$user->openid) {

                    if (!input("code")) {
                        (new WeXin())->weixin_jump();
                        die();
                    } else {
                        $user->openid = (new WeXin())->getOpenId(input("code"));
                        $user->save();
                    }
                }
            }
        } else {

            if (WeXin::is_weixin()) {
                if (input("code")) {

                    $opneid = (new WeXin())->getOpenId(input("code"));
                    $user = \app\common\model\User::get(["openid" => $opneid]);

                    if ($user) {

                        Session::set("user", $user);
                        header("location:" . url("index"));
                        die();
                        // $this->success("微信自动登录","index",[],2);
                    } else {
                        header("location:" . url("login"));
                        //$this->error("请登录", "index/index/login");
                        die();
                    }
                } else {

                    (new WeXin())->weixin_jump();
                    die();


                }
            } else {
                $this->error("请登录1", "login");
                die();
            }


        }


    }

    public function order_list()
    {
        $where = [];
        $type = input("type", "all");
        $limit = input("limit", 10);
        $where1="";
        $where['uid'] = Session::get("user")->id;
        if ($type == "yjd") {
            $where['state'] = "已接单";
        }
        if ($type == "wjd") {
            $uid=Session::get("user")->id;
            $where=[];
            $where1="(state ='未接单' and uid =$uid and state =  '否') or  (state = '已拒绝' and uid =$uid )";

        }
        if ($type == "ycl") {
            $where['state'] = "已处理";
        }

        if ($type != "all") {
            $order = "updatetime desc";
        } else {
            $where=[];
            $order = "createtime desc";
            $uid=Session::get("user")->id;
            $where1="(state = '未接单' and is_start = '否' and uid = $uid) or (state in ('已接单','已处理') and uid = $uid)";
            //$sql=Dispatch::with(['order1'])->where($where)->where($where1)->order($order)->buildSql();
            //dump($sql);
        }



        $list = Dispatch::with(['order1'])->where($where)->where($where1)->order($order)->paginate($limit)->toArray();
        foreach ($list['data'] as &$v) {
            $user = \app\admin\model\User::get($v['order1']['deal_user_id']);
            $v['order1']['user_real_name'] = $user ? $user->real_name : null;
            $v['order1']['createtime'] = date("Y-m-d H:i:s", $v['order1']['createtime']);
            $v['order1']['updatetime'] = date("Y-m-d H:i:s", $v['order1']['updatetime']);
            $v['order1']['buy_date'] = $v['order1']['buy_date']?date("Y-m-d", $v['order1']['buy_date']):null;
        }
        return json($list);

    }

    //接单
    public function jd($id = 0)
    {
        $order = Order::get($id);
        if (!$order) {
            $this->error("没找到订单");
        }
        if ($order->state != "未接单") {
            $this->error("订单已被接");
        }
        try {
            Db::startTrans();
            $order->state = "已接单";
            $order->jd_time = time();
            $uid = Session::get("user")->id;
            $order->deal_user_id = $uid;
            $order->save();
            Dispatch::where(['order_number' => $order->order_number])->update(['is_start' => "是"]);
            Dispatch::where(['order_number' => $order->order_number, "uid" => $uid])->update(['state' => "已接单", "updatetime" => time()]);
            Db::commit();
            $this->success("接单成功");

        } catch (Exception $e) {
            Db::rollback();
        }


    }


    public function jj($id = 0)
    {
        $order = Order::get($id);
        if (!$order) {
            $this->error("没找到订单");
        }

        try {
            Db::startTrans();

            Dispatch::where(['order_number' => $order->order_number, "uid" => Session::get("user")->id])->update(['state' => "已拒绝", "updatetime" => time(), "jj_time" => time()]);
            Db::commit();
            $this->success("已拒绝");

        } catch (Exception $e) {
            Db::rollback();
        }


    }

    public function cl($id = 0)
    {
        $order = Order::get($id);
        if (!$order) {
            $this->error("没找到订单");
        }
        if ($order->state != "已接单") {
            $this->error("订单状态异常");
        }
        try {
            Db::startTrans();
            $order->state = "已处理";
            $order->wc_time = time();
            $uid = Session::get("user")->id;
            if ($order->deal_user_id != $uid) {
                Db::rollback();
                $this->error("不是你的订单，无法处理");
            }
            $order->save();
            Dispatch::where(['order_number' => $order->order_number, "uid" => $uid])->update(['state' => "已处理", "updatetime" => time()]);
            Db::commit();
            $this->success("处理成功");

        } catch (Exception $e) {
            Db::rollback();
        }


    }

    public function change_password()
    {

        $py = input("password_y");
        $p = input("password");
        $u = input("username");
        $user = Session::get("user");
        if (!$user) {
            $this->error("未登录");
        }
        if (!$u && !$p) {
            $this->error("新密码与用户名不能同时为空");
        }
        if ($user->password != (new \app\common\library\Auth())->getEncryptPassword($py, $user->salt)) {
            $this->error("密码错误");
        }
        if (!empty($p) && strlen($p) < 6) {
            $this->error("密码应多于6位");
        }

        if ($u) {
            $r = \app\common\model\User::where(['username' => $u])->count();
            if ($r > 0 && $u != $user->username) {
                $this->error("重复用户名，不能更改");
            }
            $user->username = $u;
        }
        if ($p) {
            $user->salt = Random::alnum(6);
            $user->password = (new \app\common\library\Auth())->getEncryptPassword($p, $user->salt);
        }
        $res = $user->save();
        if (!$res) {
            $this->error("更改失败");
        } else {
            Session::delete("user");
            $this->success("更改成功");
        }

    }

    public function zx()
    {
        Session::delete("user");
        header("location:" . url("index"));
    }

    public function dj()
    {
        return $this->view->fetch("dj");
    }


}
