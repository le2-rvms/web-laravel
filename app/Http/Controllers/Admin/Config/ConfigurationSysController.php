<?php

namespace App\Http\Controllers\Admin\Config;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Config\CfgUsageCategory;
use App\Models\_\Configuration;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('系统设定值')]
class ConfigurationSysController extends ConfigurationController
{
    public function __construct()
    {
        // 系统配置使用 SYSTEM 分类。
        $this->usageCategory = CfgUsageCategory::SYSTEM;

        parent::__construct();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        // 仅补充权限控制，复用基类配置列表逻辑。
        return parent::index($request);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, Configuration $configuration): Response
    {
        return parent::edit($request, $configuration);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function editConfirm(Request $request, Configuration $configuration): Response
    {
        return parent::editConfirm($request, $configuration);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, Configuration $configuration): Response
    {
        return parent::update($request, $configuration);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(Configuration $configuration): Response
    {
        return parent::destroy($configuration);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        return parent::create($request);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function createConfirm(Request $request): View
    {
        return parent::createConfirm($request);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return parent::store($request);
    }
}
