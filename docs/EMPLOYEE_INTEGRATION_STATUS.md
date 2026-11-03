# Funcionários TOTVS — atualização de 03/11/2026

Fase de apontamentos implementada: grade compartilhada, autocomplete autenticado em `/api/funcionarios`, validação no servidor e snapshots locais. Migration `20261103000100_AddEmployeeSnapshotsToRequestItems` aplicada.

O diagnóstico `php bin/cake.php check_totvs_employees` agora usa exclusivamente os quatro campos autorizados e confirmou SELECT, sem retornar funcionários: RA_MAT varchar(6), RA_NOME varchar(30), RA_CODFUNC varchar(5), RA_SITFOLH varchar(1). A negação registrada em 26/10 envolvia uma consulta anterior com campos adicionais; não descreve o acesso atual.

A pesquisa consulta até 20 funcionários por nome/matrícula, parametrizada, limitada às funções locais ativas e situações vazio/espaço ou F. A/D são excluídas. Mantém zeros à esquerda. Correspondência de função é textual exata, sem converter `5` em `00005`.

O banco principal contém **zero funções permitidas ativas**. A busca retorna vazio sem ler funcionários. Pendente importar/ativar as funções autorizadas pelo processo administrativo existente e conferir a correspondência real dos códigos; esta fase não altera cadastros nem importa pessoas.

Salvamento revalida seleção e guarda employee_registration, employee_name, employee_function_code e employee_function_name em request_items e na auditoria. Matrículas ambíguas são recusadas. Antigos executantes livres permanecem visíveis; histórico não depende do TOTVS.

Testes usam funcionários simulados; a consulta de pessoas reais e a conferência visual pelo usuário dependem das funções ativas. Ambiente disponível PHP 8.2.12; repetir homologação em PHP 8.3+.

Validação final: 195 testes PHPUnit aprovados; 48 verificações no Edge headless (360/768/1366 px); Composer, sintaxe PHP e diff aprovados. Interações verificadas: inclusão/remoção até lista vazia, reinclusão, renumeração, seleção por teclado/clique, invalidação ao editar, obrigatoriedade, IDs únicos, altura de toque e ausência de transbordamento horizontal. Fonte real de pessoas ainda não homologada por ausência de funções ativas.
