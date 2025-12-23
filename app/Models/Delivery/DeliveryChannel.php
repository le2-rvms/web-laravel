<?php

namespace App\Models\Delivery;

use App\Enum\Delivery\DcKey;
use App\Enum\Delivery\DcProvider;
use App\Enum\Delivery\DcStatus;
use App\Enum\Delivery\DlSendStatus;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Sale\DtStatus;
use App\Enum\SaleContract\ScStatus;
use App\Enum\VehicleViolation\VvProcessStatus;
use App\Models\_\ModelTrait;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
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
 * @property int               $dc_id       消息类型ID
 * @property DcKey|string      $dc_key      消息类型KEY;不重复
 * @property string            $dc_title    消息类型标题
 * @property string            $dc_template 消息类型模板
 * @property int               $dc_tn       消息类型触发日期; =T-N
 * @property DcProvider|string $dc_provider 消息类型发送方式
 * @property DcStatus|int      $dc_status   消息类型状态
 */
class DeliveryChannel extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'dc_created_at';
    public const UPDATED_AT = 'dc_updated_at';
    public const UPDATED_BY = 'dc_updated_by';

    protected $primaryKey = 'dc_id';

    protected $guarded = ['dc_id'];

    protected $casts = [
        'dc_key'      => DcKey::class,
        'dc_provider' => DcProvider::class,
        'dc_status'   => DcStatus::class,
    ];

    protected $attributes = [];

    protected $appends = [
        'dc_key_label',
        'dc_provider_label',
        'dc_status_label',
    ];

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->from('delivery_channels', 'dc')
            ->orderByDesc('dc.dc_id')
            ->select('dc.*')
            ->addSelect(
                DB::raw(DcKey::toCaseSQL()),
                DB::raw(DcProvider::toCaseSQL()),
                DB::raw(DcStatus::toCaseSQL()),
                DB::raw(DcStatus::toColorSQL()),
                DB::raw(" ('T-' || dc.dc_tn ) as dc_tn_label"),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $value = DB::query()
            ->from('doc_tpls', 'dt')
            ->where('dt.dt_status', '=', DtStatus::ENABLED)
            ->when($where, $where)
            ->orderBy('dt.dt_id', 'desc')
            ->select(DB::raw("concat(dt_file_type,'|',dt_name,'→docx') as text,concat(dt.dt_id,'|docx') as value"))
            ->get()->toArray()
        ;

        return [$key => $value];
    }

    public function DeliveryLogs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class, 'dl_dc_id', 'dc_id');
    }

    public function make_payment(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        Payment::query()
            ->where('p_pay_status', '=', PPayStatus::UNPAID)
            ->where('p_is_valid', '=', PIsValid::VALID)
            ->whereBetween('p_should_pay_date', [$from, $to])
            ->whereHas('SaleContract', function (\Illuminate\Database\Eloquent\Builder $query) {
                $query->where('sc_status', '=', ScStatus::SIGNED);
            })
            ->orderby('p_id')
            ->with('SaleContract', 'SaleContract.Vehicle', 'SaleContract.SaleContractExt')
            ->chunk(100, function ($payments) {
                /** @var Payment $payment */
                foreach ($payments as $payment) {
                    $sce_wecom_group_url = $payment->SaleContract?->SaleContractExt?->sce_wecom_group_url;
                    if (!$sce_wecom_group_url) {
                        Log::channel('console')->info("因未设置sce_wecom_group_url,跳过{$this->dc_key}类型通知。", [$payment->SaleContract->se_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $payment->p_id;
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
                        'p_id'           => $payment->p_id,
                        'recipients_url' => $sce_wecom_group_url,
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

        SaleContract::query()
            ->where('sc_status', '=', ScStatus::SIGNED)
            ->whereBetween('sc_end_date', [$from, $to])
            ->orderby('sc_id')
            ->with(['Vehicle', 'SaleContractExt'])
            ->chunk(100, function ($saleContracts) {
                /** @var SaleContract $saleContract */
                foreach ($saleContracts as $saleContract) {
                    $sce_wecom_group_url = $saleContract->SaleContractExt?->sce_wecom_group_url;
                    if (!$sce_wecom_group_url) {
                        Log::channel('console')->info("因未设置sce_wecom_group_url,跳过{$this->dc_key}类型通知。", [$saleContract->sc_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $saleContract->sc_id;

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
                        'sc_id'          => $saleContract->sc_id,
                        'recipients_url' => $sce_wecom_group_url,
                        'content_title'  => $this->dc_title,
                        'content_body'   => Blade::render($this->dc_template, $saleContract),
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
            ->whereBetween('vi_compulsory_end_date', [$from, $to])
            ->orderBy('vi_id')
            ->with(['Vehicle.VehicleManager'])
            ->chunk(100, function ($vehicleInsurances) {
                /** @var array<VehicleInsurance> $vehicleInsurances */
                foreach ($vehicleInsurances as $vehicleInsurance) {
                    $a_wecom_name = $vehicleInsurance?->Vehicle?->VehicleManager?->a_wecom_name;
                    if (!$a_wecom_name) {
                        Log::channel('console')->info("a_wecom_name,跳过{$this->dc_key}类型通知。", [$vehicleInsurance?->Vehicle?->ve_plate_no, $vehicleInsurance?->Vehicle?->VehicleManager?->name]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $vehicleInsurance->vi_compulsory_plate_no.'|'.$vehicleInsurance->vi_compulsory_end_date;

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
                        'recipients'    => json_encode([$a_wecom_name]),
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
            ->whereBetween('vs_next_inspection_date', [$from, $to])
            ->orderBy('vs_id')
            ->with(['Vehicle'])
            ->chunk(100, function ($vehicleSchedules) {
                /** @var VehicleSchedule $vehicleSchedule */
                foreach ($vehicleSchedules as $vehicleSchedule) {
                    $a_wecom_name = $vehicleSchedule?->Vehicle?->VehicleManager?->a_wecom_name;
                    if (!$a_wecom_name) {
                        Log::channel('console')->info("a_wecom_name,跳过{$this->dc_key}类型通知。", [$vehicleSchedule?->Vehicle?->ve_plate_no, $vehicleSchedule?->Vehicle?->VehicleManager?->name]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $vehicleSchedule->vs_inspection_type.'|'.$vehicleSchedule->Vehicle->ve_plate_no.'|'.$vehicleSchedule->vs_next_inspection_date->format('Y-m-d');

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
                        'recipients'    => json_encode([$a_wecom_name]),
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
            ->where('vv_process_status', '=', VvProcessStatus::UNPROCESSED)
            ->whereBetween('vv_violation_datetime', [$from, $to])
            ->whereHas('VehicleUsage.SaleContract', function (\Illuminate\Database\Eloquent\Builder $q) {
                $q->where('sc_id', '>', '0');
            })
            ->orderBy('vv_id')
            ->with(['VehicleUsage.SaleContract.SaleContractExt'])
            ->chunk(100, function ($vehicleViolations) {
                /** @var VehicleViolation $vehicleViolation */
                foreach ($vehicleViolations as $vehicleViolation) {
                    $sce_wecom_group_url = $vehicleViolation?->VehicleUsage?->SaleContract?->SaleContractExt?->sce_wecom_group_url;
                    if (!$sce_wecom_group_url) {
                        Log::channel('console')->info("因未设置sce_wecom_group_url,跳过{$this->dc_key}类型通知。", [$vehicleViolation?->VehicleUsage?->SaleContract->sc_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $vehicleViolation->vv_plate_no.'|'.$vehicleViolation->vv_violation_datetime;

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
                        'recipients_url' => $sce_wecom_group_url,
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
