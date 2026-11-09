<?php
declare(strict_types=1);
namespace App\Test\Fixture;
class ServiceComponentsFixture extends \Cake\TestSuite\Fixture\TestFixture
{
    public function init(): void
    {
        $this->records = array_map(fn($row) => $row + ['active'=>true, 'created_at'=>'2026-11-04 12:00:00', 'updated_at'=>'2026-11-04 12:00:00'], [['id'=>1,'name'=>'Geral'], ['id'=>2,'name'=>'Chapa'], ['id'=>3,'name'=>'Cabo'], ['id'=>4,'name'=>'Motor'], ['id'=>5,'name'=>'Inativo','active'=>false]]);
        parent::init();
    }
}
