# Funcionários TOTVS — verificação de 26/10/2026

Projeto confirmado: `C:\PCM\Sistemas\PCM-Solicitacao-OS`.

## Evidências e impedimento

Consulta pela conexão existente `protheus`, protegida pelo driver de somente leitura.
Metadados de `dbo.SRA010` confirmaram os campos abaixo, todos `varchar`:

| Campo | Tamanho |
| --- | --- |
| RA_MAT | 6 |
| RA_NOME | 30 |
| RA_CODFUNC | 5 |
| RA_SITFOLH | 1 |
| RA_FILIAL | 2 |
| D_E_L_E_T_ | 1 |

O SELECT TOP (0) conjunto desses campos recebeu negação de permissão (SQLSTATE
42000). Nenhum funcionário foi retornado. Visibilidade de metadados não comprova
permissão de leitura dos campos. Não se tentou outra credencial, tabela ou acesso.
Não foi possível determinar qual campo específico provocou a negação.

A TI precisa avaliar e liberar somente SELECT dos campos necessários à credencial
já configurada. Depois, executar `php bin/cake.php check_totvs_employees`.
O comando consulta metadados e zero registros, sem dados pessoais ou escrita.

## Estado da implementação

A importação local de funções já estava presente ao iniciar este trabalho, assim
como a migration `20261026000100_CreateAuthorizedFunctions.php`. Foi preservada.
Prévia de `C:\Users\maria.goncalves\Desktop\Função.xlsx`: 45 funções válidas.
Não foi encontrado `Função(1).xlsx`; confirmação da fonte está pendente.
Nenhuma importação no banco principal foi feita nesta execução.

Autocomplete, identidade de funcionário e nova interface ainda não foram
implementados: a consulta real precisa ser confirmada primeiro, conforme o pedido.
Não presumir equivalência entre códigos numéricos da planilha e códigos TOTVS
com zeros. Não presumir significado de RA_SITFOLH nem traduzir vazio como NORMAL.

O backend atual exige executante livre, data ISO e horários HH:MM, aceita horários
iguais e rejeita fim anterior ao início. Descrição possui limite de 2000 caracteres
com rejeição, sem truncamento. Não foi encontrada regra de sobreposição em
RequestItemPreparation, RequestItemsTable ou RequestExecutionService.
Vínculos, permissões, auditoria e validações existentes permanecem em uso.

A imagem citada no pedido não está disponível nesta conversa. A tabela textual
fornecida permite orientar a implementação posterior. HH versus HH:MM precisa
ser tratado sem eliminar a precisão e as validações existentes.

## Verificações executadas

- Prévia XLSX: 45 funções válidas, sem gravação.
- Testes da importação existente: 9 testes, 29 verificações, aprovados.
- Testes de serviços: 19 testes, 104 verificações, aprovados.
- Suíte completa: 181 testes, 1494 verificações, aprovada.
- Sintaxe PHP dos arquivos de diagnóstico e `git diff --check`: aprovados.
- Diagnóstico real: metadados disponíveis, SELECT de campos negado.

Esses resultados verificam o sistema atual e o diagnóstico; não comprovam os dez
cenários solicitados para a nova interface, ainda pendentes de implementação.
