<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Customer\CuiGender;
use App\Enum\Customer\CuType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use App\Models\Admin\AdminTeam;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCompany;
use App\Models\Customer\CustomerIndividual;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use App\Models\Sale\SaleSettlement;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleUsage;
use App\Models\Vehicle\VehicleViolation;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('顾客')]
class CustomerController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            CuType::labelOptions(),
            CuiGender::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            AdminTeam::options(),
        );

        $query   = Customer::indexQuery();
        $columns = Customer::indexColumns();

        /** @var Admin $admin */
        $admin = auth()->user();

        // 车队作为查询条件
        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('cu.cu_team_id', $admin->a_team_ids)->orWhereNull('cu.cu_team_id');
            });
        }

        // 如果是管理员或经理，则可以看到所有的用户；如果不是管理员或经理，则只能看到销售或驾管为自己的用户。
        $role_sales_manager = $admin->hasRole(AdminRole::role_sales);
        if ($role_sales_manager) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereNull('cu.cu_sales_manager')->orWhere('cu.cu_sales_manager', '=', $admin->id);
            });
        }

        $has_role_driver = $admin->hasRole(AdminRole::role_driver_mgr);
        if ($has_role_driver) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereNull('cu.cu_driver_manager')->orWhere('cu.cu_driver_manager', '=', $admin->id);
            });
        }

        $paginate = new PaginateService(
            [],
            [['cu.cu_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->whereLike('cu.cu_contact_name', '%'.$value.'%')
                        ->orWhereLike('cu.cu_contact_phone', '%'.$value.'%')
                        ->orWhereLike('cu.cu_remark', '%'.$value.'%')
                    ;
                });
            },
        ], $columns);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(Customer $customer): Response
    {
        $customer->load('CustomerIndividual', 'CustomerCompany');

        $this->response()->withExtras(
            CuType::options(),
            CuiGender::options(),
            CuiGender::flipLabelDic(),
        );

        //        $this->response()->withExtras(
        //            VehicleInspection::kvList(cu_id: $customer->cu_id),
        //            SaleContract::kvList(cu_id: $customer->cu_id),
        //            Payment::kvList(cu_id: $customer->cu_id),
        //            SaleSettlement::kvList(cu_id: $customer->cu_id),
        //            VehicleUsage::kvList(cu_id: $customer->cu_id),
        //            VehicleRepair::kvList(cu_id: $customer->cu_id),
        //            VehicleViolation::kvList(cu_id: $customer->cu_id),
        //            VehicleManualViolation::kvList(cu_id: $customer->cu_id),
        //        );

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?Customer $customer): Response
    {
        $input0 = Validator::make(
            $request->all(),
            [
                'cu_type'                 => ['required', 'string', Rule::in(CuType::label_keys())],
                'cu_contact_name'         => ['required', 'string', 'max:255'],
                'cu_contact_phone'        => ['required', 'regex:/^\d{11}$/', Rule::unique(Customer::class, 'cu_contact_phone')->ignore($customer)],
                'cu_contact_email'        => ['nullable', 'email', Rule::unique(Customer::class, 'cu_contact_email')->ignore($customer)],
                'cu_contact_wechat'       => ['nullable', 'string', 'max:255'],
                'cu_contact_live_city'    => ['nullable', 'string', 'max:64'],
                'cu_contact_live_address' => ['nullable', 'string', 'max:255'],
                'cu_cert_no'              => ['nullable', 'string', 'max:50'],
                'cu_cert_valid_to'        => ['nullable', 'date'],
                'cu_remark'               => ['nullable', 'string', 'max:255'],
                'cu_team_id'              => ['nullable', 'integer', Rule::exists(AdminTeam::class, 'at_id')],

                'cu_sales_manager'  => ['nullable', Rule::exists(Admin::class, 'id')],
                'cu_driver_manager' => ['nullable', Rule::exists(Admin::class, 'id')],
            ],
            [],
            trans_property(Customer::class),
        )->validate();

        $validator = match ($input0['cu_type']) {
            CuType::INDIVIDUAL => Validator::make(
                $request->all(),
                [
                    'customer_individual'                                => ['nullable', 'array'],
                    'customer_individual.cui_name'                       => ['nullable', 'string', 'max:255'],
                    'customer_individual.cui_gender'                     => ['nullable', Rule::in(CuiGender::label_keys())],
                    'customer_individual.cui_date_of_birth'              => ['nullable', 'date', 'before:today'],
                    'customer_individual.cui_id_number'                  => ['nullable', 'regex:/^\d{17}[\dXx]$/'],
                    'customer_individual.cui_id_address'                 => ['nullable', 'string', 'max:500'],
                    'customer_individual.cui_id_expiry_date'             => ['nullable', 'date', 'after:date_of_birth'],
                    'customer_individual.cui_driver_license_number'      => ['nullable', 'string', 'max:50'],
                    'customer_individual.cui_driver_license_category'    => ['nullable', 'string', 'regex:/^[A-Z]\d+$/'],
                    'customer_individual.cui_driver_license_expiry_date' => ['nullable', 'date'],
                    'customer_individual.cui_emergency_contact_name'     => ['nullable', 'string', 'max:64'],
                    'customer_individual.cui_emergency_contact_phone'    => ['nullable', 'regex:/^\d{7,15}$/'],
                    'customer_individual.cui_emergency_relationship'     => ['nullable', 'string', 'max:64'],
                ]
                + Uploader::validator_rule_upload_object('customer_individual.cui_id1_photo')
                + Uploader::validator_rule_upload_object('customer_individual.cui_id2_photo')
                + Uploader::validator_rule_upload_object('customer_individual.cui_driver_license1_photo')
                + Uploader::validator_rule_upload_object('customer_individual.cui_driver_license2_photo')
                + Uploader::validator_rule_upload_object('cu_cert_photo')
                + Uploader::validator_rule_upload_array('cu_additional_photos'),
                [],
                Arr::dot(['customer_individual' => trans_property(CustomerIndividual::class)]),
            ),

            CuType::COMPANY => Validator::make(
                $request->all(),
                [
                    'customer_company' => ['nullable', 'required', 'array'],
                ],
                [],
                trans_property(CustomerCompany::class),
            ),
        };

        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($validator->failed()) {
                return;
            }
        });

        $input = $input0 + $validator->validate();

        DB::transaction(function () use (&$input, &$customer) {
            if (null === $customer) {
                $customer = Customer::query()->create($input);
            } else {
                $customer->update($input);
            }

            switch ($customer->cu_type) {
                case CuType::INDIVIDUAL:
                    $customer->CustomerCompany()->delete();

                    $input_individual = $input['customer_individual'] ?? [];

                    $customer->CustomerIndividual()->updateOrCreate(
                        [
                            'cui_cu_id' => $customer->cu_id,
                        ],
                        $input_individual,
                    );

                    break;

                case CuType::COMPANY:
                    $customer->CustomerIndividual()->delete();

                    $input_company = $input['customer_company'];

                    $customer->CustomerCompany()->updateOrCreate(
                        [
                            'cuc_cu_id' => $customer->cu_id,
                        ],
                        $input_company,
                    );

                    break;

                default:
                    break;
            }
        });

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(Customer $customer): Response
    {
        DB::transaction(function () use ($customer) {
            $customer->CustomerIndividual()->delete();
            $customer->CustomerCompany()->delete();
            $customer->delete();
        });

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_sales);
            }, 'cu_driver_manager'),
            AdminTeam::options(),
        );

        $customer = new Customer([
            'cu_type' => CuType::INDIVIDUAL,
        ]);

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Customer $customer): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_sales);
            }, 'cu_sales_manager'),
            Admin::optionsWithRoles(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_driver_mgr);
            }, 'cu_driver_manager'),
            AdminTeam::options(),
        );

        $this->response()->withExtras(
            SaleContract::indexList(function (Builder $query) use ($customer) {
                $query->where('sc.sc_cu_id', '=', $customer->cu_id);
            }),
            VehicleInspection::indexList(function (Builder $query) use ($customer) {
                $query->where('cu.cu_id', '=', $customer->cu_id);
            }),
            Payment::indexList(function (Builder $query) use ($customer) {
                $query->where('sc.sc_cu_id', '=', $customer->cu_id);
            }),
            SaleSettlement::indexList(function (Builder $query) use ($customer) {
                $query->where('cu.cu_id', '=', $customer->cu_id);
            }),
            VehicleUsage::indexList(function (Builder $query) use ($customer) {
                $query->where('sc.sc_cu_id', '=', $customer->cu_id);
            }),
            VehicleRepair::indexList(function (Builder $query) use ($customer) {
                $query->where('vr.vr_sc_id', '=', $customer->cu_id);
            }),
            VehicleViolation::indexList(function (Builder $query) use ($customer) {
                $query->where('sc.sc_cu_id', '=', $customer->cu_id);
            }),
            VehicleManualViolation::indexList(function (Builder $query) use ($customer) {
                $query->where('sc.sc_cu_id', '=', $customer->cu_id);
            }),
        );

        $customer->load('CustomerIndividual', 'CustomerCompany');

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'customer',
            ['cui_id1_photo', 'cui_id2_photo', 'cui_driver_license1_photo', 'cui_driver_license2_photo', 'cuc_business_license_photo', 'cu_cert_photo', 'cu_additional_photos'],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            CuType::options(),
            CuiGender::options(),
            CuiGender::flipLabelDic(),
        );
    }
}
