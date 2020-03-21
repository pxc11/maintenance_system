<?php

namespace app\admin\controller\order;

use app\admin\controller\user\User;
use app\admin\model\order\Order;
use app\api\controller\WeXin;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Session;

/**
 *
 *
 * @icon fa fa-circle-o
 */
class Dispatch extends Backend
{

    /**
     * Dispatch模型对象
     * @var \app\admin\model\order\Dispatch
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Dispatch;
        $this->view->assign("isWeixinList", $this->model->getIsWeixinList());
        $this->view->assign("isStartList", $this->model->getIsStartList());
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
    public function index($ids = 0)
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($ids > 0) {
            Session::set("dispatch_id", $ids);
        }
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $id = Session::get("dispatch_id");
            //dump($where);
            $where1 = [];
            if ($id) {
                $order = Order::get($id);
                $where1["order_number"] = $order->order_number;
            }
            $total = $this->model
                ->with(['user'])
                ->where($where)
                ->where($where1)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->where($where1)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id', 'uid', 'createtime', 'is_weixin', 'is_start', 'order_number', 'state', 'jj_time']);
                $row->visible(['user']);
                $row->getRelation('user')->visible(['real_name']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add()
    {
        $id = Session::get("dispatch_id");
        $order = Order::get($id);
        $order_number = $order->order_number;

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }

                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }

                    (new WeXin())->send_msg($params['pflist'], $order_number);
                    $this->success("派发成功！");


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

            }
            $this->error(__('Parameter %s can not be empty', ''));
        }


        $data = \app\admin\model\User::all();
        $list = [];


        if($order->state=="未接单"){
            foreach ($data as $v) {

                $r = \app\admin\model\order\Dispatch::where(["order_number" => $order_number, "uid" => $v["id"],"state"=>["<>","已拒绝"]])->count();
                if ($r > 0) {
                    continue;
                }
                $list[$v["id"]] = "[" . $v["id"] . "]" . $v["real_name"];
            }
        }


        $this->view->assign('user', build_select('row[pflist][]', $list, [], ['class' => 'form-control selectpicker', "data-live-search" => "true", "data-rule" => "required",
            "data-actions-box" => "true", "data-none-selected-text" => "未选择", "multiple"]));


        return $this->view->fetch();

    }

    //微信推送
    public function ts($id)
    {
        $row = \app\admin\model\order\Dispatch::get($id);
        if (!$row) {
            $this->error("没找到该派单信息");
        } else {
            $order = Order::get(["order_number" => $row->order_number]);
            if (!$order) {
                $this->error("没找到订单");
            }

            $res = (new WeXin())->send_msg_to_user($row->uid, $order);
            if (!$res) {
                $this->error("推送失败,请检查该用户是否绑定微信");
            } else {
                $row->is_weixin = "是";
                $row->save();
                $this->success("推送成功");
            }
        }

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
                Db::startTrans();
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
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
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
                    if ($v->state != "未接单"&&$v->state != "已拒绝") {
                        $this->error("该派发已被接单或处理，无法删除");
                    }
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
}
