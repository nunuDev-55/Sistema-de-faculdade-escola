<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo de permissões e perfis do sistema (ver docs/03-casos-de-uso.md).
 *
 * É a única fonte de verdade: cada módulo novo acrescenta aqui as suas permissões
 * e `php artisan db:seed` (ou o primeiro registo) sincroniza a base de dados.
 *
 * As contas dos portais (estudante, encarregado, candidato) terão uma guarda própria,
 * acrescentada na fase dos portais; nunca abrem o sistema interno.
 */
final class Permissoes
{
    public const ADMINISTRADOR = 'Administrador';

    /**
     * Permissões agrupadas por módulo, na ordem em que aparecem no ecrã de perfis.
     *
     * @var array<string, array{label: string, permissoes: array<string, string>}>
     */
    public const CATALOGO = [
        'estrutura' => [
            'label' => 'Estrutura académica',
            'permissoes' => [
                'estrutura.ver' => 'Ver unidades orgânicas, cursos e disciplinas',
                'estrutura.gerir' => 'Criar e editar cursos, disciplinas e planos curriculares',
                'estrutura.aprovar' => 'Aprovar planos curriculares',
                'calendario.gerir' => 'Gerir anos lectivos, períodos e salas',
            ],
        ],
        'candidaturas' => [
            'label' => 'Candidaturas e admissão',
            'permissoes' => [
                'candidaturas.ver' => 'Ver candidaturas',
                'candidaturas.gerir' => 'Abrir fases, verificar candidaturas e admitir',
                'candidaturas.publicar' => 'Publicar os resultados da admissão',
            ],
        ],
        'estudantes' => [
            'label' => 'Estudantes',
            'permissoes' => [
                'estudantes.ver' => 'Ver estudantes e encarregados',
                'estudantes.gerir' => 'Criar e editar estudantes e encarregados',
            ],
        ],
        'matriculas' => [
            'label' => 'Matrículas e inscrições',
            'permissoes' => [
                'matriculas.criar' => 'Matricular e inscrever em disciplinas',
                'matriculas.estado' => 'Suspender, anular e transferir matrículas',
                'inscricoes.gerir' => 'Gerir inscrições e inscrição semestral',
                'inscricoes.aprovar' => 'Aprovar pedidos de inscrição online',
                'turmas.gerir' => 'Distribuir estudantes por turmas',
            ],
        ],
        'docencia' => [
            'label' => 'Docentes, horários e aulas',
            'permissoes' => [
                'turmas.ver' => 'Ver turmas, docentes e a distribuição de serviço',
                'docentes.gerir' => 'Gerir docentes',
                'distribuicao.gerir' => 'Distribuir o serviço docente',
                'horarios.gerir' => 'Montar horários',
                'aulas.registar' => 'Registar sumários, presenças e materiais (só nas suas turmas)',
                'presencas.justificar' => 'Justificar faltas',
            ],
        ],
        'avaliacao' => [
            'label' => 'Avaliação e pautas',
            'permissoes' => [
                'notas.lancar' => 'Lançar notas e submeter pautas (só nas suas turmas)',
                'pautas.ver' => 'Ver e imprimir pautas',
                'pautas.validar' => 'Validar e publicar pautas; conselho de notas',
                'pautas.rever' => 'Rever notas de pautas publicadas',
                'pautas.fechar' => 'Fechar a época de avaliação',
            ],
        ],
        'financas' => [
            'label' => 'Finanças do estudante',
            'permissoes' => [
                'financas.ver' => 'Ver cobranças, conta corrente e devedores',
                'financas.configurar' => 'Definir a tabela de preços',
                'cobrancas.gerar' => 'Gerar as cobranças do ano',
                'pagamentos.registar' => 'Registar pagamentos e emitir recibos',
                'pagamentos.anular' => 'Anular recibos',
                'descontos.gerir' => 'Atribuir bolsas e descontos',
            ],
        ],
        'documentos' => [
            'label' => 'Documentos académicos',
            'permissoes' => [
                'documentos.ver' => 'Ver os pedidos de documentos',
                'documentos.emitir' => 'Emitir declarações, certificados e históricos',
            ],
        ],
        'comunicacao' => [
            'label' => 'Comunicação',
            'permissoes' => [
                'avisos.enviar' => 'Publicar avisos',
                'sms.enviar' => 'Enviar SMS a grupos',
            ],
        ],
        'utilizadores' => [
            'label' => 'Utilizadores',
            'permissoes' => [
                'utilizadores.ver' => 'Ver utilizadores',
                'utilizadores.gerir' => 'Criar e editar utilizadores',
                'utilizadores.desactivar' => 'Activar e desactivar utilizadores',
            ],
        ],
        'configuracao' => [
            'label' => 'Configuração',
            'permissoes' => [
                'configuracao.gerir' => 'Alterar os dados da instituição, as regras pedagógicas e a numeração',
            ],
        ],
        'perfis' => [
            'label' => 'Perfis e permissões',
            'permissoes' => [
                'perfis.gerir' => 'Gerir perfis e permissões',
            ],
        ],
        'auditoria' => [
            'label' => 'Auditoria',
            'permissoes' => [
                'auditoria.ver' => 'Ver o registo de auditoria',
            ],
        ],
        'copias' => [
            'label' => 'Cópias de segurança',
            'permissoes' => [
                'copias.gerir' => 'Fazer e descarregar cópias de segurança',
            ],
        ],
    ];

