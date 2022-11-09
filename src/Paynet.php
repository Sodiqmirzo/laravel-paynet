<?php

namespace Uzbek\Paynet;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Uzbek\Paynet\Exceptions\InvalidTransactionParameters;
use Uzbek\Paynet\Exceptions\TokenNotFound;
use Uzbek\Paynet\Exceptions\TransactionNotFound;
use Uzbek\Paynet\Exceptions\UserInvalid;
use Uzbek\Paynet\Response\CancelTransactionResponse;
use Uzbek\Paynet\Response\DetailedReportByServiceId;
use Uzbek\Paynet\Response\DetailReportByPeriodResponse;
use Uzbek\Paynet\Response\LoginResponse;
use Uzbek\Paynet\Response\PaynetTransactions;
use Uzbek\Paynet\Response\PerformTransactionResponse;
use Uzbek\Paynet\Response\ServicesResponse;

class Paynet
{
    protected mixed $config;
    private ?string $token = null;
    private ?string $last_uid = null;
    private int $tokenLifeTime;

    protected string $username;

    protected string $password;

    protected string $terminalId;

    protected PendingRequest $client;

    public function __construct()
    {
        $this->config = config('paynet');
        $this->username = $this->config['username'];
        $this->password = $this->config['password'];
        $this->terminalId = $this->config['terminal_id'];
        $this->tokenLifeTime = $this->config['token_life_time'] ?? 0;

        $proxy_url = $this->config['proxy_url'] ?? (($this->config['proxy_proto'] ?? '') . '://' . ($this->config['proxy_host'] ?? '') . ':' . ($this->config['proxy_port'] ?? '')) ?? '';
        $options = is_string($proxy_url) && str_contains($proxy_url, '://') && strlen($proxy_url) > 12 ? ['proxy' => $proxy_url] : [];

        $this->client = Http::baseUrl($this->config['base_url'])->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->withOptions($options);

        $this->login();
    }

    public function getServices(int $last_update_date)
    {
        $data = $this->sendRequest('getServices', [
            'last_update_date' => $last_update_date
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        return new ServicesResponse($data['result']['last_update_date'], $data['result']['categories']);
    }

    public function login(): void
    {
        $this->token = cache()->remember('paynet_token', $this->tokenLifeTime, function () {
            $data = $this->sendRequest('login', [
                'username' => $this->username,
                'password' => $this->password,
                'terminal_id' => $this->terminalId
            ]);

            if (empty($data['result']['token'])) {
                throw new TokenNotFound('Token not found');
            }

            return $data['result']['token'];
        });
    }

    public function changePassword(string $old_password, string $new_password): ?LoginResponse
    {
        $data = $this->sendRequest('changePassword', [
            'old_password' => $old_password,
            'new_password' => $new_password,
        ]);

        return new LoginResponse($data);
    }

    public function performTransaction($params)
    {
        $data = $this->sendRequest('performTransaction', $params);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        if (isset($data['result']['transactionId'], $data['result']['status'], $data['result']['response'])
            && is_null($data['result']['transactionId']) && $data['result']['status'] === "1" && is_null($data['result']['response'])) {
            return new InvalidTransactionParameters();
        }

        return new PerformTransactionResponse(
            $data['result']['transactionId'],
            $data['result']['status'],
            $data['result']['statusText'],
            $data['result']['time'],
            $data['result']['response']
        );
    }

    public function checkTransactionByTransactionId(int $transaction_id, string $time)
    {
        $data = $this->sendRequest('checkTransaction', [
            'transaction_id' => $transaction_id,
            'time' => $time,
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        return new PerformTransactionResponse(
            $data['result']['transactionId'],
            $data['result']['status'],
            $data['result']['statusText'],
            $data['result']['time'],
            $data['result']['response']
        );
    }

    public function checkTransactionByAgentId(int $id)
    {
        $data = $this->sendRequest('checkTransactionByAgentId', [
            'id' => $id
        ]);

        if (isset($data['error']) && $data['error']['code'] === -12007) {
            return new TransactionNotFound();
        }

        return new PerformTransactionResponse(
            $data['result']['transactionId'],
            $data['result']['status'],
            $data['result']['statusText'],
            $data['result']['time'],
            $data['result']['response']
        );
    }

    public function detailedReportByPeriod(string $start_date, string $end_date)
    {
        $data = $this->sendRequest('summaryReportByDate', [
            "start_date" => $start_date,
            "end_date" => $end_date,
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        return new DetailReportByPeriodResponse(
            $data['result']['transactionCount'],
            $data['result']['transactionSum'],
            $data['result']['commissionFeeSum']
        );
    }

    public function detailedReportById(string $beginId, string $endId, $service_id = null)
    {
        $data = $this->sendRequest('detailedReportById', [
            "start_id" => $beginId,
            "end_id" => $endId,
            "service_id" => (string)$service_id,
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        return new DetailReportByPeriodResponse(
            $data['result']['transactionCount'],
            $data['result']['transactionSum'],
            $data['result']['commissionFeeSum']
        );
    }

    public function detailedReportByServiceId(string $beginId, string $endId, int $service_id)
    {
        $data = $this->sendRequest('detailedReportById', [
            "start_id" => $beginId,
            "end_id" => $endId,
            "service_id" => $service_id,
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        $res = [];

        foreach ($data['result'] as $item) {
            $res[] = new DetailedReportByServiceId(
                $item['terminalId'],
                $item['transactionId'],
                $item['customerId'],
                $item['amount'],
                $item['commissionFee'],
                $item['transactionStatusText'],
                $item['statusTime'],
                $item['status'],
                $item['id'],
            );
        }

        return $res;
    }

    public function cancelTransaction(string $transaction_id)
    {
        $data = $this->sendRequest('cancelTransaction', [
            "transaction_id" => $transaction_id,
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        return new CancelTransactionResponse(
            $data['result']['transactionId'],
            $data['result']['status'],
            $data['result']['statusText'],
            $data['result']['time'],
            $data['result']['response']
        );
    }

    public function reportTransaction(string $start_date, string $end_date, int $service_id, int $page = 0, int $count = 20)
    {
        $data = $this->sendRequest('detailedReportByDateTimeAndServiceId', [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "service_id" => $service_id,
            "page" => $page,
            "count" => $count,
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        $res = [];

        foreach ($data['result'] as $item) {
            $res[] = new DetailedReportByServiceId(
                $item['terminalId'],
                $item['transactionId'],
                $item['customerId'],
                $item['amount'],
                $item['commissionFee'],
                $item['transactionStatusText'],
                $item['statusTime'],
                $item['status'],
                $item['id'],
            );
        }

        return $res;
    }

    public function getLogo(int $provider_id, int $size = 128)
    {
        $query = ['providerId' => $provider_id, 'size' => $size];
        return $this->client->get('/gw/getLogo?' . http_build_query($query));
    }

    public function sendRequest($method, $params = [])
    {
        $url = '/gw/transaction';

        $uid = $this->generateUuid();

        $req = [
            "jsonrpc" => "2.0",
            "method" => $method,
            "params" => $params,
            "id" => $uid,
            "token" => $this->token,
        ];

        $res = $this->client->post($url, [
            'json' => $req
        ])->json();

        return $res;
    }

    private function generateUuid(): string
    {
        $this->last_uid = Uuid::uuid4();
        return $this->last_uid;
    }

    private static function catchHttpRequestError($res, $e)
    {
        throw $e;
    }
}
