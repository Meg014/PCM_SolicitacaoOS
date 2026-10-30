# Permissões

| Ação | Mantenedor | PCM | Administrador PCM |
|---|---|---|---|
| Criar e acompanhar próprias solicitações | Sim | Sim | Sim |
| Apontamentos, execução e anexos no escopo operacional existente | Sim | Sim | Sim |
| Ver todas / fila PCM | Não | Sim | Sim |
| Relatórios e histórico operacional | Não | Sim | Sim |
| Vincular O.S. TOTVS / cancelar com motivo | Não | Sim | Sim |
| Usuários, mão de obra, setores e cadastros administrativos existentes | Não | Não | Sim |
| Configurações e auditorias administrativas existentes | Não | Não | Sim |

Mantenedor acompanha também solicitações de acompanhamento compartilhado, conforme o fluxo existente. Minhas solicitações permanece restrita ao próprio usuário.

Migration `20261030000100_SimplifyUserRoles`: SOLICITANTE → MANTENEDOR; GESTOR e ENCARREGADO → PCM (ambos possuíam acesso geral à fila). MANTENEDOR, PCM e ADMIN_PCM permanecem iguais. Perfis desconhecidos interrompem a migration antes de qualquer alteração. Não há exclusão de usuários.

A tabela `user_role_migration_backup` guarda usuário, perfil anterior, perfil novo e data da migração. Rollback restaura apenas contas ainda no perfil atribuído pela migration. Snapshots históricos de auditoria preservam os perfis da época. A constraint do PostgreSQL aceita somente os três perfis vigentes.