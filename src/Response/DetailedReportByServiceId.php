<?php
/**
 * Created by Sodikmirzo.
 * User: Sodikmirzo Sattorov ( https://github.com/Sodiqmirzo )
 * Date: 11/9/2022
 * Time: 2:57 PM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class DetailedReportByServiceId extends Data
{
    public function __construct(
        public string $terminalId,
        public int    $transactionId,
        public string $customerId,
        public float  $amount,
        public float  $commissionFee,
        public string $transactionStatusText,
        public string $statusTime,
        public int    $status,
        public string $id,
    )
    {
    }
}
