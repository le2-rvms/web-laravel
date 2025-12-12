<?php

namespace App\Http\Controllers\Admin\_;

use App\Enum\Statistics\Dimension;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StatisticsController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request)
    {
        $this->options(true);

        $validator = Validator::make(
            $request->all(),
            [
                'dimension' => ['nullable', 'string', Rule::in(Dimension::label_keys())],
            ],
            [],
        )->after(function (\Illuminate\Validation\Validator $validator) {
            if (!$validator->failed()) {
                // 获取当前的数据
                $data = $validator->getData();

                if (!isset($data['dimension'])) {
                    $data['dimension'] = Dimension::MONTH;

                    $validator->setData($data);
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();
        $this->response()->withOption($input);

        $endDate = Carbon::now();

        switch ($input['dimension']) {
            case Dimension::DAY:
                $startDate = $endDate->copy()->subDays(5)->format('Y-m-d');
                $format    = 'YYYY-MM-DD';

                break;

            case Dimension::WEEK:
                $startDate = $endDate->copy()->subWeeks(5)->startOfWeek()->format('Y-m-d');
                $format    = 'IYYY-IW';

                break;

            case Dimension::MONTH:
                $startDate = $endDate->copy()->subMonths(5)->format('Y-m-1');
                $format    = 'YYYY-MM';

                break;

            case Dimension::QUARTER:
                $startDate = $endDate->copy()->subQuarters(5)->format('Y-m-1');
                $format    = 'YYYY-"Q"Q';

                break;

            case Dimension::YEAR:
                $startDate = $endDate->copy()->subYears(5)->format('Y-1-1');
                $format    = 'YYYY';

                break;

            default:
        }

        $endDate = $endDate->format('Y-m-d');

        /** @var Admin $admin */
        $admin = auth()->user();

        $sql_array = [
            [
                'SaleContract::read',
                "SELECT to_char(signed_at, 'YYYY-MM') as period,count(1) as count,sum(total_rent_amount) as amount from sale_contracts where 1=1 GROUP BY 1 order by 1",
                function ($sql_value, &$result) {
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '租车数量', 'data' => array_column($sql_value, 'count')]],
                    ];

                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '租车金额', 'data' => array_column($sql_value, 'amount')]],
                    ];
                },
            ],

            [
                'SaleSettlement::read',
                "SELECT to_char(return_datetime, 'YYYY-MM') as period,count(1) as count from sale_settlements where 1=1 GROUP BY 1 order by 1",
                function ($sql_value, &$result) {
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '结算次数', 'data' => array_column($sql_value, 'count')]],
                    ];
                },
            ],
            [
                'Payment::read',
                "SELECT to_char(should_pay_date,'YYYY-MM') AS period,COUNT(1) AS count,SUM(CASE WHEN rp.should_pay_amount> 0 THEN rp.should_pay_amount ELSE 0 END) AS sum_amount,SUM(CASE WHEN rp.should_pay_amount< 0 THEN abs(rp.should_pay_amount) ELSE 0 END) AS sum_amount_refund,2=2 FROM payments rp WHERE rp.is_valid='1' and 1=1 GROUP BY 1 order by 1",
                function ($sql_value, &$result) {
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '计划收付款次数', 'data' => array_column($sql_value, 'count')]],
                    ];
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '计划收款金额', 'data' => array_column($sql_value, 'sum_amount')], ['name' => '付款金额', 'data' => array_column($sql_value, 'sum_amount_refund')]],
                    ];
                },
            ],
            [
                'Payment::read',
                "SELECT to_char(actual_pay_date,'YYYY-MM') AS period,COUNT(1) AS count,SUM(CASE WHEN rp.actual_pay_amount> 0 THEN rp.actual_pay_amount ELSE 0 END) AS sum_amount,SUM(CASE WHEN rp.actual_pay_amount< 0 THEN abs(rp.actual_pay_amount) ELSE 0 END) AS sum_amount_refund FROM payments rp WHERE rp.is_valid='1' and 1=1 GROUP BY 1 order by 1",
                function ($sql_value, &$result) {
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '实际收付款次数', 'data' => array_column($sql_value, 'count')]],
                    ];
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '实际收款金额', 'data' => array_column($sql_value, 'sum_amount')], ['name' => '付款金额', 'data' => array_column($sql_value, 'sum_amount_refund')]],
                    ];
                },
            ],
            [
                'VehicleInspection::read',
                "SELECT to_char(inspection_datetime, 'YYYY-MM') as period,count(1) as count from vehicle_inspections where 1=1 GROUP BY 1 order by 1",
                function ($sql_value, &$result) {
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '验车次数', 'data' => array_column($sql_value, 'count')]],
                    ];
                },
            ],
            [
                'VehicleRepair::read',
                "SELECT to_char(entry_datetime, 'YYYY-MM') as period,count(1) as count,sum(repair_cost) as amount from vehicle_repairs where 1=1 GROUP BY 1 order by 1",
                function ($sql_value, &$result) {
                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '维修数量', 'data' => array_column($sql_value, 'count')]],
                    ];

                    $result[] = [
                        'categories' => array_column($sql_value, 'period'),
                        'series'     => [['name' => '维修金额', 'data' => array_column($sql_value, 'amount')]],
                    ];
                },
            ],
        ];

        $result = [];
        foreach ($sql_array as [$permission,$sql,$sql_opt]) {
            if (!$admin->can($permission)) {
                continue;
            }

            preg_match('/to_char\((.+?),/', $sql, $rt);
            $column = $rt[1];

            if ($format instanceof \Closure) {
                $format_value = $format($column);
                $sql          = preg_replace('/to_char\((.+?),\s?\'(.+?)\'\)/', $format_value, $sql);
            } else {
                $sql = preg_replace('/to_char\((.+?),\s?\'(.+?)\'\)/', "to_char($1, '{$format}')", $sql);
            }

            $sql = preg_replace('/1=1/', "{$column} between DATE '{$startDate}' and DATE '{$endDate}' ", $sql);

            $sql_value = DB::select($sql);
            $sql_opt($sql_value, $result);
        }

        return $this->response()->withData($result)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            Dimension::options(),
        );
    }
}
