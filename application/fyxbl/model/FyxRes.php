<?php

namespace app\fyxbl\model;

use think\Model;

class FyxRes extends Model
{
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'create_time';


}
