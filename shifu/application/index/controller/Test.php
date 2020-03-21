<?php


namespace app\index\controller;


use app\common\controller\Frontend;

class Test extends Frontend
{
    protected $noNeedLogin = ['index'];
public function index(){
    echo 111;
    return 11;
}
}