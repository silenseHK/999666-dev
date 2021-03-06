<?php


namespace App\Services\Admin\agent;


use App\Repositories\Admin\agent\AgentUserRepository;
use App\Services\BaseService;

class BaseAgentService extends BaseService
{

    protected $admin_id;

    protected $admin;

    /**
     * @var AgentUserRepository
     */
    protected $AgentUserRepository;

    protected function getAdmin(){
        $this->admin_id = request()->get('admin_id');
        $this->admin = $this->AgentUserRepository->getAdminUserId($this->admin_id);
    }

    public function getRelationWhere($user_id){
        return ['invite_relation' => ['like', "%-{$user_id}-%"]];
    }

}
