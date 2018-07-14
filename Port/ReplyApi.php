<?php
/**
* @Author: pizepei
* @Date:   2017-06-12 21:24:25
* @Last Modified by:   pizepei
* @Last Modified time: 2018-05-13 10:59:19
*/
/**
 * 信息处理类
 * 微信消息接口
 * 包含微信扫描二维码登录
 * 扫描二维码绑定账号
 * 包括微信关键字回复
 * 语音回复等
 */
namespace pizepei\WechatBrief\Port;
use pizepei\WechatBrief\func;
use model\wechat\KeywordModel;
use pizepei\WechatBrief\Module\Chat\RedisModel;
use pizepei\WechatBrief\Config;
use pizepei\WechatBrief\Port\SHALApi;
use pizepei\WechatBrief\Port\AccessToken;
use pizepei\WechatBrief\Port\WXBizMsgCrypt;
class ReplyApi{

    private $postObj;//接受管理的xml对象

    //得到的是来源用户，是哪个用户跟我们发的消息$fromUsername$mediald
    private $fromUsername;

     //发给谁的。ToUserName   原始ID  开发者微信号
    private$toUsername;

    //被发送过来的内容
    private $keyword;

    //休息类型
    private $msgtype;

    //unix时间戳
    private $time;

    //视频消息缩略图的媒体id
    private $thumbmediaid = '';

    //媒体id
    private $mediald = '';

    //语音识别结果
    private $recognition = '';

    //图片网址
    private $picurl = '';

    //事件KEY值，与自定义菜单接口中KEY值对应 
    private $eventkey = '';

    //事件类型，subscribe(订阅)、unsubscribe(取消订阅)等
    private $event = '';

    //二维码的ticket，可用来换取二维码图片
    private $Ticket = '';

    //地理位置纬度 
    private $Latitude = '';

    //地理位置经度         
    private $Longitude = '';

    //地理位置精度
    private $Precision = '';

    /**
     * 审核事件推送
     */
    //商户自己内部ID，即字段中的sid
    public $UniqId = '';

    //微信的门店ID，微信内门店唯一标示ID
    public $PoiId = '';

    //审核结果，成功succ 或失败fail
    public $Result = '';

    //成功的通知信息，或审核失败的驳回理由
    public $msg = '';





    /**
     * 加解密
     */

    //加密类型
    private $encrypt_type = null;
    //加解密
    private $WXBizMsgCrypt = null;
    //随机数
    private $nonce = null;
    //时间戳
    private $timeStamp = null;

//----------------回复信息需要的 成员属性---------------------------------------

    //text 文字  image 图片  news 图文模板
    //信息  模板  array
    private $template_xml = array(
            'text'=>'<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[%s]]></MsgType>
        <Content><![CDATA[%s]]></Content>
        <FuncFlag>0</FuncFlag>
        </xml>',

            'image'=>'<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[%s]]></MsgType>
        <Image>
        <MediaId><![CDATA[%s]]></MediaId>
        </Image>
        </xml>',

            'news'=>'<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[%s]]></MsgType>
        <ArticleCount>%s</ArticleCount>
            <Articles>
                {$item}
            </Articles>
        </xml>'

        );

    //提取的关键字
    private $content_keyword;

    //回复信息 使用的xml 模板 字符串
    private $template_Type;
//--------------------------数据库存储方式-----------------------------        
    public $config = '';

