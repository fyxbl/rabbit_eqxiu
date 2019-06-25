<?php
/**
 * Created by PhpStorm.
 * User: yiliu
 * Date: 2018/6/2
 * Time: 上午10:12
 */

namespace app\fyxbl\model;

use think\Model;

class User extends Model
{


    public function profile()
    {
        return $this->hasOne('fyx','pid');
    }

}