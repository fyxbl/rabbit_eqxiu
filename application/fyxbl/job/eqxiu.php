<?php
/**
 * Created by PhpStorm.
 * User: yiliu
 * Date: 2018/6/12
 * Time: 下午3:44
 */

namespace app\fyxbl\job;


use Qiniu\Storage\UploadManager;
use think\queue\Job;
use think\Db;
use think\Exception;
use app\fyxbl\model\FyxData;
use app\common\model\User;
use think\Controller;
use app\fyxbl\model\FyxRes;


class eqxiu


{
    public static $eid = [];

    /**
     * fire方法是消息队列默认调用的方法
     * @param Job $job 当前的任务对象
     * @param $data array 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {
        print ('Job v1.0');
        try {
            print ('-ID:' . $data['uid'] . '-');
            $sql = FyxData::get($data['uid']);
            $isJobDone = $this->sendTask($data, $sql);
            if ($isJobDone) {
                $this->TaskDone($sql);
                print ("Task Done--");
                $job->delete();
            } else {
                //如果执行到这里的话 说明队列执行失败  如果失败三次就删除该任务  否则重新执行
                if ($job->attempts() > 3) {
                    $this->TaskReturn($sql);
                    print ("Task Fail!--");
                    $job->delete();
                } else {
                    print ('Task Retry -' . $job->attempts() . '- Strat--');
                    $job->release(); //重发任务
                }
            }
        } catch (Exception $e) {
            print_r($e);
            print ("Run Error--");
            @User::score(2, $data['user_id'], 'Task fail return integral');
            @Db::name('fyx_data')->where('id', $data['uid'])->update(['status' => 'fail']);
            $job->delete();
        }
        print ('This is the ' . $job->attempts() . ' run--');
    }

    /**
     * 发送任务
     * @param $data array 数据合集
     * @param $sql mixed 当前数据
     * @return bool
     */
    private function sendTask($data, $sql)
    {
        try {

            $sql->status = 'running';
            $sql->save();
            print ("Start analysis--");
            $scene = $this->http_curl($data['url']);
            $scene = $content = str_replace(array('&nbsp;', '&quot;', '&amp;', '&lt;', '&gt'), ' ', $scene);
            @preg_match_all("/{\nid([^;]*)/", $scene, $matches);
            @$scene = json_decode($this->ex_json_decode($matches[0][0]));
            if (!empty($scene)) {
                print ("Done analysis--");
                $sql->title = $scene->name;
                $sql->save();
                return $this->DA_Eqxiu($sql, $scene, $sql->user_id);


            } else {
                print ("Fail analysis--");
                $sql->msg = '解析失败';
                $sql->save();
                return false;
            }

        } catch (Exception $e) {
            print ("sendTask Run Error");
            return false;
        }
    }


    /**
     * 分析易企秀数据
     * @param $sql mixed 当前执行数据行
     * @param $data mixed 易企秀数据
     * @return bool
     */
    private function DA_Eqxiu($sql, $data, $uid)
    {
        print ('DA_Eqxiu');

        try {
            //开始分析
            $pageid = null;
            $appid = null;
            $create = $this->get_curl('http://service.eqxiu.com/m/scene/create', 'name=' . $data->name . '&description=' . $data->description . '&cover=' . $data->cover . '&bgAudio=' . json_encode($data->bgAudio, JSON_UNESCAPED_UNICODE) . '&type=101&pageMode=2', 'POST', $uid);
            print ('get_done');

            if ($create->code == 200) {
                print('作品创建成功');
                $appid = $create->obj;
                $create_page = $this->get_curl('http://vservice.eqxiu.com/m/scene/pages/' . $create->obj . '?pageNo=1&pageSize=20', '', 'GET', $uid);
                //获取作品数据
                if ($create_page->code == 200) {
                    if (empty($create_page->list[0]->id)) {
                        print ( '获取页面错误');
                        print_r($create_page);
                        return false;
                    }
                    $pageid = $create_page->list[0]->id;
                    //当前页面ID 获取成功  开始历遍页面
                    $page_data = $this->get_curl('http://s1.eqxiu.com/eqs/page/' . $data->id . '?code=' . $data->code . '&time=' . $data->publishTime, null, 'GET');
                    if ($page_data->code != 200) return false;

                    foreach ($page_data->list as $value => $item) {
                        $item->id = $pageid;
                        $item->sceneId = $appid;

                        foreach ($item->elements as $vv => $tt) {
                            $item->elements[$vv]->pageId = $pageid;
                            $item->elements[$vv]->sceneId = $appid;
                        }

                        $build_page = json_encode($item, JSON_UNESCAPED_UNICODE);

                        $test = $this->get_curl_length('http://vservice.eqxiu.com/m/scene/save', $build_page, 'POST', $uid);
                        if ($test->code == 200) {
                            echo '--数据发送成功--';
                        } else {
                            $test = $this->get_curl_length('http://vservice.eqxiu.com/m/scene/save', $build_page, 'POST', $uid);
                            if ($test->code != 200) return false;
                            //再试一次  失败的话 直接退出程序
                        }

                        //
                        $create_page = $this->get_curl_length('http://vservice.eqxiu.com/m/scene/createPage/' . $pageid, '', 'GET', $uid);
                        if ($create_page->code == 200) {
                            echo '--页面新建成功--';
                            $pageid = $create_page->obj->id;
                        } else {
                            print_r($create_page);
                            return false;
                            //失败的话 直接退出程序
                        }
                    }
                    echo '循环结束并且删除最后一个页面';
                    $this->get_curl_length('http://vservice.eqxiu.com/m/scene/delPage/' . $pageid, '', 'GET', $uid);
                    echo '返回成功';
                    return true;
                }else{
                    print ( '获取页面错误');

                    return false;
                }
            }
        } catch (Exception $e) {
            print ($e);
            return false;
        }
    }

