<?php

namespace app\admin\controller\user;

use app\admin\model\order\Order;
use app\common\controller\Backend;
use fast\Random;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;


    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->count();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $v) {
                $v["is_bind_weixin"]=$v["openid"]?"已绑定":"未绑定";
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = NULL)
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

                if($row->username!=$params['username']){
                    $username_count=\app\common\model\User::where(["username"=>$params['username']])->count();
                    if($username_count>0){
                        $this->error("用户名已存在,修改失败");
                    }
                }
                if($row->real_name!=$params["real_name"]){
                    $real_name=$params["real_name"];
                    $r=\app\admin\model\User::where(["real_name"=>$real_name])->count();
                    if($r>0){
                        $this->error("姓名重复，无法添加");
                    }
                }







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
        $radio=build_radios('row[is_use]', ['冻结'=>__('冻结'),'正常'=>__('正常')],[$row->is_use]);
        $this->view->assign("radio",$radio);
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                //新增
                $salt = \fast\Random::alnum();
                if(!$params['password']){
                    $params['password'] ="123456789";

                }
                $real_name=$params["real_name"];
                $r=\app\admin\model\User::get(["real_name"=>$real_name]);
                if($r){
                    $this->error("姓名重复，无法添加");
                }
                $password=$params['password'];
                $params['password'] = \app\common\library\Auth::instance()->getEncryptPassword($params['password'], $salt);
                $params['salt'] = $salt;
                if(!empty($params["username"])){
                    $res=\app\admin\model\User::get(["username"=>$params["username"]]);
                        if($res){
                            $this->error("用户名重复,添加失败");
                        }
                    }else{
                  $last_row=\app\admin\model\User::order("id desc")->find();
                    $params["username"]=$last_row?"user".($last_row->id+1):"user0";
                    }

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

                    $row=new \app\admin\model\User();
                    $result=$row->allowField(true)->save($params);
                    $res=\app\admin\model\user\Password::create(["uid"=>$row->id,"password"=>$password]);
                    if(!$result||!$res){
                        Db::rollback();
                        $this->error("添加失败");
                    }
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
        }

        $radio=build_radios('row[is_use]', ['冻结'=>__('冻结'),'正常'=>__('正常')],["正常"]);
        $this->view->assign("radio",$radio);
        return $this->view->fetch();
    }

    //取消微信绑定
    public function qx($id){
        $user=\app\admin\model\User::get($id);
        if(!$user){
            $this->error("未找到该用户");
        }
        $user->openid=null;
        $user->save();
        $this->success("取消成功");
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
