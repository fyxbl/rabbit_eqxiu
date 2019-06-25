<?php
/**
 * Created by PhpStorm.
 * User: yiliu
 * Date: 2018/6/12
 * Time: 下午3:44
 */

namespace app\fyxbl\job;

require 'qiniu/autoload.php';

use Qiniu\Storage\UploadManager;
use think\queue\Job;
use think\Db;
use think\Exception;
use app\fyxbl\model\FyxData;
use app\common\model\User;
use think\Controller;
use app\fyxbl\model\FyxRes;


class rabbit



{
    public static $eid = [];

    /**
     * fire方法是消息队列默认调用的方法
     * @param Job $job 当前的任务对象
     * @param $data array 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {
        print ('Job v1.4');
        #设置执行时间不限时
//
//        set_time_limit(0);
//
//#清除并关闭缓冲，输出到浏览器之前使用这个函数。
//        ob_end_clean();
//
//#控制隐式缓冲泻出，默认off，打开时，对每个 print/echo 或者输出命令的结果都发送到浏览器。
//        ob_implicit_flush(1);

        try {
            print ('-ID:' . $data['uid'] . '-');
            $sql = FyxData::get($data['uid']);
            $isJobDone = $this->sendTask($data, $sql);
            if ($isJobDone) {
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
            @User::score(0, $data['user_id'], 'Task fail return integral');
            @Db::name('fyx_data')->where('id',$data['uid'])->update(['status' => 'fail']);
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
            $scene =   $this->http_curl($data['url']);

            $scene =   $content = str_replace(array('&nbsp;','&quot;','&amp;','&lt;','&gt'), ' ', $scene);
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
            //设置文件夹

            $folder = TEMP_PATH . $data->code;
            if (!is_readable($folder)) mkdir($folder);
            $folder = $folder . '/';
            $bg_music = array();
            $cover = array();
            //封面采集
            if (!empty($data->cover)) {

                $cover = $this->Up_qiniu($folder . 'cover.png', $this->get_eqxiu_cdn($data->cover) . '?time=' . time(), $uid);
                if ($cover) $cover = $this->uploaded($cover, $uid, 'png');

            }

            //采集背景音频
            if (!empty($data->bgAudio->url)) {

                $bg_music = $this->Up_qiniu($folder . 'bg_music.mp3', $this->get_eqxiu_cdn($data->bgAudio->url) . '?time=' . time(), $uid);

                if ($bg_music) $bg_music = $this->uploaded($bg_music, $uid, 'mp3');
            }


            //$page_eqxdata = $this->get_curl('http://s1.eqxiu.com/eqs/page/' . $data->id . '?code=' . $data->code . '&time=' . $data->publishTime, null, 'GET');

           print ('GET_PAGE');
            $page_eqxdata = $this->http_curl('http://s1.eqxiu.com/eqs/page/' . $data->id . '?code=' . $data->code . '&time=' . $data->publishTime,  'GET');
            print ('GET_PAGE_END');
            $page_eqxdata = json_decode($page_eqxdata);


	if(!empty($page_eqxdata->obj)){

		$ddd = substr($page_eqxdata->obj, 0, 19);
                $eee = substr($page_eqxdata->obj, 19 + 16);
                $bbb = substr($page_eqxdata->obj, 19, 19 + 16);
                $ccc = $bbb;
                $aaa = $ddd . $eee;
                $cryptText = base64_decode($aaa);
                @$decrypt_data = openssl_decrypt($cryptText, 'aes-128-cfb', $bbb, OPENSSL_NO_PADDING,$ccc);
                $page_eqxdata->list = json_decode($decrypt_data);


            }
            if ($page_eqxdata->success) {
                $test_len = [1, 2];
                $e_create_page = null;
                $bl = true;

                $page_data = $this->DA_eqx_page($sql, $page_eqxdata, $folder, $uid);
                if (empty($cover->id)) $cover->id = null;
                if (empty($cover->url)) $cover->url = null;
                if ($bg_music) $page_data['page']['0']['cmps'][] = $this->CV_bgmusic($bg_music);

                $e_create_page = $this->CV_app($data->name, $data->description, $cover, $page_data['page'], $page_data['gather']);
                $e_create_page = json_encode($e_create_page, JSON_UNESCAPED_SLASHES);


                $result = $this->get_curl_length('http://editor.rabbitpre.com/api/app', $e_create_page, 'POST', $uid);
                print ('-------------ok----------');

                print (json_encode($result));
                print ('-------------ok----------');
                $sql->msg = $result->statuscode;
                $sql->save();
                if ($result->success) {
                    $this->TaskDone($sql);
                    return true;
                }
                {
                    return false;
                }
            } else {
                //print_r('error');
                $sql->msg = 'Page Data Error';
                $sql->save();
                return false;
            }
        } catch (Exception $e) {
            print ($e);
            return false;
        }
    }

    /**
     * 分析易企秀页面
     * @param $sql mixed
     * @param $page mixed
     * @param $uid int
     * @param $folder string
     * @return array
     */
    private function DA_eqx_page($sql, $page, $folder, $uid)
    {
        print ('DA_page');
        $e_big_page = array();
        $gather = array();

        foreach ($page->list as $key => $values) {
            print ('PAGE' . $key);

            $e_min_page = array();
            $cmps = array();

            $bg_pic = null;
            if (empty($values->elements)) $values->elements = array();
            $e_min_page = $this->DA_eqx_elements($sql, $values->elements, $folder . $key . '_', $uid);
            $bg_pic = $e_min_page['bg_pic'];
            $bgColor = $e_min_page['bg_Color'];
            $e_min_page = $e_min_page['data'];
            print ('-元素解析完毕-');
            @ksort($e_min_page);
            foreach ($e_min_page as $sss => $xxx) {
                array_push($cmps, $xxx);
            }

            $longPage = null;
            if (!empty($values->properties->longPage)) $longPage = $values->properties->longPage;

            $e_big_page[] = $this->CV_eqxiu_page($key, $bgColor, $bg_pic, $cmps, $longPage);
        }
        print ('-页面分析完毕-');


        return ['page' => $e_big_page, 'gather' => $gather];
    }

