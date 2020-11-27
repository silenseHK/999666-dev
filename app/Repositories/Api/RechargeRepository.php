<?php


namespace App\Repositories\Api;


use App\Models\Cx_User_Recharge_Log;
use App\Mongodb;

class RechargeRepository
{
    protected $cx_User_Recharge_Log;


    public $_data = [];

    public function __construct(
        Cx_User_Recharge_Log $cx_User_Recharge_Log
    )
    {
        $this->cx_User_Recharge_Log = $cx_User_Recharge_Log;
    }

    /**
     *  添加充值记录
     */
    public function addRechargeLog(object $user, $money, $order_no, $pay_type, $pltf_order_id = '',
                                   $native_url = '', $verify_money = '', $match_code = '',$sign='')
    {
        $data = [
            'is_first_recharge' => $user->is_first_recharge,
            'user_id' => $user->id,
            'phone' => $user->phone,
            'nickname' => $user->nickname,
            'money' => $money,
            'order_no' => $order_no,
            'status' => 1,
            'time' => time(),
            'dq_balance' => $user->balance,
            'wc_balance' => bcadd($user->balance, $money, 2),
            'pay_company' => '',
            'pay_type' => $pay_type,
//            'msg' => $msg,

            'sign' => $sign,
            'pltf_order_id' => $pltf_order_id,
            'native_url' => $native_url,
            'verify_money' => $verify_money,
            'match_code' => $match_code,
            'expire' => 600,
        ];
        $this->cx_User_Recharge_Log->insert($data);
    }

    /**
     *  获取充值记录
     */
    public function getRechargeLogs($status = 1, $limit = 10, $page = 1)
    {
       return $this->cx_User_Recharge_Log->where('status', $status)->orderBy('time', 'desc')
           ->select('order_no','time','money','status','native_url')
           ->paginate($limit, ['*'], 'page', $page)->getCollection();
    }

    /**
     * 根据订单号查询充值记录
     */
    public function findRechargeLogByOrderNo($order_no)
    {
        return $this->cx_User_Recharge_Log->where('order_no', $order_no)->first();
    }

    /**
     * 根据条件查询充值信息
     */
    public function getRechargeInfoByCondition(array $where)
    {
        return $this->cx_User_Recharge_Log->where($where)->first();
    }

    /**
     * 更新充值记录状态
     */
    public function updateRechargeLog(object $rechargeLog, $status = 2,$money)
    {
        $rechargeLog->arrive_time = time();
        $rechargeLog->arrive_money = $money;
        $rechargeLog->status = $status;
        $rechargeLog->save();
    }

}
