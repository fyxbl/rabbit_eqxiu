<?php

namespace app\admin\model;

use app\admin\library\Auth;
use think\Model;

class FyxData extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';
    //自定义日志标题
    protected static $title = '';
    //自定义日志内容
    protected static $content = '';

    public function profile()
    {
        return $this->hasOne('user','user_id')->bind('id');
    }
}
