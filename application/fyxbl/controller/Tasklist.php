<?php

namespace app\fyxbl\controller;

use app\common\controller\Backend;
use think\Db;
use app\common\library\Auth;
use app\common\controller\Frontend;
use app\common\library\Token;
use app\fyxbl\model\FyxData;
use think\exception\ErrorException;

/**
 * 自定义搜索
 *
 * @icon fa fa-search
 * @remark 自定义列表的搜索
 */
class Tasklist extends Backend
{

    protected $layout = null;
    protected $model = null;
    protected $noNeedLogin = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 当前用户任务列表
     */
    public function index()
    {
        @$token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('token')));
        @$user_id = Token::get($token)['user_id'];
        if ($this->request->isAjax()) {
            $list = Db::name('fyx_data')->where('user_id', $user_id)->limit($this->request->get('offset'), $this->request->get('limit'))->order('id', 'DESC')->select();
            $total = Db::name('fyx_data')->where('user_id', $user_id)->count();
            for ($x = 0; $x <= 4; $x++) {
                if (empty($list[$x])) {
                    $list[$x] = [
                        'createtime' => 0, 'create_time' => 0, 'email' => null, 'id' => 0,
                        'ip' => null, 'msg' => null, 'source' => null, 'status' => null, 'target' => null,
                        'title' => null, 'update_time' => 0, 'updatetime' => 0, 'user_id' => null, 'url' => null
                    ];
                }

            }
            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }
        return $this->fetch();
    }

    /**
     * @param $ids int
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function feedback($ids)
    {
        try {
            $row = FyxData::get($ids);
            if ($row)
                $this->success(__('Task confirmation'), null, ['id' => $ids, 'row' => $row]);

            if ($this->request->isAjax()) {
                $this->error(__('Without this task'));

            }
        } catch (ErrorException $e) {
            print_r($e);
        }


    }

    public function record(){
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $data['create_time']=time();
            Db::name('fyx_record')->insert($data);
            }
    }
}
