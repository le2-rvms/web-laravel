<?php

namespace App\Console\Commands\App\One;

use App\Enum\One\OaIsSyncRentalContract;
use App\Enum\SaleContract\ScStatus;
use App\Models\One\OneAccount;
use App\Models\One\OneContract;
use App\Models\Sale\SaleContract;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:one-contracts:sync',
    description: '同步 122 平台租赁合同'
)]
class OneContractsSync extends Command
{
    private const REQUEST_HEADERS = [
        'Accept'           => 'application/json, text/javascript, */*; q=0.01',
        'Accept-Language'  => 'zh',
        'Connection'       => 'keep-alive',
        'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
        'User-Agent'       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
    protected $signature = '_app:one-contracts:sync
        {--since= : 同步起始日期，格式 YYYY-MM-DD}
        {--until= : 同步结束日期，格式 YYYY-MM-DD}
        {--page-size=100 : 合同列表分页大小}
        {--skip-fetch : 跳过线上合同拉取，仅使用本地 one_contract}
        {--dry-run : 仅输出统计，不提交请求}';

    protected $description = '同步 122 平台租赁合同';

    private static string $listUrl   = ''; // 122 合同列表接口地址
    private static string $createUrl = ''; //  122 合同新增接口地址
    private static string $voidUrl   = ''; // 122 合同作废接口地址
    private Carbon $since;
    private Carbon $until;
    private Carbon $compareUntil;

    public function handle(): int
    {
        $this->resolveOption();

        $this->info('开始同步 122 租赁合同...');
        $this->info('同步范围：'.$this->since->toDateString().' ~ '.$this->until->toDateString());

        $accounts = OneAccount::getListForSync();
        if ($accounts->isEmpty()) {
            $this->warn('未找到可同步的 122 账号。');

            return CommandAlias::SUCCESS;
        }

        if (!$this->option('skip-fetch')) {
            [$onlineContracts, $fetchErrors] = $this->fetchOnlineContracts($accounts);
            $this->info('线上合同拉取完成，数量：'.count($onlineContracts));

            if ($fetchErrors) {
                foreach ($fetchErrors as $error) {
                    $this->error($error);
                }

                return CommandAlias::FAILURE;
            }

            if (!$this->option('dry-run')) {
                $this->overwriteLocalContracts($onlineContracts);
                $this->info('本地合同已按线上全量覆盖。');
            }
        }

        $onlineContracts = OneContract::query()
            ->whereDate('oc_rental_end_at', '>=', $this->since->toDateString())
            ->whereDate('oc_rental_start_at', '<=', $this->until->toDateString())
            ->get()
        ;
        $onlineMap = $this->mapContractsByKey($onlineContracts);

        $invalidContractNos = SaleContract::query()
            ->whereNotIn('sc_status', ScStatus::getSignAndAfter)
            ->whereDate('sc_end_date', '>=', $this->since->toDateString())
            ->whereDate('sc_start_date', '<=', $this->until->toDateString())
            ->whereNotNull('sc_no')
            ->pluck('sc_no')
            ->filter()
            ->unique()
            ->values()
            ->all()
        ;
        $invalidOnlineMap = $this->filterOnlineByContractNos($onlineContracts, $invalidContractNos);

        $validContracts = SaleContract::query()
            ->whereIn('sc_status', ScStatus::getSignAndAfter)
            ->whereDate('sc_end_date', '>=', $this->since->toDateString())
            ->whereDate('sc_start_date', '<=', $this->until->toDateString())
            ->with(['Customer.CustomerIndividual', 'Vehicle.ViolationAccount'])
            ->orderBy('sc_id')
            ->get()
        ;
        //        $skipReasons = [];
        $skipContractNos = [];
        $localMap        = $this->buildLocalContracts($validContracts, $this->compareUntil, $skipContractNos);

        $onlineExtra = array_diff_key($onlineMap, $localMap);
        $localExtra  = array_diff_key($localMap, $onlineMap);

        //        $skipContractNos = array_values(array_filter(array_column($skipReasons, 'sc_no'), function ($no) {
        //            return $no && '未知合同号' !== $no;
        //        }));
        if ($skipContractNos) {
            $skipMap             = array_flip($skipContractNos);
            $filteredOnlineExtra = [];
            foreach ($onlineExtra as $key => $payload) {
                if (isset($skipMap[$payload['oc_contract_no']])) {
                    continue;
                }
                $filteredOnlineExtra[$key] = $payload;
            }
            $onlineExtra = $filteredOnlineExtra;
        }

        $invalidateMap = $onlineExtra + $invalidOnlineMap;

        $this->info(sprintf('线上合同数：%d', count($onlineMap)));
        $this->info(sprintf('线下合同数：%d', count($localMap)));
        $this->info(sprintf('待作废合同数：%d', count($invalidateMap)));
        $this->info(sprintf('待新增合同数：%d', count($localExtra)));

        //        if ($skipReasons) {
        //            $this->warn('以下合同因校验不通过已跳过：');
        //            foreach ($skipReasons as $item) {
        //                $this->warn(sprintf('%s：%s', $item['sc_no'], $item['reason']));
        //            }
        //        }

        if ($this->option('dry-run')) {
            $this->info('当前为 dry-run 模式，未提交作废或新增请求。');

            return CommandAlias::SUCCESS;
        }

        [$contractAccountMap, $plateAccountMap] = $this->buildAccountMaps();

        $voidSuccess = 0;
        foreach ($invalidateMap as $payload) {
            $account = $this->resolveAccountForOnline($payload, $contractAccountMap, $plateAccountMap);
            if (!$account) {
                $this->warn('作废失败：无法匹配 12123 账号，合同号 '.$payload['oc_contract_no']);

                continue;
            }

            if ($this->requestVoid($account, $payload)) {
                ++$voidSuccess;
            }
        }

        $createSuccess = 0;
        foreach ($localExtra as $item) {
            $account = $item['account'];
            if (!$account) {
                $this->warn('新增失败：无法匹配 12123 账号，合同号 '.$item['payload']['oc_contract_no']);

                continue;
            }

            if ($this->requestCreate($account, $item['payload'])) {
                ++$createSuccess;
            }
        }

        $this->info(sprintf('作废成功：%d', $voidSuccess));
        $this->info(sprintf('新增成功：%d', $createSuccess));

        return CommandAlias::SUCCESS;
    }

    private function resolveOption(): void
    {
        $this->since = $this->option('since') ? Carbon::parse($this->option('since'))->startOfDay() : now()->subYear()->startOfDay();
        $this->until = $this->option('until') ? Carbon::parse($this->option('until'))->setTime(23, 59) : now()->setTime(23, 59);

        $currentMonthEnd = now()->endOfMonth()->setTime(23, 59);

        $this->compareUntil = $this->until->lt($currentMonthEnd) ? $this->until->copy() : $currentMonthEnd->copy();
    }

    private function fetchOnlineContracts(Collection $accounts): array
    {
        $contracts = [];
        $errors    = [];

        foreach ($accounts as $account) {
            [$items, $error] = $this->fetchOnlineContractsByAccount($account);
            if ($error) {
                $errors[] = $error;
            }

            foreach ($items as $item) {
                $contracts[] = $item;
            }
        }

        return [$contracts, $errors];
    }

    private function fetchOnlineContractsByAccount(OneAccount $account): array
    {
        $url = $this->buildUrl($account, self::$listUrl);
        if (!$url) {
            return [[], '合同列表接口地址无效，账号 '.$account->oa_name];
        }

        $page     = 1;
        $pageSize = (int) $this->option('page-size');
        $items    = [];

        while (true) {
            $payload = [
                'startDate' => $this->since->format('Ymd'),
                'endDate'   => $this->until->format('Ymd'),
                'page'      => (string) $page,
                'size'      => (string) $pageSize,
            ];

            $response = Http::withHeaders(self::REQUEST_HEADERS)
                ->withHeaders(['Cookie' => $account->oa_cookie_string])
                ->asForm()
                ->post($url, $payload)
            ;

            if (!$response->successful()) {
                return [[], "合同列表请求失败：{$account->oa_name}，状态码 {$response->status()}"];
            }

            $data = $response->json();
            if (!is_array($data)) {
                return [[], "合同列表响应解析失败：{$account->oa_name}"];
            }

            if (($data['code'] ?? 200) != 200) {
                $message = $data['message'] ?? '未知错误';

                return [[], "合同列表返回异常：{$account->oa_name}，{$message}"];
            }

            $content = $data['data']['content'] ?? [];
            foreach ($content as $row) {
                $normalized = $this->normalizeOnlineContract($row);
                if ($normalized) {
                    $items[] = $normalized;
                }
            }

            $isLast = $data['data']['last'] ?? true;
            if ($isLast) {
                break;
            }

            ++$page;
        }

        return [$items, null];
    }

    private function normalizeOnlineContract(array $row): ?array
    {
        $contractNo = $this->pickValue($row, ['oc_contract_no', 'contractNo', 'hth', 'htbh']);
        $plateType  = $this->pickValue($row, ['oc_plate_type', 'plateType', 'hpzl']);
        $plateNo    = $this->pickValue($row, ['oc_plate_number', 'plateNo', 'hphm']);
        $rentalType = $this->pickValue($row, ['oc_rental_type', 'rentalType', 'zllx']);
        $signedAt   = $this->parseMinute($this->pickValue($row, ['oc_signed_at', 'signedAt', 'qdsj']));
        $startAt    = $this->parseMinute($this->pickValue($row, ['oc_rental_start_at', 'rentalStartAt', 'kssj']));
        $endAt      = $this->parseMinute($this->pickValue($row, ['oc_rental_end_at', 'rentalEndAt', 'jssj']));
        $idType     = $this->pickValue($row, ['oc_id_doc_type', 'idDocType', 'zjlx']);
        $idNo       = $this->pickValue($row, ['oc_id_doc_no', 'idDocNo', 'zjhm']);

        if (!$contractNo || !$plateType || !$plateNo || !$rentalType || !$signedAt || !$startAt || !$endAt || !$idType || !$idNo) {
            return null;
        }

        return [
            'oc_plate_type'      => (string) $plateType,
            'oc_plate_number'    => $this->normalizePlateNumber((string) $plateNo),
            'oc_rental_type'     => (string) $rentalType,
            'oc_contract_no'     => (string) $contractNo,
            'oc_signed_at'       => $signedAt,
            'oc_rental_start_at' => $startAt,
            'oc_rental_end_at'   => $endAt,
            'oc_id_doc_type'     => (string) $idType,
            'oc_id_doc_no'       => (string) $idNo,
        ];
    }

    private function pickValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($row, $key);
            if (null !== $value && '' !== $value) {
                return is_string($value) ? trim($value) : (string) $value;
            }
        }

