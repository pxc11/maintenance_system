<?php

namespace app\index\validate;
use app\common\library\Auth;
use app\common\model\User;
use think\Validate;

class Login extends Validate
{
    protected $rule = [
        'username'  =>  'require',
        'password' =>  'checkPassword'
    ];

    protected $message = [
        'username.require'  =>  '用户名必须',

    ];

    protected function checkPassword($v,$r,$d){
        $user=User::get(["username"=>$d["username"]]);
        if(!$user){
            return "用户不存在";
        }
        if($user->password==(new Auth())->getEncryptPassword($v,$user->salt)){
            return true;
        }else{
            return "密码错误";
        }
    }


}