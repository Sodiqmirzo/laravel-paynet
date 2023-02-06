<?php
/**
 * Date: 11/1/2022
 * Time: 10:57 AM
 */

namespace Uzbek\Paynet\Response;

use Spatie\LaravelData\Data;

class LoginResponse extends Data
{
    public function __construct(
        public int $agentId,
        public string $terminalId,
        public int $userId,
        public string $userLogin,
        public string $token,
    ) {
    }
}