    //没有关注的的扫描二维码事件
    public $qrscene_='';
   function __construct($get=null,$config = null)
   {
       //消息接口token验证
       $SHALApi =  new SHALApi($get);
       $SHALApi-> control();
        //获取post变量
        // $this->postObj = $GLOBALS["HTTP_RAW_POST_DATA"];
        $this->postObj = file_get_contents("php://input");

        if($config == null){
            //没有  定义自定义配置使用系统定义配置
            $this->config = config::WECHAT_CONFIG;
        }else{
            //有自定义配置
            $this->config = $config;
        }

        //对信息进行解密
       if(isset($get['encrypt_type']) && $this->config['encodingAesKey'] != '' ){

           if($get['encrypt_type'] == 'aes'){

               $this->encrypt_type = $get['encrypt_type'];
                //实例化 加解密
               $WXBizMsgCrypt = new WXBizMsgCrypt($this->config['token'],$this->config['encodingAesKey'],$this->config['appid']);
               $this->WXBizMsgCrypt = $WXBizMsgCrypt;

               $this->timeStamp = $get['timestamp'];
               $this->nonce = $get['nonce'];

               $msg = '';
               $WXBizMsgCrypt->decryptMsg($get['msg_signature'],$get['timestamp'],$get['nonce'],$this->postObj,$msg);
               $this->postObj = $msg;
           }
       }
       //xml_todj()获取 xml并且 初始化 接收的成员属性
        //template_xml() 初始化 信息面板 成员属性
        $this->xml_todj();
        // $this->template_xml();
        $this->content_type();//提取关键字
   }

   /**
    * [xml_todj 获取 xml 对象]
    * @Effect
    * @return [type] [description]
    */
   public function xml_todj()
   {

        // file_put_contents('./log/sql.txt','1122211');
        if(!empty($this->postObj))
        {
            //这个语句直接百度的时候，查到的信息是做安全防御用的：对于PHP，由于simplexml_load_string 函数的XML解析问题出现在libxml库上，所以加载实体前可以调用这样一个函数，所以这一句也应该是考虑到了安全问题。
            libxml_disable_entity_loader(true);
            // simplexml_load_string() 函数把 XML 字符串载入对象中。
            // 如果失败，则返回 false。
            $postObj = simplexml_load_string($this->postObj, 'SimpleXMLElement', LIBXML_NOCDATA);
            //判断是否成功获取 xml对象
           if($postObj)
            {   
                // 赋值postObj成员属性
                $this->postObj = $postObj;
                //初始化成员属性
                $this->fromUsername = (string)$postObj->FromUserName;

                $this->toUsername = $postObj->ToUserName;

                $this->keyword = trim($postObj->Content);

                $this->msgtype = $postObj->MsgType;

                $this->time = time();

                $this->thumbmediaid = $postObj->ThumbMediaId;

                $this->mediald = $postObj->posMediaId;

                $this->recognition = trim($postObj->Recognition,"！");

                $this->picurl = $postObj->PicUrl;

                $this->eventkey = $postObj->EventKey;

                $this->event = $postObj->Event;

                $this->Ticket  = $postObj->Ticket;

                $this->UniqId  = $postObj->UniqId;
                $this->PoiId  = $postObj->PoiId;
                $this->Result  = $postObj->Result;
                $this->msg  = $postObj->msg;

                /*测试使用
                    file_put_contents('sssss.txt',$this->fromUsername);
                */
            }else{
                //写入错误日志 mt_rand(0,500)
               file_put_contents('../utils/WechatBrief/Module/Cache/ReplyApi_log'.date('ymd_h').'.txt','['.date('y_m_d H').json_encode($this->postObj).']\n',FILE_APPEND);
                exit('非法请求');
            }
            //返回 
            return true;
        }
   }

