<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

require_once ROOT . '/config/Migrations/20261030000100_SimplifyUserRoles.php';

class UserRoleMigrationTest extends TestCase
{
    public function testMappingBackupConstraintAndRollback(): void
    {
        $db = ConnectionManager::get('test');
        $db->begin();
        try {
            // Isolated schema: never modify actual application users.
            $db->execute('CREATE SCHEMA roles_migration_test');
            $db->execute('SET LOCAL search_path TO roles_migration_test');
            $db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, role VARCHAR(20))');
            $db->execute("INSERT INTO users VALUES (1, 'SOLICITANTE'), (2, 'GESTOR'), (3, 'ENCARREGADO'), (4, 'MANTENEDOR'), (5, 'PCM'), (6, 'ADMIN_PCM')");
            $migration = new class extends \SimplifyUserRoles {
                public function execute(string $sql, array $params = []): int
                {
                    return ConnectionManager::get('test')->execute($sql, $params)->rowCount();
                }

                public function fetchRow(string $sql): array|false
                {
                    return ConnectionManager::get('test')->execute($sql)->fetch('assoc');
                }
            };
            $migration->up();
            $this->assertSame(['MANTENEDOR', 'PCM', 'PCM', 'MANTENEDOR', 'PCM', 'ADMIN_PCM'],
                array_column($db->execute('SELECT role FROM users ORDER BY id')->fetchAll('assoc'), 'role'));
            $this->assertSame(3, (int)$db->execute('SELECT COUNT(*) AS total FROM user_role_migration_backup')->fetch('assoc')['total']);
            $this->assertNotFalse($db->execute("SELECT 1 FROM pg_constraint WHERE conname = 'users_functional_role_check' AND conrelid = 'users'::regclass")->fetch('assoc'));
            $db->execute("UPDATE users SET role = 'ADMIN_PCM' WHERE id = 2");
            $migration->down();
            $this->assertSame(['SOLICITANTE', 'ADMIN_PCM', 'ENCARREGADO', 'MANTENEDOR', 'PCM', 'ADMIN_PCM'],
                array_column($db->execute('SELECT role FROM users ORDER BY id')->fetchAll('assoc'), 'role'));
            $db->execute("UPDATE users SET role = 'UNKNOWN' WHERE id = 1");
            try {
                $migration->up();
                $this->fail('Perfil desconhecido deve interromper a migration.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('perfis desconhecidos', $e->getMessage());
                $this->assertNull($db->execute("SELECT to_regclass('user_role_migration_backup') AS backup")->fetch('assoc')['backup']);
            }
        } finally {
            $db->rollback();
        }
    }
}
