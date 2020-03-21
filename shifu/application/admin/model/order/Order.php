<?php

namespace app\admin\model\order;

use think\Model;


class Order extends Model
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'buy_date_text',
        'state_text'
    ];
    

    
    public function getStateList()
    {
        return ['未接单' => __('未接单'), '已接单' => __('已接单'), '已处理' => __('已处理')];
    }


    public function getBuyDateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['buy_date']) ? $data['buy_date'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['state']) ? $data['state'] : '');
        $list = $this->getStateList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setBuyDateAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'deal_user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