   /**
    * [content_type 提取关键字 判断信息类型 处理内容]
    * @Effect
    * @return [type] [description]
    */
   function content_type()
   {
    
        switch($this->msgtype)
        {
            case 'text'://文字回复

//                $this->msg_Type = "text";
                $this->content_keyword = $this->keyword;

                //数据库关键字
                $this->keyword_trigger();

                break;

            case 'image'://图片

                //$this->msg_Type = "text";
                break;
            case 'voice'://语音

                
                $this->voice();

                break;

            case 'video'://视频


                break;


            case 'event' ://事件

                switch($this->event)
                {

                    //审核事件推送
                    case 'poi_check_notify':
                        $this->content_keyword = 'Event_poi_check_notify';
                        //数据库关键字
                        $this->keyword_trigger();
                        break;


                    //subscribe(订阅)、unsubscribe(取消订阅)
                     case 'subscribe':
//                     if(empty()){
//
                         $this->content_keyword = 'subscribe';
                         //数据库关键字
                         $this->keyword_trigger();
//
//                     }

                     break;


                    case 'unsubscribe':

                        $this->content_keyword = 'unsubscribe';
                        //数据库关键字
                        $this->keyword_trigger();
                    break;


                    case 'CLICK':
                    //
                    //点击菜单拉取消息时的事件推送
                    //用户点击自定义菜单后，微信会把点击事件推送给开发者，请注意，点击菜单弹出子菜单，不会产生上报。
                        $this->content_keyword = 'CLICK_'.$this->eventkey;
                        //数据库关键字
                        $this->keyword_trigger();
                        //EventKey    事件KEY值，与自定义菜单接口中KEY值对应
                    break;


                    //点击菜单跳转链接时的事件推送
                    case 'VIEW':

                    //EventKey    事件KEY值，设置的跳转URL
                        $this->content_keyword = 'unsubscribe';

                    break;

                    // 扫描带参数二维码事件------------------------------------------------//
                    case 'subscribe': //1. 用户未关注时，进行关注后的事件推送

                        if(empty($this->Ticket)){
                            //没有  扫描事件  的关注事件
                            $this->content_keyword = 'subscribe';
                            //数据库关键字
                            $this->keyword_trigger();

                        }else{
                            //扫描二维码  并且没有关注公众号
                                $this->Ticket = ltrim($this->Ticket,'qrscene_');
                                // $this->qrscene_ = 'qrscene_';
                                $this->subscribe();                  
                        }
                        // $this->content_keyword = '关注事件';

                    // EventKey    事件KEY值，qrscene_为前缀，后面为二维码的参数值
                    // Ticket  二维码的ticket，可用来换取二维码图片
                    break;

                    case 'SCAN': //2. 用户已关注时的事件推送 (包括二维码)

                        $this->subscribe();

                        // $this->content_keyword = '绑定成功';

                    // EventKey    事件KEY值，是一个32位无符号整数，即创建二维码时的二维码scene_id
                    // Ticket  二维码的ticket，可用来换取二维码图片
                    
                    break;

                    //上报地理位置事件
                    case 'LOCATION':
                    // Latitude    地理位置纬度
                    // Longitude   地理位置经度
                    // Precision   地理位置精度
                        $this->content_keyword = 'unsubscribe';

                    break;

                };

                break;

            default:
        }
   }

