<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AllowOptionalUsernames extends BaseMigration
{
    public function up(): void
    {
        $this->table('users')->changeColumn('username', 'string', ['limit' => 60, 'null' => true])->update();
        $this->execute("ALTER TABLE users ADD CONSTRAINT users_login_identifier_required CHECK
            (NULLIF(btrim(username), '') IS NOT NULL OR NULLIF(btrim(email), '') IS NOT NULL)");
    }

    public function down(): void
    {
        if ($this->fetchRow('SELECT id FROM users WHERE username IS NULL LIMIT 1')) {
            throw new RuntimeException('Existem usuários apenas com e-mail; não é seguro voltar a exigir matrícula.');
        }
        $this->execute('ALTER TABLE users DROP CONSTRAINT users_login_identifier_required');
        $this->table('users')->changeColumn('username', 'string', ['limit' => 60, 'null' => false])->update();
    }
}
