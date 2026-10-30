<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SimplifyUserRoles extends BaseMigration
{
    public function up(): void
    {
        // Abort before changing anything if a profile needs an explicit decision.
        $unknown = $this->fetchRow("SELECT COUNT(*) AS total FROM users WHERE role IS NULL OR role NOT IN ('SOLICITANTE', 'MANTENEDOR', 'GESTOR', 'ENCARREGADO', 'PCM', 'ADMIN_PCM')");
        if ((int)$unknown['total'] > 0) {
            throw new RuntimeException('Existem perfis desconhecidos. Defina o mapeamento antes de migrar.');
        }
        $this->execute('CREATE TABLE user_role_migration_backup (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE RESTRICT, old_role VARCHAR(50) NOT NULL, new_role VARCHAR(50) NOT NULL, migrated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->execute("INSERT INTO user_role_migration_backup (user_id, old_role, new_role) SELECT id, role, CASE WHEN role = 'SOLICITANTE' THEN 'MANTENEDOR' ELSE 'PCM' END FROM users WHERE role IN ('SOLICITANTE', 'GESTOR', 'ENCARREGADO')");
        $this->execute('UPDATE users SET role = backup.new_role FROM user_role_migration_backup backup WHERE users.id = backup.user_id');
        $this->execute("ALTER TABLE users ADD CONSTRAINT users_functional_role_check CHECK (role IN ('MANTENEDOR', 'PCM', 'ADMIN_PCM'))");
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE users DROP CONSTRAINT users_functional_role_check');
        // Preserve subsequent administrator changes to a different profile.
        $this->execute('UPDATE users SET role = backup.old_role FROM user_role_migration_backup backup WHERE users.id = backup.user_id AND users.role = backup.new_role');
        $this->execute('DROP TABLE user_role_migration_backup');
    }
}
