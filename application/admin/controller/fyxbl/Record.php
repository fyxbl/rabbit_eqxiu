<?php

namespace app\admin\controller\fyxbl;

use app\common\controller\Backend;

/**
 * 任务列表
 *
 * @icon fa fa-list
 * @remark 所有任务列表，以及相关API
 */
class record extends Backend
{

    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('FyxRecord');
    }

    /**
     * 查找地图
     */
    public function map()
    {
        echo 'test';
    }


}
