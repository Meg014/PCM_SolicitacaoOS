<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddUsernames extends BaseMigration
{
    public function up(): void
    {
        $this->table('users')->addColumn('username', 'string', ['limit' => 60, 'null' => true])
            ->changeColumn('email', 'string', ['limit' => 254, 'null' => true])->update();
        // Sufixo do ID evita colisões entre contas existentes, sem alterar suas senhas.
        $this->execute("UPDATE users SET username = COALESCE(NULLIF(LEFT(TRIM(BOTH '.' FROM regexp_replace(lower(split_part(COALESCE(email, ''), '@', 1)), '[^a-z0-9._-]', '', 'g')), 45), ''), 'usuario') || '.' || id::text");
        $this->execute("UPDATE users SET email = NULL WHERE TRIM(COALESCE(email, '')) = ''");
        $this->table('users')->changeColumn('username', 'string', ['limit' => 60, 'null' => false])->addIndex(['username'], ['unique' => true])->update();
    }

    public function down(): void
    {
        // Não fabricar e-mails para contas que passaram a existir sem e-mail.
        if ($this->fetchRow('SELECT id FROM users WHERE email IS NULL LIMIT 1')) {
            throw new RuntimeException('Preencha os e-mails das contas antes de reverter esta migration.');
        }
        $this->table('users')->removeIndex(['username'])->removeColumn('username')
            ->changeColumn('email', 'string', ['limit' => 254, 'null' => false])->update();
    }
}
