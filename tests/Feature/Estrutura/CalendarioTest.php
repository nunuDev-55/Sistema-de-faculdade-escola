<?php

namespace Tests\Feature\Estrutura;

use App\Models\AnoLectivo;
use App\Support\Definicoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_ensino_superior_o_ano_tem_dois_semestres_seguidos(): void
    {
        $this->actingAs($this->administrador())
            ->post('/estrutura/calendario', ['nome' => '2027', 'inicio' => '2027-02-01', 'fim' => '2027-12-15'])
            ->assertSessionHasNoErrors();

        $periodos = AnoLectivo::where('nome', '2027')->firstOrFail()->periodos;
        $this->assertCount(2, $periodos);
        $this->assertSame('semestre', $periodos[0]->tipo);
        $this->assertSame('2027-02-01', $periodos[0]->inicio->toDateString());
        $this->assertSame('2027-12-15', $periodos[1]->fim->toDateString());
        // Sem dias por cobrir entre um semestre e o seguinte
        $this->assertSame($periodos[0]->fim->addDay()->toDateString(), $periodos[1]->inicio->toDateString());
    }

    public function test_no_ensino_geral_o_ano_tem_tres_trimestres(): void
    {
        app(Definicoes::class)->guardar(['instituicao.tipo' => 'geral']);

        $this->actingAs($this->administrador())
            ->post('/estrutura/calendario', ['nome' => '2027', 'inicio' => '2027-01-15', 'fim' => '2027-11-30'])
            ->assertSessionHasNoErrors();

        $periodos = AnoLectivo::firstOrFail()->periodos;
        $this->assertCount(3, $periodos);
        $this->assertSame('trimestre', $periodos[2]->tipo);
        $this->assertSame('2027-11-30', $periodos[2]->fim->toDateString());
    }

    public function test_um_periodo_nao_sai_do_ano_lectivo(): void
    {
        $this->actingAs($this->administrador())
            ->post('/estrutura/calendario', ['nome' => '2027', 'inicio' => '2027-02-01', 'fim' => '2027-12-15']);
        $periodo = AnoLectivo::firstOrFail()->periodos[0];

        $this->put("/estrutura/calendario/periodos/{$periodo->id}", ['inicio' => '2027-01-10', 'fim' => '2027-07-10'])
            ->assertSessionHasErrors('periodo');

        $this->put("/estrutura/calendario/periodos/{$periodo->id}", [
            'inicio' => '2027-02-01', 'fim' => '2027-07-10',
            'inscricoes_inicio' => '2027-01-15', 'inscricoes_fim' => '2027-01-31',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2027-01-31', $periodo->fresh()?->inscricoes_fim?->toDateString());
    }
}
