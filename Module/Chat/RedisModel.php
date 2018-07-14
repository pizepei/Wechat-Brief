<?php
/**
 * Created by PhpStorm.
 * User: pizepei
 * Date: 2018/7/3
 * Time: 14:55
 */

namespace pizepei\WechatBrief\Module\Chat;
use  pizepei\WechatBrief\RedisModel as Redis;
use  pizepei\WechatBrief\func;


class RedisModel extends Redis
{
    const HASH_SESSION_USER_INFO_NAME = 'session_u_info_';//缓存会话状态
    const SET_SESSION_CHAT_RELATION_NAME = 'sessionchatrelation_';//客服接入列表
    const SET_SESSION_MAG_STATUS_NAME = 'session_magstatus_';//当客服不在线时的数据
    const SET_SESSION_MAGINC_STATUS_NAME = 'session_magincstatus_';//自增未读信息数量

    public $userInfo = null;//用户会话信息
    public $userMagStatus = null;//当客服不在线时的数据

    /**
     * 初始化会话
     * @param $openid
     */
    public function init_session($openid)
    {
        /**
         * 集合 使用集合保存客服对应的服务成员
         * Hash 使用hash保存
         *          opid
         *          微信昵称
         *          账号名称
         *          用户id
         *          会话创建时间
         *          上次会话时间（超过一定时间结束回话）
         *
         *
         */
        if($this->set_session($openid)){
            //不是第一次
            return false;

        }else{
            //第一次
            return true;

        }
    }

    /**
     * 判断是否存在缓存
     * @param $openid
     * @param $type
     */
    public  function set_session($openid,$type=true)
    {
//        $this->redis->del(self::HASH_SESSION_USER_INFO_NAME.$openid);
//        exit;
        $this->userInfo = $this->redis->hgetall(self::HASH_SESSION_USER_INFO_NAME.$openid);

        if(!$type){
            return $this->userInfo;
        }
//        var_dump($this->userInfo);
//        var_dump($this->zrange($this->userInfo['ChatId'],0,time()));
        if($this->userInfo){
            return true;
        }else{
            $this->found_session($openid);
            return false;
        }
    }
    /**
     * 准备数据
     * @param $openid
     */
    public function found_session($openid)
    {
        //获取微信信息
        $wxData = func::get_user_info($openid);

        //获取用户信息
        $userData = ['uid'=>null,'name'=>null];

        //通过用户信息判断对应的客服人员id
        $ChatId = '6c909ddc-735b-3593-db91-3e5913707590';
        $ChatName = '美女客服';

        //拼接数据
        $sessionData['openid'] = $openid;//openid
        $sessionData['headimgurl'] = $wxData['headimgurl'];//头像
        $sessionData['nickname'] = $wxData['nickname'];//昵称
        $sessionData['city'] = $wxData['city'];//城市
        $sessionData['province'] = $wxData['province'];//省sex
        $sessionData['sex'] = $wxData['sex'];//性别

        $sessionData['uid'] = $userData['uid'];//uid
        $sessionData['name'] = $userData['name'];//名字
        $sessionData['ChatId'] = $ChatId;//名字 客服人员id
        $sessionData['ChatName'] = $ChatName;//名字 客服人员昵称
        $sessionData['addChat'] = time();//创建时间
        //创建客服接入列表
        $this->zAdd($sessionData);
        //创建会话
        $this->hmsetCon($sessionData);
        $this->userInfo = $sessionData;

    }

    /**
     * 创建会话
     * @param $data
     */
    public function hmsetCon($data)
    {
        return $this->redis->hmset(self::HASH_SESSION_USER_INFO_NAME.$data['openid'],$data);
    }
    /**
     * 创建客服接入列表
     * @param $data
     */
    public function zAdd($data)
    {
        return $this->redis->zAdd(self::SET_SESSION_CHAT_RELATION_NAME.$data['ChatId'],time(),$data['openid']);
    }
    /**
     * 查，通过(score从大到小)【排序名次范围】拿member值，返回有序集key中，【指定区间内】的成员 [array | null]
     * @param $ChatId
     * @param $start
     * @param $stop
     * @return array
     */
    public function zrange($ChatId,$start = 0,$stop = 0)
    {
        if($stop == 0 ){
            $stop = time();
        }
        return $this->redis->zrevrange(self::SET_SESSION_CHAT_RELATION_NAME.$ChatId,$start,$stop);
    }

    /**
     * 获取客服对应的客户列表
     * @param $ChatId
     * @param int $start
     * @param int $stop
     * @return array
     */
    public function getUserlist($ChatId,$start = 0,$stop = 0)
    {
        $array = [];
        $zArr = $this->zrange($ChatId,$start,$stop);
        foreach ($zArr as $k => $v){
            $value =   $this->redis->hgetall(self::HASH_SESSION_USER_INFO_NAME.$v);
            $mag = $this->getMag($v);
            if($value){
//                $array[] = $value;

                $array[] = array_merge($mag,$value);
            }
        }
        return $array;
    }
    /**
     * 删除
     * @param $key
     * @param  $type
     */
    public function del($key,$type = true)
    {
        if($type){
            $key =self::HASH_SESSION_USER_INFO_NAME.$key;
        }
        return $this->redis->del($key);
    }

    /**
     * 处理客服不在线时的信息设置
     */
    public function setHashMag($data)
    {
        /**
         * 先 openid 做hash表  保存数据  本次信息时间、本次信息内容
         *设置自增未读信息数量
         */
        $this->setIncrbyMag($data['openid']);
        return $this->redis->hmset(self::SET_SESSION_MAG_STATUS_NAME.$data['openid'],$data);
    }
    /**
     * 设置自增未读信息数量
     * @param $openid
     * @param int $num
     * @return int
     */
    public function setIncrbyMag($openid,$num =1)
    {
        return $this->redis->incrby(self::SET_SESSION_MAGINC_STATUS_NAME.$openid,$num);
    }

    /**
     * 获取未读信息
     * @param $openid
     * @return array|null
     */
    public  function getMag($openid)
    {
        //获取数据
        $this->userMagStatus = $this->redis->hgetall(self::SET_SESSION_MAG_STATUS_NAME.$openid);
        //获取自增未读信息数量
        $this->userMagStatus['count'] = $this->redis->get(self::SET_SESSION_MAGINC_STATUS_NAME.$openid);
        return $this->userMagStatus;
    }

    /**重置未读信息操作
     * @param $openid
     * @return int
     */
    public function delMag($openid)
    {
        return $this->del([self::SET_SESSION_MAG_STATUS_NAME.$openid,self::SET_SESSION_MAGINC_STATUS_NAME.$openid],false);
    }

}