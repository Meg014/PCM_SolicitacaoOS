<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class AuthorizedFunctionsFixture extends TestFixture
{
    public array $records = [
        ['id' => 1, 'code' => '00005', 'name' => 'Mecânico', 'active' => true, 'created_at' => '2026-11-03 12:00:00', 'updated_at' => '2026-11-03 12:00:00'],
        ['id' => 2, 'code' => '00006', 'name' => 'Inativa', 'active' => false, 'created_at' => '2026-11-03 12:00:00', 'updated_at' => '2026-11-03 12:00:00'],
    ];
}
