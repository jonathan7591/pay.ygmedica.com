<?php
namespace lib\Complain;

class CommUtil
{
    public static $plugins = ['alipay','alipaysl','alipayd','wxpayn','wxpaynp','huifu','kuaiqian','huolian','yeepay','epayn'];

    public static function getModel($channel){
        if($channel['plugin'] == 'alipay' || $channel['plugin'] == 'alipaysl' || $channel['plugin'] == 'alipayd'){
            if($channel['source'] == 1){
                return new AlipayRisk($channel);
            }else{
                return new Alipay($channel);
            }
        }elseif($channel['plugin'] == 'wxpayn' || $channel['plugin'] == 'wxpaynp'){
            return new Wxpay($channel);
        }elseif($channel['plugin'] == 'huifu' && $channel['type']==2){
            return new HuifuWxpay($channel);
        }elseif($channel['plugin'] == 'kuaiqian' && $channel['type']==2){
            return new KuaiqianWxpay($channel);
        }elseif($channel['plugin'] == 'kuaiqian' && $channel['type']==1){
            return new KuaiqianAlipay($channel);
        }elseif($channel['plugin'] == 'huolian' && $channel['type']==2){
            return new HuolianWxpay($channel);
        }elseif($channel['plugin'] == 'yeepay' && $channel['type']==1){
            return new YeepayAlipay($channel);
        }elseif($channel['plugin'] == 'yeepay' && $channel['type']==2){
            return new YeepayWxpay($channel);
        }elseif($channel['plugin'] == 'epayn' && $channel['type']==1){
            return new EpayAlipay($channel);
        }elseif($channel['plugin'] == 'epayn' && $channel['type']==2){
            return new EpayWxpay($channel);
        }
        return false;
    }

    public static function getChannel($row){
        global $DB;
        $channel = \lib\Channel::get($row['channel']);
        if(!$channel) return false;
        if($row['subchannel'] > 0){
            $channel = \lib\Channel::getSub($row['subchannel']);
        }
        elseif($channel['plugin'] == 'alipaysl' && substr($channel['appmchid'],0,1)=='['){
            $channelinfo = $DB->findColumn('user','channelinfo',['uid'=>$row['uid']]);
            if($channelinfo){
                $channel = \lib\Channel::get($row['channel'], $channelinfo);
            }
        }
        return $channel;
    }

    public static function autoHandle($trade_no, $status){
        global $DB, $conf;
        if(empty($trade_no)) return;

        //自动冻结订单
        if($conf['complain_freeze_order']==1){
            if($status < 2){ //冻结订单
                \lib\Order::freeze($trade_no);
            }elseif($status == 2){ //解冻订单
                \lib\Order::unfreeze($trade_no);
            }
        }

        //自动拉黑支付账号
        $order = $DB->find('order', 'buyer,realmoney,status,ip', ['trade_no'=>$trade_no]);
        if(!$order) return;
        if($status < 2 && $conf['complain_auto_black'] == 1 && !empty($order['buyer'])){
            if(!$DB->getRow("select * from pre_blacklist where type=:type and content=:content limit 1", [':type'=>0, ':content'=>$order['buyer']])){
                $DB->insert('blacklist', ['type'=>0, 'content'=>$order['buyer'], 'addtime'=>'NOW()', 'remark'=>'投诉自动拉黑']);
                //$DB->insert('blacklist', ['type'=>1, 'content'=>$order['ip'], 'addtime'=>'NOW()', 'remark'=>'投诉自动拉黑']);
            }
        }

        //自动退款
        if($status == 0 && $conf['complain_auto_refund'] == 1 && (empty($conf['complain_auto_refund_money']) || $conf['complain_auto_refund_money']>=$order['realmoney']) && ($order['status'] == 1 || $order['status'] == 3)){
            $params = ['trade_no'=>$trade_no, 'money'=>$order['realmoney'], 'key'=>md5($trade_no.SYS_KEY.$trade_no)];
            get_curl($conf['localurl'].'api.php?act=refundapi', http_build_query($params));
            //\lib\Order::refund($trade_no, $order['realmoney'], 1);
        }
    }
}