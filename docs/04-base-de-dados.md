# Base de dados (modelo conceptual)

Modelo de dados proposto, agrupado por módulo. É o ponto de partida para as migrações; quando o sistema existir, este documento passa a ser gerado a partir das tabelas (como `docs/monografia/base-de-dados.md` do nsERP).

Convenções (as do nsERP): chave primária `id`; nomes de tabelas e campos em português, no plural e em `snake_case`; dinheiro em `decimal(15,2)`; notas em `decimal(5,2)`; datas em `date`; estados em `varchar` com os valores numa enumeração PHP; os documentos guardam quem criou e validou (`criado_por`, `validado_por` → `users`), ligações que não aparecem nos diagramas para não os encher.

## 1. Ideia central

Três camadas que não se misturam:

```mermaid
flowchart LR
    subgraph Curricular[O que se ensina]
        C[cursos] --> P[planos_curriculares] --> PD[plano_disciplinas] --> D[disciplinas]
    end
    subgraph Oferta[O que abre neste período]
        PL[periodos_lectivos] --> T[turmas] --> L[leccionacoes<br/>disciplina + turma + docente]
    end
    subgraph Percurso[O percurso do estudante]
        E[estudantes] --> M[matriculas] --> I[inscricoes] --> N[notas]
    end
    D -.-> L
    L -.-> I
    P -.-> M
```

- **Curricular**: muda raramente e tem versões. Um estudante fica preso ao plano em que entrou.
- **Oferta**: criada todos os períodos. Uma **leccionação** é uma disciplina dada a uma turma, num período, por um docente; é aí que se pendura o horário, as aulas, o plano de avaliação e a pauta.
- **Percurso**: tudo o que diz respeito a um estudante. A **inscrição** liga o estudante a uma leccionação; as notas e as presenças pertencem à inscrição.

## 2. Estrutura académica

```mermaid
erDiagram
    unidades_organicas ||--o{ unidades_organicas : "unidade_pai_id"
    unidades_organicas ||--o{ cursos : "unidade_id"
    unidades_organicas ||--o{ disciplinas : "unidade_id"
    cursos ||--o{ planos_curriculares : "curso_id"
    planos_curriculares ||--o{ plano_disciplinas : "plano_id"
    disciplinas ||--o{ plano_disciplinas : "disciplina_id"
    disciplinas ||--o{ precedencias : "disciplina_id"
    disciplinas ||--o{ precedencias : "precedente_id"
    anos_lectivos ||--o{ periodos_lectivos : "ano_lectivo_id"
    unidades_organicas {
        bigint id PK
        bigint unidade_pai_id FK
        varchar tipo "faculdade, escola, departamento"
        varchar codigo
        varchar nome
        bigint responsavel_id FK
        tinyint activo
    }
    cursos {
        bigint id PK
        bigint unidade_id FK
        varchar codigo
        varchar nome
        varchar grau "licenciatura, mestrado, ensino_primario, ensino_secundario"
        tinyint duracao_anos
        varchar regime "laboral, pos_laboral"
        bigint director_id FK
        tinyint activo
    }
    planos_curriculares {
        bigint id PK
        bigint curso_id FK
        varchar versao
        smallint creditos_total
        varchar estado "rascunho, aprovado, em_uso, descontinuado"
        date aprovado_em
    }
    disciplinas {
        bigint id PK
        bigint unidade_id FK
        varchar codigo
        varchar nome
        decimal creditos
        smallint horas_teoricas
        smallint horas_praticas
        tinyint activo
    }
    plano_disciplinas {
        bigint id PK
        bigint plano_id FK
        bigint disciplina_id FK
        tinyint ano_curricular "ou classe"
        tinyint periodo "semestre ou 0 = anual"
        tinyint obrigatoria
        tinyint nuclear "conta para a passagem de classe"
    }
    precedencias {
        bigint id PK
        bigint disciplina_id FK
        bigint precedente_id FK
    }
    anos_lectivos {
        bigint id PK
        varchar nome "2026 ou 2026/2027"
        date inicio
        date fim
        varchar estado "planeado, aberto, fechado"
    }
    periodos_lectivos {
        bigint id PK
        bigint ano_lectivo_id FK
        varchar tipo "semestre, trimestre"
        tinyint numero
        date inicio
        date fim
        date inscricoes_inicio
        date inscricoes_fim
        date exames_inicio
        date exames_fim
    }
```

