<?php
/**
 * @Author: anchen
 * @Date:   2017-04-18 22:22:41
 * @Last Modified by:   pizepei
 * @Last Modified time: 2018-06-28 17:02:10
 */
    // namespace Think\Wechat\Module;
    // \Module\拓展目录\
    
    namespace pizepei\WechatBrief\Module\Chat;
    use  pizepei\GatewayClient\Gateway;
    use pizepei\WechatBrief\func;
    use pizepei\WechatBrief\Module\Chat\RedisModel;
    use pizepei\model\wechat\ChatrecordModel;
    use pizepei\WechatBrief\Config;
    class ChatModule{
        /**
         * 初始化会话
         * @param $template_Type
         * @param $fromUsername
         * @param $toUsername
         * @param $time
         * @param $type
         * @param $content
         * @param $news_content
         * @param $content_keyword
         * @param $recognition
         * @return array|string
         */
        function init($template_Type,$fromUsername,$toUsername,$time,$type,$content,$news_content,$content_keyword,$recognition)
        {
            //判断是否已经存在缓存信息
            $RedisModel = new RedisModel();
            //判断是否是创建会话
            if($RedisModel->init_session($fromUsername)){
                $sendInfo = $RedisModel->userInfo;
                $sendInfo['emit'] = 'online';
                $sendInfo['content'] = '有新的客户信息';
                $sendInfo['sender_id'] = $fromUsername;
                $sendInfo['time'] = time();

                Gateway::$registerAddress = config::WECHAT_CONFIG['registerAddress'];

                //判断客服是否在线
                if(Gateway::isUidOnline($sendInfo['ChatId']) == 1){
                    //在线
                    Gateway::sendToUid($sendInfo['ChatId'],json_encode($sendInfo,JSON_UNESCAPED_UNICODE));
                }else{
                    //设置未读状态信息
                    $magData = ['openid'=>$fromUsername,'addtime'=>time(),'content'=>$content];
                    $RedisModel->setHashMag($magData);

                    $ChatRecordData['readStatus'] = 1;
                    //不在线
                    $content = '正在安排客服人员，您可以先留言客服上线后会第一时间回复您的。';
                }

                $ChatRecordData = [
                    'openid'=>$sendInfo['openid'],
                    'chatId'=>$sendInfo['ChatId'],
                    'contentType'=>'text',
                    'sendType' =>'2',//客服1  2客户
                    'content'=>'创建会话',
                ];

                 ChatrecordModel::addChatrecord($ChatRecordData);


            }else{
                $content = '已在客服会话中';
            }

            $content_text = sprintf($template_Type, $fromUsername, $toUsername, $time, $type, $content);
            return $content_text;

        }

        /**
         * 处理聊天
         * @param $template_Type
         * @param $fromUsername
         * @param $toUsername
         * @param $time
         * @param $type
         * @param $content
         * @param $news_content
         * @param $content_keyword
         * @param $recognition
         * @return string
         */
        function chat($template_Type,$fromUsername,$toUsername,$time,$type,$content,$news_content,$content_keyword,$recognition)
        {
            //判断是否已经存在缓存信息
            $RedisModel = new RedisModel();
            //判断是否是创建会话
            if($RedisModel->set_session($fromUsername,false)){
                $sendInfo = $RedisModel->userInfo;
                $sendInfo['emit'] = 'wechat';
                $sendInfo['content'] = $content;
                $sendInfo['sender_id'] = $fromUsername;
                $sendInfo['time'] = time();

                Gateway::$registerAddress = config::WECHAT_CONFIG['registerAddress'];

                //准备聊天记录
                $ChatRecordData = [
                    'openid'=>$sendInfo['openid'],
                    'chatId'=>$sendInfo['ChatId'],
                    'contentType'=>'text',
                    'sendType' =>'2',//客服1  2客户 3系统
                    'content'=>$content,
                ];

                //判断客服是否在线
                if(Gateway::isUidOnline($sendInfo['ChatId']) ==1){

                    $ChatRecordData['chatStatus'] = 1;
                    //在线
                    Gateway::sendToUid($sendInfo['ChatId'],json_encode($sendInfo,JSON_UNESCAPED_UNICODE));
                    ChatrecordModel::addChatrecord($ChatRecordData);

                }else{
                    //保存客户发送数据
                    $ChatRecordData['chatStatus'] = 0;
                    ChatrecordModel::addChatrecord($ChatRecordData);

                    //设置未读状态信息
                    $magData = ['openid'=>$fromUsername,'msgtime'=>time(),'content'=>$content];
                    $RedisModel->setHashMag($magData);


                    //不在线
                    $content = '正在安排客服人员，您可以先留言客服上线后会第一时间回复您的。';
                    $content_text = sprintf($template_Type, $fromUsername, $toUsername, $time, $type, $content);
                    //保持系统发送数据
                    $ChatRecordData['sendType'] = 3;
                    $ChatRecordData['content'] =$content;
                    $ChatRecordData['readStatus'] = 1;
                    ChatrecordModel::addChatrecord($ChatRecordData);

                    //清除会话标签
                    return $content_text;
                }

            }else{
                $content_text = sprintf($template_Type, $fromUsername, $toUsername, $time, $type, $content);
                return $content_text;
            }

        }

        /**
         * 结束会话
         * @param $template_Type
         * @param $fromUsername
         * @param $toUsername
         * @param $time
         * @param $type
         * @param $content
         * @param $news_content
         * @param $content_keyword
         * @param $recognition
         * @return string
         */
        public function finishCaht($template_Type,$fromUsername,$toUsername,$time,$type,$content,$news_content,$content_keyword,$recognition)
        {

            $content = '客户退出会话';
            //判断是否已经存在缓存信息
            $RedisModel = new RedisModel();
            //判断是否是创建会话
            if($RedisModel->set_session($fromUsername,false)){
                $sendInfo = $RedisModel->userInfo;
                $sendInfo['emit'] = 'finish';
                $sendInfo['content'] = $content;
                $sendInfo['sender_id'] = $fromUsername;
                Gateway::$registerAddress = config::WECHAT_CONFIG['registerAddress'];

                //准备聊天记录
                $ChatRecordData = [
                    'openid'=>$sendInfo['openid'],
                    'chatId'=>$sendInfo['ChatId'],
                    'contentType'=>'text',
                    'sendType' =>'2',//客服1  2客户 3系统
                    'content'=>$content,
                ];


                if(!$RedisModel->del($sendInfo['openid'])){
                    $content_text = sprintf($template_Type, $fromUsername, $toUsername, $time, $type,'您不在会话中');
                    return $content_text;
                }
                //判断客服是否在线
                if(Gateway::isUidOnline($sendInfo['ChatId']) ==1){

                    $ChatRecordData['chatStatus'] = 1;
                    //在线
                    Gateway::sendToUid($sendInfo['ChatId'],json_encode($sendInfo,JSON_UNESCAPED_UNICODE));
                    ChatrecordModel::addChatrecord($ChatRecordData);
                }
                $content_text = sprintf($template_Type, $fromUsername, $toUsername, $time, $type, $content);
                return $content_text;
            }else{
                $content_text = sprintf($template_Type, $fromUsername, $toUsername, $time, $type,'您不在会话中');
                return $content_text;
            }

        }

    }
