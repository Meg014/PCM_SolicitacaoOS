<?php
declare(strict_types=1);
namespace App\Test\Fixture;
use Cake\TestSuite\Fixture\TestFixture;

class SsoIdentitiesFixture extends TestFixture
{
    public array $records = [
        ['id' => 1, 'user_id' => 1, 'issuer' => 'https://sso.example.test/realms/pcm', 'subject' => 'linked-id', 'created_at' => '2026-11-16 12:00:00'],
    ];
}