## 3. Candidaturas, estudantes e encarregados

```mermaid
erDiagram
    fases_candidatura ||--o{ vagas : "fase_id"
    cursos ||--o{ vagas : "curso_id"
    fases_candidatura ||--o{ candidaturas : "fase_id"
    candidatos ||--o{ candidaturas : "candidato_id"
    candidaturas ||--o{ candidatura_opcoes : "candidatura_id"
    cursos ||--o{ candidatura_opcoes : "curso_id"
    candidaturas |o--o| estudantes : "estudante_id"
    estudantes ||--o{ estudante_encarregado : "estudante_id"
    encarregados ||--o{ estudante_encarregado : "encarregado_id"
    estudantes ||--o{ anexos : "anexavel"
    estudantes |o--o| contas_portal : "estudante_id"
    encarregados |o--o| contas_portal : "encarregado_id"
    fases_candidatura {
        bigint id PK
        bigint ano_lectivo_id FK
        varchar nome
        date inicio
        date fim
        decimal taxa
        varchar estado "aberta, fechada, publicada"
    }
    vagas {
        bigint id PK
        bigint fase_id FK
        bigint curso_id FK
        smallint numero
    }
    candidatos {
        bigint id PK
        varchar nome
        varchar email
        varchar telemovel
        varchar password
        varchar documento_tipo
        varchar documento_numero
        date data_nascimento
    }
    candidaturas {
        bigint id PK
        varchar numero
        bigint fase_id FK
        bigint candidato_id FK
        decimal nota_admissao
        varchar estado "rascunho, submetida, verificada, admitida, nao_admitida, matriculada"
        bigint estudante_id FK
    }
    candidatura_opcoes {
        bigint id PK
        bigint candidatura_id FK
        bigint curso_id FK
        tinyint preferencia
        varchar resultado
    }
    estudantes {
        bigint id PK
        varchar numero UK "2026/00123"
        varchar nome
        varchar sexo
        date data_nascimento
        varchar documento_tipo
        varchar documento_numero
        varchar nuit
        varchar nacionalidade
        varchar naturalidade
        varchar provincia
        varchar morada
        varchar telemovel
        varchar email
        varchar foto
        text necessidades_especiais
        varchar estado "activo, suspenso, desistente, transferido, graduado"
        text nota_privada
    }
    encarregados {
        bigint id PK
        varchar nome
        varchar documento_numero
        varchar profissao
        varchar telemovel
        varchar email
    }
    contas_portal {
        bigint id PK
        varchar tipo "estudante, encarregado"
        bigint estudante_id FK
        bigint encarregado_id FK
        varchar email UK
        varchar password
        varchar estado "pendente, activa, suspensa"
    }
    estudante_encarregado {
        bigint estudante_id FK
        bigint encarregado_id FK
        varchar parentesco
        tinyint principal
        tinyint ve_financas
    }
    anexos {
        bigint id PK
        varchar anexavel_type
        bigint anexavel_id
        varchar tipo "bi, certificado, foto, justificacao"
        varchar caminho
    }
```

As contas de acesso aos portais ficam em `contas_portal`, com guarda própria (`estudante`), como as `contas_cliente` do nsERP: nunca abrem o sistema interno. Os candidatos têm a sua guarda (`candidato`).

## 4. Oferta lectiva: turmas, docentes, horários

