<?php

namespace app\admin\controller\order;

use app\admin\controller\user\User;
use app\admin\model\order\Dispatch;
use app\api\controller\SubWechat;
use app\api\controller\WeXin;
use app\common\controller\Backend;
use fast\Random;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\order\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Order;
        $this->view->assign("stateList", $this->model->getStateList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {;
                 $row["order_number"]=(string)$row["order_number"];
                 $row["user"]["real_name"]=$row["user"]["real_name"]?:"无";
                $row->getRelation('user')->visible(['username', 'real_name']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    //订单号
                    $params["order_number"] = time() . Random::numeric(4);
                    $result = $this->model->allowField(true)->save($params);
                    //微信推送消息
                    (new WeXin())->send_msg($params['pflist'], $params["order_number"]);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();

                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        } else {
            $data = \app\admin\model\User::all();
            $list = [];
            foreach ($data as $v) {
                $list[$v["id"]] = "[" . $v["id"] . "]" . $v["real_name"];
            }
            $this->view->assign('userList', build_select('row[pflist][]', $list, [], ['class' => 'form-control selectpicker', "data-live-search" => "true", "data-rule" => "required",
                "data-actions-box" => "true", "data-none-selected-text" => "未选择", "multiple", "id" => "pflist"]));
            return $this->view->fetch();
        }

    }

    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    //删除派发通知
                    Dispatch::where(["order_number" => $v['order_number']])->delete();
                    $count += $v->delete();
                }


                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }


        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                $params["createtime"]=strtotime($params["createtime"]);
                if($params["deal_user_id"]==0){
                    unset($params["deal_user_id"]);
                }
                Db::startTrans();
                if($params["state"]=="未接单"){
                    $params["wc_time"]=null;
                    $params["jd_time"]=null;
                    $params["deal_user_id"]=null;
                    Dispatch::where(["order_number"=>$row->order_number,"state"=>["<>","已拒绝"]])->update(["state"=>"未接单","is_start"=>"否"]);
                }
                if($params["state"]=="已接单"){
                   $params["wc_time"]=null;
                    if(!isset($params["deal_user_id"])){
                        Db::rollback();
                        $this->error("请选择接单师傅");
                    }

                    if(!$params["jd_time"]){
                        Db::rollback();
                        $this->error("请选择接单时间");
                    }else{
                        $params["jd_time"]=strtotime($params["jd_time"]);
                    }
                    Dispatch::where(["order_number"=>$row->order_number,"state"=>["<>","已拒绝"]])->update(["state"=>"未接单","is_start"=>"是"]);
                    $res=Dispatch::where(["order_number"=>$row->order_number,"uid"=>$params["deal_user_id"]])->update(["state"=>"已接单"]);
                    if(!$res){
                        Dispatch::create(["order_number"=>$row->order_number,"uid"=>$params["deal_user_id"],"is_start"=>"是","state"=>"已接单"]);
                    }
                }
                if($params["state"]=="已处理"){
                    if(!isset($params["deal_user_id"])){
                        $this->error("请选择接单师傅");
                    }
                    if(!$params["jd_time"]){
                        $this->error("请选择接单时间");
                    }else{
                        $params["jd_time"]=strtotime($params["jd_time"]);

                    }
                    if(!$params["wc_time"]){
                        $this->error("请选择完成时间");
                    }else{
                        $params["wc_time"]=strtotime($params["wc_time"]);

                    }
                    Dispatch::where(["order_number"=>$row->order_number,"state"=>["<>","已拒绝"]])->update(["state"=>"未接单","is_start"=>"是"]);
                    $res=Dispatch::where(["order_number"=>$row->order_number,"uid"=>$params["deal_user_id"]])->update(["state"=>"已处理"]);
                    if(!$res){
                        Dispatch::create(["order_number"=>$row->order_number,"uid"=>$params["deal_user_id"],"is_start"=>"是","state"=>"已处理"]);
                    }
                }

                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);

                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        if ($row["deal_user_id"]) {
            $user = $row["deal_user_id"];

        }else{
            $user=0;
        }
        $data = \app\admin\model\User::all();
        $list = [];
        foreach ($data as $v) {
            $list[$v["id"]] = "[" . $v["id"] . "]" . $v["real_name"];
        }
        $list[0]="无";
        $this->view->assign('userList', build_select('row[deal_user_id]', $list, [$user], ['class' => 'form-control selectpicker', "data-live-search" => "true", "data-rule" => "required"]));
        $radio=build_radios('row[state]', ['未接单'=>__('未接单'),'已接单'=>__('已接单'), "已处理"=>__('已处理')],[$row->state]);
        $this->view->assign("radio",$radio);
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function addall()
    {
        $s = new SubWechat();
        $r = $s->addall();
   
    }
    public function test(){
        echo url("index/index/index");echo "<br>";
        echo url("index/index/index","","",true);echo "<br>";
        echo request()->domain();
    }
}
