<?php
/**
 * Created by PhpStorm.
 * User: yiliu
 * Date: 2018/6/21
 * Time: 下午9:12
 */

namespace app\fyxbl\controller;


use app\fyxbl\model\FyxData;
use think\Controller;
use think\Config;
use think\Db;
use think\cache\driver\Redis as Redis;
use think\Exception;
use think\Queue;
use app\common\model\User;
use app\common\controller\Frontend;
use app\fyxbl\model\FyxRes;

/**
 *
 *include_once('Analysis/qiniu.php');
 *
 *
 */
class Task extends Frontend
{

    protected $layout = 'default';
    protected $model = null;
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('AdminLog');
        $ipList = $this->model->whereTime('createtime', '-37 days')->group("ip")->column("ip,ip as aa");
        $this->view->assign("ipList", $ipList);
    }

    public function index()
    {
        return $this->fetch();
    }

    /**
     * 个人信息
     */
    public function score()
    {
        $score_log = null;
        $score_log_page = null;
        try {

            $user = $this->auth->getUser();
            $uid = $user->id;
            $score_log = Db::name('user_score_log')->where('user_id', $uid)->order('id desc')->paginate(10);
            $score_log_page = $score_log->render();
            $this->view->assign('score_log', $score_log);
            $this->view->assign('score_log_page', $score_log_page);

        } catch (Exception $e) {


        }

        return $this->view->fetch();
    }

    public function rechargepay()
    {
        $user = $this->auth->getUser();
        $uid = $user->id;
        $price = $this->request->get('price');
        hook('fatepay', ['uid' => $uid, 'price' => $price]);
    }

    public function recharge()
    {

        $user = $this->auth->getUser();
        $uid = $user->id;

        if ($this->request->isAjax()) {

            $price = $this->request->post('radio');

            return $this->success(__('Success'), 'rechargepay', $price);
        }

        return $this->view->fetch();

    }


    public function shop()
    {
    }

    public function test()
    {

   $str = get_curl_test('http://www.eqxiu.com/auth/login/mp',null,'GET',1);
        print_r($str);
        exit();
        $ss = hook('fatepay', ['uid' => 1, 'price' => 0.1]);
        echo 2;
        print_r($ss);
        exit();
        $code = input('post.');
        $code ['time'] = time();
        $code ['user_email'] = session('email');
        $code ['count'] = $code['rmb'] / 10;
        switch ($code['rmb']) {
            case 100;
                $code ['pay_count'] = 10;
                break;
            case 300;
                $code ['pay_count'] = 33;
                break;
            case 500;
                $code ['pay_count'] = 59;
                break;
            case 999;
                $code ['pay_count'] = 126;
                break;
            case 0.1;
                $code ['pay_count'] = 0;
                break;
            default;
                $code ['pay_count'] = 0;
                break;
        }
        $code ['ip'] = Request::instance()->ip();
        $login = new Paymodel($code);
        $login->allowField(true)->save();
        $codepay_id = "44149";//这里改成码支付ID
        $codepay_key = "3w46931dMtPmMXIhmGKM9yRgx0KsA36c"; //这是您的通讯密钥
        $data = array(
            "id" => $codepay_id,//你的码支付ID
            "pay_id" => $login['id'], //唯一标识 可以是用户ID,用户名,session_id(),订单ID,ip 付款后返回
            "type" => 3,//1支付宝支付 3微信支付 2QQ钱包
            "price" => $code['rmb'],//金额100元
            "param" => "fyxgzs",//自定义参数
            "notify_url" => "http://new.fyxbl.top/user/codepay/notify",//通知地址
            "return_url" => "http://new.fyxbl.top",//跳转地址
        ); //构造需要传递的参数

        ksort($data); //重新排序$data数组
        reset($data); //内部指针指向数组中的第一个元素
        $sign = ''; //初始化需要签名的字符为空
        $urls = ''; //初始化URL参数为空
        foreach ($data AS $key => $val) { //遍历需要传递的参数
            if ($val == '' || $key == 'sign') continue; //跳过这些不参数签名
            if ($sign != '') { //后面追加&拼接URL
                $sign .= "&";
                $urls .= "&";
            }
            $sign .= "$key=$val"; //拼接为url参数形式
            $urls .= "$key=" . urlencode($val); //拼接为url参数形式并URL编码参数值

        }

        $query = $urls . '&sign=' . md5($sign . $codepay_key); //创建订单所需的参数
        $url = "http://api2.fateqq.com:52888/creat_order/?{$query}"; //支付页面
        print_r(http_curl($url, $data, 'POST', null));
        flush();


        $scene = http_curl1('http://h5.eqxiu.com/s/y4Kg24nh', 'GET');
        $scene = $content = str_replace(array('&nbsp;', '&quot;', '&amp;', '&lt;', '&gt'), ' ', $scene);
        @preg_match_all("/{\nid([^;]*)/", $scene, $matches);
//        print_r($matches);
        echo '--<br>';
        @$scene = $matches[0][0];
        print_r($scene);
        exit();
        $a = [1, 2, 3, 4];

        $b = 0;
        do {
            $b = rand(0, 9);
            print_r($b . '-');
        } while (in_array($b, $a));
        exit();
        $user = $this->auth->getUser();
        $uid = $user->id;

        $url = 'http://res.eqh5.com/group2/M00/BF/A3/yq0KXlZ4I_KAfbIEAAAeMrr_tME004.svg';
        $user = FyxRes::get($url);
        if ($user) {
            print_r($user['reskey']);

        } else {
            echo 666;

        }
        exit();
    }


    public function notice()
    {
        $row = Db::name('fyx_notice')->select();
        $row = array_reverse($row);
        $this->assign('row', $row);
        return $this->fetch();
    }

    /**
     * 添加任务
     */
    public function add_task()
    {
        $url = $this->request->request('url');
        $app = $this->request->request('app');

        $user = $this->auth->getUser();
        $target = $_SERVER['HTTP_REFERER'];
        if (stripos($target, 'rabbit')) {
            $target = 'rabbit';
        } elseif (stripos($target, 'eqxiu')) {
            $target = 'eqxiu';
        } else {
            $this->error(__('Unknown source'));
        }
        if (empty($url)) $this->error(__('Link to empty'));
        $data = ['user_id' => $user->id, 'url' => $url, 'source' => $app, 'target' => $target, 'createtime' => time()];
        if (!$user->score >= -999999) {
           //$this->error(__('Lack of scores'));
        }
        $ret = Db::name('fyx_data')->insertGetId($data);
        if ($ret) {

            switch ($target) {
                case 'rabbit';
                    $data ['uid'] = $ret;
                    $jobName = 'app\fyxbl\job\rabbit';  //负责处理队列任务的类
                    $jobQueueName = 'rabbit'; //当前任务归属的队列名称，如果为新队列，会自动创建
                    $result = Queue::push($jobName, $data, $jobQueueName);
                    //解析成功添加队列
                    if ($result) {
                        User::score(-2, $user->id, 'Submission of the task');
                        $this->success(__('Successfully added to the queue'));
                    } else {
                        $this->error(__('Add queue error'));
                    }
                    break;
                case 'eqxiu';
                    $data ['uid'] = $ret;
                    $jobName = 'app\fyxbl\job\eqxiu';  //负责处理队列任务的类
                    $jobQueueName = 'eqxiu'; //当前任务归属的队列名称，如果为新队列，会自动创建
                    $result = Queue::push($jobName, $data, $jobQueueName);
                    //解析成功添加队列
                    if ($result) {
                        User::score(0, $user->id, 'Submission of the task');
                        $this->success(__('Successfully added to the queue'));
                    } else {
                        $this->error(__('Add queue error'));
                    }

                    break;

                default;
                    $this->error(__('Unknown'));
                    break;
            }
        } else {
            $this->error(__('Data add fail'));
        }

    }

    /**
     * 兔展中心
     */
    public function rabbit()
    {
        $user = $this->auth->getUser();
        $rabbit = get_curl('https://www.rabbitpre.com/api/home/user', null, 'GET', $user->id);

        get_curl('http://editor.rabbitpre.com/api/org/package', null, 'GET', $user->id);

        $this->view->assign('rabbit', $rabbit);
        return $this->fetch();
    }


    /**
     * 兔展登陆
     */
    public function login_rabbit()
    {
        $account = $this->request->request('account');
        $password = $this->request->request('password');
        $user = $this->auth->getUser();
        $ret = get_curl_rabbit('https://passport.rabbitpre.com/api/sso/login', ['account' => $account, 'password' => $password], 'POST', $user->id);
        if ($ret->code != 200) {
            $this->error($ret->msg);
            $this->error(__('Invalid parameters'));
        } else {
            $this->success(__('Logged in successful'));
        }
    }

    /**
     * 兔展退出
     */
    public function logout_rabbit()
    {
        $user = $this->auth->getUser();
        $ret = get_curl('https://passport.rabbitpre.com/api/sso/exit', null, 'GET', $user->id);
        if ($ret) {
            $this->error(__('Invalid parameters'));
        } else {
            $this->success(__('Logout successful'));
        }
    }


    /**
     * 易企秀中心
     */
    public function eqxiu()
    {
        
        $user = $this->auth->getUser();
        $eqxiu = get_curl('http://service.eqxiu.com/eqs/login', null, 'GET', $user->id);
        $this->view->assign('eqxiu', $eqxiu);
        return $this->fetch();
    }



    /**
     * 易企秀登陆
     */
    public function login_eqxiu()
    {
        $account = $this->request->request('account');
        $password = $this->request->request('password');
        $user = $this->auth->getUser();
        get_curl('http://m1.eqxiu.com/login', 'username=' . $account . '&password=' . $password . '&version=63.0&channel=22', 'POST', $user->id);
        $ret = get_curl('http://service.eqxiu.com/eqs/login', null, 'GET', $user->id);

if ($ret->code != 200) {
            $this->error($ret->msg);
            $this->error(__('Invalid parameters'));
        } else {
            $this->success(__('Logged in successful'));
        }
    }

    /**
     * 易企秀退出
     */
    public function logout_eqxiu()
    {
        $user = $this->auth->getUser();
        $ret = get_curl('http://service.eqxiu.com/logout', null, 'GET', $user->id);
        if ($ret) {
            $this->error(__('Invalid parameters'));
        } else {
            $this->success(__('Logout successful'));
        }
    }

    /**
     * 注销登录
     */
    function logout()
    {
        //注销本站
        $this->auth->logout();
        $synchtml = '';
        ////////////////同步到Ucenter////////////////
        if (defined('UC_STATUS') && UC_STATUS) {
            $uc = new \addons\ucenter\library\client\Client();
            $synchtml = $uc->uc_user_synlogout();
        }
        $this->success(__('Logout successful') . $synchtml, url('/'));
    }


}
