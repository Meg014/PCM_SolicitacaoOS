<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UserDepartmentsFixture extends TestFixture
{
    public array $records = [
        ['id' => 1, 'name' => 'PCM teste', 'active' => true],
        ['id' => 2, 'name' => 'Moenda teste', 'active' => true],
        ['id' => 3, 'name' => 'Setor inativo', 'active' => false],
    ];
}
