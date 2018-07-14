<?php
/**
 * @Author: pizepei
 * @Date:   2018-05-12 17:01:55
 * @Last Modified by:   pizepei
 * @Last Modified time: 2018-06-28 16:34:02
 */
namespace pizepei\WechatBrief;
use pizepei\WechatBrief\Port\AccessToken;

/**
 * 微信扩展模块公共函数
 */
class func{

    //定义url
    //命名规则
    /**
     * 定义url命名规则
     * 前置
     *  KF 客服
     */
    const URL_ARR= [
        'KF_LIST'=>['https://api.weixin.qq.com/cgi-bin/customservice/getkflist?access_token=','获取客服列表'],
        'KF_ADD'=>['https://api.weixin.qq.com/customservice/kfaccount/add?access_token=','添加客服'],
        'KF_CREATE'=>['https://api.weixin.qq.com/customservice/kfsession/create?access_token=','创建客服会话'],
        'KF_SEND_V1'=>['https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=','客服向客户发送信息'],
        'MENU_ADD'=>['https://api.weixin.qq.com/cgi-bin/menu/create?access_token=','创建自定义菜单'],
        'STORE_CREATE'=>['http://api.weixin.qq.com/cgi-bin/poi/addpoi?access_token=','创建门店'],
        'STORE_CREATE_UPLOAD'=>['https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=','创建门店上传图片'],
        'STORE_POI_LIST'=>['https://api.weixin.qq.com/cgi-bin/poi/getpoilist?access_token=','查询门店列表'],

    ];
    const CONFIG = [];

    protected static $access_token = '';//access_token

    /**
     * [http_request curl HTTP请求（支持HTTP/HTTPS，支持GET/POST）]
     * @Effect
     * @param  [type] $url  [地址]
     * @param  [type] $data [数据]
     * @return [type]       [description]
     */
    public static function http_request($url, $data = null)
     {
         $curl = curl_init();
         curl_setopt($curl, CURLOPT_URL, $url);
         curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
         curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
         if (!empty($data)){
             curl_setopt($curl, CURLOPT_POST, 1);
             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
         }
         curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
         $output = curl_exec($curl);
         curl_close($curl);
         return $output;
     }

   /**
    * [inject_check 自动过滤Sql的注入语句]
    * @Effect
    * @param  [type] $Sql_Str [需要过滤的数据]
    * @return [type]          [description]
    */
    public static function inject_check($Sql_Str)//。
    {   

        if (!get_magic_quotes_gpc()) // 判断magic_quotes_gpc是否打开     
        {     
        $Sql_Str = addslashes($Sql_Str); // 进行过滤     
        }     
        $Sql_Str = str_replace("_", "_", $Sql_Str); // 把 '_'过滤掉     
        $Sql_Str = str_replace("%", "%", $Sql_Str); // 把' % '过滤掉  
        return $Sql_Str;
    }
    /**
     * [filter_mark 过滤英文标点符号 过滤中文标点符号]
     * @Effect
     * @param  [type] $text [description]
     * @return [type]       [description]
     */
    public static function filter_mark($text){ 
        if(trim($text)=='')return ''; 
        $text=preg_replace("/[[:punct:]\s]/",' ',$text); 
        $text=urlencode($text); 
        $text=preg_replace("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%E2%80%9C|%E2%80%98|%E2%80%99|%EF%BD%9E|%EF%BC%8E|%EF%BC%88)+/",' ',$text); 
        $text=urldecode($text); 
        return trim($text); 
    } 

    /**
     * [get_user_info 获取用户基本信息]
     * @Effect
     * @param  [type] $openid [description]
     * @param  [type] $access_token [description]
     * @return [type]         [description]
     */
    public static function get_user_info($openid,$access_token = null)
    {
        if($access_token == null){
            static::set_AccessToken();
        }
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".static::$access_token."&openid=".$openid."&lang=zh_CN";
        $res = self::http_request($url);
        return json_decode($res, true);
    }

    /**
     * 获取AccessToken
     */
    public static function set_AccessToken()
    {
        //判断并且获取AccessToken
        if(static::$access_token == ''){
            $AccessToken = new AccessToken();
            static::$access_token = $AccessToken->access_token();
            return static::$access_token;
        }
    }

    /**
     * @param $name  链接索引  如不存在 直接拼接$access_token成为URL
     * @param null $data  post 时的数据   默认null  存入自动post
     * @param bool $returntype  默认 返回json   如果为true 返回数组
     * @param bool $options  默认 编码中文   1为 不编码中文
     * @return mixed
     */
    public static function send($name, $data =null , $returntype = false,$options =0)
    {
        static::set_AccessToken();
        //判断url类型
        if(!isset(static::URL_ARR[$name][0])){
            $url = $name;
        }else{
            $url = static::URL_ARR[$name][0];
        }
        //拼接URL
        $url = $url.static::$access_token;
//        var_dump(json_encode($data));
        //请求

        if($options == 0){
            $ApiData = self::http_request($url,json_encode($data));
        }else if($options ==1){
            $ApiData = self::http_request($url,json_encode($data,JSON_UNESCAPED_UNICODE ));

        }else if($options ==2){
            $ApiData = self::http_request($url,urldecode(json_encode($data,JSON_UNESCAPED_UNICODE )));
        }else  if($options == 3){
            $ApiData = self::http_request($url,$data);
        }
        if($returntype){
            return json_decode($ApiData,true);
        }
        return $ApiData;
    }

}