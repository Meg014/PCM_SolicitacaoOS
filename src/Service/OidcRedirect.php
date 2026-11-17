<?php
declare(strict_types=1);
namespace App\Service;

final class OidcRedirect extends \RuntimeException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct('Redirecionamento OIDC.');
    }
}
