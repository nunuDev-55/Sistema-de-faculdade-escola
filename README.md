# nsEDU — Sistema de gestão de universidades e escolas

> Nome provisório.

Sistema de gestão académica e financeira para instituições de ensino em Moçambique: candidaturas, estudantes e encarregados, matrículas e inscrições, turmas e horários, presenças, avaliação e pautas, propinas e recibos, documentos académicos e portais para estudantes e encarregados.

Serve **universidades e institutos** (cursos, semestres, créditos, exames e recorrência) e **escolas do ensino geral** (classes, trimestres, passagem de classe), **uma instituição por instalação**.

## Estado

Fase de **análise de sistemas**. Ainda não há código.

| Documento | Conteúdo |
|---|---|
| [Referências open source](docs/01-referencias-opensource.md) | 13 sistemas do GitHub (Frappe Education, OpenEduCat, openSIS, RosarioSIS, Gibbon, Moodle...) e o que levamos de cada um |
| [Requisitos](docs/02-requisitos.md) | Visão, actores, requisitos funcionais e não funcionais, regras de negócio |
| [Casos de uso](docs/03-casos-de-uso.md) | Diagrama geral, descrição dos principais, endereços e permissões por módulo, perfis |
| [Base de dados](docs/04-base-de-dados.md) | Modelo entidade-relação por módulo |
| [Arquitectura](docs/05-arquitectura.md) | Stack, camadas, as quatro caras da aplicação, regras, instalação |
| [Processos e estados](docs/06-processos-e-estados.md) | Ciclo de vida da candidatura, matrícula, inscrição, pauta, cobrança; sequências principais |
| [Análise do eDondzo](docs/07-analise-edondzo.md) | Os passos do portal do estudante do eDondzo que o nsEDU segue, e o que faz melhor |
| [Plano](docs/ROADMAP.md) | Fases de desenvolvimento |

Os diagramas estão em Mermaid: o GitHub mostra-os directamente; para a monografia, cole-os em https://mermaid.live e exporte em PNG ou SVG.

## Tecnologias

O mesmo stack do [nsERP](https://github.com/nunuDev-55/nsERP), do qual parte a base (entrada, permissões, auditoria, configuração, numeração, recibos):

| Camada | Tecnologia |
|---|---|
| Frontend | React 19 + TypeScript, Tailwind CSS 4, componentes shadcn/ui |
| Ligação frontend/backend | Inertia.js 2 |
| Backend | Laravel 12 (PHP 8.2) |
| Base de dados | MariaDB 10.4 (XAMPP) |
