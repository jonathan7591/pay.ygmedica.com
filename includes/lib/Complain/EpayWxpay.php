<?php
namespace lib\Complain;

use Exception;

require_once PLUGIN_ROOT.'epayn/inc/EpayCore.class.php';

class EpayWxpay implements IComplain
{

    private $channel;
    private $service;

    function __construct($channel){
		$this->channel = $channel;
        require(PLUGIN_ROOT.'epayn/inc/epay.config.php');
        $this->service = new \EpayCore($epay_config);
	}

    //刷新最新投诉记录列表
    public function refreshNewList($num){
        $page_num = 1;
        $page_size = $num > 20 ? 20 : $num;
        $page_count = ceil($num / $page_size);
        $begin_date = date('Y-m-d', strtotime('-29 days'));
        $end_date = date('Y-m-d');

        $count_add = 0;
        $count_update = 0;
        for($page_num = 1; $page_num <= $page_count; $page_num++){
            $params = [
                'page_num' => $page_num,
                'page_size' => $page_size,
                'pay_type' => 'wxpay',
                'begin_date' => $begin_date,
                'end_date' => $end_date
            ];
            try{
                $result = $this->service->execute('api/complain/list', $params);
            }catch(Exception $e){
                return ['code'=>-1, 'msg'=>$e->getMessage()];
            }
            if($result['data']['total'] == 0 || count($result['data']['rows']) == 0) break;

            foreach($result['data']['rows'] as $info){
                $rescode = $this->updateInfo($info);
                if($rescode == 2) $count_update++;
                elseif($rescode == 1) $count_add++;

                if(isset($_GET['key']) && $rescode > 0 && $info['status'] < 2){ //监控模式
                    global $DB;
                    $msgtype = null;
                    if($rescode == 2){
                        $msgtype = '用户提交了新的反馈，请尽快处理';
                    }elseif($rescode == 1){
                        $msgtype = '您有新的支付交易投诉，请尽快处理';
                    }
                    if($msgtype){
                        $row = $DB->getRow("SELECT A.uid,A.trade_no,A.title,A.content,A.addtime,B.name ordername,B.money FROM pre_complain A LEFT JOIN pre_order B ON A.trade_no=B.trade_no WHERE thirdid=:thirdid", [':thirdid'=>$info['id']]);
                        \lib\MsgNotice::send('complain', $row['uid'], ['trade_no'=>$row['trade_no'], 'title'=>$row['title'], 'content'=>$row['content'], 'type'=>$msgtype, 'name'=>$row['ordername'], 'money'=>$row['money'], 'time'=>$row['addtime']]);
                    }
                }
            }
        }
        return ['code'=>0, 'msg'=>'成功添加'.$count_add.'条投诉记录，更新'.$count_update.'条投诉记录'];
    }

    //回调刷新单条投诉记录
    public function refreshNewInfo($thirdid, $type = null){
        return;
    }

    //获取单条投诉记录
    public function getNewInfo($id){
        global $DB;
        $data = $DB->find('complain', '*', ['id'=>$id]);
        $params = [
            'id' => $data['thirdid']
        ];
        try{
            $result = $this->service->execute('api/complain/detail', $params);
            $info = $result['data'];
            $showtype = $result['showtype'];
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }

        $status = $info['status'];
        if($status != $data['status']){
            $data['status'] = $status;
            $data['edittime'] = $info['edittime'];
            $DB->update('complain', ['status'=>$data['status'], 'edittime'=>$data['edittime']], ['id'=>$data['id']]);
            CommUtil::autoHandle($data['trade_no'], $status);
        }

        $data['money'] = $info['money'];
        $data['images'] = $info['images'];
        $data['is_full_refunded'] = $info['is_full_refunded']; //订单是否已全额退款
        $data['incoming_user_response'] = $info['incoming_user_response']; //是否有待回复的用户留言
        $data['user_complaint_times'] = $info['user_complaint_times']; //用户投诉次数
        if(isset($info['apply_refund_amount'])){
            $data['apply_refund_amount'] = $info['apply_refund_amount']; //申请退款金额
        }
        $data['reply_detail_infos'] = $info['reply_detail_infos']; //协商记录

        return ['code'=>0, 'showtype'=>$showtype, 'data'=>$data];
    }
    
