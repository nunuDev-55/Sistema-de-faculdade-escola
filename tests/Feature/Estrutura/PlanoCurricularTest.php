<?php

namespace Tests\Feature\Estrutura;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\PlanoCurricular;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanoCurricularTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Curso $curso;

    private Disciplina $prog1;

    private Disciplina $prog2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->administrador();
        $this->curso = Curso::create(['codigo' => 'LIG', 'nome' => 'Licenciatura em Informática de Gestão', 'grau' => 'licenciatura', 'duracao_anos' => 4]);
        $this->prog1 = Disciplina::create(['codigo' => '00168', 'nome' => 'Programação I', 'creditos' => 6, 'horas_teoricas' => 30, 'horas_praticas' => 30]);
        $this->prog2 = Disciplina::create(['codigo' => '00169', 'nome' => 'Programação II', 'creditos' => 6, 'horas_teoricas' => 30, 'horas_praticas' => 30]);
    }

    /** Plano 2027 com Programação I no 1.º semestre e Programação II no 2.º */
    private function planoComDuasDisciplinas(): PlanoCurricular
    {
        $this->actingAs($this->admin)->post("/estrutura/cursos/{$this->curso->id}/planos", ['versao' => '2027'])->assertSessionHasNoErrors();
        $plano = PlanoCurricular::where('versao', '2027')->firstOrFail();
        $this->post("/estrutura/planos/{$plano->id}/disciplinas", ['disciplina_id' => $this->prog1->id, 'ano' => 1, 'periodo' => 1])->assertSessionHasNoErrors();
        $this->post("/estrutura/planos/{$plano->id}/disciplinas", ['disciplina_id' => $this->prog2->id, 'ano' => 1, 'periodo' => 2])->assertSessionHasNoErrors();

        return $plano;
    }

    public function test_cria_curso_e_disciplina(): void
    {
        $this->actingAs($this->admin)
            ->post('/estrutura/cursos', ['codigo' => 'lec', 'nome' => 'Licenciatura em Economia', 'grau' => 'licenciatura', 'duracao_anos' => 4, 'activo' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cursos', ['codigo' => 'LEC']);

        $this->post('/estrutura/disciplinas', ['codigo' => '00062', 'nome' => 'Base de Dados I', 'creditos' => '4,5', 'horas_teoricas' => 30, 'horas_praticas' => 15, 'activo' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('disciplinas', ['codigo' => '00062', 'creditos' => '4.5']);
    }

    public function test_a_precedente_tem_de_vir_antes(): void
    {
        $plano = $this->planoComDuasDisciplinas();

        $this->post("/estrutura/planos/{$plano->id}/precedencias", ['disciplina_id' => $this->prog2->id, 'precedente_id' => $this->prog1->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('precedencias', ['plano_id' => $plano->id, 'disciplina_id' => $this->prog2->id, 'precedente_id' => $this->prog1->id]);

        // Programação II não pode ser precedente de Programação I (vem depois)
        $this->post("/estrutura/planos/{$plano->id}/precedencias", ['disciplina_id' => $this->prog1->id, 'precedente_id' => $this->prog2->id])
            ->assertSessionHasErrors('precedente_id');
    }

    public function test_uma_disciplina_nao_entra_num_ano_que_o_curso_nao_tem(): void
    {
        $this->actingAs($this->admin)->post("/estrutura/cursos/{$this->curso->id}/planos", ['versao' => '2027']);
        $plano = PlanoCurricular::firstOrFail();

        $this->post("/estrutura/planos/{$plano->id}/disciplinas", ['disciplina_id' => $this->prog1->id, 'ano' => 6, 'periodo' => 1])
            ->assertSessionHasErrors('ano');
    }

    public function test_o_plano_aprovado_fica_em_vigor_e_nao_se_altera(): void
    {
        $plano = $this->planoComDuasDisciplinas();

        $this->post("/estrutura/planos/{$plano->id}/aprovar")->assertSessionHasNoErrors();
        $this->assertSame(PlanoCurricular::APROVADO, $plano->fresh()?->estado);
        $this->assertSame($plano->id, $this->curso->planoEmVigor()?->id);

        $base = Disciplina::create(['codigo' => '00062', 'nome' => 'Base de Dados I', 'creditos' => 6, 'horas_teoricas' => 30, 'horas_praticas' => 30]);
        $this->post("/estrutura/planos/{$plano->id}/disciplinas", ['disciplina_id' => $base->id, 'ano' => 1, 'periodo' => 2])
            ->assertSessionHasErrors('plano');
    }

    public function test_um_plano_sem_disciplinas_nao_se_aprova(): void
    {
        $this->actingAs($this->admin)->post("/estrutura/cursos/{$this->curso->id}/planos", ['versao' => '2027']);
        $plano = PlanoCurricular::firstOrFail();

        $this->post("/estrutura/planos/{$plano->id}/aprovar")->assertSessionHasErrors('plano');
    }

    public function test_a_versao_nova_copia_a_anterior_e_substitui_a_ao_ser_aprovada(): void
    {
        $plano = $this->planoComDuasDisciplinas();
        $this->post("/estrutura/planos/{$plano->id}/precedencias", ['disciplina_id' => $this->prog2->id, 'precedente_id' => $this->prog1->id]);
        $this->post("/estrutura/planos/{$plano->id}/aprovar");

        $this->post("/estrutura/planos/{$plano->id}/versao", ['versao' => '2028'])->assertSessionHasNoErrors();
        $novo = PlanoCurricular::where('versao', '2028')->firstOrFail();
        $this->assertSame(PlanoCurricular::RASCUNHO, $novo->estado);
        $this->assertSame(2, $novo->linhas()->count());
        $this->assertSame(1, $novo->precedencias()->count());

        $this->post("/estrutura/planos/{$novo->id}/aprovar")->assertSessionHasNoErrors();
        $this->assertSame(PlanoCurricular::SUBSTITUIDO, $plano->fresh()?->estado);
        $this->assertSame($novo->id, $this->curso->planoEmVigor()?->id);
    }

    public function test_o_docente_nao_ve_a_estrutura(): void
    {
        $this->actingAs($this->utilizadorComPerfil('Docente'))->get('/estrutura/cursos')->assertForbidden();
    }

    public function test_o_director_de_curso_monta_o_plano_mas_so_a_direccao_aprova(): void
    {
        $plano = $this->planoComDuasDisciplinas();

        $this->actingAs($this->utilizadorComPerfil('Director de curso'))
            ->get("/estrutura/planos/{$plano->id}")->assertOk();
        $this->post("/estrutura/planos/{$plano->id}/aprovar")->assertForbidden();

        $this->actingAs($this->utilizadorComPerfil('Direcção'))
            ->post("/estrutura/planos/{$plano->id}/aprovar")->assertSessionHasNoErrors();
    }
}
