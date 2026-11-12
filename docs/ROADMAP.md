# Plano de desenvolvimento

Regra de trabalho (a do nsERP): um módulo só fica "feito" quando tem ecrãs (tema claro, escuro e telemóvel), permissões, auditoria e testes automáticos a verde.

## Fase 0 — Análise (agora)

| # | Entrega | Estado |
|---|---|---|
| 0.1 | Estudo de sistemas open source — [01-referencias-opensource.md](01-referencias-opensource.md) | feito |
| 0.2 | Requisitos funcionais, não funcionais e regras — [02-requisitos.md](02-requisitos.md) | feito (rever com a instituição-piloto) |
| 0.3 | Casos de uso e perfis — [03-casos-de-uso.md](03-casos-de-uso.md) | feito |
| 0.4 | Modelo de dados — [04-base-de-dados.md](04-base-de-dados.md) | feito |
| 0.5 | Arquitectura — [05-arquitectura.md](05-arquitectura.md) | feito |
| 0.6 | Processos e estados — [06-processos-e-estados.md](06-processos-e-estados.md) | feito |
| 0.6a | Análise do eDondzo (portal do estudante) — [07-analise-edondzo.md](07-analise-edondzo.md) | feito (falta ver o lado da secretaria e dos docentes) |
| 0.6b | Estudo de código dos sistemas mais integrados (Frappe Education, OpenEduCat) — [08-estudo-sistemas-academicos.md](08-estudo-sistemas-academicos.md) | feito |
| 0.7 | Escolher a instituição-piloto e obter o regulamento pedagógico (escala, limites, pesos, propinas) | por fazer |
| 0.8 | Protótipo dos ecrãs principais (folha de notas, ficha do estudante, portal) | por fazer |

## Fase 1 — Base (do nsERP)

| # | Módulo | O que inclui |
|---|---|---|
| 1.1 | Esqueleto | **Feito**: Laravel 12 + Inertia + React a partir do nsERP, sem os módulos comerciais; entrada, permissões e perfis de origem, painel e menu por perfil, ecrãs "em construção" por módulo; CI no GitHub |
| 1.2 | Utilizadores, perfis e auditoria | **Feito**: contas da equipa (criar, mudar o perfil, activar e desactivar, nunca ficar sem administrador), matriz de permissões por perfil, perfis novos da instituição, auditoria com filtros por pessoa, módulo e datas. Início com o ano em curso e números reais. Falta a verificação em dois passos |
| 1.3 | Configuração da instituição | **Feito**: dados, logótipo e cores, tipo de instituição, escala por intervalos, limites de avaliação e de créditos. Falta o ecrã da numeração |

## Fase 2 — Estrutura académica

| # | Módulo | O que inclui |
|---|---|---|
| 2.1 | Unidades orgânicas e cursos / classes | **Feito**: unidades orgânicas e cursos com grau e duração |
| 2.2 | Disciplinas e planos curriculares | **Feito**: versões (rascunho → em vigor → substituído), precedências por plano, créditos, método de avaliação por plano (OpenEduCat) |
| 2.3 | Anos e períodos lectivos | **Feito**: calendário, prazos de inscrição e exames; geração dos períodos a partir do ano (2 semestres / 3 trimestres, OpenEduCat) |
| 2.4 | Salas e docentes | **Feito**: salas com tipo e lotação; ficha do docente (grau, categoria, regime, unidade) ligada à conta com que entra |

## Fase 3 — Percurso do estudante

| # | Módulo | O que inclui |
|---|---|---|
| 3.1 | Estudantes e encarregados | **Feito**: número único por ano de entrada, ficha com separadores, encarregados (um para vários irmãos, pelo telemóvel), pesquisa e filtros. Falta a importação do Excel e a fotografia |
| 3.2 | Matrícula e inscrição | **Feito**: matrícula no plano em vigor (a renovação mantém o plano em que entrou), suspender / reactivar / anular, inscrição semestral com precedências, disciplinas em atraso e limite de créditos, comprovativo para imprimir; no ensino geral, inscrição anual em todas as disciplinas da classe. Falta o pedido de inscrição no portal |
| 3.3 | Turmas, distribuição de serviço e horários | **Feito em parte**: turmas por curso, ano e semestre (do ano todo no ensino geral) com as disciplinas do plano; docente de cada disciplina em cada turma; estudantes distribuídos por ordem alfabética e lotação, disciplinas em atraso na turma onde são dadas; o docente vê as suas turmas. Falta o horário sem sobreposições |
| 3.4 | Sumários e presenças | Folha de presenças, justificações, assiduidade |

## Fase 4 — Avaliação

| # | Módulo | O que inclui |
|---|---|---|
| 4.1 | Plano de avaliação e lançamento de notas | Escala de classificação por intervalos (Frappe); folha de notas tipo Excel |
| 4.2 | Pautas | Rascunho → submetida → validada → publicada → fechada; impressão |
| 4.3 | Exame e recorrência / trimestres e conselho de notas | Os dois tipos de instituição |
| 4.4 | Revisões de nota | Sem apagar a anterior |

## Fase 5 — Finanças

| # | Módulo | O que inclui |
|---|---|---|
| 5.1 | Tabela de preços e geração de cobranças | Por ano lectivo e curso |
| 5.2 | Pagamentos, recibos e referências bancárias | Parciais, adiantamentos, alocação, recibo assinado (da facturação do nsERP); uma referência por cobrança e baixa pelo ficheiro do banco |
| 5.3 | Multas, bolsas e descontos, bloqueios por dívida | |
| 5.4 | Devedores e lembretes | E-mail, depois SMS / WhatsApp |

## Fase 6 — Portais e documentos

| # | Módulo | O que inclui |
|---|---|---|
| 6.1 | Portal do estudante e do encarregado | Horário, notas, presenças, finanças |
| 6.2 | Pedidos e emissão de documentos | Declarações, certificados, histórico, QR de verificação |
| 6.3 | Candidaturas online | Fases, vagas, resultados, admissão |
| 6.4 | Avisos e SMS | Por curso, turma, perfil, candidatos admitidos |
| 6.5 | Inscrição na graduação | Finalistas, lista e reimpressão |

## Fase 7 — Relatórios e integrações

| # | Módulo | O que inclui |
|---|---|---|
| 7.1 | Painel por perfil e relatórios | Efectivos, aproveitamento, assiduidade, receitas |
| 7.2 | Mapas para o Ministério | Efectivos escolares e aproveitamento |
| 7.3 | Pagamento online | M-Pesa, e-Mola com confirmação imediata |
| 7.4 | Ligação a um LMS | Moodle: exportar turmas, importar notas |
| 7.5 | Publicação | Alojamento, domínio, cópias automáticas |
