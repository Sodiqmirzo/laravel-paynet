<?php
/**
 * Created by Sodikmirzo.
 * User: Sodikmirzo Sattorov ( https://github.com/Sodiqmirzo )
 * Date: 11/9/2022
 * Time: 2:52 PM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class DetailReportByPeriodResponse extends Data
{
    public function __construct(
        public int $transactionCount,
        public int $transactionSum,
        public int $commissionFeeSum,
    )
    {
    }
}
