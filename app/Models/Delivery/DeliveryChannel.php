<?php

namespace App\Models\Delivery;

use App\Enum\Delivery\DcDcKey;
use App\Enum\Delivery\DcDcProvider;
use App\Enum\Delivery\DcDcStatus;
use App\Enum\Delivery\DlSendStatus;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Sale\DtDtStatus;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Vehicle\VvProcessStatus;
use App\Models\_\ModelTrait;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleOrder;
use App\Models\Vehicle\VehicleInsurance;
use App\Models\Vehicle\VehicleSchedule;
use App\Models\Vehicle\VehicleViolation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @property int                 $dc_id       消息类型ID
 * @property DcDcKey|string      $dc_key      消息类型KEY;不重复
 * @property string              $dc_title    消息类型标题
 * @property string              $dc_template 消息类型模板
 * @property int                 $dc_tn       消息类型触发日期; =T-N
 * @property DcDcProvider|string $dc_provider 消息类型发送方式
 * @property DcDcStatus|int      $dc_status   消息类型状态
 */
class DeliveryChannel extends Model
{
    use ModelTrait;

    protected $primaryKey = 'dc_id';

    protected $guarded = ['dc_id'];

    protected $casts = [
        'dc_key'      => DcDcKey::class,
        'dc_provider' => DcDcProvider::class,
        'dc_status'   => DcDcStatus::class,
    ];

    protected $attributes = [];

