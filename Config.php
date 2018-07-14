<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/5
 * Time: 16:36
 * 微信类库配置
 */
namespace pizepei\WechatBrief;

class Config
{
    //微信接口配置
    const WECHAT_CONFIG = [
        //中深软微信公众号
        'appid'=>'wx84a717c9d2bbff47',
        'appsecret'=>'88478bc8d7fac2e7b7ec37e43b0a8e7c',
        'token'=>'nZyRlcCbmqNh',
        'encodingAesKey'=>'HLBphAV46tZkJSLFtIY6gAQNQ0FomzuwWudbGEPBkbD',
        'cache_type'=> 'redis',//自定义 微信token存储方式 支持  redis  file
        'cache_keyword_type'=> 'mysql',//自定义 关键字  存储方式 支持  redis  mysql
        'host'         => '192.168.2.211', // redis主机
        'port'         => 6379, // redis端口
        'password'     => '', // 密码
        'select'       => 0, // 操作库
        // 'expire'       => 3600, // 有效期(秒)
        'timeout'      => 0, // 超时时间(秒)
        'persistent'   => true, // 是否长连接
        'type'   => 'wechat_access_token', // 名称前缀
        // Gateway 配置
        'registerAddress' => '104.168.30.225:1238',

    ];

}