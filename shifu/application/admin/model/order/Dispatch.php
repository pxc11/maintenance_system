<?php

namespace app\admin\model\order;

use think\Model;


class Dispatch extends Model
{

    

    

    // 表名
    protected $name = 'dispatch';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;
    // 追加属性
    protected $append = [
        'is_weixin_text',
        'is_start_text',
        'state_text',
        'jj_time_text'
    ];
    public function order1()
    {
        return $this->hasOne('Order','order_number','order_number',"","left");
    }






    public function getIsWeixinList()
    {
        return ['是' => __('是'), '否' => __('否')];
    }

    public function getIsStartList()
    {
        return ['是' => __('是'), '否' => __('否')];
    }

    public function getStateList()
    {
        return ['未接单' => __('未接单'), '已接单' => __('已接单'), '已处理' => __('已处理'), '已拒绝' => __('已拒绝')];
    }


    public function getIsWeixinTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_weixin']) ? $data['is_weixin'] : '');
        $list = $this->getIsWeixinList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsStartTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_start']) ? $data['is_start'] : '');
        $list = $this->getIsStartList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['state']) ? $data['state'] : '');
        $list = $this->getStateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getJjTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['jj_time']) ? $data['jj_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setJjTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'uid', 'id', [], 'LEFT')->setEagerlyType(0);
    }



    




}
