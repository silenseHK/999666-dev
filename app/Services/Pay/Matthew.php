<?php


namespace App\Services\Pay;

use App\Libs\Aes;
use App\Repositories\Api\UserRepository;
use App\Services\RequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 *  如：unicasino.in  的充值和提现类
 */
class Matthew extends PayStrategy
{

    protected static $rechargeUrl = 'https://payapitest.soon-ex.com/';

    protected static $withdrawUrl = 'https://payapitest.soon-ex.com/';

    protected static $nativeUrl = 'http://paytest.soon-ex.com/#/?orderId=';

    private  $recharge_callback_url = '';     // 充值回调地址
    private  $withdrawal_callback_url = '';  //  提现回调地址

    public $withdrawMerchantID;
    public $withdrawSecretkey;
    public $rechargeMerchantID;
    public $rechargeSecretkey;
    public $company = 'matthew';   // 支付公司名

    protected $iv = '!WFNZFU_{H%M(S|a';

    public $rechargeRtn='success'; //支付成功的返回
    public $withdrawRtn='success'; //提现成功的返回

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
     * 生成签名   sign = Md5(key1=vaIue1&key2=vaIue2…商户密钥);
     */
    public function generateSign(array $params)
    {
        sort($params,SORT_LOCALE_STRING);
        $string = '';
        foreach ($params as $key => $value) {
            $string .= $value;
        }
        return strtolower(sha1($string));
    }

    /**
     * 充值下单接口
     */
    function rechargeOrder($pay_type,$money)
    {
        $order_no = self::onlyosn();

        $en = [
            'amount' => (string)$money,
            'thirdOrderNumber' => $order_no,//商家自己平台的订单号
            'thirdUserId' => $this->getUserId(),//商家自己平台的会员ID，如果没有可以用上面的订单号
        ];
        $Aes = new Aes();
        $key = substr($this->rechargeSecretkey, 0,16);
        $encryptedData = $Aes->encryptWithOpenssl($key, $en, $this->iv);
        $data = [
            'encryptedData' => $encryptedData,   //openssl_encrypt进行aes对称加密
            'signaturePo' => [
                'apiId' => $this->rechargeMerchantID,  //商家ID
                'nonce' => (string)randomStr(10),
                'signature' => '',
                'timestamp' => get_total_millisecond()
            ],
        ];
        $signature = $this->generateSign([   //调用签名函数进行数据签名
            $data['signaturePo']['timestamp'].'',
            $data['signaturePo']['nonce'].'',
            $data['signaturePo']['apiId'],
            $this->rechargeSecretkey,
            json_encode($en),  //将数组转json格式的数据
        ]);
        $data['signaturePo']['signature'] = $signature;

        \Illuminate\Support\Facades\Log::channel('mytest')->info('matthew_rechargeOrder', $data);

        $res = $this->requestService->postJsonData(self::$rechargeUrl . 'otc/api/recharge', $data);
        if ($res['code'] <> 0) {
            \Illuminate\Support\Facades\Log::channel('mytest')->info('matthew_rechargeOrder_return', $res);
            $this->_msg = $res['message'];
            return false;
        }
        $resData = [
            'out_trade_no' => $order_no,
            'shop_id' => $this->rechargeMerchantID,
            'pay_company' => $this->company,
            'pay_type' => $pay_type,
            'native_url' => self::$nativeUrl . $res['data']['orderNumber'],
            'pltf_order_id' => '',
            'verify_money' => $en['amount'],
            'match_code' => '',
            'notify_url' => $this->recharge_callback_url,
            'params' => [],
            'is_post' => 0,
        ];
        return $resData;
    }

    function withdrawalOrder(object $withdrawalRecord)
    {
        $money = $withdrawalRecord->payment;    // 打款金额
        $order_no = $withdrawalRecord->order_no;

        $en = [
            'amount' => (string)$money,
            'thirdOrderNumber' => $order_no,//uniqid(),商家自己平台的提现订单号
            'thirdUserId' => $this->getUserId(), //商家自己平台的会员ID，如果没有可以用上面的订单号
            'issuePayPo'=>[
                'accountName' => $withdrawalRecord->bank_number,  //提现用户收款的账户
                'ifscCode'=> $withdrawalRecord->ifsc_code, //提现用户的IFS code
                'bankName' => $withdrawalRecord->bank_name,  //银行名
                'name' => $withdrawalRecord->account_holder,  //提现用户姓名
                'paymentId'=>'11' //收款方式ID这里以IMPS为例
            ]
        ];
        $Aes = new Aes();
        $key = substr($this->rechargeSecretkey, 0,16);
        $data = [
            'encryptedData' => $Aes->encryptWithOpenssl($key, $en, $this->iv),   //提现数据加密
            'signaturePo' => [
                'apiId' => $this->withdrawMerchantID,
                'nonce' => (string)randomStr(10).'',
                "apisecret" => $this->withdrawSecretkey,
                'signature' => '',
                'timestamp' => get_total_millisecond()
            ],
        ];
        $signature = $this->generateSign([   //调用签名函数进行数据签名
            $data['signaturePo']['timestamp'].'',
            $data['signaturePo']['nonce'].'',
            $data['signaturePo']['apiId'],
            $this->withdrawSecretkey,
            json_encode($en), //将数组转json格式的数据
        ]);
        $data['signaturePo']['signature'] = $signature;

        \Illuminate\Support\Facades\Log::channel('mytest')->info('sepro_withdrawalOrder',$data);
        $res = $this->requestService->postFormData(self::$withdrawUrl . 'otc/api/withdrawal', $data);
        \Illuminate\Support\Facades\Log::channel('mytest')->info('sepro_withdrawalOrder_rtn',$res);
        if($res['code'] != 0){
            $this->_msg = $res['message'];
            return false;
        }
        return  [
            'pltf_order_no' => '',
            'order_no' => $order_no
        ];
    }

    function rechargeCallback(Request $request)
    {
        \Illuminate\Support\Facades\Log::channel('mytest')->info('sepro_rechargeCallback',$request->post());

        if ($request->tradeResult != '1')  {
            $this->_msg = 'sepro-recharge-交易未完成';
            return false;
        }

        // 验证签名
        $params = $request->post();
        $sign = $params['sign'];
        unset($params['sign']);
        unset($params['signType']);
        if ($this->generateSign($params,1) <> $sign){
            $this->_msg = '签名错误';
            return false;
        }

        $where = [
            'order_no' => $request->mchOrderNo,
            'pltf_order_id' => $request->orderNo,
        ];

        return $where;
    }

    function withdrawalCallback(Request $request)
    {
        \Illuminate\Support\Facades\Log::channel('mytest')->info('sepro_withdrawalCallback',$request->post());

        $pay_status = 0;
        $status = (string)($request->tradeResult);
        if($status == '1'){
            $pay_status= 1;
        }
        if($status == '2' || $status == '3'){
            $pay_status = 3;
        }
        if ($pay_status == 0) {
            $this->_msg = 'sepro_withdrawal-交易未完成';
            return false;
        }
        // 验证签名
        $params = $request->post();
        $sign = $params['sign'];
        unset($params['sign']);
        unset($params['signType']);
        if ($this->generateSign($params,2) <> $sign) {
            $this->_msg = 'sepro_签名错误';
            return false;
        }
        $where = [
            'order_no' => $request->merTransferId,
            'plat_order_id' => $request->tradeNo,
            'pay_status' => $pay_status
        ];
        return $where;
    }
}

