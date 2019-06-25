<?php

namespace app\fyxbl\controller;

use think\Controller;
use think\Config;
use think\Db;
use think\cache\driver\Redis as Redis;
use think\Queue;
use app\common\model\User;
use app\common\controller\Frontend;

class Index extends Frontend
{
    protected $layout = 'default';
    protected $model = null;
    //protected $noNeedLogin = ['index', 'demo1'];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }



    public function index()
    {
        $this->assign('rabbit_v', 0);
        $this->view->assign('title', __('User center'));

        return $this->fetch();
    }

    public function test()
    {
        //直接加分
        exit();
        User::score(2,$this->auth->getUserinfo()['id'],'测试用');

        exit();
        $jobName = 'addons\mymoyi\job\fuckyou';  //负责处理队列任务的类
        $jobQueueName = 'fuckyou'; //当前任务归属的队列名称，如果为新队列，会自动创建

        $data['url'] = 'https://e.eqxiu.com/s/0hojlMEJ';

        @preg_match_all("/{\nid([^;]*)/", get_curl($data['url'], 'GET'), $matches);
        @$scene = json_decode(ex_json_decode($matches[0][0]));
        if (empty($scene)) {
            $this->error('解析失败');
        } else {
            $result = Queue::push($jobName, $scene, $jobQueueName);
            //解析成功添加队列
            if ($result) {
                Db::name('fyx_copy')->insert($data);

                $this->success('成功添加新任务');
            } else {
                $this->error('添加队列出错');
            }
        }

    }
}


