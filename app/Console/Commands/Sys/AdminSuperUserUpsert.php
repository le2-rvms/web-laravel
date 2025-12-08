<?php

namespace App\Console\Commands\Sys;

use App\Enum\Admin\AdmUserType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_sys:admin-super-user:upsert',
    description: 'Create or update super-admin user from config/setting.php'
)]
class AdminSuperUserUpsert extends Command
{
    protected $signature   = '_sys:admin-super-user:upsert';
    protected $description = 'Create or update super-admin user from config/setting.php';

    public function handle(): int
    {
        // 一次性拉取并设默认
        [$email, $name, $password, $roleName] = [
            config('setting.super_user.email'),
            config('setting.super_user.name'),
            config('setting.super_user.password'),
            config('setting.super_role.name'),
        ];

        // 必填校验
        if (!$email || !$name || !$roleName) {
            $this->error('请在 .env 设置 SUPER_USER_EMAIL、SUPER_USER_NAME、SUPER_USER_PASSWORD、SUPER_ROLE_NAME，并确认 config/setting.php 已读取。');

            return CommandAlias::FAILURE;
        }

        if (!$password) {
            $password = Str::random(12);
        }

        // 创建或更新角色
        $role = AdminRole::query()->updateOrCreate(
            ['name' => $roleName],
            ['guard_name' => config('auth.defaults.guard')]
        );

        // 创建或更新管理员并同步角色
        DB::transaction(function () use ($email, $name, $password, $role) {
            Admin::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name'                 => $name,
                    'password'             => Hash::make($password),
                    'password_verified_at' => now(),
                    'user_type'            => AdmUserType::TEMP,
                    'expires_at'           => Carbon::now()->addDays(3),
                ]
            )
                ->syncRoles([$role])
            ;
        });

        // 输出最终信息（包括生成或提供的密码）
        $this->info("Super-admin 已同步: Email={$email}, Name={$name}, Password={$password}, Role={$roleName}");

        return CommandAlias::SUCCESS;
    }
}
