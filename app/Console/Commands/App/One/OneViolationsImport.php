<?php

namespace App\Console\Commands\App\One;

use App\Models\One\OneRequest;
use App\Models\Vehicle\VehicleViolation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:one-violations:import',
    description: '从 vehicle_122_requests 表中获取违章信息并写入 vehicle_violations 表'
)]
class OneViolationsImport extends Command
{
    protected $signature   = '_app:one-violations:import {--turn=}';
    protected $description = '从 vehicle_122_requests 表中获取违章信息并写入 vehicle_violations 表';

    public function handle(): int
    {
        $this->info('开始导入违章信息...');

        $turn = $this->option('turn') ?: OneRequest::query()->max('or_turn');

        if (!$turn) {
            $this->info('vehicle_122_requests 表中没有 turn 数据。');

            return CommandAlias::SUCCESS;
        }

        $this->info("最大 turn 日期为: {$turn}");

        /** @var Collection<OneRequest> $requests */
        $requests = OneRequest::query()->where('or_status_code', '=', '200')
            ->where('or_turn', $turn)
            ->where('or_key', 'like', 'violation,%')
            ->get()
        ;

        if ($requests->isEmpty()) {
            $this->info('没有需要处理的请求。');

            return CommandAlias::SUCCESS;
        }

        DB::transaction(function () use ($requests) {
            foreach ($requests as $request) {
                $response = $request->response;

                if (!$response || 200 != $response['code']) {
                    continue;
                }

                $violations = $response['data']['content'] ?? [];

                $violationsToUpsert = [];

                foreach ($violations as $violation) {
                    // 准备 upsert 数据
                    $violationsToUpsert[] = [
                        'vv_decision_number'    => $violation['xh'] ?? $violation['jdsbh'],
                        'vv_plate_no'           => $violation['hphm'],
                        'vv_vu_id'              => null, // 根据需要填充
                        'vv_violation_datetime' => Carbon::parse($violation['wfsj']),
                        'vv_violation_content'  => $violation['wfms'] ?? '',
                        'vv_location'           => $violation['wfdz'],
                        'vv_fine_amount'        => floatval($violation['fkje']),
                        'vv_penalty_points'     => intval($violation['wfjfs']),
                        'vv_process_status'     => $violation['clbj'] ?? -1,
                        'vv_payment_status'     => $violation['jkbj'],
                        'vv_response'           => json_encode($violation),
                        'vv_code'               => $violation['wfxw'],
                    ];
                }

                if (!empty($violationsToUpsert)) {
                    $plate_no_array = array_unique(array_column($violationsToUpsert, 'vv_plate_no'));
                    if (count($plate_no_array) > 1) {
                        throw new \Exception('plate_no err.');
                    }

                    // 使用 upsert 插入或更新违章记录
                    $violationsToUpsertAffectRows = VehicleViolation::query()->upsert(
                        $violationsToUpsert,
                        ['vv_decision_number'], // 唯一键
                        [ // 需要更新的字段
                            'vv_plate_no',
                            'vv_vu_id',
                            'vv_violation_datetime',
                            'vv_violation_content',
                            'vv_location',
                            'vv_fine_amount',
                            'vv_penalty_points',
                            'vv_process_status',
                            'vv_payment_status',
                            'vv_response',
                            'vv_code',
                        ]
                    );

                    $this->info("成功 upsert {$violationsToUpsertAffectRows} 条违章记录。");
                } else {
                    $this->info('没有新的违章记录需要 upsert。');
                }
            }
        });

        $this->info('违章信息导入完成。');

        return CommandAlias::SUCCESS;
    }
}
