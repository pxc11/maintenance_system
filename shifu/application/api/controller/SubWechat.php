<?php


namespace app\api\controller;


use app\admin\model\ScmmNewtmpl;

class SubWechat
{


    private $token; //公众号token
    private $appid;//公众号appid
    private $secret;//公众号secret

    private $weixin;


    /*一键添加的模板内容*/
    private $add_tmp = [
        [
            'tid'=>'OPENTM409913353',
            'sceneDesc'=>'收到新派单通知',
            'name'=>'tmp_pd',
        ]

    ];
    public function __construct()
    {
        $this->weixin=new WeXin();
        $this->appid = $this->weixin->appid;
        $this->secret = $this->weixin->app_secret;
        $this->add_tmp[0]["tid"]=$this->weixin->template_id;


    }

    //一键添加
    public function addall(){

        $category = $this->getindustry();
        if(is_array($category)){
            //是数组未添加分类
            return $category;
        }
        //第二步循环添加并记录添加失败信息
        $err_list = [];
        $data = [];
        $in_arr = [];
        foreach ($this->add_tmp as $k=>$v){
            $res = $this->addtmp($v['tid']);
            if(intval($res['errcode'])!==0){
                //添加失败记录错误信息
                $err_list[$v['tid']] = $res['errmsg'];
            }else{
                //成功添加生成数据库数据
                $data[$res['template_id']] = $v;
                $data[$res['template_id']]['tmpid'] = $res['template_id'];
                $in_arr[] = $res['template_id'];
            }
        }



        //第三步获取列表 并组合数据插入数据库
        $list = $this->gettmplist();
        if(empty($list['template_list'])){
            //获取失败，返回错误信息
            return $list;
        }

        //获取成功开始处理数据
        $list = $list['template_list'];

        foreach ($list as $k=>$v){
            if(in_array($v['template_id'],$in_arr)){
                $tmpid = $v['template_id'];
                $params = $this->getcontentinfo($v['content']);
                //在添加的中组合数据
                $data[$tmpid]['content'] = $v['content'];
                $data[$tmpid]['params'] = serialize($params);
                $data[$tmpid]['is_use'] = 1;
                $data[$tmpid]['type'] = 2;
                $data[$tmpid]['utime'] = time();
                //检查是否已有
                $has=ScmmNewtmpl::get(['status'=>1,'name'=>$data[$tmpid]['name'],'type'=>2]);

                if($has){
                    ScmmNewtmpl::update($data[$tmpid],["id"=>$has['id']]);

                }else{
                    //没有新增
                    $data[$tmpid]['ctime'] = time();
                    $data[$tmpid]['status'] = 1;

                    ScmmNewtmpl::create($data[$tmpid]);

                }
            }
        }
        dump(['status'=>0,'errmsg'=>'获取完成','err'=>count($err_list),'errlist'=>$err_list]);
        return ['status'=>0,'errmsg'=>'获取完成','err'=>count($err_list),'errlist'=>$err_list];
    }
    /**
     * 发送消息
     * @param $name string 模板库名称
     * @param $openid string 接收用户openid
     * @param $data array 接收消息详情
     * @param $url string 跳转地址
     * @param $page string 跳转小程序页面
     */
    public function sendmsg($name,$openid,$data,$url='',$page=''){
        //处理参数 避免多加空格
        $name = trim($name)?:"tmp_pd";
        $openid = trim($openid);
        $page = trim($page);
        //第一步 获取对应模板tmpid 并检查是否启用模板发送
        $is_use=ScmmNewtmpl::get(["name"=>$name,"status"=>1]);
        if($is_use){
            $is_use=$is_use->is_use;
        }else{
            $is_use=0;
        }

       if(intval($is_use)!==1){
            //未启用或没添加模板直接返回

            return false;
        }

        $token = $this->gettoken();
        if($token===false){
            //发送日志写入
            return false;
        }
        $send = [
            'touser'=>$openid,
            'template_id'=>$this->gettmpid($name),
            'data'=>$this->gettmpdata($name,$data),
        ];
        if(!empty($url)){
            //存在页面跳转
            $send['url'] = $url;
        }
        if(!empty($page)){
            //存在小程序跳转
            $send['miniprogram'] = [
                'appid'=>$_W['account']['key'],
                'pagepath'=>$page
            ];
        }
        $send_info =  serialize($send);
        $json = json_encode($send,JSON_UNESCAPED_UNICODE  );
        $header = [
            "Content-Type: application/json",
            "Accept: application/json",
            "Content-Length: ".strlen($json),
        ];
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$token}";
        $result = $this->getcurl($url,$json,$header);
        $result = json_decode($result,true);
        return $result;
    }

    /**
     * 发送统一消息(用于小程序内发送模板消息openid不同时)
     * @param $name string 模板库名称
     * @param $openid string 接收用户openid
     * @param $data array 接收消息详情
     * @param $url string 跳转地址
     * @param $page string 跳转小程序页面
     */
    public function sendunimsg($name,$openid,$data,$url='',$page=''){
        global $_GPC, $_W;
        if ( empty($_W['account']['access_time']) || time() > $_W['account']['access_time']) {
            //获取access_token
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $_W['account']['key'] . "&secret=" . $_W['account']['secret'];
            $list = $this->getcurl($url);
            $list = json_decode($list, true);
//			echo '<pre>';
//			print_r($list);exit;
            $_W['account']['access_tokne'] = $list['access_token'];

            $_W['account']['access_time'] = time() + 7150;
            $token =  $_W['account']['access_tokne'];
        } else {
            $token =  $_W['account']['access_tokne'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token={$token}";

        $send = [
            'touser'=>$openid,
            'mp_template_msg'=>[
                'appid'=>$this->appid,
                'template_id'=>$this->gettmpid($name),
                'data'=>$this->gettmpdata($name,$data),
            ]
        ];
        if(!empty($url)){
            //存在页面跳转
            $send['mp_template_msg']['url'] = $url;
        }
        if(!empty($page)){
            //存在小程序跳转
            $send['mp_template_msg']['miniprogram'] = [
                'appid'=>$_W['account']['key'],
                'pagepath'=>$page
            ];
        }
        $send_info = serialize($send);
        $json = json_encode($send,JSON_UNESCAPED_UNICODE  );
        $header = [
            "Content-Type: application/json",
            "Accept: application/json",
            "Content-Length: ".strlen($json),
        ];
        $result = $this->getcurl($url,$json,$header);
        $result = json_decode($result,true);
        //写入发送日志
        $f = fopen("/new_wechattmp.txt",'a+');
        $write = serialize($result);
        $write = "发送时间:".date("Y-m-d H:i:s",time())."\n模板名:{$name}\n接收人:{$openid}\n内容详情:{$send_info}\n发送返回:{$write}\r\n ";
        fwrite($f,$write);
        fclose($f);
        return true;
    }

    //获取公众号模板列表
    private function gettmplist(){
        $token = $this->gettoken();
        if($token===false){
            return ['status'=>1,'errmsg'=>'获取公众号token失败，请检查appid及secret是否正确'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token={$token}";
        $res = $this->getcurl($url);
        $res = json_decode($res,true);
        return $res;
    }

    //添加模板到模板库
    private function addtmp($tid){
        $token = $this->gettoken();
        if($token===false){
            return ['status'=>1,'errmsg'=>'获取公众号token失败，请检查appid及secret是否正确'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token={$token}";
        $data = ['template_id_short'=>$tid];
        $json = json_encode($data);
        $header = [
            "Content-Type: application/json",
            "Accept: application/json",
            "Content-Length: ".strlen($json),
        ];
        $res = $this->getcurl($url,$json,$header);
        $res = json_decode($res,true);
        return $res;
    }

    //获取已添加分类
    private function getindustry(){

       $token=$this->gettoken();
        if($token===false){
            return ['status'=>1,'errmsg'=>'获取公众号token失败，请检查appid及secret是否正确'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/template/get_industry?access_token={$token}";
        $res = $this->getcurl($url);
        $res = json_decode($res,true);
        if(!empty($res)){
            $first = 0;
            $second = 0;
            foreach ($res as $k=>$v){
                if($v['first_class']=='IT科技'){
                    $first++;
                }
                if($v['second_class']=='互联网|电子商务'){
                    $second++;
                }
            }
            if($first===0 || $second===0){
                //有未添加的分类
                return ['status'=>1,'errmsg'=>'分类未添加，请检查公众号分类设置','err'=>1,'errlist'=>[$res,$url,$token]];
            }
        }else{
            return true;
        }
    }

    //获取公众号token
    private function gettoken(){
        $token = $this->weixin->get_access_token();
        return $token;
    }

    //根据名称获取模板id
    private function gettmpid($name){
       $tempid=ScmmNewtmpl::get(['name'=>$name,'status'=>1,"is_use"=>1])?:null;
       if($tempid){
           return $tempid->tmpid;
       }
       return $tempid;
    }


    /**
     * 生成消息内容数组
     * @param $name string 模板name
     * @param $data array 模板内容 一维数组或二维数组，一维数组时固定颜色 ，二维数组时 value color分别代表值和颜色
     */
    private function gettmpdata($name,$data){
        //第一步获取参数
        $param=ScmmNewtmpl::get(["name"=>$name,'status'=>1, 'is_use'=>1])?ScmmNewtmpl::get(["name"=>$name,'status'=>1, 'is_use'=>1])->params:null;
        $param = unserialize($param);
        if(empty($param)){
            return true;
        }

        $tmp = [];
        foreach ($param as $k=>$v){


            if(is_array($data[$v])){
                $tmp[$v] = [
                    'value'=>$data[$v]['value'],
                    'color'=>!empty($data[$v]['color'])?$data[$k]['color']:'#173177'
                ];
            }else{
                $tmp[$v] = [
                    'value'=>$data[$v],
                    'color'=>'#173177'
                ];
            }

        }
        return $tmp;
    }


    protected function getcurl($url,$data = null,$headers=array())
    {
        $curl = curl_init();
        if( count($headers) >= 1 ){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);

        curl_close($curl);
        return $output;
    }

    //提取{{}}内容
    private function getcontentinfo($cent){
        $arr = [];
        preg_match_all("/(?<={{)[^}}]+/", $cent, $arr);
        $tmp = [];
        foreach ($arr[0] as $k=>$v){
            $arr1 = [];
            $arr1 = explode(".",$v);
            $tmp[] = $arr1[0];
        }
        return $tmp;
    }

}