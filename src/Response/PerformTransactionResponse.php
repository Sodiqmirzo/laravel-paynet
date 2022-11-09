<?php
/**
 * Created by Sodikmirzo.
 * User: Sodikmirzo Sattorov ( https://github.com/Sodiqmirzo )
 * Date: 11/1/2022
 * Time: 10:59 AM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class PerformTransactionResponse extends Data
{
    public function __construct(
        public string $transactionId,
        public string $status,
        public string $statusText,
        public string $time,
        public array  $response,
    )
    {
    }
}
