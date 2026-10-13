<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| For CustomerAuthTest.php
|--------------------------------------------------------------------------
*/

/**
 * @return array<string, string>
 */
function newCustomerDetails(string $email = 'ada@example.com'): array
{
    return [
        'name' => 'Ada Lovelace',
        'email' => $email,
        'password' => 'a-long-shopper-password',
        'password_confirmation' => 'a-long-shopper-password',
        'device_name' => 'Ada\'s phone',
    ];
}