    /**
     * Perfis criados na instalação e as permissões que trazem de origem (ver docs/03-casos-de-uso.md §5).
     * O Administrador não precisa de lista: tem acesso a tudo (ver AppServiceProvider).
     *
     * @var array<string, list<string>>
     */
    public const PERFIS_INICIAIS = [
        self::ADMINISTRADOR => [],
        'Direcção' => [
            'estrutura.ver', 'estrutura.aprovar', 'calendario.gerir',
            'candidaturas.ver', 'candidaturas.gerir', 'candidaturas.publicar',
            'estudantes.ver', 'matriculas.estado', 'turmas.ver', 'docentes.gerir',
            'pautas.ver', 'pautas.validar', 'pautas.rever',
            'financas.ver', 'financas.configurar', 'descontos.gerir', 'pagamentos.anular',
            'avisos.enviar', 'sms.enviar', 'auditoria.ver',
        ],
        'Director de curso' => [
            'estrutura.ver', 'estrutura.gerir',
            'estudantes.ver', 'inscricoes.aprovar', 'turmas.ver', 'turmas.gerir', 'horarios.gerir', 'distribuicao.gerir',
            'presencas.justificar', 'pautas.ver', 'pautas.validar', 'pautas.rever',
        ],
        'Secretaria académica' => [
            'estrutura.ver', 'calendario.gerir',
            'candidaturas.ver', 'candidaturas.gerir',
            'estudantes.ver', 'estudantes.gerir',
            'matriculas.criar', 'matriculas.estado', 'inscricoes.gerir', 'inscricoes.aprovar', 'turmas.ver', 'turmas.gerir',
            'docentes.gerir', 'horarios.gerir', 'presencas.justificar',
            'pautas.ver', 'pautas.fechar',
            'documentos.ver', 'documentos.emitir', 'avisos.enviar', 'sms.enviar',
        ],
        'Docente' => [
            'aulas.registar', 'notas.lancar', 'pautas.ver',
        ],
        'Tesouraria' => [
            'estudantes.ver', 'financas.ver', 'financas.configurar',
            'cobrancas.gerar', 'pagamentos.registar',
        ],
    ];

    /**
     * @param  array<string, array{label: string, permissoes: array<string, string>}>  $catalogo
     * @return list<string>
     */
    public static function todas(array $catalogo = self::CATALOGO): array
    {
        $todas = [];
        foreach ($catalogo as $modulo) {
            array_push($todas, ...array_keys($modulo['permissoes']));
        }

        return $todas;
    }

    /**
     * Cria as permissões que faltam e os perfis iniciais. Pode correr várias vezes.
     * Nunca retira permissões que o administrador tenha dado entretanto.
     */
    public static function sincronizar(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existentes = Permission::where('guard_name', 'web')->pluck('name')->all();
        $novas = array_values(array_diff(self::todas(), $existentes));

        foreach ($novas as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        foreach (self::PERFIS_INICIAIS as $perfil => $permissoes) {
            $role = Role::where('name', $perfil)->where('guard_name', 'web')->first();
            if ($role === null) {
                Role::create(['name' => $perfil, 'guard_name' => 'web'])->givePermissionTo($permissoes);
            } else {
                // Perfil que já existia: recebe só as permissões acabadas de criar (módulos novos).
                $role->givePermissionTo(array_values(array_intersect($permissoes, $novas)));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Perfis que vêm com o sistema e não se podem apagar nem mudar de nome. */
    public static function eDeSistema(string $perfil): bool
    {
        return array_key_exists($perfil, self::PERFIS_INICIAIS);
    }
}