    /**
     * 解析分解字符串
     * @param $str
     * @return null|string|string[]
     */
    function ex_json_decode($str)
    {
        $str = preg_replace('/\w+:/', '"$0":', $str);
        $str = preg_replace('/:":/', '":', $str);
        return $str;
    }

    /**
     * 请求http返回字符串
     * @param string $url
     * @param string $mode
     * @return string
     */
    protected function http_curl($url, $mode = "GET")
    {
        @header('Content-Type: text/html; charset=utf-8');
        $ch = curl_init($url); //初始化
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
        curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//返回字符串，而非直接输出
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 6.0.1; 1509-A00 Build/MMB29M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/53.0.2785.49 Mobile MQQBrowser/6.2 TBS/043508 Safari/537.36 MicroMessenger/6.5.13.1100 NetType/WIFI Language/zh_CN');
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /**
     * 增加length的http请求
     * @param $url
     * @param $data
     * @param string $mode
     * @param int $uid
     * @return mixed
     */
    protected function get_curl_length($url, $data, $mode = "POST", $uid = 0)
    {
        $user_id = md5($uid);
        $cookie_file = ADDON_PATH . 'fyxbl/cookie/' . $user_id . '.cookie';
        @header('Content-type:application/json;charset=utf-8');
        @header('Content-Type: text/html; charset=utf-8');
        $ch = curl_init($url); //初始化
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
        curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); //读取cookies
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length:' . strlen($data)));
        if ($data != null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $content = curl_exec($ch);
        $content = json_decode($content);
        curl_close($ch);
        return $content;
    }

    /**
     * 请求http返回数组
     * @param string $url
     * @param string $data
     * @param string $mode
     * @param string $user_id
     * @return object
     */
    protected function get_curl($url, $data, $mode = "POST", $user_id = 0)
    {
        $user_id = md5($user_id);
        $cookie_file = ADDON_PATH . 'fyxbl/cookie/' . $user_id . '.cookie';
        @header('Content-type:application/json;charset=utf-8');
        $ch = curl_init($url); //初始化
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
        curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); //存储cookies
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); //读取cookies
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36');
        if ($data != null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $content = curl_exec($ch);
        $content = json_decode($content);
        curl_close($ch);
        return $content;
    }

    /**
     * 任务失败回退
     * @param $sql
     * @return bool
     */
    protected function TaskReturn($sql)
    {
        try {
            User::score(2, $sql->user_id, 'Task fail return integral');
            $sql->status = 'fail';
            $sql->save();
            print ('Task fail return integral');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 完成任务
     * @param $sql
     * @return bool
     */
    protected function TaskDone($sql)
    {
        try {
            $sql->status = 'done';
            $sql->save();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 兔展CDN
     * @param $key string 文件名
     * @param $url string 连接
     * @return string
     */
    function get_rabbit_cdn($key, $url = 'fyxbl')
    {
        $arrcdn = array('file1', 'file2', 'wscdn', 'file3', 'wscdn', 'cdn1', 'cdn2', 'cdn3', 'cdn4');
        $strlen = strlen($url);
        $len = floor($strlen * 0.8);
        $str = substr($url, $len, 1);
        $sum = null;
        $array = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $index = array_search($str[$i], $array);
            $sum += ($index) * pow(52, $len - $i - 1);
        }
        $sum = $sum % 8;
        $arrcdn = $arrcdn[$sum];
        return '//' . $arrcdn . '.rabbitpre.com/' . $key;
    }

    /**
     * 易企秀CDN
     * @param $key string 文件名
     * @return string
     */
    function get_eqxiu_cdn($key)
    {
        if (strpos('FYX' . $key, "http") > 0) {
            return $key;
        } else {
            return 'http://res.eqh5.com/' . $key;
        }
    }

    /**
     * 七牛以凭证上传文件
     * @param $filePath mixed 上传文件路径
     * @param $name mixed 上传文件名
     * @param $token mixed 上传口令
     * @return bool
     * @throws Exception
     */
    protected function qiniu_token_up($filePath, $name, $token)
    {

        //print ('start_up');
        //print ($filePath);
        try {
            $uploadMgr = new UploadManager();
            list($ret, $err) = $uploadMgr->putFile($token, $name, $filePath);
            if ($err) {
                return false;
            } else {
                return true;
            }
        } catch (Exception $e) {
            print ($e);
            return false;
        }


    }
}