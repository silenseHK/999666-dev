<?php


namespace App\Services\Admin;


use App\Repositories\Admin\BettingRepository;
use App\Services\BaseService;

class BettingService extends BaseService
{
    private $BettingRepository;

    public function __construct(BettingRepository $bettingRepository)
    {
        $this->BettingRepository = $bettingRepository;
    }

    public function findAll($page, $limit)
    {
        $list = $this->BettingRepository->findAll(($page - 1) * $limit, $limit);
        $total = $this->BettingRepository->countAll();
        $this->_data = ["total" => $total, "list" => $list];
    }

    /**
     * 获取最新的数据
     */
    public function getNewest()
    {
        return $this->BettingRepository->getNewest();
    }

    public function searchBettingLogs($data)
    {
        $page = $data["page"];
        $limit = $data["limit"];
        $offset = ($page - 1) * $limit;
        $data = $this->assemblyParameters($data);
        $list = $this->BettingRepository->searchBettingLogs($data, $offset, $limit);
        $total = $this->BettingRepository->countSearchBettingLogs($data);
        $this->_data = ["total" => $total, "list" => $list];

    }

    public function statisticsBettingLogs()
    {
        $this->_data["betting_count"] = $this->BettingRepository->countAll();
        $this->_data["betting_money"] = $this->BettingRepository->sumAll("money");
        $this->_data["service_charge"] = $this->BettingRepository->sumAll("service_charge");
        $this->_data["win_money"] = $this->BettingRepository->sumAll("win_money");
    }

    public function assemblyParameters($data)
    {
        if (!array_key_exists("conditions", $data)) {
            return $data;
        }
        if (array_key_exists("selection", $data["conditions"])) {
            $data["conditions"]["game_c_x_id"] = $this->BettingRepository->findPlayIds($data["conditions"]["selection"]);
            $data["ops"]["game_c_x_id"] = "in";
            unset($data["conditions"]["selection"]);
            unset($data["ops"]["selection"]);
        }

        if (array_key_exists("number", $data["conditions"])) {
            $data["conditions"]["game_p_id"] = $this->BettingRepository->findNumberId($data["conditions"]["number"]);
            $data["ops"]["game_p_id"] = "in";
            unset($data["conditions"]["number"]);
            unset($data["ops"]["number"]);
        }

        if (array_key_exists("phone", $data["conditions"])) {
            $data["conditions"]["user_id"] = $this->BettingRepository->findUserId($data["conditions"]["phone"]);
            $data["ops"]["user_id"] = "=";
            unset($data["conditions"]["phone"]);
            unset($data["ops"]["phone"]);
        }
        return $data;
    }

    /**
     * @param $type
     * @return mixed
     */
    public function statistics($type)
    {
        $this->_data = $this->BettingRepository->statistics($type);
    }

    /**
     * 下注提醒用户列表
     */
    public function noticeList()
    {
        $size = $this->sizeInput();
        $user_id = $this->intInput('user_id');
        $phone = $this->strInput('phone');
        $where =
            [
                'is_betting_notice' => ['=', 1]
            ];
        if($user_id)
            $where['id'] = ['=', $user_id];
        if($phone)
            $where['phone'] = ['=', $phone];
        $this->_data = $this->BettingRepository->noticeList($where, $size);
    }

    public function noticeBettingList()
    {
        $size = $this->sizeInput();
        $sort = $this->intInput('sort');
        $user_id = $this->intInput('user_id');
        $type = $this->intInput('type');

        $where =
            [
                'user_id' => ['=', $user_id]
            ];
        if($type)
            $where['game_id'] = ['=', $type];
        $this->_data = $this->BettingRepository->noticeBettingList($where, $sort, $size);
//        $this->_data = $this->BettingRepository->noticeBettingList($sort, $size);
    }

}
