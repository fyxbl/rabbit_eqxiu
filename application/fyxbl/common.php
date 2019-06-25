<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com> common
// +----------------------------------------------------------------------

// 应用公共文件


function download($fname,$fpath="download/"){

    //避免中文文件名出现检测不到文件名的情况，进行转码utf-8->gbk
    $filename=iconv('utf-8', 'gb2312', $fname);
    $path=$fpath.$filename;
    if(!file_exists($path)){//检测文件是否存在
        echo "文件不存在！";
        die();
    }

    $fp=fopen($path,'r');//只读方式打开
    $filesize=filesize($path);//文件大小

    //返回的文件(流形式)
    header("Content-type: application/octet-stream");
    //按照字节大小返回
    header("Accept-Ranges: bytes");
    //返回文件大小
    header("Accept-Length: $filesize");
    //这里客户端的弹出对话框，对应的文件名
    header("Content-Disposition: attachment; filename=".$filename);
    //================重点====================
    ob_clean();
    flush();
    //=================重点===================
    //设置分流
    $buffer=1024;
    //来个文件字节计数器
    $count=0;
    while(!feof($fp)&&($filesize-$count>0)){
        $data=fread($fp,$buffer);
        $count+=$data;//计数
        echo $data;//传数据给浏览器端
    }

    fclose($fp);

}

function DA_Eqxiu($data){
    try{
        return false;
    }catch (Exception $e){
        return false;
    }
}

function ex_json_decode($str)
{
    $str = preg_replace('/\w+:/', '"$0":', $str);
    $str = preg_replace('/:":/', '":', $str);
    return $str;
}


if (!function_exists('http_test')) {

    /**
     * 请求http
     * @param string $url
     * @param string $data
     * @param string $mode
     * @param string $cookie
     * @return string
     */

    function http_test()
    {
        print ("Start test");
    }

}

if (!function_exists('http_eqxiu')){

    /**
     * 请求http
     * @param string $url
     * @param string $data
     * @param string $mode
     * @param string $cookie
     * @return string
     */

    function http_eqxiu($url, $data, $mode = "POST", $cookie)
    {

        header('Content-Type: text/html; charset=utf-8');
        $ch = curl_init($url); //初始化
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
        curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERAGENT, 'YiQiXiu Android');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie); //存储cookies
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

}

if (!function_exists('http_curl')) {

    /**
     * 请求http
     * @param string $url
     * @param string $data
     * @param string $mode
     * @param string $cookie
     * @return string
     */

    function http_curl($url, $data, $mode = "POST", $cookie)
    {
        $ch = curl_init($url); //初始化
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
        curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
        curl_setopt($ch, CURLOPT_USERAGENT, 'YiQiXiu Android');
        if (file_exists($cookie )) curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie); //读取cookies
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie); //存储cookies
        if ($data != null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }


}



if (!function_exists('get_curl_rabbit')) {

    /**
     * 请求http
     * @param string $url
     * @param array $data
     * @param string $mode
     * @param string $user_id
     * @return array
     */



    function get_curl_rabbit($url, $data, $mode = "POST", $user_id)
    {
        $user_id = md5($user_id);
        $cookie_file = ADDON_PATH . 'fyxbl/cookie/' . $user_id . '.cookie';
        $json_data = json_encode($data);
        @header('Content-type:application/json;charset=utf-8');
        $ch = curl_init($url); //初始化
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
        curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); //存储cookies
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); //读取cookies
        if ($data != null) curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json;charset=utf-8',
                'Content-Length: ' . strlen($json_data)
            )
        );
        $content = curl_exec($ch);
        $content = json_decode($content);
        curl_close($ch);
        return $content;
    }


}


if (!function_exists('get_curl')) {

    /**
     * 请求http
     * @param string $url
     * @param string $data
     * @param string $mode
     * @param string $user_id
     * @return array
     */

    function get_curl($url, $data, $mode = "POST", $user_id)
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
        if ($data != null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $content = curl_exec($ch);
        $content = json_decode($content);
        curl_close($ch);
        return $content;
    }
}


if (!function_exists('get_curl_test')) {

        /**
         * 请求http
         * @param string $url
         * @param string $data
         * @param string $mode
         * @param string $user_id
         * @return array
         */

        function get_curl_test($url, $data, $mode = "POST", $user_id)
        {

            $proxy = "39.137.69.9";
            $proxyport = "80";
            $user_id = md5($user_id);
            $cookie_file = ADDON_PATH . 'fyxbl/cookie/' . $user_id . '.cookie';
            @header('Content-type:application/json;charset=utf-8');
            $ch = curl_init($url); //初始化
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            curl_setopt($ch,CURLOPT_PROXYPORT,$proxyport);
            curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); //存储cookies
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); //读取cookies
            if ($data != null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $data = curl_exec($ch);
            $content = json_decode($data);
            if (empty($content)) $content = $data;
            curl_close($ch);
            return $content;
        }
    }
