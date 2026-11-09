# Estudo de código dos sistemas mais integrados

Segunda passagem sobre o open source, agora **ao nível do código** (modelos de dados e lógica), não só da lista de módulos ([01-referencias-opensource.md](01-referencias-opensource.md)). O objectivo é melhorar a construção e a lógica do nsEDU. Estudaram-se os dois sistemas de gestão académica **mais integrados** (candidatura → matrícula → avaliação → propinas, num só modelo):

- **Frappe Education** (módulo do ERPNext, Python/Frappe) — modelo de dados muito limpo, cada entidade é um *DocType* com os seus campos e o seu controlador.
- **OpenEduCat** (módulo do Odoo, Python) — forte em **máquinas de estado**: cada transição é um método (`action_*`).

Lido a 09/11/2026 a partir do código-fonte (branches `develop` e `16.0`). Como sempre, servem de **referência**; nenhum código é copiado (ambos são copyleft).

## 1. Como cada um organiza o percurso académico

| Conceito | Frappe Education | OpenEduCat | nsEDU |
|---|---|---|---|
| Oferta de curso | `Program` (+ `Program Level`, `Area of Study`) | `op.course` (hierárquico, `parent_id`) + `op.batch` (turno/ano) | `cursos` + `planos_curriculares` |
| Disciplina | `Course` (+ `Topic`) | `op.subject` (tipo: teórica/prática; compulsory/elective) | `disciplinas` |
| Ano e período | `Academic Year` / `Academic Term` | `op.academic.year` (com **`term_structure`**: 2 sem., 3 trim., 4 trim.…) / `op.academic.term` | `anos_lectivos` / `periodos_lectivos` |
| Candidatura | `Student Applicant` (+ `Student Admission` com vagas) | `op.admission` (+ `op.admission.register` com `min_count`/`max_count`) | `candidaturas` (+ `fases_candidatura` com `vagas`) |
| Matrícula/inscrição | `Course Enrollment` (com `subjects` filhos, `billing_status`) | `op.student.course` + `op.subject.registration` | `matriculas` + `inscricoes` |
| Avaliação | `Assessment Plan` → `Assessment Result` (+ `Grading Scale`) | `op.exam` / `op.exam.session` → `op.marksheet.register` → `op.result.line` | `componentes_avaliacao` → `notas` → `pautas` |
| Propinas | `Fee Structure` → `Fee Schedule` → `Fees` | `op.fees.terms` → `op.student.fees.details` | `precos` → `cobrancas` → `pagamentos` |

**Conclusão:** a cadeia que desenhámos ([04-base-de-dados.md](04-base-de-dados.md)) coincide com a dos dois sistemas maduros. As melhorias abaixo são afinações, não mudanças de rumo.

## 2. Oito padrões que adoptamos

### 2.1 A escala de classificação é uma entidade com intervalos (Frappe) — a melhoria maior

O Frappe não guarda "10 para passar" no código: tem um **`Grading Scale`** com **intervalos** (`grade_code`, `minimum_percentage`, `maximum_percentage`, `gpa`, `earn_credits`). O controlador valida que os intervalos **começam em 0 %, não se sobrepõem e não têm falhas**, e a nota de letra/situação sai de "em que intervalo cai a percentagem". A percentagem é sempre `score / maximum_score × 100`.

Isto é melhor do que os nossos limites soltos em `config` (10/14/75). Passamos a ter uma **escala configurável por intervalos**: cada instituição define a sua (0–20, 0–100, A–F…), com a situação de cada intervalo (excluído / admitido / dispensado / aprovado) e se conta créditos. Ver a nova tabela `escalas_classificacao` em [04-base-de-dados.md](04-base-de-dados.md) e `App\Support\Escala`.

### 2.2 Cada transição é um método, com guardas (OpenEduCat)

O OpenEduCat modela o ciclo de vida com um método por transição e verifica as condições à entrada:

- `op.admission.register`: `draft → confirm → application → admission → done`, com `check_dates` e `check_no_of_admission` (vagas).
- `op.exam`: `draft → schedule → held → result_updated → done` (+ `cancel`).
- `op.marksheet.register` (a pauta): `draft → validated → cancelled` — `action_validate`.
- `op.subject.registration`: `draft → submitted → approved → rejected`.

É exactamente o nosso padrão (serviços com uma acção por mudança de estado, [06-processos-e-estados.md](06-processos-e-estados.md)). Confirma a escolha e dá-nos os nomes dos estados já testados noutro sistema. Mantemo-lo e validamos os nossos diagramas contra estes.

### 2.3 A inscrição sabe o seu estado de facturação (Frappe)

