<?php

namespace App\Http\Controllers\Admin\Config;

use App\Enum\Config\CfgMasked;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\_\Configuration;
use App\Services\PaginateService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

abstract class ConfigurationController extends Controller
{
    protected ?int $usageCategory;

    public function __construct()
    {
        if (null !== $this->usageCategory) {
            $this->response()->withExtras(
                [
                    'uc' => $this->usageCategory,
                ],
            );
        }

        $this->middleware(CheckAdminIsMock::class);
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            CfgMasked::labelOptions(),
        );
    }

    public function index(Request $request)
    {
        $this->options(true);
        $this->response()->withExtras();

        // 因为有可能要显示 mask 之后的值，所以需要直接query orm。
        $query = Configuration::query()
            ->where('cfg_usage_category', $this->usageCategory)
        ;

        $paginate = new PaginateService(
            [],
            [['cfg_key', 'asc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('cfg_key', 'like', '%'.$value.'%')
                        ->orWhere('cfg_remark', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    public function edit(Request $request, Configuration $configuration): Response
    {
        $this->options();

        return $this->response()->withData($configuration)->respond();
    }

    public function editConfirm(Request $request, Configuration $configuration): Response
    {
        $input = $this->validatedEditRequest($request, $configuration);

        $configuration->fill($input);

        return view('config.edit_confirm', compact('input', 'configuration'));
    }

    public function update(Request $request, Configuration $configuration): Response
    {
        $input = $this->validatedEditRequest($request, $configuration);

        DB::transaction(function () use ($configuration, $input) {
            $configuration->update($input);
        });

        $this->response()->withMessages(message_success(str_replace(get_parent_class($this), get_called_class(), __METHOD__)));

        return $this->response()->withRedirect(redirect()->route(sprintf('config%d.index', $this->usageCategory)))->respond();
    }

    public function destroy(Configuration $configuration): Response
    {
        DB::transaction(function () use ($configuration) {
            if ($configuration->cfg_usage_category->value === $this->usageCategory) {
                $configuration->deleteOrFail();
            }
        });

        $this->response()->withMessages(message_success(str_replace(get_parent_class($this), get_called_class(), __METHOD__)));

        return $this->response()->withRedirect(redirect()->route(sprintf('config%d.index', $this->usageCategory)))->respond();
    }

    public function create(Request $request): Response
    {
        $this->options();

        $config = new Configuration([
            'cfg_masked'         => CfgMasked::NO,
            'cfg_usage_category' => $this->usageCategory,
        ]);

        return $this->response()->withData($config)->respond();
    }

    public function createConfirm(Request $request): View
    {
        $input = $this->validatedCreateRequest($request);

        $configuration = new Configuration($input);

        return view('config.create_confirm', compact('input', 'configuration'));
    }

    public function store(Request $request): Response
    {
        $input = $this->validatedCreateRequest($request);

        DB::transaction(function () use ($input) {
            $configuration = Configuration::query()->create($input + ['cfg_usage_category' => $this->usageCategory]); // todo 改为前端要传，控制器要验证。
        });

        $this->response()->withMessages(message_success(str_replace(get_parent_class($this), get_called_class(), __METHOD__)));

        return $this->response()->withRedirect(redirect()->route(sprintf('config%d.index', $this->usageCategory)))->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            CfgMasked::options(),
        );
    }

    private function validatedCreateRequest(Request $request): array
    {
        $validator = Validator::make(
            $request->all(),
            [
                'cfg_key'    => ['required', 'max:255', Rule::unique(Configuration::class, 'cfg_key')],
                'cfg_value'  => ['required'],
                'cfg_masked' => ['required', Rule::in(CfgMasked::label_keys())],
                'cfg_remark' => ['nullable'],
            ],
            [],
            trans_property(Configuration::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validatedEditRequest(Request $request, Configuration $configuration): array
    {
        $validator = Validator::make(
            $request->all(),
            [
                'cfg_key'    => ['required', 'max:255', Rule::unique(Configuration::class, 'cfg_key')->ignore($configuration, 'cfg_key')],
                'cfg_value'  => ['required'],
                'cfg_masked' => ['required', Rule::in(CfgMasked::label_keys())],
                'cfg_remark' => ['nullable'],
            ],
            [],
            trans_property(Configuration::class)
        )
            ->after(function ($validator) {
                if ($validator->failed()) {
                    return;
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
