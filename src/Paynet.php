<?php

namespace Uzbek\Paynet;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Uzbek\Paynet\Exceptions\{InvalidTransactionParameters,
    TokenNotFound,
    TransactionNotFound,
    Unauthorized,
    UserInvalid
};
use Uzbek\Paynet\Response\{CancelTransactionResponse,
    DetailedReportByServiceId,
    DetailReportByPeriodResponse,
    LoginResponse,
    PerformTransactionResponse,
    ServicesResponse
};

class Paynet
{
    protected mixed $config;

    private ?string $token = null;

    private ?string $last_uid = null;

    private int $tokenLifeTime = 60 * 60 * 24;

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
            'Accept' => 'application/json',
        ])->withOptions($options);

        $this->login();
    }

    public function getServices(int $last_update_date): UserInvalid|ServicesResponse
    {
        $data = $this->sendRequest('getServices', [
            'last_updated_at' => $last_update_date,
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
                'terminal_id' => $this->terminalId,
            ]);

            if (empty($data['result']['token'])) {
                throw new TokenNotFound('Token not found');
            }

            return $data['result']['token'];
        });
    }

    public function changePassword(string $old_password, string $new_password): UserInvalid|LoginResponse
    {
        $data = $this->sendRequest('changePassword', [
            'old_password' => $old_password,
            'new_password' => $new_password,
        ]);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        return new LoginResponse(
            $data['result']['agentId'],
            $data['result']['terminalId'],
            $data['result']['userId'],
            $data['result']['userLogin'],
            $data['result']['token']
        );
    }

    public function performTransaction($params): PerformTransactionResponse|InvalidTransactionParameters|UserInvalid
    {
        $data = $this->sendRequest('performTransaction', $params);

        if (isset($data['result']['error']) && $data['result']['error']['code'] === -10000) {
            return new UserInvalid();
        }

        if (isset($data['result']['transactionId'], $data['result']['status'], $data['result']['response'])
            && is_null($data['result']['transactionId']) && $data['result']['status'] === '1' && is_null($data['result']['response'])) {
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

    public function checkTransactionByTransactionId(int $transaction_id, string $time): PerformTransactionResponse|UserInvalid
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

    public function checkTransactionByAgentId(int $id): PerformTransactionResponse|TransactionNotFound
    {
        $data = $this->sendRequest('checkTransactionByAgentId', [
            'id' => $id,
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

    public function detailedReportByPeriod(string $start_date, string $end_date): UserInvalid|DetailReportByPeriodResponse
    {
        $data = $this->sendRequest('summaryReportByDate', [
            'start_date' => $start_date,
            'end_date' => $end_date,
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

    public function detailedReportById(string $beginId, string $endId, $service_id = null): UserInvalid|DetailReportByPeriodResponse
    {
        $data = $this->sendRequest('detailedReportById', [
            'start_id' => $beginId,
            'end_id' => $endId,
            'service_id' => (string)$service_id,
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

    public function detailedReportByServiceId(string $beginId, string $endId, int $service_id): UserInvalid|array
    {
        $data = $this->sendRequest('detailedReportById', [
            'start_id' => $beginId,
            'end_id' => $endId,
            'service_id' => $service_id,
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

    public function cancelTransaction(string $transaction_id): CancelTransactionResponse|UserInvalid
    {
        $data = $this->sendRequest('cancelTransaction', [
            'transaction_id' => $transaction_id,
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

    public function reportTransaction(string $start_date, string $end_date, int $service_id, int $page = 0, int $count = 20): UserInvalid|array
    {
        $data = $this->sendRequest('detailedReportByDateTimeAndServiceId', [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'service_id' => $service_id,
            'page' => $page,
            'count' => $count,
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

        $res = $this->client->post($url, [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $uid,
            'token' => $this->token,
        ])->throw(fn($r, $e) => self::catchHttpRequestError($r, $e))->json();

        return $res;
    }

    private function generateUuid(): string
    {
        $this->last_uid = Uuid::uuid4();

        return $this->last_uid;
    }

    private static function catchHttpRequestError($res, $e)
    {
        if ($res['error']['code'] === -9999) {
            return new Unauthorized();
        }
        /*if ($res['error']['code'] === -9999) {
            return new Unauthorized();
        }
        if ($res['error']['code'] === -10000) {
            return new UserInvalid();
        }
        if ($res['error']['code'] === -12007) {
            return new TransactionNotFound();
        }*/
        throw $e;
    }
}
