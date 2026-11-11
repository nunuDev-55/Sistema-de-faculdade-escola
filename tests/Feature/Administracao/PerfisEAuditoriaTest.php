<?php

namespace Tests\Feature\Administracao;

use App\Models\User;
use App\Support\Permissoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerfisEAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_as_permissoes_de_um_perfil_passam_a_valer(): void
    {
        $admin = $this->administrador();
        $docente = Role::findByName('Docente');

        $this->actingAs($admin)->get('/administracao/perfis')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('administracao/perfis')->has('catalogo')->where('perfis.0.nome', Permissoes::ADMINISTRADOR));

        $this->put("/administracao/perfis/{$docente->id}", ['permissoes' => ['estudantes.ver']])->assertSessionHas('sucesso');
        $this->assertTrue($docente->fresh()?->hasPermissionTo('estudantes.ver'));

        $user = User::factory()->create()->assignRole('Docente');
        $this->actingAs($user)->get('/estudantes')->assertOk();
    }

    public function test_permissao_que_nao_existe_e_recusada_e_o_administrador_nao_se_altera(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->put('/administracao/perfis/'.Role::findByName('Docente')->id, ['permissoes' => ['tudo.e.mais.alguma']])
            ->assertSessionHasErrors('permissoes.0');
        $this->put('/administracao/perfis/'.Role::findByName(Permissoes::ADMINISTRADOR)->id, ['permissoes' => []])->assertForbidden();
    }

    public function test_os_perfis_do_sistema_nao_se_apagam_e_os_novos_sim(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->delete('/administracao/perfis/'.Role::findByName('Tesouraria')->id)->assertForbidden();

        $this->post('/administracao/perfis', ['nome' => 'Biblioteca'])->assertSessionHas('sucesso');
        $biblioteca = Role::findByName('Biblioteca');

        // Com pessoas lá dentro não se apaga
        $leitor = User::factory()->create()->assignRole('Biblioteca');
        $this->delete("/administracao/perfis/{$biblioteca->id}")->assertSessionHas('erro');
        $leitor->syncRoles(['Docente']);

        $this->delete("/administracao/perfis/{$biblioteca->id}")->assertSessionHas('sucesso');
        $this->assertDatabaseMissing('roles', ['name' => 'Biblioteca']);
    }

    public function test_sem_permissao_nao_ve_perfis_nem_auditoria(): void
    {
        $secretaria = $this->utilizadorComPerfil('Secretaria académica');

        $this->actingAs($secretaria)->get('/administracao/perfis')->assertForbidden();
        $this->get('/administracao/auditoria')->assertForbidden();
    }

    public function test_criar_uma_conta_fica_na_auditoria_sem_a_senha(): void
    {
        $admin = $this->administrador(['name' => 'Admin Principal']);

        $this->actingAs($admin)->post('/administracao/utilizadores', [
            'name' => 'Rita Sitoe', 'email' => 'rita@instituicao.ac.mz', 'perfil' => 'Tesouraria',
            'password' => 'SenhaForte2027', 'password_confirmation' => 'SenhaForte2027',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_log', ['log_name' => 'utilizadores', 'description' => 'Utilizador criado', 'causer_id' => $admin->id]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Perfil atribuído: Tesouraria']);
        $this->assertFalse(Activity::all()->contains(fn (Activity $a) => str_contains((string) json_encode($a->properties), 'SenhaForte2027')));

        $this->get('/administracao/auditoria?modulo=utilizadores')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('administracao/auditoria')
                ->where('registos.data.0.utilizador', 'Admin Principal')
                ->where('registos.data.0.registo', 'Rita Sitoe'));
    }

    public function test_a_auditoria_mostra_matriculas_e_configuracao(): void
    {
        $admin = $this->administrador();
        $this->actingAs($admin)->put('/administracao/configuracao/avaliacao', [
            'escala_maxima' => '20', 'casas_decimais' => 1, 'nota_minima_aprovacao' => '10', 'minimo_admissao_exame' => '10',
            'minimo_dispensa_exame' => '14', 'assiduidade_minima' => 75, 'limite_creditos' => '30',
            'intervalos' => [['min' => '0', 'max' => '9,4', 'grau' => 'Reprovado'], ['min' => '9,5', 'max' => '20', 'grau' => 'Aprovado']],
        ])->assertSessionHasNoErrors();

        $this->get('/administracao/auditoria?modulo=configuracao')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('registos.data.0.descricao', 'Regras de avaliação alteradas')
                ->where('registos.data.0.detalhes', fn ($d) => str_contains((string) $d, 'Limite de créditos')));
    }

    public function test_entrar_no_sistema_fica_registado(): void
    {
        $user = $this->utilizadorComPerfil('Docente', ['email' => 'carlos@instituicao.ac.mz']);

        $this->post('/login', ['email' => 'carlos@instituicao.ac.mz', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()?->last_login_at);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'acessos', 'causer_id' => $user->id, 'description' => 'Entrou no sistema']);
    }
}
