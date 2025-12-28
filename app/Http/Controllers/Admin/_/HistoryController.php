<?php

namespace App\Http\Controllers\Admin\_;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class HistoryController extends Controller
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('setting.dblog');
    }

    public function __invoke(Request $request): Response
    {
        $class_basename_array = $class_basename_models = [];
        foreach ($this->config['models'] as $model_name => $pk_name) {
            $class_basename_array[] = $class_basename = class_basename($model_name);

            $class_basename_models[$class_basename] = $model_name;
        }

        $input = Validator::make(
            $request->route()->parameters(),
            [
                'class_basename' => ['required', Rule::in($class_basename_array)],
                'pk'             => ['required', 'int'],
            ]
        )
            ->validate()
        ;

        $class_basename = $input['class_basename'];
        $pk             = $input['pk'];
        $class_name     = $class_basename_models[$class_basename];

        /** @var Model $model */
        $model = new $class_name();

        $table = $model->getTable();

        // 关联模型的审计记录合并配置。
        $unions = $this->config['union'][$class_name] ?? [];
        if ($unions) {
            foreach ($unions as $key => &$union) {
                [$relation_class, $field] = $union;

                /** @var Model $relation_model */
                $relation_model = new $relation_class();
                $relation_value = $relation_model->query()->where($field, '=', $pk)->first()?->getKey();

                if ($relation_value) {
                    $union[] = class_basename($relation_class);
                    $union[] = $relation_model->getTable();
                    $union[] = $relation_value;
                } else {
                    unset($unions[$key]);
                }
            }
        }

        $auditSchema = $this->config['schema'];

        // 查询历史记录
        $history = DB::table($auditSchema.'.'.$table)
            ->select(
                '*',
                DB::raw("DATE_TRUNC('second', changed_at) as changed_at_"),
                DB::raw(sprintf(" '%s' as tb", $table)),
            )
            ->where('pk', '=', $pk)
            ->when($unions, function (Builder $query) use ($auditSchema, $unions) {
                foreach ($unions as $union) {
                    list(, , , $union_table, $union_id) = $union;

                    $sub = DB::table($auditSchema.'.'.$union_table)
                        ->select(
                            '*',
                            DB::raw("DATE_TRUNC('second', changed_at) as changed_at"),
                            DB::raw(sprintf(" '%s' as tb", $union_table))
                        )
                        ->where('pk', '=', $union_id)
                    ;

                    $query->union($sub);
                }
            })
            ->orderBy('changed_at', 'desc')
            ->limit(100)
            ->get()
        ;

        $history->map(function (\stdClass $rec) {
            $rec->new_data = $rec->new_data ? json_decode($rec->new_data, true) : null;
            $rec->old_data = $rec->old_data ? json_decode($rec->old_data, true) : null;

            //            $rec->changed = array_udiff_assoc($rec->new_data, $rec->old_data, 'shallow_diff');
        });

        // 注入字段翻译，供前端显示。
        $properties = trans('property.'.$class_basename);
        $this->response()->withLang($properties);

        $table_trans = trans('model.'.$class_basename);
        $this->response()->withLang(['model.'.$table => $table_trans['name']]);

        if ($unions) {
            foreach ($unions as $key => $union) {
                list(, , $relation_basename, $relation_table) = $union;

                $relation_basename_props = trans('property.'.$relation_basename);
                $this->response()->withLang($relation_basename_props);

                $relation_basename_trans = trans('model.'.$relation_basename.'.name');
                $this->response()->withLang(['model.'.$relation_table => $relation_basename_trans]);
            }
        }

        $controller_class = getNamespaceByComposerMap($class_basename.'Controller', 'Admin');

        try {
            // 尝试加载对应控制器的 labelOptions 补充枚举选项。
            $controller_class::{'labelOptions'}($this);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->response()->withData($history)->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
