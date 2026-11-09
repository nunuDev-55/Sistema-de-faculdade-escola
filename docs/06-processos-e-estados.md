# Processos e estados

Diagramas de estado (ciclo de vida de cada registo) e de actividade / sequência dos processos principais. Cada transição é uma acção de um serviço, numa transacção, registada na auditoria.

## 1. Candidatura

```mermaid
stateDiagram-v2
    [*] --> Rascunho: candidato cria
    Rascunho --> Submetida: submeter (documentos e taxa)
    Submetida --> Rascunho: devolver (falta documento)
    Submetida --> Verificada: secretaria confere
    Verificada --> Admitida: publicar resultados (dentro das vagas)
    Verificada --> NaoAdmitida: publicar resultados
    Admitida --> Matriculada: admitir → cria estudante
    NaoAdmitida --> [*]
    Matriculada --> [*]
```

## 2. Matrícula

```mermaid
stateDiagram-v2
    [*] --> Rascunho: nova matrícula
    Rascunho --> Activa: confirmar (sem dívida, inscrições feitas)
    Rascunho --> [*]: apagar
    Activa --> Suspensa: suspender (pedido ou dívida)
    Suspensa --> Activa: reactivar
    Activa --> Anulada: anular (com motivo)
    Activa --> Transferida: transferência para outra instituição
    Activa --> Concluida: fecho do ano lectivo
    Concluida --> [*]
    Anulada --> [*]
    Transferida --> [*]
```

No fecho do ano, a matrícula concluída recebe o **resultado**: transita, não transita ou graduado (fim do curso / da última classe).

## 3. Inscrição numa disciplina

```mermaid
stateDiagram-v2
    [*] --> Inscrito
    Inscrito --> Anulado: anular inscrição (dentro do prazo)
    Inscrito --> Excluido: média de frequência < 10 ou faltas acima do limite
    Inscrito --> Dispensado: média de frequência ≥ 14
    Inscrito --> AdmitidoExame: 10 ≤ média < 14
    AdmitidoExame --> Aprovado: exame normal ≥ 10
    AdmitidoExame --> Recorrencia: exame normal < 10
    Recorrencia --> Aprovado: recorrência ≥ 10
    Recorrencia --> Reprovado: recorrência < 10
    Dispensado --> Aprovado
    Excluido --> Reprovado
    Aprovado --> [*]
    Reprovado --> [*]
```

Ensino superior, com os limites de origem (RN-02 a RN-04). No ensino geral, a inscrição fica "Inscrito" o ano todo e a decisão é da **passagem de classe** (secção 6).

## 4. Pauta

```mermaid
stateDiagram-v2
    [*] --> Rascunho: abrir a época
    Rascunho --> Submetida: docente submete (todas as notas lançadas)
    Submetida --> Rascunho: director devolve com comentário
    Submetida --> Validada: director valida (recebe número)
    Validada --> Publicada: publicar (aparece nos portais)
    Publicada --> Publicada: revisão de nota (guarda a anterior)
    Publicada --> Fechada: secretaria fecha a época
    Fechada --> [*]
```

## 5. Cobrança e pedido de documento

```mermaid
stateDiagram-v2
    state Cobranca {
        [*] --> Aberta: gerada
        Aberta --> Parcial: pagamento parcial
        Parcial --> Paga: resto pago
        Aberta --> Paga: pagamento total
        Aberta --> Aberta: vence → multa
        Aberta --> Anulada: anular (com motivo)
    }
    state PedidoDocumento {
        [*] --> Pedido
        Pedido --> PorPagar: gera emolumento
        PorPagar --> EmPreparacao: emolumento pago
        EmPreparacao --> Pronto: secretaria emite
        Pronto --> Entregue: estudante levanta
        Pedido --> Recusado: falta requisito
    }
```

## 6. Fim do ano lectivo (ensino geral)

```mermaid
flowchart TD
    A[3.º trimestre: pautas publicadas] --> B[Sistema calcula a média anual<br/>de cada disciplina]
    B --> C{Classe com exame?}
    C -- sim --> D[Pauta de exame] --> E[Nota final = média anual + exame<br/>pesos configuráveis]
    C -- não --> E2[Nota final = média anual]
    E --> F[Conselho de notas da turma]
    E2 --> F
    F --> G{Negativas ≤ máximo e<br/>nucleares positivas?}
    G -- sim --> H[Transita de classe]
    G -- não --> I[Não transita]
    H --> J[Renovação de matrícula na classe seguinte]
    I --> K[Renovação de matrícula na mesma classe]
```

## 7. Inscrição online pelo estudante (ensino superior)

```mermaid
sequenceDiagram
    actor E as Estudante
    participant P as Portal
    participant S as InscricaoService
    participant F as ContaCorrente
    participant R as Precedencias
    actor SA as Secretaria

    E->>P: abre "Inscrição" no período de inscrições
    P->>F: tem dívida vencida acima do limite?
    alt com dívida
        F-->>P: sim
        P-->>E: mostra o valor em falta e o botão para pagar
    else sem dívida
        P->>R: disciplinas que pode fazer (plano + aprovadas + precedências)
        R-->>P: lista com as bloqueadas e o motivo
        E->>P: escolhe disciplinas e turmas
        P->>S: pedir(matricula, escolhas)
        S->>S: limite de créditos, vagas, horário sem sobreposição
        S-->>P: pedido criado
        P-->>E: "Pedido enviado"
        SA->>S: aprovar pedido
        S->>S: cria inscrições e cobranças (transacção + auditoria)
        S-->>E: aviso por e-mail
    end
```

## 8. Pagamento de propina na tesouraria

```mermaid
sequenceDiagram
    actor T as Tesouraria
    participant C as PagamentosController
    participant S as PagamentoService
    participant A as AlocacaoPagamentos
    participant N as Numeracao
    participant I as IntegridadeDocumentos
    participant BD as MariaDB

    T->>C: POST /estudantes/{id}/pagamentos (valor, método, referência)
    C->>S: registar(estudante, dados)
    S->>BD: begin
    S->>A: distribuir pelas cobranças mais antigas
    A-->>S: abatimentos
    S->>N: proximo('RC')
    N-->>S: RC2026/0153
    S->>I: assinar(recibo, assinatura anterior)
    S->>BD: grava pagamento, cobranca_pagamento, estados, auditoria, commit
    C-->>T: ficha do recibo + imprimir
```
