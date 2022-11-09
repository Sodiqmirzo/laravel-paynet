<?php
/**
 * Created by Sodikmirzo.
 * User: Sodikmirzo Sattorov ( https://github.com/Sodiqmirzo )
 * Date: 11/9/2022
 * Time: 1:11 PM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class ServicesResponse extends Data
{
    public function __construct(
        public int $lastUpdatedAtUtc,
        public array $categories,
    ) {
    }
}
