<?php

header("content-type:text/html;charset=utf-8");
define("TOK","zhangjiajie");
$wxobj = new wxobj();
if(isset($_GET['echostr'])){
    $wxobj->qian();
}else if(isset($_GET['menu']) && $_GET['menu']=='add'){
    $wxobj->menus();
}else if(isset($_GET['ewm']) && $_GET['ewm']=='adds'){
    $wxobj->addewm();
}
else if(isset($_GET['sc']) && $_GET['sc']=='adds'){
    $wxobj->sucai();
}else{
    $wxobj->respon();
}
class wxobj{

    public $appid="wx77355b318879ab37";
    public $secret="7640b36f5e8516b2a143d956308e38a7";

    public function qian(){
        $echostr   = $_GET['echostr'];
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];
        $token     = TOK;
        $tmpArr = array($timestamp,$nonce,$token);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if($tmpStr == $signature){
            echo $echostr;
        }
    }

    public function getToken(){
        $url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->secret;
//        $url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid.'&secret='.$this->secret;
        $tokenInfo=$this->sendUrl($url);
        $token=json_decode($tokenInfo,true);
        return $token['access_token'];
    }

    public function getInfo($openid){
        $token=$this->getToken();
        $url="https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$token."&openid=".$openid."&lang=zh_CN";
//        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$token."&openid=".$openid."&lang=zh_CN";
        $data=$this->sendUrl($url);
//         var_dump($data);die;
        $userInfo=json_decode($data,true);
        return $userInfo;
    }
//创建临时二维码
    public function addewm(){
        $token=$this->getToken();
        $url="https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$token";
        $data='{"expire_seconds":"600","action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str":"dsafasd"}}}';
        $infoAtr=$this->sendUrl($url,$data);
        $infoTick=json_decode($infoAtr,true);
        $ticket=$infoTick['ticket'];
        $ewmurl="https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=$ticket";
        $getewm=$this->sendUrl($ewmurl);
        if(file_exists("guanzhu.png")){
            file_put_contents("guanzhu.png",$getewm);
        }else{
            echo 111;
        }

    }

//自定义菜单
    public function menus(){
        $menu='{
          "button":[
             {
                  "type":"click",
                  "name":"推广",
                  "key":"V1001_BOOK"
              },
              {
                   "name":"菜单",
                   "sub_button":[
                   {
                       "type":"view",
                       "name":"搜索助手",
                       "url":"http://www.baidu.com/"
                    },
                    {
                        "type":"view",
                        "name":"翻译助手",
                        "url":"http://fanyi.baidu.com/"
                   },
                    {
                       "type":"click",
                       "name":"赞一下我们",
                       "key":"V1001_GOOD"
                    }]
              }]
        }';
        $token=$this->getToken();
        $url="https://api.weixin.qq.com/cgi-bin/menu/create?access_token=$token";
        $success=$this->sendUrl($url,$menu);
        $success=json_decode($success,true);
        var_dump($success);
    }

    public function respon(){
        $data = $GLOBALS["HTTP_RAW_POST_DATA"];//$GLOBALS["HTTP_RAW_POST_DATA"];
        if(empty($data)){
            echo $_GET['menu'];
            echo "22";exit;
        }
        $postObj = simplexml_load_string($data,'SimpleXMLElement',LIBXML_NOCDATA);//把微信传值转换为对象
        switch ($postObj->MsgType){
            case 'text'://文本类型
                $this->textType($postObj);
                break;
            case 'event':
                $this->shijian($postObj);
                break;
        }
    }
    public function sucai($postObj)
    {
        $token = $this->getToken();
        $url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=$token&type=image";
        $data=array("name"=>"tupian","url"=>"@/phpstudy/www/guanzhu.png");
        $res=json_decode($this->sendUrl($url,$data),true);
        $madin_id=$res['media_id'];
//        var_dump($madin_id);
        $fromUserName=$postObj->FromUserName;
        $toUserName=$postObj->ToUserName;
        echo $textTpl="<xml><ToUserName><![CDATA[".$fromUserName."]]></ToUserName><FromUserName><![CDATA[".$toUserName."]]></FromUserName><CreateTime>".time()."</CreateTime><MsgType><![CDATA[image]]></MsgType><Image><MediaId><![CDATA[".$madin_id."]]></MediaId></Image></xml>";
    }


    public function textType($postObj){
        $pdo=new PDO("mysql:host=132.232.70.164;dbname=test","root","root");
        $pdo->query("set names utf8");
        $k_word=trim($postObj->Content) ? trim($postObj->Content):'';
        $f_name=$postObj->FromUserName;
//        $k_word=trim($postObj->Content);
        $userInfo=$this->getInfo($f_name);//用户信息
        $sql="select * from msg where id='$k_word' or keyname like'%$k_word%'";
        $data=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if($k_word=="?"||$k_word=="？"){
            $huifu=$userInfo['nickname']."请您回复以下书名或书籍编号进行查询:
             1：西游记
             2：鲁冰逊漂流记
             3：繁星
             4：钢铁是怎样炼成的
             5：诗经。
             ";
            $text=$this->setTpl('text',$postObj);
            $result=sprintf($text,$huifu);
        }elseif($data==''){
            $sex=($userInfo['sex']==1)?'先生':'女士';
            $huifu="对不起".$sex."我们暂无此书籍信息";
            $text=$this->setTpl('text',$postObj);
            $result=sprintf($text,$huifu);
        }else{
            $huifu=$data['keyname']."：".$data['msg'];
            $text=$this->setTpl('text',$postObj);
            $result=sprintf($text,$huifu);
        }
        echo $result;
    }
    public function shijian($postObj){
//        $type=$postObj->Event;
        switch($postObj->Event){
            case 'subscribe':
                $this->guanzhu($postObj);
                break;
            case 'CLICK':
                $this->sucai($postObj);
                break;
            default:echo '11';
        }
    }
    public function guanzhu($postObj){
        $f_name=$postObj->FromUserName;
        $userInfo=$this->getInfo($f_name);//用户信息
        $huifu=$userInfo['nickname']."欢迎您关注书评公众号如想了解书籍简介请回复“？”";
        $text=$this->setTpl('text',$postObj);
        $result=sprintf($text,$huifu);
        echo $result;
    }

    public function setTpl($type='text',$postObj){
        $fromUserName=$postObj->FromUserName;
        $toUserName=$postObj->ToUserName;
        switch($type){
            case 'text':
                $textTPl="<xml>
                            <ToUserName><![CDATA[".$fromUserName."]]></ToUserName>
                            <FromUserName><![CDATA[".$toUserName."]]></FromUserName>
                            <CreateTime>".time()."</CreateTime>
                            <MsgType><![CDATA[text]]></MsgType>
                            <Content><![CDATA[%s]]></Content>
                            </xml>";
//                <xml> <ToUserName>< ![CDATA[toUser] ]>
//                     </ToUserName> <FromUserName>< ![CDATA[fromUser] ]></FromUserName> <CreateTime>12345678</CreateTime> <MsgType>< ![CDATA[text] ]></MsgType> <Content>< ![CDATA[你好] ]></Content> </xml>
                break;
            case 'image':
                $textTPl="";
                break;
        }
        return $textTPl;
    }

    public function sendUrl($url, $data = false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); //信任任何证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 检查证书中是否设置域名,0不验证
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        if ($data !== false){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


}