<?php
/**
 * Date: 11/1/2022
 * Time: 10:59 AM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class PerformTransactionResponse extends Data
{
    public function __construct(
        public string $key,
        public string $labelRu,
        public string $labelUz,
        public string $value,
    ) {
    }
}
