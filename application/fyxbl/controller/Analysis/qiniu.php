<?php
/**
 * Created by PhpStorm.
 * User: MoYi
 * Date: 2017/8/11
 * Time: 下午12:35
 */

require 'qiniu/autoload.php';
use Qiniu\Storage\UploadManager;


/**
 * @param $filePath mixed 上传文件路径
 * @param $name mixed 上传文件名
 * @param $token mixed 上传口令
 * @return bool
 * @throws Exception
 */
function qiniu_token_up($filePath, $name, $token)
{

    print ('start_up');
    $uploadMgr = new UploadManager();
    list($ret, $err) = $uploadMgr->putFile($token, $name, $filePath);
    if ($err) {
        return false;
    } else {
        return true;
    }
}
function qiniu_token_newup(
    $bucket,
    $key = null,
    $expires = 3600,
    $policy = null
){
    $test = new \Qiniu\Auth();
    list($ret, $err) = $test->uploadToken($bucket,$key,$expires,$policy);
    print_r($ret);
    if ($err) {
        return false;
    } else {
        return true;
    }
}