        return null;
    }

    private function parseMinute(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function overwriteLocalContracts(array $contracts): void
    {
        DB::transaction(function () use ($contracts) {
            OneContract::query()
                ->whereDate('oc_rental_end_at', '>=', $this->since->toDateString())
                ->whereDate('oc_rental_start_at', '<=', $this->until->toDateString())
                ->delete()
            ;

            if ($contracts) {
                OneContract::query()->insert($contracts);
            }
        });
    }

    private function buildLocalContracts(Collection $contracts, Carbon $rangeEnd, array &$skipContractNos): array
    {
        $result = [];

        foreach ($contracts as $contract) {
            $reasons = $this->validateContract($contract);
            if ($reasons) {
                $this->warn(sprintf('%s：%s', $contract->sc_no, implode('；', $reasons)));

                $skipContractNos[] = $contract->sc_no;

                //                $skipReasons[] = [
                //                    'sc_no'  => $contract->sc_no,
                //                    'reason' => implode('；', $reasons),
                //                ];

                continue;
            }

            [$startAt, $endAt] = $this->resolveContractPeriod($contract);
            $segments          = $this->splitByMonth($startAt, $endAt, $rangeEnd);

            foreach ($segments as [$segmentStart, $segmentEnd]) {
                $payload = $this->buildLocalPayload($contract, $segmentStart, $segmentEnd);
                $key     = $this->contractKey($payload);
                if ('' === $key) {
                    continue;
                }
                $result[$key] = [
                    'payload' => $payload,
                    'account' => $contract->Vehicle?->ViolationAccount,
                ];
            }
        }

        return $result;
    }

    private function validateContract(SaleContract $contract): array
    {
        $errors = [];

        if (!$contract->sc_no) {
            $errors[] = '合同编号为空';
        }

        [$startAt, $endAt] = $this->resolveContractPeriod($contract);
        if (!$startAt || !$endAt) {
            $errors[] = '合同开始或结束时间缺失';
        }

        $vehicle = $contract->Vehicle;
        if (!$vehicle || !$vehicle->ve_oa_id) {
            $errors[] = '车辆未绑定 12123 账号';
        }

        if (!$vehicle?->ve_type) {
            $errors[] = '车辆号牌种类缺失';
        }
        if (!$vehicle?->ve_plate_no) {
            $errors[] = '车辆号牌号码缺失';
        }

        $account = $vehicle?->ViolationAccount;
        if (!$account) {
            $errors[] = '车辆 12123 账号不存在';
        } elseif (($account->oa_is_sync_rental_contract?->value ?? null) !== OaIsSyncRentalContract::ENABLED) {
            $errors[] = '车辆 122 配置未开启合同同步';
        }

        $customer = $contract->Customer;

        $individual = $customer?->CustomerIndividual;

        //        $driver = $this->resolveDriverInfo($contract);

        //        ($individual?->cui_name && $individual?->cui_id_number

        if (!$individual?->cui_name || !$individual?->cui_id_number) {
            $errors[] = '司机姓名或身份证号码缺失';
        }

        return $errors;
    }

    private function resolveContractPeriod(SaleContract $contract): array
    {
        $startAt = $contract->sc_start_date;
        $endAt   = $contract->sc_end_date;

        if ($startAt) {
            $startAt->setTime(0, 0);
        }
        if ($endAt) {
            $endAt->setTime(23, 59);
        }

        return [$startAt, $endAt];
    }

    private function splitByMonth(Carbon $startAt, Carbon $endAt, Carbon $syncEnd): array
    {
        $rangeStart = $startAt->copy();
        if ($rangeStart->lt($this->since)) {
            $rangeStart = $this->since->copy();
        }

        $rangeEnd = $endAt->copy();
        if ($rangeEnd->gt($syncEnd)) {
            $rangeEnd = $syncEnd->copy();
        }

        if ($rangeStart->gt($rangeEnd)) {
            return [];
        }

        $segments = [];
        $cursor   = $rangeStart->copy();

        // 按自然月拆分，超过本次同步截止时间的部分不处理。
        while ($cursor->lte($rangeEnd)) {
            $segmentStart = $cursor->copy();
            $segmentEnd   = $cursor->copy()->endOfMonth()->setTime(23, 59);
            if ($segmentEnd->gt($rangeEnd)) {
                $segmentEnd = $rangeEnd->copy();
            }

            $segments[] = [$segmentStart, $segmentEnd];
            $cursor     = $segmentEnd->copy()->addMinute();
        }

        return $segments;
    }

    private function buildLocalPayload(SaleContract $contract, Carbon $segmentStart, Carbon $segmentEnd): array
    {
        $vehicle    = $contract->Vehicle;
        $customer   = $contract->Customer;
        $individual = $customer->CustomerIndividual;
        //        $driver   = $this->resolveDriverInfo($contract);
        $signedAt = ($contract->sc_signed_at) ?: $segmentStart->copy();

        return [
            'oc_plate_type'      => (string) $vehicle->ve_type,
            'oc_plate_number'    => $this->normalizePlateNumber((string) $vehicle->ve_plate_no),
            'oc_rental_type'     => $contract->sc_rental_type->value,
            'oc_contract_no'     => (string) $contract->sc_no,
            'oc_signed_at'       => $signedAt->format('Y-m-d H:i'),
            'oc_rental_start_at' => $segmentStart->format('Y-m-d H:i'),
            'oc_rental_end_at'   => $segmentEnd->format('Y-m-d H:i'),
            'oc_id_doc_type'     => 'PRC_ID_CARD',
            'oc_id_doc_no'       => $individual->cui_id_number,
            'driver_name'        => $individual->cui_name,
        ];
    }

    private function mapContractsByKey(Collection $contracts): array
    {
        $map = [];
        foreach ($contracts as $contract) {
            $key = $this->contractKey($contract);
            if ($key) {
                $map[$key] = $contract;
            }
        }

        return $map;
    }

    private function filterOnlineByContractNos(Collection $contracts, array $contractNos): array
    {
        if (!$contractNos) {
            return [];
        }

        $contractNoMap = array_flip($contractNos);
        $result        = [];

        foreach ($contracts as $contract) {
            if (isset($contractNoMap[$contract['oc_contract_no']])) {
                $key = $this->contractKey($contract);
                if ('' === $key) {
                    continue;
                }
                $result[$key] = $contract;
            }
        }

        return $result;
    }

    private function contractKey(array $payload): string
    {
        $required = [
            'oc_contract_no',
            'oc_rental_start_at',
            'oc_rental_end_at',
            'oc_plate_type',
            'oc_plate_number',
        ];

        foreach ($required as $field) {
            if (empty($payload[$field])) {
                return '';
            }
        }

        return implode('|', array_map(fn ($field) => (string) ($payload[$field] ?? ''), $required));
    }

    private function buildAccountMaps(): array
    {
        $contractAccountMap = [];
        $plateAccountMap    = [];

        $contracts = SaleContract::query()
            ->whereDate('sc_end_date', '>=', $this->since->toDateString())
            ->whereDate('sc_start_date', '<=', $this->until->toDateString())
            ->with(['Vehicle.ViolationAccount'])
            ->get()
        ;

        foreach ($contracts as $contract) {
            $account = $contract->Vehicle?->ViolationAccount;
            if (!$account) {
                continue;
            }

            if ($contract->sc_no) {
                $contractAccountMap[$contract->sc_no] = $account;
            }

            if ($contract->Vehicle?->ve_plate_no) {
                $plate                   = $this->normalizePlateNumber($contract->Vehicle->ve_plate_no);
                $plateAccountMap[$plate] = $account;
            }
        }

        return [$contractAccountMap, $plateAccountMap];
    }

    private function resolveAccountForOnline(array $payload, array $contractAccountMap, array $plateAccountMap): ?OneAccount
    {
        $contractNo = $payload['oc_contract_no'] ?? null;
        if ($contractNo && isset($contractAccountMap[$contractNo])) {
            return $contractAccountMap[$contractNo];
        }

        $plateNo = $payload['oc_plate_number'] ?? null;
        if ($plateNo && isset($plateAccountMap[$plateNo])) {
            return $plateAccountMap[$plateNo];
        }

        return null;
    }

    private function requestVoid(OneAccount $account, array $payload): bool
    {
        $url = $this->buildUrl($account, self::$voidUrl);
        if (!$url) {
            $this->error('作废接口地址无效，账号 '.$account->oa_name);

            return false;
        }

        if (strlen((string) $account->oa_cookie_string) <= 30) {
            $this->error('作废失败：账号 cookie 无效，合同号 '.$payload['oc_contract_no']);

            return false;
        }

        $requestPayload = $this->buildRequestPayload($payload, forCreate: false);
        $response       = Http::withHeaders(self::REQUEST_HEADERS)
            ->withHeaders(['Cookie' => $account->oa_cookie_string])
            ->asForm()
            ->post($url, $requestPayload)
        ;

        if (!$response->successful()) {
            $this->error('作废失败，合同号 '.$payload['oc_contract_no'].'，状态码 '.$response->status());

            return false;
        }

        $data = $response->json();
        if (is_array($data) && ($data['code'] ?? 200) != 200) {
            $message = $data['message'] ?? '未知错误';
            $this->error('作废失败，合同号 '.$payload['oc_contract_no'].'，'.$message);

            return false;
        }

        return true;
    }

    private function requestCreate(OneAccount $account, array $payload): bool
    {
        $url = $this->buildUrl($account, self::$createUrl);
        if (!$url) {
            $this->error('新增接口地址无效，账号 '.$account->oa_name);

            return false;
        }

        if (strlen((string) $account->oa_cookie_string) <= 30) {
            $this->error('新增失败：账号 cookie 无效，合同号 '.$payload['oc_contract_no']);

            return false;
        }

        $requestPayload = $this->buildRequestPayload($payload, forCreate: true);
        $response       = Http::withHeaders(self::REQUEST_HEADERS)
            ->withHeaders(['Cookie' => $account->oa_cookie_string])
            ->asForm()
            ->post($url, $requestPayload)
        ;

        if (!$response->successful()) {
            $this->error('新增失败，合同号 '.$payload['oc_contract_no'].'，状态码 '.$response->status());

            return false;
        }

        $data = $response->json();
        if (is_array($data) && ($data['code'] ?? 200) != 200) {
            $message = $data['message'] ?? '未知错误';
            $this->error('新增失败，合同号 '.$payload['oc_contract_no'].'，'.$message);

            return false;
        }

        return true;
    }

    private function buildRequestPayload(array $payload, bool $forCreate): array
    {
        $base = [
            'contractNo'    => $payload['oc_contract_no'],
            'plateType'     => $payload['oc_plate_type'],
            'plateNumber'   => $payload['oc_plate_number'],
            'rentalType'    => $payload['oc_rental_type'],
            'signedAt'      => $payload['oc_signed_at'],
            'rentalStartAt' => $payload['oc_rental_start_at'],
            'rentalEndAt'   => $payload['oc_rental_end_at'],
            'idDocType'     => $payload['oc_id_doc_type'],
            'idDocNo'       => $payload['oc_id_doc_no'],
        ];

        if ($forCreate) {
            $base['driverName'] = $payload['driver_name'] ?? null;
        }

        return $base;
    }

    private function buildUrl(OneAccount $account, ?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $domain = $account->oa_province_value['url'] ?? null;
        if (!$domain) {
            return null;
        }

        return rtrim($domain, '/').'/'.ltrim($url, '/');
    }

    private function normalizePlateNumber(string $plateNo): string
    {
        $plateNo = trim($plateNo);
        if ('' === $plateNo) {
            return $plateNo;
        }

        if (!function_exists('mb_substr')) {
            return $plateNo;
        }

        $firstChar = mb_substr($plateNo, 0, 1);
        if (strlen($firstChar) > 1) {
            return mb_substr($plateNo, 1);
        }

        return $plateNo;
    }
}
