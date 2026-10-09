<?php
declare(strict_types=1);

namespace App\Identifier;

use Authentication\Identifier\PasswordIdentifier;

class UsernameOrEmailIdentifier extends PasswordIdentifier
{
    public function identify(array $credentials): \ArrayAccess|array|null
    {
        if (isset($credentials[self::CREDENTIAL_USERNAME]) && is_string($credentials[self::CREDENTIAL_USERNAME])) {
            $credentials[self::CREDENTIAL_USERNAME] = strtolower(trim($credentials[self::CREDENTIAL_USERNAME]));
        }

        return parent::identify($credentials);
    }
}
