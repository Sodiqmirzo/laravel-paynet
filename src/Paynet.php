<?php

namespace Uzbek\Paynet;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Uzbek\Paynet\Exceptions\InvalidTransactionParameters;
use Uzbek\Paynet\Exceptions\PaynetException;
use Uzbek\Paynet\Exceptions\TokenNotFound;
use Uzbek\Paynet\Exceptions\TransactionNotFound;
use Uzbek\Paynet\Exceptions\Unauthorized;
use Uzbek\Paynet\Exceptions\UserInvalid;
use Uzbek\Paynet\Response\LoginResponse;
use Uzbek\Paynet\Response\PaynetTransactions;
use Uzbek\Paynet\Response\ServicesResponse;

class Paynet
{
    protected mixed $config;

    protected string $username;

    protected string $password;

    protected string $terminalId;

    protected PendingRequest $client;

    private ?string $token = null;

    private ?string $last_uid = null;

    private int $tokenLifeTime = 60 * 60 * 24;

    public function __construct()
    {
        $this->config = config('paynet');
        $this->username = $this->config['username'];
        $this->password = $this->config['password'];
        $this->terminalId = $this->config['terminal_id'];
        $this->tokenLifeTime = $this->config['token_life_time'] ?? 0;

        $proxy_url = $this->config['proxy_url'] ?? (($this->config['proxy_proto'] ?? '').'://'.($this->config['proxy_host'] ?? '').':'.($this->config['proxy_port'] ?? '')) ?? '';
        $options = is_string($proxy_url) && str_contains($proxy_url, '://') && strlen($proxy_url) > 12 ? ['proxy' => $proxy_url] : [];

        $this->client = Http::baseUrl($this->config['base_url'])->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->withOptions($options);

        $this->login();
    }

    public function login(): void
    {
        $this->token = cache()->remember('paynet_token', $this->tokenLifeTime, function () {
            $data = $this->sendRequest('login', [
                'username' => $this->username,
                'password' => $this->password,
                'terminal_id' => $this->terminalId,
            ]);

            if (empty($data['token'])) {
                throw new TokenNotFound('Token not found');
            }

            return $data['token'];
        });
    }

