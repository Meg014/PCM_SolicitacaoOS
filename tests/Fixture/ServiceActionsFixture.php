<?php
declare(strict_types=1);
namespace App\Test\Fixture;
class ServiceActionsFixture extends \Cake\TestSuite\Fixture\TestFixture
{
    public function init(): void
    {
        $this->records = array_map(fn($row) => $row + ['active'=>true, 'created_at'=>'2026-11-04 12:00:00', 'updated_at'=>'2026-11-04 12:00:00'], [['id'=>1,'name'=>'Limpar'], ['id'=>2,'name'=>'Soldar'], ['id'=>3,'name'=>'Emendar'], ['id'=>4,'name'=>'Inativa','active'=>false]]);
        parent::init();
    }
}
