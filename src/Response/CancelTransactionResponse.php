<?php
/**
 * Created by Sodikmirzo.
 * User: Sodikmirzo Sattorov ( https://github.com/Sodiqmirzo )
 * Date: 11/9/2022
 * Time: 3:09 PM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class CancelTransactionResponse extends Data
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
