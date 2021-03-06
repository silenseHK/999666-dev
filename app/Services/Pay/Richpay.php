<?php


namespace App\Services\Pay;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Richpay extends PayStrategy
{

    protected static $url = 'http://api.tshop.live/order/';    // 网关

    private  $recharge_callback_url = '';     // 充值回调地址
    private  $withdrawal_callback_url = '';  //  提现回调地址

    public $withdrawMerchantID;
    public $withdrawSecretkey;
    public $rechargeMerchantID;
    public $rechargeSecretkey;
    public $company = 'richpay';   // 支付公司名

    public function _initialize()
    {
        $withdrawConfig = DB::table('settings')->where('setting_key','withdraw')->value('setting_value');
        $rechargeConfig = DB::table('settings')->where('setting_key','recharge')->value('setting_value');
        $withdrawConfig && $withdrawConfig = json_decode($withdrawConfig,true);
        $rechargeConfig && $rechargeConfig = json_decode($rechargeConfig,true);
        $this->withdrawMerchantID = isset($withdrawConfig[$this->company])?$withdrawConfig[$this->company]['merchant_id']:"";
        $this->withdrawSecretkey = isset($withdrawConfig[$this->company])?$withdrawConfig[$this->company]['secret_key']:"";

        $this->rechargeMerchantID = isset($rechargeConfig[$this->company])?$rechargeConfig[$this->company]['merchant_id']:"";
        $this->rechargeSecretkey = isset($rechargeConfig[$this->company])?$rechargeConfig[$this->company]['secret_key']:"";

        $this->recharge_callback_url = self::$url_callback . '/api/recharge_callback' . '?type='.$this->company;
        $this->withdrawal_callback_url =  self::$url_callback . '/api/withdrawal_callback' . '?type='.$this->company;
    }

    /**
     * 生成签名  sign = Md5(key1=vaIue1&key2=vaIue2&key=签名密钥);
     */
    public  function generateSign(array $params, $type=1)
    {
        if(!isset($params['channleOid']))$params['channleOid'] = $params['channelOid'];
        $secretKey = $type == 1 ? $this->rechargeSecretkey : $this->withdrawSecretkey;
        $sign = $params['channelId'] . $params['channleOid'] . $params['amount'] . $secretKey;
        return md5($sign);
    }

    /**
     * 充值下单接口
     */
    public function rechargeOrder($pay_type, $money)
    {
        $order_no = self::onlyosn();
        $params = [
            'amount' => (string)number_format((float)$money,2),
//            'callbackUrl' => $this->recharge_callback_url,
            'channelId' => (string)($this->rechargeMerchantID),
            'channleOid' => (string)$order_no,
            'email' => '88888888@in.com',
            'firstName' => 'Customer',
            'mobile' => '88888888',
            'notifyUrl' => $this->recharge_callback_url,
            'payType' => 1,
            'remark' => 'recharge',
            'timestamp' => time() * 1000,  //精确到毫秒
        ];
        $params['sign'] = $this->generateSign($params,1);

        \Illuminate\Support\Facades\Log::channel('mytest')->info('richpay_rechargeOrder', [$params]);
        $res = $this->requestService->postJsonData(self::$url . 'order/submit', $params);
        if ($res['code'] != "0000") {
            \Illuminate\Support\Facades\Log::channel('mytest')->info('richpay_rechargeOrder_return', $res);
            $this->_msg = $res['message'];
            return false;
        }
        $native_url = $res['data']['payUrl'];
        $resData = [
            'out_trade_no' => $order_no,
            'pay_type' => $pay_type,
            'order_no' => $order_no,
            'native_url' => $native_url,
            'notify_url' => $this->recharge_callback_url,
            'pltf_order_id' => '',
            'verify_money' => '',
            'match_code' => '',
            'is_post' => isset($is_post)?$is_post:0,
            'params' => []
        ];
        return $resData;
    }

    /**
     * 充值回调
     */
    function rechargeCallback(Request $request)
    {
        \Illuminate\Support\Facades\Log::channel('mytest')->info('richpay_rechargeCallback',$request->post());
        if ($request->status != 1)  {
            $this->_msg = 'richpay-recharge-交易未完成';
            return false;
        }
        // 验证签名
        $params = $request->input();
        $sign = $params['sign'];
        if ($this->generateSign($params,1) <> $sign) {
            $this->_msg = 'richpay-签名错误';
            return false;
        }

        $where = [
            'order_no' => $request->channleOid,
            'pltf_order_id' => $request->oid
        ];
        return $where;
    }

    /**
     *  后台审核请求提现订单 (提款)  代付方式
     */
    public function withdrawalOrder(object $withdrawalRecord)
    {
        $money = $withdrawalRecord->payment;    // 打款金额
        $order_no = $withdrawalRecord->order_no;

        $params = [
            'amount' => (string)$money,
            'channelId' => (string)$this->withdrawMerchantID,
            'channelOid' => (string)$order_no,
            'fundAccount' => [
                'accountType' => 'bank_account',
                'bankAccount' => [
                    'accountNumber' => (string)$withdrawalRecord->bank_number,
                    'ifsc' => (string)$withdrawalRecord->ifsc_code,
                    'name' => (string)$withdrawalRecord->account_holder
                ],
            ],
            'mode' => 'upi',
            'notifyUrl' => $this->withdrawal_callback_url,
            'timestamp' => time() * 1000,
        ];
        \Illuminate\Support\Facades\Log::channel('mytest')->info('richpay_withdrawalOrder_test',$params);
        $params['sign'] = $this->generateSign($params,2);

        \Illuminate\Support\Facades\Log::channel('mytest')->info('richpay_withdrawalOrder',$params);
        $res = $this->requestService->postJsonData(self::$url . 'order/payout/submit', $params);
        \Illuminate\Support\Facades\Log::channel('mytest')->info('richpay_withdrawalOrder2_res',$res);
        if ($res['code'] != '0000') {
            $this->_msg = $res['message'];
            return false;
        }
        if (in_array($res['data']['state'], [2,3])) {
            $this->_msg = $res['data']['msg'];
            return false;
        }
        return  [
            'pltf_order_no' => $res['data']['payOutId'],
            'order_no' => $order_no
        ];
    }

    /**
     * 提现回调
     */
    function withdrawalCallback(Request $request)
    {
        \Illuminate\Support\Facades\Log::channel('mytest')->info('richpay_withdrawalCallback',$request->input());

        $pay_status = 0;
        $status = $request->status;
        if(in_array($status, [2,3])){
            $pay_status = 3;
        }
        if($status == 1){
            $pay_status = 1;
        }
        if ($pay_status == 0) {
            $this->_msg = 'richpay-withdrawal-交易未完成';
            return false;
        }
        // 验证签名
        $params = $request->input();
        $sign = $params['sign'];
        if ($this->generateSign($params,2) <> $sign) {
            $this->_msg = 'richpay-签名错误';
            return false;
        }
        $where = [
            'order_no' => $request->channleOid,
            'plat_order_id' => $request->oid,
            'pay_status' => $pay_status
        ];
        return $where;
    }

}
