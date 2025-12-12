<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Sale\DtDtFileType;
use App\Enum\Sale\DtDtStatus;
use App\Enum\Sale\DtDtType;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('生成合同文件模板')]
/**
 * @property int                 $dt_id        文件模板序号
 * @property DtDtType|string     $dt_type      文件模板类型
 * @property DtDtFileType|string $dt_file_type 文件模板格式类型
 * @property string              $dt_name      文件模板名称
 * @property DtDtStatus|int      $dt_status    文件模板状态
 * @property array               $dt_file      文件模板文件
 * @property string              $dt_html      文件模板HTML
 * @property null|string         $dt_remark    文件模板备注
 */
class DocTpl extends Model
{
    use ModelTrait;

    protected $primaryKey = 'dt_id';

    protected $guarded = ['dt_id'];

    protected $casts = [
        'dt_type'      => DtDtType::class,
        'dt_file_type' => DtDtFileType::class,
        'dt_status'    => DtDtStatus::class,
    ];

    protected $attributes = [];

    protected $appends = [
        'dt_type_label',
        'dt_file_type_label',
        'dt_status_label',
        //        'dt_file',
    ];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('doc_tpls', 'dt')
            ->orderByDesc('dt.dt_id')
            ->select('dt.*')
            ->addSelect(
                DB::raw(DtDtType::toCaseSQL()),
                DB::raw(DtDtFileType::toCaseSQL()),
                DB::raw(DtDtStatus::toCaseSQL()),
                DB::raw(DtDtStatus::toColorSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Options';

        $value1 = DB::query()
            ->from('doc_tpls', 'dt')
            ->where('dt.dt_status', '=', DtDtStatus::ENABLED)
            ->when($where, $where)
            ->orderBy('dt.dt_id', 'desc')
            ->select(DB::raw("concat(dt_file_type,'|',dt_name,'→docx') as text,concat(dt.dt_id,'|docx') as value"))
            ->get()->toArray()
        ;

        $value2 = DB::query()
            ->from('doc_tpls', 'dt')
            ->where('dt.dt_status', '=', DtDtStatus::ENABLED)
            ->when($where, $where)
            ->orderBy('dt.dt_id', 'desc')
            ->select(DB::raw("concat(dt_file_type,'|',dt_name,'→pdf') as text,concat(dt.dt_id,'|pdf') as value"))
            ->get()->toArray()
        ;

        return [$key => array_merge($value1, $value2)];
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