    /**
     * 触发 关键字 函数
     * @param string $type 关键字名称
     * @param string $contents 内容
     */
   function keyword_trigger($content='',$type='text')
   {
       //获取关键字
        //inject_check($Sql_Str)//自动过滤Sql的注入语句。
        //$content_keyword = func::inject_check($this->content_keyword);
       $content_keyword = $this->content_keyword;
        if(empty($content)){
//            获取关键字
            $sql_keyword = $this->content_keyword;
            // 判断数据库存储类型
            if($this->config['cache_keyword_type'] == 'mysql'){
                //查询关键字
                $result = KeywordModel::getKeyword($sql_keyword);

                if($result){
                    //获取关键字 参数
                    $name = $result['name'];
                    $model = $result['model'];
                    $method = $result['method'];
                    $type = $result['type'];
                    $content = $result['content'];
                    //部分模块不需要现在定义回复内容，数据库在无内容
                    if(empty($content))
                    {
                        $content='';
                    }
                }else{
                    //不在系统关键字范围
                    //判断是否已经存在客服会话缓存信息
                    $RedisModel = new RedisModel();
                    //判断是否是创建会话
                    if($RedisModel->set_session($this->fromUsername,false)){

                        $name = 'name';
                        $model = 'chat';
                        $method = 'chat';
                        $content=$this->content_keyword;

                    }else{
                        //不在会话中
                        $name = 'name';
                        $model = 'keyword';
                        $method = 'index';
                        $content=$this->content_keyword;
                    }
                }
            }else if($this->config == 'redis'){


            }
            //自定义关键字
        }else{
            $name = 'name';
            $model = 'keyword';
            $method = 'index';
        }
       $this->content_keyword = $content;
       //匹配回复   信息模板
       $this->template_Type = $this->template_xml[$type];
        /*
            这里设置检查   模块类是否存在
            不存在  写入日志
         */
        if(!file_exists('../utils/WechatBrief/Module/'.ucfirst($model).'/'.ucfirst($model).'Module.php'))
        {
            file_put_contents('../utils/WechatBrief/Module/Cache/LOG_module'.date('ymd_h').'.txt','[类名称]./module/'.$model.'/'.ucfirst($model).'Module.class.php'.'[关键字]'.$name,FILE_APPEND);
            exit();
        }
       //声明模块类
        $new1 = '\utils\WechatBrief\Module\\'.ucfirst($model).'\\'.ucfirst($model).'Module';

       $new = new $new1;
        //参数1、模板、2、来源用户，是哪个用户跟我们发的消息 3、开发者微信id  4、时间戳   5、回复信息的格式    6、需要被回复的内容
        //7、图文信息内容  类型array
        //8、关键字 
        $template_Type = $this->template_Type;
        $fromUsername = $this->fromUsername;
        $toUsername = $this->toUsername;
        $time = $this->time;
        //$ontent_keyword = $this->content_keyword;
        $recognition = $this->recognition;
        //回复信息      =   调用$method（）模块中的方法 处理学院返回的 完整xml内容  标签echo 到微信

       $replyMsg = $new->$method($template_Type,$fromUsername,$toUsername,$time,$type,$content,$news_content='',$this->content_keyword,$recognition,$this);
       /**
        *加密处理
        */
       if($this->encrypt_type){
           $this->WXBizMsgCrypt->encryptMsg($replyMsg,$this->timeStamp,$this->nonce,$replyMsg);
           echo $replyMsg;
       }else{
           echo $replyMsg;
       }
   }

   //语音识别 选择处理
   function voice()
   {

   }

   //绑定
   public function subscribe()
   {

   }

    //获取用户基本信息
    public function get_user_info($access_token,$openid)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
        $res = func::http_request($url);
        return json_decode($res, true);
    }

   //无法处理的未知触发
   public function abnormal_trigger()
   {

   }

   /**
    * [inject_check 自动过滤Sql的注入语句]
    * @Effect
    * @param  [type] $Sql_Str [需要过滤的数据]
    * @return [type]          [description]
    */
    public function inject_check($Sql_Str)//。
    {   

        if (!get_magic_quotes_gpc()) // 判断magic_quotes_gpc是否打开     
        {     
        $Sql_Str = addslashes($Sql_Str); // 进行过滤     
        }     
        $Sql_Str = str_replace("_", "_", $Sql_Str); // 把 '_'过滤掉     
        $Sql_Str = str_replace("%", "%", $Sql_Str); // 把' % '过滤掉  
        
        $check=preg_match("/select|insert|update|;|delete|'|\*|*|../|./|union|into|load_file|outfile/i",$Sql_Str);
        if ($check) {
                return '非法关键字';
                //echo '<script language="JavaScript">alert("系统警告：nn请不要尝试在参数中包含非法字符尝试注入！");</script>';
                exit();
        }else{
                return $Sql_Str;
        }
    }

    /**
     * 向某个客户端连接发消息
     *
     * @param int    $client_id
     * @param string $message
     * @return bool
     */
    public function sendToClient($client_id, $message)
    {
        // 设置GatewayWorker服务的Register服务ip和端口，请根据实际情况改成实际值
        Gateway::$registerAddress = '127.0.0.1:1238';
        return Gateway::sendToClient($client_id, $message);
    }


}