    protected $appends = [
        'dc_key_label',
        'dc_provider_label',
        'dc_status_label',
    ];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('delivery_channels', 'dc')
            ->orderByDesc('dc.dc_id')
            ->select('dc.*')
            ->addSelect(
                DB::raw(DcDcKey::toCaseSQL()),
                DB::raw(DcDcProvider::toCaseSQL()),
                DB::raw(DcDcStatus::toCaseSQL()),
                DB::raw(DcDcStatus::toColorSQL()),
                DB::raw(" ('T-' || dc.dc_tn ) as dc_tn_label"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Options';

        $value = DB::query()
            ->from('doc_tpls', 'dt')
            ->where('dt.dt_status', '=', DtDtStatus::ENABLED)
            ->when($where, $where)
            ->orderBy('dt.dt_id', 'desc')
            ->select(DB::raw("concat(dt_file_type,'|',dt_name,'→docx') as text,concat(dt.dt_id,'|docx') as value"))
            ->get()->toArray()
        ;

        return [$key => $value];
    }

    public function DeliveryLogs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class);
    }

    public function make_payment(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        Payment::query()
            ->where('pay_status', '=', RpPayStatus::UNPAID)
            ->where('is_valid', '=', RpIsValid::VALID)
            ->whereBetween('should_pay_date', [$from, $to])
            ->whereHas('SaleOrder', function (\Illuminate\Database\Eloquent\Builder $query) {
                $query->where('order_status', '=', SoOrderStatus::SIGNED);
            })
            ->orderby('rp_id')
            ->with('SaleOrder', 'SaleOrder.Vehicle', 'SaleOrder.SaleOrderExt')
            ->chunk(100, function ($payments) {
                /** @var Payment $payment */
                foreach ($payments as $payment) {
                    $soe_wecom_group_url = $payment->SaleOrder?->SaleOrderExt?->soe_wecom_group_url;
                    if (!$soe_wecom_group_url) {
                        Log::channel('console')->info("因未设置soe_wecom_group_url,跳过{$this->dc_key}类型通知。", [$payment->SaleOrder->so_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $payment->rp_id;
                    $exist  = DeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }

                    DeliveryLog::query()->insert([
                        'dc_key'         => $dc_key,
                        'dc_tn'          => $dc_tn,
                        'dl_key'         => $dl_key,
                        'rp_id'          => $payment->rp_id,
                        'recipients_url' => $soe_wecom_group_url,
                        'content_title'  => $this->dc_title,
                        'content_body'   => Blade::render($this->dc_template, $payment),
                        'send_status'    => DlSendStatus::ST_PENDING,
                        'send_attempt'   => '0',
                        'scheduled_for'  => now(),
                    ]);
                }
            })
        ;
    }

    public function make_settlement(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        SaleOrder::query()
            ->where('order_status', '=', SoOrderStatus::SIGNED)
            ->whereBetween('rental_end', [$from, $to])
            ->orderby('so_id')
            ->with(['Vehicle', 'SaleOrderExt'])
            ->chunk(100, function ($saleOrders) {
                /** @var SaleOrder $saleOrder */
                foreach ($saleOrders as $saleOrder) {
                    $soe_wecom_group_url = $saleOrder->SaleOrderExt?->soe_wecom_group_url;
                    if (!$soe_wecom_group_url) {
                        Log::channel('console')->info("因未设置soe_wecom_group_url,跳过{$this->dc_key}类型通知。", [$saleOrder->so_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $saleOrder->so_id;

                    $exist = DeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }

                    DeliveryLog::query()->insert([
                        'dc_key'         => $dc_key,
                        'dc_tn'          => $dc_tn,
                        'dl_key'         => $dl_key,
                        'so_id'          => $saleOrder->so_id,
                        'recipients_url' => $soe_wecom_group_url,
                        'content_title'  => $this->dc_title,
                        'content_body'   => Blade::render($this->dc_template, $saleOrder),
                        'send_status'    => DlSendStatus::ST_PENDING,
                        'send_attempt'   => '0',
                        'scheduled_for'  => now(),
                    ]);
                }
            })
        ;
    }

    public function make_vehicle_insurance(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        VehicleInsurance::query()
            ->whereBetween('compulsory_end_date', [$from, $to])
            ->orderBy('vi_id')
            ->with(['Vehicle.VehicleManager'])
            ->chunk(100, function ($vehicleInsurances) {
                /** @var array<VehicleInsurance> $vehicleInsurances */
                foreach ($vehicleInsurances as $vehicleInsurance) {
                    $wecom_name = $vehicleInsurance?->Vehicle?->VehicleManager?->wecom_name;
                    if (!$wecom_name) {
                        Log::channel('console')->info("wecom_name,跳过{$this->dc_key}类型通知。", [$vehicleInsurance?->Vehicle?->plate_no, $vehicleInsurance?->Vehicle?->VehicleManager?->name]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $vehicleInsurance->compulsory_plate_no.'|'.$vehicleInsurance->compulsory_end_date;

                    $exist = DeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }

                    DeliveryLog::query()->insert([
                        'dc_key'        => $dc_key,
                        'dc_tn'         => $dc_tn,
                        'dl_key'        => $dl_key,
                        'vi_id'         => $vehicleInsurance->vi_id,
                        'recipients'    => json_encode([$wecom_name]),
                        'content_title' => $this->dc_title,
                        'content_body'  => Blade::render($this->dc_template, $vehicleInsurance),
                        'send_status'   => DlSendStatus::ST_PENDING,
                        'send_attempt'  => '0',
                        'scheduled_for' => now(),
                    ]);
                }
            })
        ;
    }

    public function make_vehicle_schedule(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        VehicleSchedule::query()
            ->whereBetween('next_inspection_date', [$from, $to])
            ->orderBy('vs_id')
            ->with(['Vehicle'])
            ->chunk(100, function ($vehicleSchedules) {
                /** @var VehicleSchedule $vehicleSchedule */
                foreach ($vehicleSchedules as $vehicleSchedule) {
                    $wecom_name = $vehicleSchedule?->Vehicle?->VehicleManager?->wecom_name;
                    if (!$wecom_name) {
                        Log::channel('console')->info("wecom_name,跳过{$this->dc_key}类型通知。", [$vehicleSchedule?->Vehicle?->plate_no, $vehicleSchedule?->Vehicle?->VehicleManager?->name]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $vehicleSchedule->inspection_type.'|'.$vehicleSchedule->Vehicle->plate_no.'|'.$vehicleSchedule->next_inspection_date->format('Y-m-d');

                    $exist = DeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }
                    DeliveryLog::query()->insert([
                        'dc_key'        => $dc_key,
                        'dc_tn'         => $dc_tn,
                        'dl_key'        => $dl_key,
                        'vs_id'         => $vehicleSchedule->vs_id,
                        'recipients'    => json_encode([$wecom_name]),
                        'content_title' => $this->dc_title,
                        'content_body'  => Blade::render($this->dc_template, $vehicleSchedule),
                        'send_status'   => DlSendStatus::ST_PENDING,
                        'send_attempt'  => '0',
                        'scheduled_for' => now(),
                    ]);
                }
            })
        ;
    }

    public function make_vehicle_violation(): void
    {
        $from = Carbon::now()->subDays(3000)->format('Y-m-d');
        $to   = Carbon::now()->format('Y-m-d');

        VehicleViolation::query()
            ->where('process_status', '=', VvProcessStatus::UNPROCESSED)
            ->whereBetween('violation_datetime', [$from, $to])
            ->whereHas('VehicleUsage.SaleOrder', function (\Illuminate\Database\Eloquent\Builder $q) {
                $q->where('so_id', '>', '0');
            })
            ->orderBy('vv_id')
            ->with(['VehicleUsage.SaleOrder.SaleOrderExt'])
            ->chunk(100, function ($vehicleViolations) {
                /** @var VehicleViolation $vehicleViolation */
                foreach ($vehicleViolations as $vehicleViolation) {
                    $soe_wecom_group_url = $vehicleViolation?->VehicleUsage?->SaleOrder?->SaleOrderExt?->soe_wecom_group_url;
                    if (!$soe_wecom_group_url) {
                        Log::channel('console')->info("因未设置soe_wecom_group_url,跳过{$this->dc_key}类型通知。", [$vehicleViolation?->VehicleUsage?->SaleOrder->so_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $vehicleViolation->plate_no.'|'.$vehicleViolation->violation_datetime;

                    $exist = DeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;

                    if ($exist) {
                        continue;
                    }

                    DeliveryLog::query()->insert([
                        'dc_key'         => $dc_key,
                        'dc_tn'          => $dc_tn,
                        'dl_key'         => $dl_key,
                        'vv_id'          => $vehicleViolation->vv_id,
                        'recipients_url' => $soe_wecom_group_url,
                        'content_title'  => $this->dc_title,
                        'content_body'   => Blade::render($this->dc_template, $vehicleViolation),
                        'send_status'    => DlSendStatus::ST_PENDING,
                        'send_attempt'   => '0',
                        'scheduled_for'  => now(),
                    ]);
                }
            })
        ;
    }

    protected function dcKeyLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('dc_key')?->label
        );
    }

    protected function dcProviderLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('dc_provider')?->label ?? null
        );
    }

    protected function dcStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('dc_status')?->label ?? null
        );
    }
}