```mermaid
erDiagram
    periodos_lectivos ||--o{ turmas : "periodo_id"
    cursos ||--o{ turmas : "curso_id"
    turmas ||--o{ leccionacoes : "turma_id"
    disciplinas ||--o{ leccionacoes : "disciplina_id"
    docentes ||--o{ leccionacoes : "docente_id"
    users ||--o| docentes : "user_id"
    leccionacoes ||--o{ horarios : "leccionacao_id"
    salas ||--o{ horarios : "sala_id"
    leccionacoes ||--o{ aulas : "leccionacao_id"
    turmas {
        bigint id PK
        bigint periodo_id FK
        bigint curso_id FK
        tinyint ano_curricular "ou classe"
        varchar nome "A, B, 10.a A"
        varchar turno "manha, tarde, noite"
        smallint capacidade
        bigint director_turma_id FK
    }
    docentes {
        bigint id PK
        bigint user_id FK
        varchar numero
        varchar grau_academico
        varchar categoria
        varchar regime "integral, parcial"
        bigint unidade_id FK
    }
    leccionacoes {
        bigint id PK
        bigint turma_id FK
        bigint disciplina_id FK
        bigint docente_id FK
        smallint vagas
    }
    salas {
        bigint id PK
        varchar codigo
        varchar tipo
        smallint capacidade
    }
    horarios {
        bigint id PK
        bigint leccionacao_id FK
        bigint sala_id FK
        tinyint dia_semana
        time inicio
        time fim
    }
    aulas {
        bigint id PK
        bigint leccionacao_id FK
        date data
        time inicio
        text sumario
        bigint registada_por FK
    }
```

## 5. Matrícula, inscrição, presenças e avaliação

```mermaid
erDiagram
    estudantes ||--o{ matriculas : "estudante_id"
    cursos ||--o{ matriculas : "curso_id"
    planos_curriculares ||--o{ matriculas : "plano_id"
    anos_lectivos ||--o{ matriculas : "ano_lectivo_id"
    matriculas ||--o{ inscricoes : "matricula_id"
    leccionacoes ||--o{ inscricoes : "leccionacao_id"
    aulas ||--o{ presencas : "aula_id"
    inscricoes ||--o{ presencas : "inscricao_id"
    leccionacoes ||--o{ componentes_avaliacao : "leccionacao_id"
    componentes_avaliacao ||--o{ notas : "componente_id"
    inscricoes ||--o{ notas : "inscricao_id"
    notas ||--o{ revisoes_nota : "nota_id"
    leccionacoes ||--o{ pautas : "leccionacao_id"
    pautas ||--o{ pauta_linhas : "pauta_id"
    inscricoes ||--o{ pauta_linhas : "inscricao_id"
    matriculas {
        bigint id PK
        varchar numero
        bigint estudante_id FK
        bigint curso_id FK
        bigint plano_id FK
        bigint ano_lectivo_id FK
        tinyint ano_curricular "ou classe"
        varchar estado "rascunho, activa, suspensa, anulada, transferida, concluida"
        varchar resultado "transita, nao_transita, graduado"
        date confirmada_em
    }
    inscricoes {
        bigint id PK
        bigint matricula_id FK
        bigint leccionacao_id FK
        varchar estado "inscrito, anulado, aprovado, reprovado, excluido"
        decimal media_frequencia
        decimal nota_final
        decimal assiduidade
    }
    presencas {
        bigint id PK
        bigint aula_id FK
        bigint inscricao_id FK
        varchar estado "presente, falta, atraso, justificada"
        varchar motivo
    }
    componentes_avaliacao {
        bigint id PK
        bigint leccionacao_id FK
        varchar nome "Teste 1, Trabalho, Exame"
        varchar epoca "frequencia, exame, recorrencia"
        tinyint trimestre "ensino geral"
        decimal peso
        date data
    }
    notas {
        bigint id PK
        bigint componente_id FK
        bigint inscricao_id FK
        decimal valor
        tinyint faltou
        bigint lancada_por FK
    }
    revisoes_nota {
        bigint id PK
        bigint nota_id FK
        decimal valor_anterior
        decimal valor_novo
        text motivo
        bigint decidida_por FK
    }
    pautas {
        bigint id PK
        varchar numero
        bigint leccionacao_id FK
        varchar epoca "frequencia, exame, recorrencia, trimestre_1"
        varchar estado "rascunho, submetida, validada, publicada, fechada"
        datetime publicada_em
    }
    pauta_linhas {
        bigint id PK
        bigint pauta_id FK
        bigint inscricao_id FK
        decimal nota
        varchar situacao "excluido, admitido, dispensado, aprovado, reprovado"
    }
```

## 6. Finanças do estudante

Segue o modelo de facturação do nsERP: a **cobrança** faz o papel da factura (linhas com o serviço e o valor) e liga-se aos pagamentos por uma tabela intermédia com o valor abatido.

