# Integração TOTVS

## Princípio
O TOTVS é fonte oficial dos Bens. A aplicação não duplica o cadastro completo de equipamentos.

## Conexão
Criar uma conexão separada, por exemplo `totvs`, apontando para SQL Server.

Requisitos:
- usuário SQL com permissão SELECT apenas;
- timeout curto;
- nenhuma migration nessa conexão;
- nenhuma escrita;
- credenciais em ambiente local/secret.

## Serviço
Criar um único ponto de acesso, por exemplo:
`src/Service/TotvsAssetsService.php`

Responsabilidades:
- normalizar busca;
- buscar por código e descrição;
- limitar quantidade de resultados;
- retornar DTO/array limpo com apenas os campos necessários;
- esconder detalhes do banco TOTVS dos Controllers.

## Endpoint sugerido
`GET /api/totvs/assets?q=texto`

Resposta conceitual:
```json
[
  {
    "code": "BEM00123",
    "description": "BOMBA AGUA 04"
  }
]
```

## Validação no salvamento
Não confiar apenas no valor enviado pelo frontend. Validar que o código selecionado existe no TOTVS antes de salvar.

Se o TOTVS estiver indisponível:
- não aceitar texto livre como se fosse Bem válido;
- mostrar mensagem clara;
- permitir tentar novamente.
