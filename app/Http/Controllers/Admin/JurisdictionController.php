<?php


namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Services\Admin\JurisdictionService;
use Illuminate\Http\Request;

class JurisdictionController extends Controller
{
    protected $JurisdictionService;

    public function __construct(JurisdictionService $JurisdictionService)
    {
        $this->JurisdictionService = $JurisdictionService;
    }

    public function FindAll()
    {
        $data = $this->JurisdictionService->FindAll();
        return json_encode([
            "code" => 200,
            "msg" => "查询成功",
            "data" => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    public function Add(Request $request)
    {
        if ($this->JurisdictionService->Add($request->post())) {
            return json_encode([
                "code" => 200,
                "msg" => "添加成功"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            return json_encode([
                "code" => 402,
                "msg" => "添加失败"
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function FindById(Request $request)
    {
        $data = $this->JurisdictionService->FindById($request->input("id"));
        return json_encode([
            "code" => 200,
            "msg" => "查询成功",
            "data" => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    public function Edit(Request $request)
    {
        if ($this->JurisdictionService->Edit($request->post())) {
            return json_encode([
                "code" => 200,
                "msg" => "编辑成功"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            return json_encode([
                "code" => 402,
                "msg" => "编辑失败"
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function RightAll()
    {
        $data = $this->JurisdictionService->RightAll();
        return json_encode([
            "code" => 200,
            "msg" => "查询成功",
            "data" => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    public function All()
    {
        $data = $this->JurisdictionService->FindRightAll();
        return json_encode([
            "code" => 200,
            "msg" => "查询成功",
            "data" => $data
        ], JSON_UNESCAPED_UNICODE);
    }
}