```mermaid
erDiagram
    anos_lectivos ||--o{ precos : "ano_lectivo_id"
    servicos ||--o{ precos : "servico_id"
    cursos |o--o{ precos : "curso_id"
    estudantes ||--o{ cobrancas : "estudante_id"
    matriculas |o--o{ cobrancas : "matricula_id"
    servicos ||--o{ cobrancas : "servico_id"
    estudantes ||--o{ descontos : "estudante_id"
    estudantes ||--o{ pagamentos : "estudante_id"
    cobrancas ||--o{ cobranca_pagamento : "cobranca_id"
    pagamentos ||--o{ cobranca_pagamento : "pagamento_id"
    servicos {
        bigint id PK
        varchar codigo
        varchar nome "Matricula, Propina, Declaracao"
        varchar tipo "matricula, propina, emolumento, multa"
        tinyint activo
    }
    precos {
        bigint id PK
        bigint ano_lectivo_id FK
        bigint servico_id FK
        bigint curso_id FK "vazio = todos"
        decimal valor
        varchar periodicidade "unica, mensal, semestral"
        tinyint prestacoes
    }
    cobrancas {
        bigint id PK
        varchar numero
        bigint estudante_id FK
        bigint matricula_id FK
        bigint servico_id FK
        varchar descricao "Propina Marco 2026"
        decimal valor
        decimal desconto
        decimal multa
        decimal pago
        date vencimento
        varchar estado "aberta, parcial, paga, anulada"
    }
    descontos {
        bigint id PK
        bigint estudante_id FK
        bigint ano_lectivo_id FK
        varchar tipo "bolsa, irmao, funcionario, merito"
        decimal percentagem
        decimal valor
        bigint aprovado_por FK
    }
    pagamentos {
        bigint id PK
        varchar numero "RC2026/0001"
        bigint estudante_id FK
        date data
        decimal valor
        varchar metodo "numerario, banco, mpesa, emola, pos"
        varchar referencia
        varchar estado "valido, anulado"
        varchar assinatura
    }
    cobranca_pagamento {
        bigint cobranca_id FK
        bigint pagamento_id FK
        decimal valor
    }
```

## 7. Documentos académicos e comunicação

```mermaid
erDiagram
    estudantes ||--o{ pedidos_documento : "estudante_id"
    tipos_documento ||--o{ pedidos_documento : "tipo_id"
    pedidos_documento |o--o| cobrancas : "cobranca_id"
    pedidos_documento |o--o| documentos_emitidos : "documento_id"
    estudantes ||--o{ documentos_emitidos : "estudante_id"
    avisos ||--o{ aviso_destinatarios : "aviso_id"
    tipos_documento {
        bigint id PK
        varchar nome "Declaracao de matricula, Certificado"
        bigint servico_id FK "emolumento"
        smallint prazo_dias
        text modelo
    }
    pedidos_documento {
        bigint id PK
        varchar numero
        bigint estudante_id FK
        bigint tipo_id FK
        varchar finalidade
        varchar estado "pedido, por_pagar, em_preparacao, pronto, entregue, recusado"
        bigint cobranca_id FK
        bigint documento_id FK
    }
    documentos_emitidos {
        bigint id PK
        varchar numero
        bigint estudante_id FK
        varchar tipo
        json conteudo "dados congelados na emissao"
        varchar codigo_verificacao UK
        varchar assinatura
        datetime emitido_em
    }
    avisos {
        bigint id PK
        varchar titulo
        text texto
        datetime publicado_em
        tinyint enviar_email
    }
    aviso_destinatarios {
        bigint aviso_id FK
        varchar alvo_type "curso, turma, perfil, todos"
        bigint alvo_id
    }
```

## 8. Tabelas herdadas do nsERP

Vêm iguais (ou quase) da base do nsERP e não se desenham aqui: `users`, `roles`, `permissions` e tabelas de ligação (Spatie Permission), `activity_log` (Spatie Activitylog), `definicoes`, `numeracoes`, `sessions`, `cache`, `jobs`, `failed_jobs`, `notifications`, `password_reset_tokens`.
