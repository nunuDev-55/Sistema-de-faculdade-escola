# Análise funcional — eDondzo (Portal do Estudante)

Estudo feito a 09/11/2026 a partir da utilização do **eDondzo — Sistema Integrado de Gestão Académica** (COSYS, 2013), na instalação de uma universidade moçambicana, com uma conta de estudante. O nsEDU segue os mesmos passos do estudante e reproduz as funcionalidades com código próprio, como o nsERP fez com o Dolibarr. **Nenhum código é copiado.**

Só se viu o lado do estudante. O resto do sistema (registo académico, docentes, tesouraria, administração) conhece-se pelos nomes dos ecrãs que o portal carrega (secção 9) e deve ser confirmado com uma conta da secretaria.

Os exemplos abaixo são fictícios: não se copiam para aqui dados de estudantes.

## 1. Entrada

Ecrã único: **Usuário** (o número de estudante, ex.: `2023XXX000`) e **Senha**, botão **Autenticar**. Depois de entrar, o sistema verifica a **validade da senha** e pode obrigar a mudá-la. O menu do nome do utilizador (canto superior direito) tem **Dados Pessoais · Alterar Senha · Sair do Sistema**.

## 2. Menu do sistema (perfil Estudante)

| Grupo | Ecrã |
|---|---|
| Portal do Estudante | Pré-Inscrição · Notas de Frequência · Exames · Situação Financeira |
| Gestão do Currículo | Ver Planos Curriculares |
| Dados Pessoais | (ficha do estudante) |

## 3. Pré-Inscrição semestral

Cabeçalho (só leitura): **Nº de Estudante · Nome · Regime** (turno: Manhã / Tarde / Noite) **· Curso · Delegação · Campus**.

Tabela **Dados sobre Inscrições**:

| Código da Inscrição | Status | Ano | Sem. | Acções |
|---|---|---|---|---|
| 00000000000000000001 | Inscrição Confirmada | 2026 | 2 | Eliminar · 2.ª Via |

- A inscrição é **por semestre**, com um código próprio de 20 dígitos e um estado (pré-inscrição → confirmada).
- **2.ª Via** reimprime o comprovativo de inscrição.
- O formulário de inscrição (não aberto, para não gravar nada) trata também as **disciplinas em atraso**, a **turma em atraso** e a **especialização** (pelos nomes das funções do ecrã).

## 4. Notas de Frequência

Filtros: **Ano Lectivo** e **Semestre** → **Buscar**.

| Disciplina | Semestre | Avaliação 1.ª | Avaliação 2.ª | Trabalho 1.ª | Trabalho 2.ª | Média | Classificação |
|---|---|---|---|---|---|---|---|
| Disciplina A | 4 | 12 | 14 | 15 | | 13 | Admitido |
| Disciplina B | 4 | | | | | 0 | Excluído |

- O plano de avaliação de origem tem **duas avaliações (testes) e dois trabalhos**.
- A **classificação** sai da média (Excluído / Admitido / Dispensado).
- **Problema visto:** com o semestre a decorrer e sem notas lançadas, a disciplina já aparece com média 0 e "Excluído". O estudante vê um resultado que ainda não existe.

## 5. Exames

Filtros iguais. Colunas:

| Disciplina | Semestre | Média Frequência | Obs. | Exame Normal | Recorrência | Especial | Média Final | Resultado Final |
|---|---|---|---|---|---|---|---|---|

- Três épocas de exame: **Normal, Recorrência e Especial**.
- O resultado final é Aprovado / Reprovado. O mesmo problema: "Reprovado" aparece antes de haver notas.

## 6. Situação Financeira

- No topo: **Dívida Actual** e **Saldo dos Adiantamentos** (o que o estudante pagou adiantado).
- **Extracto Financeiro** por Ano Lectivo e Semestre → **Mostrar Extracto**:

| Propina | Data (vencimento) | Valor | Data Pagto | Valor Pago | Dívida |
|---|---|---|---|---|---|
| Inscrição em 2026 - II Semestre | 17-07-2026 | 2,000.00 | 15-07-2026 | 2,000.00 | 0.00 |
| Propina de Agosto de 2026 | 10-08-2026 | 6,000.00 | 01-08-2026 | 6,000.00 | 0.00 |
| … até Dezembro | 10 de cada mês | | | | |