    /**
     * @throws UserInvalid
     * @throws RequestException
     * @throws TransactionNotFound
     * @throws InvalidTransactionParameters
     * @throws \Exception
     */
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
        ])->throw(fn ($r, $e) => self::catchHttpRequestError($r, $e))->json();

        $result = $res['result'] ?? null;
        $response = $result['response'] ?? null;
        $error = $res['error'] ?? null;

        if ($error && $error['code'] === -10000) {
            throw new UserInvalid($error['message'], $error['code']);
        }

        if ($error && $error['code'] === -12007) {
            throw new TransactionNotFound($error['message'], $error['code']);
        }

        if ($error) {
            throw new PaynetException($error['message'] ?? 'Serverda xatolik.', $error['code']);
        }

        if ($result !== null && $response === null && isset($result['status'])) {
            throw new PaynetException($result['statusText'], $result['status']);
        }

        return $result;
    }

    private function generateUuid(): string
    {
        $this->last_uid = Uuid::uuid4();

        return $this->last_uid;
    }

    /**
     * @param $res
     * @param $e
     *
     * @throws TransactionNotFound
     * @throws Unauthorized
     * @throws UserInvalid
     */
    private static function catchHttpRequestError($res, $e)
    {
        throw match ($res['error']['code']) {
            -9999 => new Unauthorized($res['error']['message']),
            -10000 => new UserInvalid($res['error']['message']),
            -12007 => new TransactionNotFound($res['error']['message']),
            default => $e,
        };
    }

    public function getServices(int $last_update_date): ServicesResponse
    {
        $data = $this->sendRequest('getServices', [
            'last_updated_at' => $last_update_date,
        ]);

        return new ServicesResponse($data['last_update_date'], $data['categories']);
    }

    /**
     * @throws UserInvalid
     * @throws TransactionNotFound
     * @throws RequestException
     * @throws InvalidTransactionParameters
     */
    public function changePassword(string $old_password, string $new_password): LoginResponse
    {
        $data = $this->sendRequest('changePassword', [
            'old_password' => $old_password,
            'new_password' => $new_password,
        ]);

        return new LoginResponse(
            $data['agentId'],
            $data['terminalId'],
            $data['userId'],
            $data['userLogin'],
            $data['token']
        );
    }

    /**
     * @throws \Exception
     */
    public function performTransaction(string $service_id, array $fields)
    {
        $request = $this->sendRequest('performTransaction', [
            'id' => random_int(100000000000000000, 999999999999999999),
            'service_id' => $service_id,
            'time' => time(),
            'fields' => $fields,
        ]);

        return new PaynetTransactions($request->result);
    }

    /**
     * @throws UserInvalid
     * @throws TransactionNotFound
     * @throws RequestException
     * @throws InvalidTransactionParameters
     */
    public function checkTransactionByTransactionId(string $transaction_id, string $time)
    {
        return $this->sendRequest('checkTransaction', [
            'transaction_id' => $transaction_id,
            'time' => $time,
        ]);
    }

    /**
     * @throws UserInvalid
     * @throws TransactionNotFound
     * @throws RequestException
     * @throws InvalidTransactionParameters
     */
    public function checkTransactionByAgentId(string $id)
    {
        return $this->sendRequest('checkTransactionByAgentId', [
            'id' => $id,
        ]);
    }

    /**
     * @param  string  $start_date
     * @param  string  $end_date
     * @return array|mixed
     *
     * @throws InvalidTransactionParameters
     * @throws RequestException
     * @throws TransactionNotFound
     * @throws UserInvalid
     */
    public function detailedReportByPeriod(string $start_date, string $end_date): mixed
    {
        return $this->sendRequest('summaryReportByDate', [
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * @param  string  $beginId
     * @param  string  $endId
     * @param  null  $service_id
     * @return array|mixed
     *
     * @throws InvalidTransactionParameters
     * @throws RequestException
     * @throws TransactionNotFound
     * @throws UserInvalid
     */
    public function detailedReportById(string $beginId, string $endId, $service_id = null): mixed
    {
        return $this->sendRequest('detailedReportById', [
            'start_id' => $beginId,
            'end_id' => $endId,
            'service_id' => (string) $service_id,
        ]);
    }

    /**
     * @param  string  $beginId
     * @param  string  $endId
     * @param  string  $service_id
     * @return array|mixed
     *
     * @throws InvalidTransactionParameters
     * @throws RequestException
     * @throws TransactionNotFound
     * @throws UserInvalid
     */
    public function detailedReportByServiceId(string $beginId, string $endId, string $service_id): mixed
    {
        return $this->sendRequest('detailedReportById', [
            'start_id' => $beginId,
            'end_id' => $endId,
            'service_id' => $service_id,
        ]);
    }

    /**
     * @param  string  $transaction_id
     * @return array|mixed
     *
     * @throws InvalidTransactionParameters
     * @throws RequestException
     * @throws TransactionNotFound
     * @throws UserInvalid
     */
    public function cancelTransaction(string $transaction_id): mixed
    {
        return $this->sendRequest('cancelTransaction', [
            'transaction_id' => $transaction_id,
        ]);
    }

    /**
     * @param  string  $start_date
     * @param  string  $end_date
     * @param  string  $service_id
     * @param  int  $page
     * @param  int  $count
     * @return array|mixed
     *
     * @throws InvalidTransactionParameters
     * @throws RequestException
     * @throws TransactionNotFound
     * @throws UserInvalid
     */
    public function reportTransaction(string $start_date, string $end_date, string $service_id, int $page = 0, int $count = 20): mixed
    {
        return $this->sendRequest('detailedReportByDateTimeAndServiceId', [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'service_id' => $service_id,
            'page' => $page,
            'count' => $count,
        ]);
    }

    public function getLogo(string $provider_id, int $size = 128)
    {
        $query = ['providerId' => $provider_id, 'size' => $size];

        return $this->client->get('/gw/getLogo?'.http_build_query($query));
    }
}