No `Course Enrollment` há `billing_status` (Pending / Created / Invoiced / Failed) e `create_or_retry_fee_plan()`: **a inscrição gera as cobranças** e guarda se correu bem (com `billing_error` para reprocessar). Adoptamos: a inscrição semestral regista se as cobranças já foram geradas, e a geração é reentrante (não duplica). Ver `estado_facturacao` em `inscricoes_periodo`.

### 2.4 Propinas: modelo → geração → conta (Frappe) + prestações como regra (OpenEduCat)

- Frappe: `Fee Structure` (modelo: programa + **categoria do estudante** + componentes) → `Fee Schedule` (gera para um conjunto, com `due_date` e `status`) → `Fees` (por estudante, `grand_total = Σ componentes`, `outstanding_amount = total − pago`).
- OpenEduCat: `op.fees.terms` com **linhas de prestações** (`fixed_days`/`fixed_date`, `before`/`after` de uma data base) — um modelo reutilizável de plano de pagamento.

Adoptamos os dois: a nossa `precos` ganha **componentes** (matrícula, propina, emolumentos) e **categoria do estudante**; o plano de pagamento gera as prestações (inscrição + N mensalidades com vencimento no dia fixo). O `outstanding` calcula-se, não se guarda à mão.

### 2.5 Categoria do estudante como dimensão (Frappe)

O `Student Category` atravessa preços e relatórios (regular, bolseiro, funcionário, estrangeiro, transferido). É mais limpo do que tratar tudo com descontos avulsos: o preço pode depender da categoria, e os relatórios agrupam por ela. Acrescentamos `categoria` ao estudante e `categoria_id` (opcional) aos preços; os descontos individuais continuam a existir por cima.

### 2.6 Gerar os períodos a partir do ano (OpenEduCat)

`op.academic.year.term_structure` + `term_create()` cria os semestres/trimestres do ano a partir de um modelo (dois semestres, três trimestres…). Adoptamos: ao criar o ano lectivo, o sistema propõe os períodos conforme o tipo de instituição (2 semestres no superior, 3 trimestres no geral), em vez de os escrever um a um.

### 2.7 O método de avaliação pode variar por curso (OpenEduCat)

`op.course.evaluation_type` (normal / GPA / CWA / CCE) e `op.exam.session.evaluation_type` (normal / grade): o **curso** decide como se avalia. Encaixa no nosso "tipo de instituição", mas mais fino: um curso pode usar média simples e outro média ponderada por créditos. Guardamos o método de avaliação no plano curricular, com o do tipo de instituição como omissão.

### 2.8 Validações concretas que copiamos como regra

Do código dos controladores, regras que passam a estar nos nossos serviços e Form Requests:

- Nota: `0 ≤ score ≤ maximum_score`; `percentagem = score / max × 100`; **uma nota por estudante e componente** (sem duplicados); a nota só se lança com o plano de avaliação "submetido" (no nosso caso, a pauta em rascunho).
- Candidatura: data de nascimento não pode ser futura; o período tem de pertencer ao ano; respeitar a capacidade da turma/vagas; valor da propina > 0.
- Datas: `start_date < end_date` em fases, períodos e exames (um `@api.constrains` repetido em todo o OpenEduCat).

## 3. O que NÃO levamos

| Decisão | Porquê |
|---|---|
| Acoplar as propinas a uma contabilidade geral (ERPNext `Sales Invoice`, Odoo `account.move`) | Pesado de mais para o nosso contexto; a nossa facturação (do nsERP) chega. A ligação a um sistema contabilístico fica como integração futura. |
| Hierarquia de cursos por `parent_id` (OpenEduCat) | A nossa árvore está nas unidades orgânicas (faculdade → departamento); o curso não precisa de ser recursivo. |
| LMS (aulas, testes online) dentro do sistema | Fora do âmbito; liga-se ao Moodle mais tarde (RF-78). |
| Odoo/Frappe como plataforma | Mantemos Laravel + Inertia + React (o stack do nsERP), que a instituição-piloto corre num alojamento simples. |

## 4. Mudanças que este estudo introduz

1. [02-requisitos.md](02-requisitos.md): escala de classificação configurável por intervalos (RN-01 reescrita) e preços por categoria do estudante (RF-80).
2. [04-base-de-dados.md](04-base-de-dados.md): tabelas `escalas_classificacao` e `escala_intervalos`; `categoria` em `estudantes`; componentes e `categoria_id` em `precos`; `estado_facturacao` em `inscricoes_periodo`.
3. `app/Support/Escala.php`: resolve a situação a partir dos intervalos (com uma escala 0–20 de origem em `config/nsedu.php`), em vez de limites soltos.
4. [ROADMAP.md](ROADMAP.md): a geração de períodos a partir do ano e o método de avaliação por curso entram nas fases 2.3 e 4.1.
