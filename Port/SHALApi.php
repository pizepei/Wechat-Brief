<?php
/**
 * @Author: pizepei
 * @Date:   2017-06-03 14:39:36
 * @Last Modified by:   pizepei
 * @Last Modified time: 2018-06-29 11:23:07
 */
namespace pizepei\WechatBrief\Port;
use pizepei\WechatBrief\Config;

/**
 * 验证 微信公众号 接口
 */
class SHALApi{
    /**
     * 用SHA1算法生成安全签名
     * @param string $token 票据
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $signature 微信加密签名，signature结合了开发者填写的token参数和请求中的timestamp参数、nonce参数。
     */
    private $token; //票据
    private $timestamp; // 时间戳
    private $echostr; //随机字符串
    private $nonce; //随机数
    private $signature;//微信加密签名，signature结合了开发者填写的token参数和请求中的timestamp参数、nonce参数。
    private $status = false; //是否验证状态

    //构造函数 为 基本参数 赋 值
   function __construct($get,$token=null)
   {

       if(!$token){
           $token = Config::WECHAT_CONFIG['token'];
       }
        //判断是否是$token验证
       if(isset($get['timestamp']) && isset($get['timestamp']) && $get['signature'] && $get['echostr']){
           $this->token = $token;
           $this->timestamp = $get['timestamp'];
           $this->nonce = $get['nonce'];
           $this->signature = $get['signature'];
           $this->echostr = $get['echostr'];
           $this->status = true;
       }

   }

    /**
     * 验证权限控制
     * @param bool $msg 控制是否直接输出
     * @return bool
     */
   function control($msg = true)
   {
        //验证开启经常
       if($this->status){
           //验证是否成功
           if($this->token())
           {
               //是否开启直接输出
               if($msg){
                   echo $this->echostr;
               }
               return $this->echostr;
           }else{
               exit('非法请求');
           }

       }
       return $this->status;
   }
   /**
    * [token 进行验证]
    * @Effect
    * @return [type] [description]
    */
   function token()
   {
        //将token、timestamp、nonce三个参数进行字典序排序
        $array = array($this->token,$this->timestamp,$this->nonce);
        //   sort()  SORT_STRING - 把每一项作为字符串来处理
        sort($array,SORT_STRING);  

        //implode 对array 的值使用空‘默认’进行拼接=字符串
        $sign = implode($array);

        //sha1() 函数计算字符串的 SHA-1 散列。  是一种加密方式
        $sign = sha1($sign);

        //对微信传送过来的signature 与 本地拼接的$sign 进行比较
        if($sign == $this->signature)
        {
            //验证通过
            return true;
        }else{
            return false;
        }
   }

}


