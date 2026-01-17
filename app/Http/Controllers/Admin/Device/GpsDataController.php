<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\Iot\GpsDevice;
use App\Models\Iot\GpsDeviceLastPosition;
use App\Models\Iot\IotDeviceBinding;
use App\Models\Vehicle\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    #[PermissionAction(PermissionAction::READ)]
    public function latest(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'device_ids'   => ['array'],
                'device_ids.*' => ['string', 'max:64', Rule::exists(GpsDevice::class, 'terminal_id')],
            ]
        )->validate();

        $query = GpsDeviceLastPosition::query();

        $companyId = config('app.company_id');
        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        if (!empty($input['device_ids'])) {
            $query->whereIn('terminal_id', $input['device_ids']);
        }

        $positions = $query->get()->map(function (GpsDeviceLastPosition $row) {
            $timestamp = $row->gps_time;

            try {
                $timestamp = Carbon::parse($row->gps_time, 'UTC')->toIso8601String();
            } catch (\Throwable) {
            }

            return [
                'terminal_id' => $row->terminal_id,
                'ts'          => $timestamp,
                'latitude'    => null !== $row->latitude_gcj ? (float) $row->latitude_gcj : null,
                'longitude'   => null !== $row->longitude_gcj ? (float) $row->longitude_gcj : null,
                'coord_sys'   => 'GCJ02',
                'source'      => 'snapshot',
            ];
        });

        return $this->response()->withData($positions)->respond();
    }

    /**
     * @throws ValidationException
     */
    #[PermissionAction(PermissionAction::READ)]
    public function history_vehicle(Request $request, Vehicle $vehicle): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'db_start_at' => ['required', 'date'],
                'db_end_at'   => ['required', 'date', 'after:db_start_at'],
            ],
            [],
            trans_property(IotDeviceBinding::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request) {
                if ($validator->failed()) {
                    return;
                }

                $start = Carbon::parse($request->input('db_start_at'));
                $end   = Carbon::parse($request->input('db_end_at'));

                // 限制查询窗口，避免扫描过大轨迹区间。
                if ($end->gt($start->copy()->addMonths(2))) {
                    $validator->errors()->add('db_end_at', '开始与结束的时间间隔不能超过2个月。');
                }
            })
            ->validate()
        ;

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
            // 该时间段内没有设备绑定记录。
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

    /**
     * @throws ValidationException
     */
    #[PermissionAction(PermissionAction::READ)]
    public function history_device(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'db_d_code'   => ['required', 'string', Rule::exists(GpsDevice::class, 'terminal_id')],
                'db_start_at' => ['required', 'date'],
                'db_end_at'   => ['required', 'date', 'after:db_start_at'],
            ],
            [],
            trans_property(IotDeviceBinding::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request) {
                if ($validator->failed()) {
                    return;
                }

                $start = Carbon::parse($request->input('db_start_at'));
                $end   = Carbon::parse($request->input('db_end_at'));

                // 限制查询窗口，避免扫描过大轨迹区间。
                if ($end->gt($start->copy()->addMonths(2))) {
                    $validator->errors()->add('db_end_at', '开始与结束的时间间隔不能超过2个月。');
                }
            })
            ->validate()
        ;

        /** @var GpsDevice $gpsDevice */
        $gpsDevice = GpsDevice::query()->where('terminal_id', $input['db_d_code'])->first();

        $results = DB::connection('pgsql-iot')->select(
            <<<'SQL'
SELECT device_id, to_char(gps_time, 'YYYY-MM-DD HH24:MI:SS') as gps_time,latitude_gcj as latitude,longitude_gcj as longitude,altitude,direction,speed
FROM public.gps_position_histories ph
WHERE
    device_id = :device_id
  AND ph.gps_time >= :start_at AND ph.gps_time < :end_at
ORDER BY ph.gps_time;
SQL,
            [
                'device_id' => $gpsDevice->id,
                'start_at'  => $input['db_start_at'],
                'end_at'    => $input['db_end_at'],
            ]
        );

        return $this->response()->withData($results)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
