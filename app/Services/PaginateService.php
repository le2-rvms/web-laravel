<?php

namespace App\Services;

use App\Models\_\Configuration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class PaginateService
{
    //    private function getOrderByRaw()
    //    {
    //        $return = [];
    //        foreach ($this->order as $order) {
    //            $return[] = $order[0].' '.$order[1];
    //        }
    //
    //        return join(',', $return);
    //    }

    public Builder|EloquentBuilder $builder;

    public ?LengthAwarePaginator $paginator = null;

    public ?array $columns        = null;
    protected array $DefaultOrder = [];
    protected array $DefaultParam = [];

    protected array $AllowOrderField = [];

    protected array $AllowSearchField = [];

    private array $param = [];

    private ?string $param_sorting = null;

    private ?array $array_sorting = [];

    private string $param_routeName = '';

    public function __construct($DefaultParam, $DefaultOrder, $AllowSearchField, $AllowOrderField)
    {
        $this->DefaultParam     = $DefaultParam;
        $this->DefaultOrder     = $DefaultOrder;
        $this->AllowSearchField = $AllowSearchField;
        $this->AllowOrderField  = $AllowOrderField;
    }

    public function getSortClass($key): string
    {
        $return = 'sorting';
        if ($this->array_sorting) {
            foreach ($this->array_sorting as $k => $o) {
                if ($o[0] == $key) {
                    $return = ('desc' == $o[1] ? 'sorting_desc' : 'sorting_asc');
                }
            }
        }

        return $return;
    }

    public function paginator(Builder|EloquentBuilder $query, $request, $searchMap = null, ?array $columns = null): ?LengthAwarePaginator
    {
        $this->builder = $query;

        $this->setParam($request);

        $this->searchBuilder($searchMap);

        $this->columns = $columns;

        $this->orderBuilder();

        $perPage = $this->param['pageSize'] ?? Configuration::getPerPageNum($this->param_routeName);

        if (PageExcel::check_request($request)) {
            return null;
        }

        $this->paginator = $query->paginate($perPage);

        $this->paginator->appends($this->getParam());

        return $this->paginator;
    }

    public function getParam(): array
    {
        return $this->param;
    }

    public function getDefaultParam(): array
    {
        return $this->DefaultParam;
    }

    protected function setParam(Request $request): void
    {
        $this->param = $request->query->all();

        if ($this->DefaultParam) {
            foreach ($this->DefaultParam as $key => $value) {
                if (!isset($this->param[$key])) {
                    $this->param[$key] = $value;
                }
            }
        }

        $this->param_sorting = $this->param['sorting'] ?? null;

        // paginate
        $this->param_routeName = $request->route()->getName();
    }

    protected function searchBuilder($searchMap = null)
    {
        foreach ($this->param as $key => &$value) {
            if (null === $value) {
                continue;
            }

            @list($_keyName, $keyType) = @explode('__', $key);
            if (!$_keyName) {
                continue;
            }

            if (!in_array($_keyName, $this->AllowSearchField)) {
                continue;
            }

            @list($keyAs, $keyField) = @explode('_', $_keyName, 2);
            if ('kw' !== $keyAs && (!$keyAs || !$keyField)) {
                continue;
            }

            switch ($keyType) {
                case null:
                case 'eq':
                    $this->builder->where($keyAs.'.'.$keyField, '=', $value);

                    break;

                case 'in':
                    $this->builder->whereIn($keyAs.'.'.$keyField, $value);

                    break;

                case 'nin':
                    $this->builder->whereNotIn($keyAs.'.'.$keyField, $value);

                    break;

                case 'like':
                    $this->builder->where($keyAs.'.'.$keyField, 'like', "%{$value}%");

                    break;

                case 'gt': // greater than
                    $this->builder->where($keyAs.'.'.$keyField, '>', $value);

                    break;

                case 'lt': // less than
                    $this->builder->where($keyAs.'.'.$keyField, '<', $value);

                    break;

                case 'gte': // greater than or equal to
                    $this->builder->where($keyAs.'.'.$keyField, '>=', $value);

                    break;

                case 'lte': // less than or equal to
                    $this->builder->where($keyAs.'.'.$keyField, '<=', $value);

                    break;

                case 'neq': // not equal to 或者
                    break;

                case 'func':
                    $func = $searchMap[$key];
                    $func($value, $this->builder);

                    break;

                case 'between_date':
                    $value_array = json_decode($value, true) ?? explode(',', $value); // 小程序过来的格式是["2025-11-24","2025-11-24"];web 过来的格式是 "2025-11-24","2025-11-24"
                    if ($value_array && is_array($value_array) && count($value_array)) {
                        $this->builder->whereBetween($keyAs.'.'.$keyField, [$value_array[0], $value_array[1].' 23:59:59']);
                    }

                    break;

                case '_':
                    break;

                default:
                    throw_if(true);

                    break;
            }
        }
    }

    protected function orderBuilder(): void
    {
        $sorting = $this->param_sorting;
        if ($sorting) {
            $sorting = array_filter(explode(' ', $sorting));
            $sorting = [$sorting];

            foreach ($sorting as $k => $o) {
                if (in_array($o[0], $this->AllowOrderField) && in_array($o[1], ['desc', 'asc'])) {
                } else {
                    unset($sorting[$k]);
                }
            }
        }

        $this->array_sorting = $sorting ?? $this->DefaultOrder;

        foreach ($this->array_sorting as $k => $o) {
            if (sizeof($o) > 1) {
                $this->builder->orderBy($o[0], $o[1]);
            } elseif (1 === sizeof($o)) {
                $this->builder->orderByRaw($o[0]);
            }
        }
    }

    protected function paginateBuilder(Builder $query): Paginator
    {
        $perPage = $this->param['pageSize'] ?? Configuration::getPerPageNum($this->param_routeName);

        return $query->paginate($perPage);
    }
}