- Por semestre: **uma taxa de inscrição** e **cinco propinas mensais** com vencimento no **dia 10**. Pode pagar-se antes do vencimento.
- **Referências Bancárias**: cada cobrança tem a sua **Entidade** (5 dígitos, a mesma para a instituição) e **Referência** (11 dígitos, única), o montante, a data limite e **Pago? SIM / NÃO**. O estudante paga no banco, ATM ou banca móvel ("Pagamento de serviços"), e o sistema marca como paga a partir da informação do banco.

## 7. Planos curriculares

Tabela única do plano do curso: **Código da Disciplina** (5 dígitos) · **Nome** · **Ano** · **Sem.** · **Componente** (algumas disciplinas marcadas `CFG`; o significado deve ser confirmado com a instituição).

- Exemplo do curso visto: 4 anos × 2 semestres, cerca de 6 disciplinas por semestre, disciplinas **transversais** numeradas (Transversal I a V) e, no fim, estágio / projecto e defesa do trabalho de fim de curso.

## 8. Outras funções (pelos nomes no código do portal)

| Função | O que indica |
|---|---|
| Aulas do estudante: lista, pesquisa, ver, **descarregar ficheiro** | Os docentes publicam o sumário e materiais por aula; o estudante descarrega-os |
| SMS: a um, a todos, por perfil, por curso, aos candidatos admitidos | Comunicação por SMS a grupos |
| Pré-inscrição para a **graduação** (formulário, pesquisa, reimpressão, relatório) | Inscrição na cerimónia de graduação dos finalistas |
| Província → Distrito → Localidade | Morada com as três divisões, em cascata |
| Assiduidade | Registo de presenças |
| Validade da senha | A senha caduca e tem de ser mudada |

## 9. Arquitectura observada

| | eDondzo | nsEDU |
|---|---|---|
| Servidor | Apache 2.4.18 (Ubuntu 16.04), PHP com pastas `view/`, `controller/`, `reports/` | Laravel 12, PHP 8.2+ |
| Ecrã | jQuery 2.1.1 + Bootstrap + bootbox; cada ecrã é um pedaço de HTML que o JavaScript mete numa caixa da página | React 19 + Inertia 2 |
| Pedidos | `POST controller/<módulo>.php` com `criterio=<acção>` | Rotas com nome e permissão (`can:`) |
| Módulos (pastas) | `estudante`, `curriculo`, `regacad` (registo académico), `admin`, `common` | ver [Arquitectura](05-arquitectura.md) |
| JavaScript | Ofuscado | Código fonte com tipos (TypeScript) |
| Telemóvel | Tabelas largas, sem adaptação | Tabelas que viram cartões |

## 10. Adaptações do nsEDU

**Seguimos os mesmos passos:**

1. Entrada pelo **número de estudante** e senha; menu do utilizador com Dados Pessoais, Alterar Senha e Sair; **senha com validade** configurável.
2. Portal com **Inscrição semestral · Notas de Frequência · Exames · Situação Financeira · Plano Curricular · Dados Pessoais**, com os filtros Ano Lectivo + Semestre.
3. Cabeçalho do estudante com **Regime (turno), Curso, Delegação e Campus**: a instituição pode ter várias delegações e campus.
4. **Inscrição semestral** com código, estado, **comprovativo (2.ª via)**, **taxa de inscrição** e **disciplinas em atraso**.
5. Plano de avaliação de origem: **2 avaliações + 2 trabalhos** (configurável por disciplina).
6. Épocas de exame **Normal, Recorrência e Especial**.
7. **Taxa de inscrição + propinas mensais** com vencimento num dia fixo (10), **adiantamentos** e extracto por semestre.
8. **Referência bancária por cobrança** (entidade + referência + montante + data limite) e baixa automática pelo ficheiro do banco.
9. Plano curricular com código, ano, semestre e componente.
10. **Aulas com materiais** para descarregar, **SMS a grupos**, **inscrição na graduação** e morada **Província → Distrito → Localidade**.

**Fazemos melhor:**

- O portal só mostra as notas e a situação de **pautas publicadas**. Antes disso aparece "Por lançar", nunca "Excluído" ou "Reprovado" (RN-09).
- Dinheiro à moçambicana: **6 300,00 MT**, e não `6,300.00`.
- Ecrãs pensados para o **telemóvel**.
- Cada recibo e comprovativo com **número e QR de verificação**.
- Revisão dos textos (ex.: "Conclusão", "1.º Semestre").
