<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Sale\DtFileType;
use App\Enum\Sale\DtStatus;
use App\Enum\Sale\DtType;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[ClassName('生成合同文件模板')]
/**
 * @property int               $dt_id        文件模板序号
 * @property DtType|string     $dt_type      文件模板类型
 * @property DtFileType|string $dt_file_type 文件模板格式类型
 * @property string            $dt_name      文件模板名称
 * @property DtStatus|int      $dt_status    文件模板状态
 * @property array             $dt_file      文件模板文件
 * @property string            $dt_html      文件模板HTML
 * @property null|string       $dt_remark    文件模板备注
 */
class DocTpl extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'dt_created_at';
    public const UPDATED_AT = 'dt_updated_at';
    public const UPDATED_BY = 'dt_updated_by';

    protected $primaryKey = 'dt_id';

    protected $guarded = ['dt_id'];

    protected $casts = [
        'dt_type'      => DtType::class,
        'dt_file_type' => DtFileType::class,
        'dt_status'    => DtStatus::class,
    ];

    protected $attributes = [];

    protected $appends = [
        'dt_type_label',
        'dt_file_type_label',
        'dt_status_label',
        //        'dt_file',
    ];

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('doc_tpls', 'dt')
            ->orderByDesc('dt.dt_id')
            ->select('dt.*')
            ->addSelect(
                DB::raw(DtType::toCaseSQL()),
                DB::raw(DtFileType::toCaseSQL()),
                DB::raw(DtStatus::toCaseSQL()),
                DB::raw(DtStatus::toColorSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        $value1 = static::query()
            ->from('doc_tpls', 'dt')
            ->where('dt.dt_status', '=', DtStatus::ENABLED)
            ->when($where, $where)
            ->orderBy('dt.dt_id', 'desc')
            ->select(DB::raw("concat(dt_file_type,'|',dt_name,'→docx') as text,concat(dt.dt_id,'|docx') as value"))
            ->get()->toArray()
        ;

        $value2 = static::query()
            ->from('doc_tpls', 'dt')
            ->where('dt.dt_status', '=', DtStatus::ENABLED)
            ->when($where, $where)
            ->orderBy('dt.dt_id', 'desc')
            ->select(DB::raw("concat(dt_file_type,'|',dt_name,'→pdf') as text,concat(dt.dt_id,'|pdf') as value"))
            ->get()->toArray()
        ;

        return [$key => array_merge($value1, $value2)];
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function dtTypeLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('dt_type')?->label
        );
    }

    protected function dtFileTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('dt_file_type')?->label ?? null
        );
    }

    protected function dtStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('dt_status')?->label ?? null
        );
    }

    protected function dtFile(): Attribute
    {
        return $this->uploadFile();
    }
}
