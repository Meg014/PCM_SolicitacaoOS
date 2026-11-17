<?php
declare(strict_types=1);
namespace App\Service;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

final class SsoAudit
{
    public static function record(string $action, ?int $userId, array $details = []): void
    {
        $table = TableRegistry::getTableLocator()->get('RegistrationAudits');
        $table->saveOrFail($table->newEntity(['user_id' => $userId, 'resource' => 'SsoIdentities',
            'record_id' => $userId ?? 0, 'action' => $action, 'new_values' => $details,
            'created_at' => new DateTime('now', 'UTC')]));
    }
}
