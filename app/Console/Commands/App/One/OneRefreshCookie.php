<?php

namespace App\Console\Commands\App\One;

use App\Enum\One\OaOaType;
use App\Models\One\OneAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:one-cookie:refresh',
    description: 'Refresh cookies for 122.gov.cn service'
)]
class OneRefreshCookie extends Command
{
    private const REQUEST_HEADERS = [
        'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language'           => 'zh',
        'Cache-Control'             => 'no-cache',
        'Connection'                => 'keep-alive',
        'Pragma'                    => 'no-cache',
        'Referer'                   => 'https://gab.122.gov.cn/',
        'Upgrade-Insecure-Requests' => '1',
        'User-Agent'                => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
    ];

    protected $signature   = '_app:one-cookie:refresh';
    protected $description = 'Refresh cookies for 122.gov.cn service';

    public function handle(): int
    {
        $accounts = OneAccount::query()
            ->where(function (Builder $query) {
                $query->whereNull('cookie_refresh_at')
                    ->orWhere('cookie_refresh_at', '>=', now()->subMinutes(90))
                ;
            })
            ->whereRaw('LENGTH(cookie_string) > ?', [30])
            ->orderBy('cookie_refresh_at', 'DESC')
            ->get()
        ;

        foreach ($accounts as $account) {
            switch ($account->oa_type) {
                case OaOaType::PERSON:
                    $this->processPerson($account);

                    break;

                case OaOaType::COMPANY:
                    $this->processCompany($account);

                    break;

                default:
                    break;
            }
        }

        return CommandAlias::SUCCESS;
    }

    private function processPerson(OneAccount $oneAccount): void
    {
        $client    = new Client(['cookies' => true]);
        $cookieJar = $oneAccount->initializeCookies();

        $domain = $oneAccount->oa_province_value['url'];

        $location = $domain.'/views/member/';

        $requestCount = 1;

        $response = null;

        while ($location && $requestCount <= 10) {
            $response = $this->makeRequest($oneAccount, $client, $location, $cookieJar);

            $location = $response->getHeaderLine('Location');

            ++$requestCount;
        }

        $html = (string) $response->getBody();

        if (str_contains($html, $searchString = '欢迎')) {
            Log::channel('console')->info('Response Body contain : '.$searchString);

            $oneAccount->cookie_refresh_at = now();
            $oneAccount->save();
        } else {
            $disk     = Storage::disk('local');
            $filePath = sprintf('html/response_body_%s.html', date('YmdHisv'));
            $disk->put($filePath, $html);

            if (str_contains($html, $searchString = '个人用户登录')) {
                Log::channel('console')->info('Response Body contain : '.$searchString);
                $oneAccount->cookie_string = null;
                $oneAccount->save();
            }
        }
    }

    private function makeRequest(OneAccount $oneAccount, Client $client, string $url, FileCookieJar $cookieJar)
    {
        Log::channel('console')->info("Request URL: {$url}");

        $domain = $oneAccount->oa_province_value['url'];

        $header = self::REQUEST_HEADERS;

        $header['Referer'] = $domain.'/';

        $debugResource = fopen(storage_path(sprintf('logs/122-%d-%s.log', $oneAccount->oa_id, date('Y-m-d'))), 'a+');

        $response = $client->get($url, [
            'headers'         => $header,
            'cookies'         => $cookieJar,
            'allow_redirects' => false,
            'debug'           => $debugResource,
        ]);

        fclose($debugResource);

        return $response;
    }

    private function processCompany(OneAccount $oneAccount)
    {
        $client    = new Client(['cookies' => true]);
        $cookieJar = $oneAccount->initializeCookies();

        $domain = $oneAccount->oa_province_value['url'];

        $location = $domain.'/views/memfyy/'; // /views/memrent/vehlist.html

        $requestCount = 1;

        $response = null;

        while ($location && $requestCount <= 10) {
            $response = $this->makeRequest($oneAccount, $client, $location, $cookieJar);

            $location = $response->getHeaderLine('Location');

            ++$requestCount;
        }

        $html = (string) $response->getBody();

        if (str_contains($html, $searchString = '欢迎')) {
            Log::channel('console')->info('Response Body contain : '.$searchString);

            $oneAccount->cookie_refresh_at = now();
            $oneAccount->save();
        } else {
            $disk     = Storage::disk('local');
            $filePath = sprintf('html/response_body_%s.html', date('YmdHisv'));
            $disk->put($filePath, $html);

            if (str_contains($html, $searchString = '单位用户登录')) {
                Log::channel('console')->info('Response Body contain : '.$searchString);
                $oneAccount->cookie_string = null;
                $oneAccount->save();
            }
        }
    }
}
