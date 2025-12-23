<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Enum\Admin\AtStatus;
use App\Enum\Admin\AUserType;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[ClassName('车队')]
#[ColumnDesc('at_name', )]
/**
 * @property int             $at_id        序号
 * @property int             $at_parent_id 上层序号
 * @property string          $at_name      车队名称
 * @property AtStatus|string $at_status    车队状态
 * @property int             $at_sort      排序
 * @property null|string     $at_remark    备注
 * @property null|array      $at_extra     扩展信息
 */
class AdminTeam extends Model
{
    use ModelTrait;

    //    const CREATED_AT = 'cu_created_at';
    //    const UPDATED_AT = 'cu_updated_at';
    public const UPDATED_BY = 'updated_by';

    protected $attributes = [];

    protected $appends = [];

    protected $primaryKey = 'at_id';

    protected $guarded = ['at_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('sort');
    }

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->select('at.*')
            ->from('admin_teams as at')
            ->addSelect([
                'admin_count'    => Admin::query()->selectRaw('count(*)')->whereRaw('admins.a_team_ids @> to_jsonb(array[at.at_id])'),
                'vehicle_count'  => Vehicle::query()->selectRaw('count(*)')->whereColumn('ve_team_id', 'at.at_id'),
                'customer_count' => Customer::query()->selectRaw('count(*)')->whereColumn('cu_team_id', 'at.at_id'),
            ])
            ->addSelect(
                DB::raw(AtStatus::toCaseSQL()),
                DB::raw(AtStatus::toColorSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $value = static::query()
            ->when($where, fn ($query) => $query->where($where))
            ->orderBy('at_sort')
            ->orderBy('at_id')
            ->selectRaw('at_name as text, at_id as value')
            ->get()
        ;

        return [$key => $value];
    }

    public static function optionsWithRoles(?\Closure $where = null): array
    {
        $key = static::getOptionKey($key);

        $admins = static::query()
            ->where($where)
            ->orderBy('at_id')
            ->where('a_user_type', '!=', AUserType::TEMP)
            ->with('roles')->get()
        ;

        $value = $admins->map(function ($admin) {
            $role_names = $admin->roles->pluck('name')->toArray();

            return [
                'text'  => $admin->name.($role_names ? '('.implode(',', $role_names).')' : ''),
                'value' => $admin->at_id,
            ];
        });

        return [$key => $value];
    }

    public static function buildUniDataPickerTree(?string $type = null): array
    {
        $query = static::query()
            ->orderBy('sort')
        ;

        if (null !== $type) {
            $query->where('type', $type);
        }

        /** @var Collection<int,static> $nodes */
        $nodes = $query->get();

        if ($nodes->isEmpty()) {
            return [];
        }

        // 1. 先把每个节点转成数组，按 at_id 暂存
        /** @var array<int,array<string,mixed>> $items */
        $items = [];

        foreach ($nodes as $node) {
            $items[$node->at_id] = [
                '_id'       => $node->at_id,          // 内部用
                '_parentId' => $node->parent_id,   // 内部用
                'text'      => $node->label,
                'value'     => $node->value,
                'children'  => [],                 // 先预留
            ];

            if ($node->disabled) {
                $items[$node->at_id]['disable'] = true;
            }
        }

        // 2. 建立 parent → children 的引用关系
        $tree = [];

        foreach ($items as $id => &$item) {
            $parentId = $item['_parentId'];

            if (null !== $parentId && isset($items[$parentId])) {
                // 挂到父节点的 children 上（引用）
                $items[$parentId]['children'][] = &$item;
            } else {
                // 没有 parent_id（或父节点不在集合里）当作根节点
                $tree[] = &$item;
            }
        }
        unset($item); // 必须取消引用

        // 3. 清理内部字段 + 空 children
        self::cleanupTree($tree);

        return $tree;
    }

    public static function nameKv(?string $at_name = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = static::query()
                ->select('at_id', 'at_name')
                ->pluck('at_id', 'at_name')
                ->toArray()
            ;
        }

        return $kv[$at_name] ?? null;
    }

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort'      => 'integer',
            'disabled'  => 'boolean',
            'extra'     => 'array',
        ];
    }

    private static function cleanupTree(array &$nodes): void
    {
        foreach ($nodes as &$node) {
            unset($node['_id'], $node['_parentId']);

            if (!empty($node['children'])) {
                self::cleanupTree($node['children']);
            } else {
                unset($node['children']);
            }
        }
        unset($node);
    }
}
