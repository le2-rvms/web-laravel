<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\Iot\IotDeviceBinding;
use App\Models\Vehicle\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('轨迹数据')]
class GpsDataController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    /**
     * @throws ValidationException
     */
    #[PermissionAction(PermissionAction::READ)]
    public function history(Request $request, Vehicle $vehicle): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'db_start_at' => ['required', 'date'],
                'db_end_at'   => ['required', 'date', 'after:db_start_at'],
            ],
            [],
            trans_property(IotDeviceBinding::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request) {
                if (!$validator->failed()) {
                    $start = Carbon::parse($request->input('db_start_at'));
                    $end   = Carbon::parse($request->input('db_end_at'));

                    if ($end->gt($start->copy()->addDays(3))) {
                        $validator->errors()->add('db_end_at', '开始与结束的时间间隔不能超过 3 天。');
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $winRows = DB::connection()->select(
            <<<'SQL'
SELECT
  b.d_id,
  GREATEST(:db_start_at, b.db_start_at::timestamptz) AS start_ts,
  LEAST(:db_end_at, COALESCE(b.db_end_at, :db_end_at))     AS end_ts
FROM public.iot_device_bindings b
WHERE b.ve_id = :ve_id
  AND b.db_start_at::timestamptz < :db_start_at
  AND COALESCE(b.db_end_at, :db_end_at) > :db_start_at
  AND GREATEST(:db_start_at, b.db_start_at::timestamptz) < LEAST(:db_end_at, COALESCE(b.db_end_at, :db_end_at))
ORDER BY b.d_id, start_ts;
SQL,
            [
                've_id'       => $vehicle->ve_id,
                'db_start_at' => $input['db_start_at'],
                'db_end_at'   => $input['db_end_at'],
            ]
        );

        if (!$winRows) {
            throw ValidationException::withMessages([
                've_id' => '车辆在该时间段内无绑定信息',
            ]);
        }

        $json = json_encode($winRows);

        $results = DB::connection('pgsql-iot')->select(
            <<<'SQL'
WITH w AS (
  SELECT *
  FROM jsonb_to_recordset(?::jsonb)
       AS x(d_id bigint, start_ts timestamptz, end_ts timestamptz)
)
SELECT g.id, g.d_id, g.latitude, g.longitude, g.ts
FROM data.gps_datas g
JOIN w ON g.d_id = w.d_id
WHERE g.ts >= w.start_ts AND g.ts < w.end_ts
  AND g.ts >= (?::timestamptz) AND g.ts < (?::timestamptz)
ORDER BY g.ts;
SQL,
            [$json, $input['db_start_at'], $input['db_end_at']]
        );

        return $this->response()->withData($results)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
