<?php

namespace App\Support;

/**
 * Os módulos do sistema e o que cada um vai ter (ver docs/ROADMAP.md).
 *
 * No esqueleto, cada módulo abre num ecrã "em construção" com a sua lista de funcionalidades planeadas,
 * para que o menu e as permissões se possam experimentar desde já. À medida que cada módulo for feito,
 * a sua rota deixa de apontar para o ecrã "em construção" e passa a ter os seus próprios controladores e ecrãs:
 * marca-se com 'feito' => true e as rotas reais ficam em routes/web.php.
 */
final class Modulos
{
    /** @var array<string, array{href: string, titulo: string, permissao: string, descricao: string, planeado: list<string>, feito?: bool}> */
    public const LISTA = [
        'candidaturas' => [
            'href' => '/candidaturas',
            'titulo' => 'Candidaturas e admissão',
            'permissao' => 'candidaturas.ver',
            'descricao' => 'Fases de candidatura, vagas por curso, verificação, resultados e admissão.',
            'planeado' => ['Abrir fases com vagas e taxa', 'Candidatura online do candidato', 'Lista de admitidos por classificação', 'Admitir → criar estudante'],
        ],
        'estudantes' => [
            'href' => '/estudantes',
            'titulo' => 'Estudantes e encarregados',
            'permissao' => 'estudantes.ver',
            'descricao' => 'Ficha do estudante com separadores, encarregados e importação do Excel.',
            'planeado' => ['Ficha: dados, matrículas, inscrições, notas, presenças, finanças', 'Encarregados (um para vários educandos)', 'Importar do Excel', 'Pesquisa por curso, turma, estado e dívida'],
        ],
        'matriculas' => [
            'href' => '/matriculas',
            'titulo' => 'Matrículas e inscrições',
            'permissao' => 'matriculas.criar',
            'descricao' => 'Matrícula no ano lectivo, inscrição semestral nas disciplinas e distribuição por turmas.',
            'planeado' => ['Matricular e renovar matrícula', 'Inscrição com precedências e limite de créditos', 'Inscrição semestral com comprovativo', 'Distribuir por turmas'],
        ],
        'docencia' => [
            'href' => '/docencia',
            'titulo' => 'Docentes, horários e aulas',
            'permissao' => 'aulas.registar',
            'descricao' => 'Distribuição de serviço, horários sem sobreposições, sumários, presenças e materiais.',
            'planeado' => ['Distribuição de serviço docente', 'Horário por turma, docente e sala', 'Sumários e presenças por aula', 'Materiais das aulas'],
        ],
        'pautas' => [
            'href' => '/pautas',
            'titulo' => 'Avaliação e pautas',
            'permissao' => 'pautas.ver',
            'descricao' => 'Plano de avaliação, lançamento de notas, pautas e revisões.',
            'planeado' => ['Plano de avaliação (pesos = 100%)', 'Folha de notas tipo Excel', 'Pauta: rascunho → validada → publicada → fechada', 'Exame, recorrência e especial; revisões de nota'],
        ],
        'financas' => [
            'href' => '/financas',
            'titulo' => 'Finanças do estudante',
            'permissao' => 'financas.ver',
            'descricao' => 'Tabela de preços, cobranças, pagamentos, recibos e referências bancárias.',
            'planeado' => ['Taxa de inscrição e propinas mensais', 'Cobranças do ano e conta corrente', 'Pagamentos, adiantamentos e recibos', 'Referência bancária por cobrança e devedores'],
        ],
        'documentos' => [
            'href' => '/documentos',
            'titulo' => 'Documentos académicos',
            'permissao' => 'documentos.ver',
            'descricao' => 'Declarações, certificados e histórico escolar, com número e QR de verificação.',
            'planeado' => ['Pedido de documento no portal', 'Emolumento e fila da secretaria', 'Emissão com QR de verificação', 'Verificação pública em /verificar/{codigo}'],
        ],
        'estrutura' => [
            'href' => '/estrutura',
            'titulo' => 'Estrutura académica',
            'permissao' => 'estrutura.ver',
            'feito' => true,
            'descricao' => 'Unidades orgânicas, cursos, disciplinas, planos curriculares e calendário.',
            'planeado' => ['Faculdades, departamentos e cursos', 'Disciplinas e créditos', 'Planos curriculares com versões e precedências', 'Anos e períodos lectivos'],
        ],
    ];

    /**
     * Módulos de Administração (herdados do nsERP, portam-se na fase 1).
     *
     * @var array<string, array{href: string, titulo: string, permissao: string, descricao: string, planeado: list<string>, feito?: bool}>
     */
    public const ADMINISTRACAO = [
        'utilizadores' => [
            'href' => '/administracao/utilizadores',
            'titulo' => 'Utilizadores',
            'permissao' => 'utilizadores.ver',
            'descricao' => 'Contas da equipa, perfis e activação (do nsERP).',
            'planeado' => ['Criar e editar utilizadores', 'Atribuir perfis', 'Activar e desactivar', 'Verificação em dois passos'],
        ],
        'perfis' => [
            'href' => '/administracao/perfis',
            'titulo' => 'Perfis e permissões',
            'permissao' => 'perfis.gerir',
            'descricao' => 'Matriz de permissões por perfil (Spatie Permission, do nsERP).',
            'planeado' => ['Criar e editar perfis', 'Matriz de permissões', 'Perfis de sistema protegidos'],
        ],
        'auditoria' => [
            'href' => '/administracao/auditoria',
            'titulo' => 'Auditoria',
            'permissao' => 'auditoria.ver',
            'descricao' => 'Registo de quem fez o quê e quando (do nsERP).',
            'planeado' => ['Lista de acções com autor e data', 'Valores antes e depois', 'Filtros por módulo e utilizador'],
        ],
        'configuracao' => [
            'href' => '/administracao/configuracao',
            'titulo' => 'Configuração',
            'permissao' => 'configuracao.gerir',
            'feito' => true,
            'descricao' => 'Dados da instituição, marca e cores, tipo de instituição, regras pedagógicas e numeração.',
            'planeado' => ['Dados e logótipo da instituição', 'Cores e tipo (superior / geral)', 'Regras pedagógicas (escala, limites, pesos)', 'Numeração dos documentos'],
        ],
        'copias' => [
            'href' => '/administracao/copias',
            'titulo' => 'Cópias de segurança',
            'permissao' => 'copias.gerir',
            'descricao' => 'Cópias da base de dados e dos ficheiros (do nsERP).',
            'planeado' => ['Criar cópia de segurança', 'Descarregar', 'Cópia diária automática'],
        ],
    ];

    /** @return array<string, array{href: string, titulo: string, permissao: string, descricao: string, planeado: list<string>, feito?: bool}> */
    public static function todos(): array
    {
        return [...self::LISTA, ...self::ADMINISTRACAO];
    }
}