    /**
     * 分析页面元素
     * @param $sql mixed
     * @param $elements mixed
     * @param $folder string
     * @param $uid int
     * @return array
     */
    private function DA_eqx_elements($sql, $elements, $folder, $uid)
    {
        print ('DA_elements');
        $e_min_page = array();
        $bgColor = null;
        $bg_pic = null;
        try {
            foreach ($elements as $key => $element) {
                print ('ET' . $key);
                switch ($element->type) {
                    case '2';
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_text($element, $anim, '文本' . $key);
                        break;
                    case '25';
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_newtxt($element, $anim, '文本' . $key);
                        break;
                    case '3';

                        if (!empty($element->properties->bgColor)) {
                            $bgColor = $element->properties->bgColor;
                        }
                        if (!empty($element->properties->imgSrc)) {
                            $bg_pic = $this->Up_qiniu($folder . 'bg.png', $this->get_eqxiu_cdn($element->properties->imgSrc), $uid);
                            $bg_pic = $this->get_rabbit_cdn($bg_pic, $element->properties->imgSrc);
                        }
                        break;
                    case
                    '4';
//                                    echo '-图片-';
                        if (empty($element->properties->src)) $element->properties->src = 'o_1c2tdmhjd1q521cg01lelsq5pi9.png';

                        $file = $this->Up_qiniu($folder . $key . '.png', $this->get_eqxiu_cdn($element->properties->src), $uid);
                        $file = $this->get_rabbit_cdn($file, $element->properties->src);
                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_img($style, $file, $anim, '图片' . $key);
                        break;
                    case '5';
                    case '501';
                    case'502';
                    case'503';
                    case'504';
//                                    echo '-输入框-';
                        if (empty($gather)) {
                            //初始化输入框
                            $data['gather'] = [
                                'id' => 0,
                                'strict' => null
                            ];
                        }
                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_input($element, $style, $anim, '输入框' . $key);
                        $gather['strict'][$e_min_page[$element->css->zIndex + 500]['id']] = $element->title;
                        break;
                    case'6';
//                                    break;

//                                    echo '-提交按钮-';
                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_submit($element, $style, $anim, '提交按钮' . $key);
                        break;
                    case
                    '7';
//                                    break;

                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);

                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_newtxt($element, $anim, '文本' . $key);
//                                    echo '-文本-';
                        break;
                    case
                    '8';
//                                    echo '-一键拨号-';

                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);

                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_onecall($element, $style, $anim, '一键拨号' . $key);
                        break;
                    case
                    'h';
//                        break;
                        //print ('---------svg--------');
                        $fill = [];
                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);


                        switch ($element->properties->src) {
                            case 'group1/M00/B1/A3/yq0KXFZysi-ACYaKAAACDQH4Nes625.svg';
                                $element->properties->src = 'http://cdn2.rabbitpre.com/3fe3893e-11fb-474b-b501-c753e922a3a0-3161';
                                break;
                            case 'group1/M00/B1/A3/yq0KXFZysi2AWB5GAAACGXEBTuA328.svg';
                                $element->properties->src = 'http://cdn2.rabbitpre.com/e77a9116-c5a4-4f28-bbce-5fbc40c75432-6656';
                                break;
                            case 'group1/M00/B1/A3/yq0KXFZysi6AbbJJAAACGWJaFQU121.svg';
                                $element->properties->src = 'http://cdn3.rabbitpre.com/e77a9116-c5a4-4f28-bbce-5fbc40c75432-6656';
                                break;
                            default;
//                                $element->properties->src = 'http://cdn2.rabbitpre.com/3fe3893e-11fb-474b-b501-c753e922a3a0-3161';
                                $element->properties->src = $this->get_eqxiu_cdn($element->properties->src);
                                break;
                        }

//
                        foreach ($element->properties->items as $color_on => $color_sc) {
                            $fill[] = $color_sc->fill;
                        }


                        $file = $this->Up_qiniu($folder . $key . '.svg', $element->properties->src, $uid, 'FILE');
                        $file = $this->get_rabbit_cdn($file, $element->properties->src);

                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_pshape($style, $fill, $file, $anim, '形状' . $key);

                        break;
                    case 'm';
//                                    break;

//                                    echo '-地图-';
                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_map($style, $anim, '地图' . $key);
                        break;
                    case 'v';
//                                    echo '-通用视频-';
                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_video($style, $anim, '视频' . $key);

                        break;
                    case
                    'p';
//                                    echo '-图集-';

                        $style = $this->CV_eqxiu_style($element->css);
                        $anim = $this->CV_eqxiu_anim($element->properties->anim);

                        if (empty($element->properties->children[0]->src)) $element->properties->children[0]->src = 'o_1c2tdmhjd1q521cg01lelsq5pi9.png';
                        $file = $this->Up_qiniu($folder . $key . '.png', $this->get_eqxiu_cdn($element->properties->children[0]->src), $uid);
                        $e_min_page[$element->css->zIndex + 500] = $this->CV_eqxiu_img($style, $file, $anim, '图片' . $key);
                        break;
                    default;
                        break;
                }
            }
            //print($bg_pic);
            return ['data' => $e_min_page, 'bg_pic' => $bg_pic, 'bg_Color' => $bgColor];
        } catch (Exception $e) {
            print ($e);
            $sql->msg = 'DA elements error';
            $sql->svae();
            return ['data' => $e_min_page, 'bg_pic' => $bg_pic, 'bg_Color' => $bgColor];

        }
    }

    /**
     * 转换成作品
     * @param $name
     * @param $desc
     * @param $imgurl mixed
     * @param $pages
     * @param $gather
     * @return array
     */
    protected function CV_app($name, $desc, $imgurl, $pages, $gather)
    {

//    echo '构造开始';
        $data = [

            "id" => "",
            "appExtId" => "",
            "name" => $name,
            "desc" => $desc,
            "shortUrl" => "",
            "appUrl" => "",
            "previewUrl" => "",
            "domainUrl" => "",
            "templateId" => "",
            "type" => "2",
            "level" => "0",
            "state" => "1",
            "width" => 320,
            "height" => 504,
            "dialogs" => [],
            "fonts" => [],
            "isMaterial" => false,
            "materials" => [],
            "isVideo" => false,

            "imgId" => $imgurl->id,
            "imgKey" => $imgurl->key,
            "imgBucket" => $imgurl->bucket,
            "imgServer" => $imgurl->server,
            "imgPath" => "$imgurl->url",

            "logoPath" => "//file.rabbitpre.com/logo.png",
            "timeInterval" => 0,
            "switchGuide" => true,
            "showReport" => true,
            "showViewCount" => true,
            "loop" => false,
            "publish" => false,
            "brandType" => "3",
            "animationApplyAll" => 1,
            "showWeChatHead" => 0,
            "isAdvertising" => false,
            'pages' => $pages,


        ];
        if (empty($gather)) {
//        echo '输入框未初始化';
        } else {
            $data['gather'] = json_encode($gather, JSON_UNESCAPED_SLASHES);

//        echo '添加输入框收集';
        }
        return $data;
    }

    /**
     * 转换背景音频
     * @param $music
     * @return array
     */
    protected function CV_bgmusic($music)
    {
        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;

        $data = [

            "id" => (int)$tid,
            "name" => "背景音乐",
            "type" => "bgmusic",
            "style" => array(
                "rotate" => 0,
                "opacity" => 1,
                "borderStyle" => "solid",
                "borderWidth" => 0,
                "borderColor" => "#000",
                "width" => 30,
                "height" => 30,
                "left" => 276,
                "top" => 14
            ),


            "animations" => [],
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "switchOn" => true,
            "autoPlay" => true,
            "loopPlay" => true,
            "isShowAll" => true,
            "musicName" => $music->name,
            "musickId" => $music->id,
            "src" => $music->url,
            "copyright" => null,
        ];
        return $data;
    }

    /**
     * 转换页面
     * @param int $row
     * @param null $bgcol
     * @param null $bgimage
     * @param array $cmps
     * @param $height
     * @return array
     */
    protected function CV_eqxiu_page($row = 0, $bgcol = null, $bgimage = null, $cmps = array(), $height)
    {

        $data = [
            'id' => 'page_' . $row,
            'pageExtId' => '',
            'appId' => '',
            'row' => (int)$row,
            'col' => 0,
            'in' => null,
            'out' => null,
            'bgColor' => $bgcol,
            'bgImage' => $bgimage,
            'bgImageType' => 0,
            'bgLeft' => 0,
            'bgTop' => 0,
            "formatVersion" => "2.0",
            'cmps' => $cmps
        ];
        if (!empty($height)) $data['height'] = $height;

        return $data;
    }

    /**
     * 转换文本
     * @param $obj
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_text($obj, $anim, $name)
    {
        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;

        $font_size = 24;
        $str = preg_replace("/<span[^>]*>(.*?)<\/span>/is", "$1", $obj->content);
        $str = preg_replace("/<div[^>]*>(.*?)<\/div>/is", "$1", $str);
        preg_match_all('/font-size:.*?;/', $obj->content, $matches);

        if (!empty($matches[0][0])) $font_size = substr($matches[0][0], 10, -1);
//    echo '获取到文字大小';

        preg_match_all('/color: rgb.*?;/', $obj->content, $matches);
        if (!empty($matches[0][0])) $obj->css->color = substr($matches[0][0], 7, -1);
//    echo '获取文字颜色';


        preg_match_all('/text-align:([^:]*)/', $obj->content, $matches);
        if (!empty($matches[0][0])) {
            $align = substr(strstr($matches[0][0], '"', 1), 12, -1);
        } else {
            $align = 'center';
        }
        if (empty($obj->css->lineHeight)) $obj->css->lineHeight = 1.5;
        if ($obj->css->lineHeight <= 1) $obj->css->lineHeight = 2;
        if (empty($font_size)) {
            $font_size = 12;
        } else {
            $font_size = (int)$font_size;
        }

        $data = [
            "id" => (int)$tid,
            "name" => $name,
            "type" => "text",
            "style" => array(
                "rotate" => $obj->css->transform,
                "opacity" => $obj->css->opacity,
                "borderStyle" => "solid",
                "borderWidth" => 0,
                "borderColor" => "#000",
                "width" => $obj->css->width,
                "height" => "auto",
                "left" => $obj->css->left,
                "color" => $obj->css->color,
                "fontSize" => (int)$font_size,
                "fontFamily" => "FZHTJW",
                "fontStyle" => "normal",
                "fontWeight" => "normal",
                "textAlign" => $align,
                "textDecoration" => "none",
                "lineHeight" => (int)$font_size * $obj->css->lineHeight,
                "letterSpacing" => 0,
                "borderRadius" => 0,
                "backgroundColor" => "transparent",
                "top" => $obj->css->top + 9
            ),

            "animations" => $anim,
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "text" => $this->CV_keyword($str),
            "innerText" => $this->CV_keyword($str),
            "isRichText" => false,
            "materials" => [
                array(
                    "id" => "14671bd5-0334-4548-b9dc-f1c8cffb3dfb",
                    "name" => "方正黑体简体",
                    "price" => 0,
                    "commercialPrice" => 0,
                    "fontCode" => "FZHTJW_2183502473",
                    "content" => $this->CV_keyword($str)
                )
            ]
        ];


        return $data;
    }

    /**
     * 转换图片
     * @param $style
     * @param $file
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_img($style, $file, $anim, $name)
    {

        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;


        $data = [
            "id" => $tid,
            "name" => $name,
            "type" => "image",
            "style" => $style,
            "animations" => $anim,
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "src" => $file,
            "filter" => "",
            "crop" => array(
                "width" => $style['width'],
                "height" => $style['height'],
                "left" => 0,
                "top" => 0,
                "right" => 0,
                "bottom" => 0
            ),
            "fullSize" => array(
                "width" => $style['width'],
                "height" => $style['height']
            ),
            "display" => array(
                "width" => $style['width'],
                "height" => $style['height'],
                "left" => 0,
                "top" => 0,
                "right" => 0,
                "bottom" => 0
            ),
            "ori" => array(
                "width" => $style['width'],
                "height" => $style['height']
            )
        ];
        return $data;
    }

    /**
     * 转换新文本
     * @param $obj
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_newtxt($obj, $anim, $name)
    {
        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;


        $font_size = (int)$obj->css->fontSize;
//    print_r($obj);
        $obj->css->transform = (int)substr($obj->css->transform, 8, -4);

        if (empty($obj->css->lineHeight))$obj->css->lineHeight = 12;
        $data = [

            "id" => $tid,
            "name" => $name,
            "type" => "text",
            "style" => array(
                "rotate" => $obj->css->transform,
                "opacity" => $obj->css->opacity,
                "borderStyle" => "solid",
                "borderWidth" => 0,
                "borderColor" => "#000",
                "width" => $obj->css->width,
                "height" => "auto",
                "left" => $obj->css->left,
                "color" => $obj->css->color,
                "fontSize" => (int)$font_size,
                "fontFamily" => "FZHTJW",
                "fontStyle" => "normal",
                "fontWeight" => "normal",
                "textAlign" => $obj->css->textAlign,
                "textDecoration" => "none",
                "lineHeight" => (int)$font_size * $obj->css->lineHeight,
                "letterSpacing" => 0,
                "borderRadius" => 0,
                "backgroundColor" => "transparent",
                "top" => $obj->css->top + 9
            ),

            "animations" => $anim,
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "text" => $this->CV_keyword($obj->content),
            "innerText" => $this->CV_keyword($obj->content),
            "isRichText" => false,
            "materials" => [
                array(
                    "id" => "14671bd5-0334-4548-`-f1c8cffb3dfb",
                    "name" => "方正黑体简体",
                    "price" => 0,
                    "commercialPrice" => 0,
                    "fontCode" => "FZHTJW_2183502473",
                    "content" => $this->CV_keyword($obj->content)
                )
            ]
        ];
        return $data;
    }

    /**
     * 转换地图
     * @param $style
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_map($style, $anim, $name)
    {
        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;


        $y = 113.952794;
        $x = 22.529962;
        $data = [
            'id' => $tid,
            'name' => $name,
            'type' => 'map',
            'style' => $style,
            'animations' => $anim,
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "center" => [$y, $x],
            "zoom" => 14,
            "search" => "深圳市南山区深圳湾科技生态园9栋B4座",
            "address" => "深圳市南山区深圳湾科技生态园9栋B4座",
            "district" => ""

        ];
        return $data;
    }

    /**
     * 转换视频
     * @param $style
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_video($style, $anim, $name)
    {
        $data = [
            'id' => null,
            'style' => $style,
            'file' => [
                'key' => '',
                'server' => null
            ],
            'video' => [
                'key' => null,
                'embed' => '',
                'swf' => ''
            ],
            'animation' => $anim,
            'effect' => array(),
            'link' => null,
            'type' => 'video',
            'name' => $name
        ];
        return $data;
    }

    /**
     * 转换形状
     * @param $style
     * @param $fill
     * @param $src
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_pshape($style, $fill, $src, $anim, $name)
    {

        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;


        $height = $style['height'];
        $top = $style['top'];

        if ($height < 3) {
            $top = $top - 13;
        } elseif ($height < 6) {
            $top = $top - 10;
        } elseif ($height < 9) {
            $top = $top - 7;
        } elseif ($height < 12) {
            $top = $top - 4;
        } elseif ($height < 13) {
            $top = $top - 1;
        }
        $style['top'] = $top;


        $data = [
            "id" => $tid,
            "name" => "027形状",
            "type" => "shape",
            "style" => $style,
            "animations" => $anim,
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "src" => "//file3.rabbitpre.com/3fe3893e-11fb-474b-b501-c753e922a3a0-3161",
            "fills" => $fill,
            "paths" => ["M100,100H0V0h100V100z"],
            "shapeType" => "custom",
        ];
        $data ['style']['strokeDasharray'] = 'none';
        $data ['style']['viewBox'] = '0 0 100 100';

        return $data;
    }

    /**
     * @param $items
     * @return array
     */
    protected function CV_eqxiu_pshape_color($items)
    {
        $arr = array();
        foreach ($items as $key => $v) {


            print ('---------svg---0-----');
            array_push($fill, $v->fill);

            print_r($v);
            print ('---------svg---0-----');
        }
        return $arr;

    }

    /**
     * 转换拨号按钮
     * @param $obj
     * @param $style
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_onecall($obj, $style, $anim, $name)
    {
        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;

        $data = [
            "id" => $tid,
            "name" => $name,
            "type" => "oneCall",
            "style" => $style,
            "animations" => $anim,
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "label" => $obj->properties->title,
            "tel" => "0755-123456",
            "iconColor" => "#fff",
            "version" => "1.0.0"
        ];
        return $data;
    }

    /**
     * 转换提交按钮
     * @param $obj
     * @param $style
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_submit($obj, $style, $anim, $name)
    {

        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;

        if (empty($obj->properties->text)) $obj->properties->text = '感谢提交';
        $data = [

            "id" => $tid,
            "name" => $name,
            "type" => "submit",
            "style" => $style,
            "animations" => $anim,
            "triggers" => [array(
                "event" => "message",
                "message" => $obj->properties->text
            )],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "label" => $obj->properties->title,
            "value" => "",
            "required" => false,
            "message" => "",
            "amountChecked" => false,
            "expireChecked" => false,
            "amount" => 100,
            "amountTips" => "提交名额已满，无法提交",
            "expire" => "",
            "expireTips" => "已过提交截止时间，无法提交",
            "scope" => 0
        ];
        return $data;
    }

    /**
     * 转换输入框
     * @param $obj
     * @param $style
     * @param $anim
     * @param $name
     * @return array
     */
    protected function CV_eqxiu_input($obj, $style, $anim, $name)
    {
        do {
            $tid = time() . rand(100, 999);
        } while (in_array($tid, rabbit::$eid));
        rabbit::$eid[] = $tid;

        $data = [

            "id" => $tid,
            "name" => $name,
            "type" => "input",
            "style" => $style,
            "animations" => $anim,
            "triggers" => [],
            "readonly" => false,
            "readonlySetter" => "",
            "isFixed" => false,
            "interaction" => false,
            "visible" => true,
            "isLocked" => false,
            "gid" => "",
            "label" => $obj->properties->placeholder,
            "value" => "",
            "required" => true,
            "placeholder" => "",
            "inputType" => "text",
            "maxLength" => 0
        ];

        return $data;
    }

    /**
     * 转换style样式
     * @param $style mixed
     * @return array
     */
    protected function CV_eqxiu_style($style)
    {
        $style->transform = (int)substr($style->transform, 8, -4);
        if ($style->transform == null) $style->transform = 0;
        $data = [
            "rotate" => $style->transform,
            "opacity" => $style->opacity,
            "borderStyle" => "solid",
            "borderWidth" => 0,
            "borderColor" => $style->borderColor,
            "width" => $style->width,
            "height" => $style->height,
            "shadowX" => 0,
            "shadowY" => 0,
            "shadowColor" => "transparent",
            "borderRadius" => 0,
            "left" => $style->left,
            "top" => $style->top
        ];
        return $data;
    }

    /**
     * 转换关键词
     * @param $content string
     * @return string
     */
    protected function CV_keyword($content)
    {

        $content = preg_replace('/<\/?[^>]+>/i', '', $content);
        $content = preg_replace('/<(.[^>]*)>/i', '', $content);
        $content = preg_replace('/{(.[^>]*)}/i', '', $content);


        $content = str_replace('eqxiu.com', 'rabbitpre.com', $content);
        $content = str_replace('北京中网易企秀', '深圳市兔展智能', $content);
        $content = str_replace('北京中网', '深圳兔展', $content);
        $content = str_replace('北京', '深圳', $content);
        $content = str_replace('&nbsp;', ' ', $content);
        $content = str_replace('朝阳区', '宝安区', $content);
        $content = str_replace(array('易企秀', '一起秀', '源态', '秀一起', '秀'), '兔展', $content);
        $content = str_replace('易秀', '兔展', $content);
        $content = str_replace('秀小姐', '兔小姐', $content);
        $content = str_replace('易先生', '展先生', $content);
        $content = str_replace('秀先生', '展先生', $content);
        $content = str_replace('易小姐', '兔小姐', $content);
        $content = str_replace(array('eqxiu', 'yiqixiu', 'EQXIU', 'yqixiu', 'eqixiu', 'yiqiexiu'), 'Rabbit', $content);
        $content = str_replace('秀客小店', '兔大师定制', $content);
        $content = str_replace('秀客', '兔大师', $content);
        return $content;
    }

    /**
     * 转换易企秀动画
     * @param $anim mixed
     * @return array
     */
    protected function CV_eqxiu_anim($anim)
    {
        $anmi_data = array();
        $eqx_anim = array(
            ["fadeIn"],
            ["fadeInRight", "fadeInDown", "fadeInLeft", "fadeInUp"],
            ["bounceInLeft", "bounceInUp", "bounceInRight", "bounceInDown"],
            ["bounceIn"],
            ["zoomIn"],
            ["rubberBand"],
            ["wobble"],
            ["rotate2d", "rotate2d", "rotate2d"],
            ["fadeInRight"],
            ["swing"],
            ["fadeOut"],
            ["fadeOutDown"],
            ["bounceInRight", "bounceInDown", "bounceInLeft", "bounceInUp"],
            ["stretchRight", "pullDown", "stretchLeft", "pullUp"],
            ["bounceOut"],
            ["bounceInRight", "bounceInDown", "bounceInLeft", "bounceInUp"],
            ["fadeInRight", "fadeInDown", "fadeInLeft", "fadeInUp"],
            ["stretchRight", "pullDown", "stretchLeft", "pullUp"],
            ["zoomOut"],
            ["fadeOutLeft", "fadeOutUp", "fadeOutRight", "fadeOutDown"],
            ["fadeInRight"],
            ["bounceIn"],
            ["bounceIn"],
            ["bounceIn"],
            ["bounceIn"],
            ["bounceIn"],
            ["bounceInRight", "bounceInDown", "bounceInLeft", "bounceInUp"],
            ["fadeIn"],
            ["fadeOut"],
            ["fadeInDown"],
            ["fadeInUp"],
            ["bounceInLeft", "bounceInDown", "bounceInRight", "bounceInUp"],
            ["rotateInUpLeft", "rotateInUpRight"],
            ["fadeIn"],
            ["fadeIn"]
        );
        $eqx_anim_name = array(['淡入'],
            ['从左淡入', '从上淡入', '从右淡入', '从下淡入'],
            ['从左飞入', '从上飞入', '从右飞入', '从下飞入'],
            ['飞入'],
            ['从小到大'],
            ['橡皮筋'],
            ['左右摇摆'],
            ['旋转2D"', '旋转2D"', '旋转2D"'],
            ['从左淡入'],
            ['钟摆'],
            ['消退'],
            ['向上淡出'],
            ['从右飞入', '从下飞入', '从右弹入', '从左弹入'],
            ['向右展开', '向上展开', '向左展开', '向下展开'],
            ['弹性放大'],
            ['从右飞入', '从下飞入', '从右弹入', '从左弹入'],
            ['从左淡入', '从上淡入', '从右淡入', '从下淡入'],
            ['向右展开', '向上展开', '向左展开', '向下展开'],
            ['从大到小'],
            ['向右淡出', '向下淡出', '向左淡出', '向上淡出'],
            ['向左淡出'],
            ['飞入'],
            ['飞入'],
            ['飞入'],
            ['飞入'],
            ['飞入'],
            ['从右飞入', '从下飞入', '从右弹入', '从左弹入'],
            ['淡入'],
            ['淡出'],
            ['从下淡入'],
            ['从上淡入'],
            ['从右飞入', '从下飞入', '从右弹入', '从左弹入'],
            ['右下旋入', '左下旋入'],
            ['淡入'],
            ['淡入']
        );
        foreach ($anim as $key => $value) {

            if (empty($value->direction)) $value->direction = 0;
            if (@$eqx_anim[$value->type][$value->direction] == null) $eqx_anim[$value->type][$value->direction] = 'fadeIn';
            if (@$eqx_anim_name[$value->type][$value->direction] == null) $eqx_anim_name[$value->type][$value->direction] = '淡入';
            if (empty($value->duration)) $value->duration = 1;
            if (empty($value->delay)) $value->delay = 0;
            if (empty($value->countNum)) $value->countNum = 0;

            if (@$value->count == 1) $value->countNum = 'Infinity';
            array_push($anmi_data,
                [
                    "isActive" => false,
                    "name" => $eqx_anim_name[$value->type][$value->direction],
                    "animate" => $eqx_anim[$value->type][$value->direction],
                    "duration" => $value->duration,
                    "delay" => $value->delay,
                    "count" => $value->countNum,
                    "interval" => 0,
                    "isCompose" => false,
                    "order" => "normal"
                ]);
        }
        return $anmi_data;
    }

    /**
     * KEY上传到兔展服务器返回ID
     * @param $key string 文件
     * @param $uid int 用户ID
     * @param $type string 类型
     * @return mixed
     */
    private function uploaded($key, $uid, $type = 'png')
    {
        //print ('AD_4');
	$type = 'IMAGE';
        $content = $this->get_curl('http://www.rabbitpre.com/upload/uploaded', 'key=' . $key . '&xparams=%7B%22keyprev%22%3A%22file%2F%22%2C%22type%22%3A%22FILE%22%2C%22serverType%22%3A%22Q%22%2C%22bucket%22%3A%22rabbitpre%22%2C%22filename%22%3A%22' . time() . '.' . $type . '%22%7D&isAjax=true', 'POST', $uid);
        //print ('AD_5');
        return $content->file;
    }

    /**
     * 上传到七牛云 返回key
     * @param $folder string
     * @param $fileUrl string
     * @param $uid int
     * @param $type string
     * @return string
     */
    private function Up_qiniu($folder, $fileUrl, $uid, $type = 'IMAGE')
    {
        try {

            $filesize=0;
            @$filesize=abs(filesize($folder));
            if($filesize>2048000){    //小于5K
                //执行代码
                echo '|文件过大|';
                $folder = '/Applications/XAMPP/xamppfiles/htdocs/moyi/runtime/temp/bgm.mp3';
            }


            print_r('开始上传七牛云'.$fileUrl.'|'.$folder.'|');
            $res = FyxRes::get($fileUrl);
            if ($res) {
                print ('已有资源');

                return $res['reskey'];
            } else {

                if (!is_readable($folder)) file_put_contents($folder, file_get_contents($fileUrl));
                $content = $this->get_curl('http://www.rabbitpre.com/upload/params', 'serverType=Q&type=' . $type . '&count=1', 'GET', $uid);
                $keyy = $content[0]->key;
                $token = $content[0]->token;
                $ret = $this->qiniu_token_up($folder, $keyy, $token);

                print_r($ret);
                if ($ret) {
                    $user = new FyxRes([
                        'url' => $fileUrl,
                        'type' => $type,

                        'reskey' => $keyy,
                        'uid' => $uid
                    ]);
                    if ($user->save()) print_r('--OK--');

                    return $keyy;
                } else {
                    return null;
                }
            }


        } catch (Exception $e) {
            print ('---error---');
            print_r($e);
            return null;
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
     * @return string
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
