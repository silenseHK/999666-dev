<?php


namespace App\Repositories\Admin\agent;


use App\Models\Cx_User_Bank;

class AgentBankCardRepository
{

    protected $Cx_User_Bank;

    public function __construct(Cx_User_Bank $cx_User_Bank){
        $this->Cx_User_Bank = $cx_User_Bank;
    }

    public function getBackCardList(){

    }

}