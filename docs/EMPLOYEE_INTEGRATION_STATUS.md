# Funcionários TOTVS — atualização de 03/11/2026

Fase de apontamentos implementada: grade compartilhada, autocomplete autenticado em `/api/funcionarios`, validação no servidor e snapshots locais. Migration `20261103000100_AddEmployeeSnapshotsToRequestItems` aplicada.

O diagnóstico `php bin/cake.php check_totvs_employees` agora usa exclusivamente os quatro campos autorizados e confirmou SELECT, sem retornar funcionários: RA_MAT varchar(6), RA_NOME varchar(30), RA_CODFUNC varchar(5), RA_SITFOLH varchar(1). A negação registrada em 26/10 envolvia uma consulta anterior com campos adicionais; não descreve o acesso atual.

A pesquisa consulta até 20 funcionários por nome/matrícula, parametrizada, limitada às funções locais ativas e situações vazio/espaço ou F. A/D são excluídas. Mantém zeros à esquerda. Códigos numéricos de função com até cinco dígitos são completados à esquerda: `32` na planilha equivale a `00032` no TOTVS, sem conversão para inteiro. O snapshot mantém o código retornado pelo TOTVS.

Correção do autocomplete: a consulta anterior usava `dbo.SRA010` no banco padrão da conexão, que validava os campos mas não encontrava funcionários. A busca agora usa `DADOSP12.dbo.SRA010` e o padrão validado na Entressafra: `UPPER(... ) LIKE UPPER(:termo)` para nome e matrícula, com curingas escapados e parâmetros. A lista de funções locais e a situação continuam sendo filtradas na consulta.

O banco principal contém **45 funções permitidas ativas**, importadas do arquivo anexado `C:\Users\maria.goncalves\Desktop\PLANILHAS\Função.xlsx`, com códigos completados à esquerda para cinco dígitos e descrições conferidas integralmente. Importação auditada, sem importar funcionários.

Salvamento revalida seleção e guarda employee_registration, employee_name, employee_function_code e employee_function_name em request_items e na auditoria. Matrículas ambíguas são recusadas. Antigos executantes livres permanecem visíveis; histórico não depende do TOTVS.

Testes usam funcionários simulados; a busca real por nome e revalidação por matrícula também foram verificadas após a importação; a conferência visual pelo usuário permanece disponível. Ambiente disponível PHP 8.2.12; repetir homologação em PHP 8.3+.

Validação final: 195 testes PHPUnit aprovados; 48 verificações no Edge headless (360/768/1366 px); Composer, sintaxe PHP e diff aprovados. Interações verificadas: inclusão/remoção até lista vazia, reinclusão, renumeração, seleção por teclado/clique, invalidação ao editar, obrigatoriedade, IDs únicos, altura de toque e ausência de transbordamento horizontal. Após a importação, busca real por nome retornou 10 resultados permitidos e revalidação por matrícula foi aprovada, exclusivamente por SELECT.
