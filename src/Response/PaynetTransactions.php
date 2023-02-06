<?php
/**
 * Date: 11/1/2022
 * Time: 11:01 AM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class PaynetTransactions extends Data
{
    public function __construct(
        public string                     $transactionId,
        public string                     $status,
        public string                     $statusText,
        public int                        $time,
        public PerformTransactionResponse $response,
    )
    {
    }

    public function isOk(): bool
    {
        return $this->status === '0';
    }
}
