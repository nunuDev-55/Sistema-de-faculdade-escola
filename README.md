# nsEDU — Sistema de gestão de universidades e escolas

> Nome provisório.

Sistema de gestão académica e financeira para instituições de ensino em Moçambique: candidaturas, estudantes e encarregados, matrículas e inscrições, turmas e horários, presenças, avaliação e pautas, propinas e recibos, documentos académicos e portais para estudantes e encarregados.

Serve **universidades e institutos** (cursos, semestres, créditos, exames e recorrência) e **escolas do ensino geral** (classes, trimestres, passagem de classe), **uma instituição por instalação**.

## Estado

**Esqueleto a funcionar** (fase 1.1 do [plano](docs/ROADMAP.md)): a aplicação Laravel 12 + Inertia + React, a partir da base do nsERP, já com a entrada no sistema, os perfis e as permissões de origem, o painel e o menu por perfil. Cada módulo (candidaturas, estudantes, matrículas, avaliação, finanças, documentos...) abre num ecrã "em construção" com o que vai ter, para o menu e as permissões se poderem experimentar desde já. Os módulos vão sendo feitos um a um (ver o [plano](docs/ROADMAP.md)).

### Análise de sistemas

| Documento | Conteúdo |
|---|---|
| [Referências open source](docs/01-referencias-opensource.md) | 13 sistemas do GitHub (Frappe Education, OpenEduCat, openSIS, RosarioSIS, Gibbon, Moodle...) e o que levamos de cada um |
| [Requisitos](docs/02-requisitos.md) | Visão, actores, requisitos funcionais e não funcionais, regras de negócio |
| [Casos de uso](docs/03-casos-de-uso.md) | Diagrama geral, descrição dos principais, endereços e permissões por módulo, perfis |
| [Base de dados](docs/04-base-de-dados.md) | Modelo entidade-relação por módulo |
| [Arquitectura](docs/05-arquitectura.md) | Stack, camadas, as quatro caras da aplicação, regras, instalação |
| [Processos e estados](docs/06-processos-e-estados.md) | Ciclo de vida da candidatura, matrícula, inscrição, pauta, cobrança; sequências principais |
| [Análise do eDondzo](docs/07-analise-edondzo.md) | Os passos do portal do estudante do eDondzo que o nsEDU segue, e o que faz melhor |
| [Estudo de código](docs/08-estudo-sistemas-academicos.md) | Modelos e lógica do Frappe Education e do OpenEduCat, e os padrões que adoptamos |
| [Plano](docs/ROADMAP.md) | Fases de desenvolvimento |

Os diagramas estão em Mermaid: o GitHub mostra-os directamente; para a monografia, cole-os em https://mermaid.live e exporte em PNG ou SVG.

## Tecnologias

O mesmo stack do [nsERP](https://github.com/nunuDev-55/nsERP), do qual parte a base (entrada, permissões, auditoria, configuração, numeração, recibos):

| Camada | Tecnologia |
|---|---|
| Frontend | React 19 + TypeScript, Tailwind CSS 4, componentes shadcn/ui |
| Ligação frontend/backend | Inertia.js 2 |
| Backend | Laravel 12 (PHP 8.2) |
| Base de dados | MariaDB 10.4 (SQLite no desenvolvimento e nos testes) |

## Arrancar (desenvolvimento)

Precisa de PHP 8.2+, Composer e Node 18+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # ou configure o MariaDB no .env
php artisan migrate --seed        # cria as tabelas e os perfis de origem
composer run dev                  # Laravel + Vite (ou: php artisan serve + npm run dev)
```

No primeiro acesso, o ecrã de registo cria a conta do **administrador**; depois disso o registo fecha-se e os utilizadores passam a ser criados dentro do sistema.

### Qualidade

```bash
composer run verificar   # Pint (estilo), Larastan (análise estática) e os testes
npx tsc --noEmit         # tipos do frontend
npx eslint .             # regras do frontend
npx prettier --check resources/
```

As mesmas verificações correm no GitHub a cada envio (`.github/workflows`).

## Como está organizado

O código segue a arquitectura em camadas descrita em [docs/05-arquitectura.md](docs/05-arquitectura.md):

- `app/Support/` — peças partilhadas: `Permissoes` (catálogo de permissões e perfis), `Definicoes` (configuração da instituição), `TipoInstituicao` (superior / geral), `Escala` (notas), `Dinheiro`, `Modulos`.
- `app/Services/` — regras de negócio (por agora, `Numeracao`).
- `app/Http/` — controladores finos, pedidos (validação) e middleware.
- `resources/js/` — ecrãs (`pages/`), peças (`components/`), menu (`lib/navegacao.ts`) e layouts.
- `database/migrations/` — tabelas base (utilizadores, permissões, auditoria, numeração, configuração).

Para acrescentar um módulo, ver "Acrescentar um módulo novo" em [docs/05-arquitectura.md](docs/05-arquitectura.md).
