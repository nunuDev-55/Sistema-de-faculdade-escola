<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Services\AnoLectivoService;
use App\Services\EstudanteService;
use App\Services\PlanoCurricularService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** O Início mostra o ano em curso e os números dos módulos feitos. */
class InicioTest extends TestCase
{
    use RefreshDatabase;

    public function test_mostra_o_ano_em_curso_e_os_numeros_dos_modulos(): void
    {
        $admin = $this->administrador();
        $hoje = CarbonImmutable::now();
        app(AnoLectivoService::class)->criar((string) $hoje->year, $hoje->startOfYear(), $hoje->endOfYear()->startOfDay());
        app(EstudanteService::class)->registar(['nome' => 'Ana Macuácua', 'categoria' => 'regular'], $admin);
        $curso = Curso::create(['codigo' => 'LIG', 'nome' => 'Licenciatura em Informática de Gestão', 'grau' => 'licenciatura', 'duracao_anos' => 4]);
        app(PlanoCurricularService::class)->criar($curso, '2027', 'media_simples', $admin);

        $caixa = fn (array $caixas, string $href) => collect($caixas)->firstWhere('href', $href);

        $this->actingAs($admin)->get('/painel')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('calendario.ano', (string) $hoje->year)
                ->where('calendario.periodo', fn ($p) => is_string($p) && str_ends_with($p, 'semestre'))
                ->where('caixas', function ($caixas) use ($caixa) {
                    $lista = collect($caixas)->toArray();

                    return $caixa($lista, '/estudantes')['numero'] === 1
                        && $caixa($lista, '/estrutura')['numero'] === 1
                        && $caixa($lista, '/estrutura')['alerta'] === '1 plano curricular por aprovar'
                        && $caixa($lista, '/matriculas')['numero'] === 0;
                }));
    }

    public function test_sem_ano_lectivo_avisa_na_caixa_das_matriculas(): void
    {
        $this->actingAs($this->administrador())->get('/painel')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('calendario', null)
                ->where('caixas', fn ($caixas) => str_contains((string) collect($caixas)->firstWhere('href', '/matriculas')['alerta'], 'Calendário')));
    }
}