    private function updateInfo($info){
        global $DB, $conf;
        $thirdid = $info['id'];
        $trade_no = $info['out_trade_no'];
        $api_trade_no = $info['trade_no'];
        $status = $info['status'];

        $row = $DB->find('complain', '*', ['thirdid'=>$thirdid], null, 1);
        if(!$row){
            $order = $DB->find('order', 'uid', ['trade_no'=>$trade_no]);
            if(!$order){
                $order = $DB->find('order', 'trade_no,uid', ['api_trade_no'=>$api_trade_no]);
                if($order){
                    $trade_no = $order['trade_no'];
                }else{
                    $trade_no = $api_trade_no;
                    if(!$conf['complain_range']) return 0;
                }
            }
        }

        if($row){
            if($status != $row['status']){
                $DB->update('complain', ['status'=>$status, 'edittime'=>$info['edittime']], ['id'=>$row['id']]);
                CommUtil::autoHandle($trade_no, $status);
                return 2;
            }
        }else{
            if($order || $conf['complain_range']==1){
                $DB->insert('complain', ['paytype'=>$this->channel['type'], 'channel'=>$this->channel['id'], 'uid'=>$order['uid'] ?? 0, 'trade_no'=>$trade_no, 'thirdid'=>$thirdid, 'type'=>$info['problem_type'], 'title'=>$info['problem_description'], 'content'=>$info['complaint_content'], 'status'=>$status, 'phone'=>$info['phone'], 'addtime'=>$info['addtime'], 'edittime'=>$info['edittime']]);

                if($status == 0 && $conf['complain_auto_reply'] == 1 && !empty($conf['complain_auto_reply_con'])){
                    usleep(300000);
                    $this->feedbackSubmit($thirdid, '', $conf['complain_auto_reply_con']);
                }
                CommUtil::autoHandle($trade_no, $status);
                return 1;
            }
        }
        return 0;
    }

    //上传图片
    public function uploadImage($thirdid, $filepath, $filename){
        $params = [
            'id' => $thirdid,
            'file' => new \CURLFile($filepath, null, $filename),
        ];
        try{
            $result = $this->service->execute('api/complain/upload', $params);
            return ['code'=>0, 'image_id'=>$result['image_id']];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //处理投诉（仅支付宝）
    public function feedbackSubmit($thirdid, $code, $content, $images = []){
        $params = [
            'id' => $thirdid,
            'content' => $content,
            'images' => $images,
            'complete' => '1'
        ];
        try{
            $this->service->execute('api/complain/feedback', $params);
            return ['code'=>0];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //回复用户
    public function replySubmit($thirdid, $content, $images = []){
        $params = [
            'id' => $thirdid,
            'content' => $content,
            'images' => $images,
        ];
        try{
            $this->service->execute('api/complain/reply', $params);
            return ['code'=>0];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //更新退款审批结果（仅微信）
    public function refundProgressSubmit($thirdid, $code, $content, $remark = null, $images = []){
        $params = [
            'id' => $thirdid,
            'code' => $code,
            'content' => $content,
            'remark' => $remark,
            'images' => $images,
        ];
        try{
            $this->service->execute('api/complain/refundprogress', $params);
            return ['code'=>0];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //处理完成（仅微信）
    public function complete($thirdid){
        $params = [
            'id' => $thirdid,
        ];
        try{
            $this->service->execute('api/complain/complete', $params);
            return ['code'=>0];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //商家补充凭证（仅支付宝）
    public function supplementSubmit($thirdid, $content, $images = []){
        return false;
    }

    //下载图片（仅微信）
    public function getImage($media_id){
        return false;
    }
}