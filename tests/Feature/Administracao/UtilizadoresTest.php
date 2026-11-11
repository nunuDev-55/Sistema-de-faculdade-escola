<?php

namespace Tests\Feature\Administracao;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Contas da equipa (regras herdadas do nsERP). */
class UtilizadoresTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function dados(array $alterar = []): array
    {
        return [
            'name' => 'Marta Cossa',
            'email' => 'Marta.Cossa@Instituicao.ac.mz',
            'phone' => '84 123 4567',
            'perfil' => 'Secretaria académica',
            'password' => 'SenhaForte2027',
            'password_confirmation' => 'SenhaForte2027',
            ...$alterar,
        ];
    }

    public function test_o_administrador_ve_a_lista(): void
    {
        $admin = $this->administrador(['name' => 'Admin Principal']);

        $this->actingAs($admin)->get('/administracao/utilizadores')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('administracao/utilizadores')
                ->where('utilizadores.data.0.name', 'Admin Principal')
                ->where('utilizadores.data.0.perfil', 'Administrador')
                ->where('resumo.total', 1));
    }

    public function test_cria_a_conta_com_o_perfil_e_o_email_em_minusculas(): void
    {
        $this->actingAs($this->administrador())->post('/administracao/utilizadores', $this->dados())
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        $marta = User::where('email', 'marta.cossa@instituicao.ac.mz')->firstOrFail();
        $this->assertTrue($marta->hasRole('Secretaria académica'));
        $this->assertTrue($marta->active);
    }

    public function test_dados_invalidos_voltam_com_erros(): void
    {
        $this->actingAs($this->administrador(['email' => 'admin@instituicao.ac.mz']))
            ->post('/administracao/utilizadores', $this->dados([
                'email' => 'admin@instituicao.ac.mz',
                'phone' => '12345',
                'perfil' => 'Perfil que não existe',
                'password_confirmation' => 'outra',
            ]))
            ->assertSessionHasErrors(['email', 'phone', 'perfil', 'password']);
    }

    public function test_quem_so_ve_nao_cria_e_sem_permissao_nem_ve(): void
    {
        $leitor = $this->utilizadorComPermissoes(['utilizadores.ver']);
        $this->actingAs($leitor)->get('/administracao/utilizadores')->assertOk();
        $this->post('/administracao/utilizadores', $this->dados())->assertForbidden();

        $docente = $this->utilizadorComPerfil('Docente');
        $this->actingAs($docente)->get('/administracao/utilizadores')->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'marta.cossa@instituicao.ac.mz']);
    }

    public function test_alterar_sem_senha_mantem_a_senha_e_muda_o_perfil(): void
    {
        $user = $this->utilizadorComPerfil('Docente');
        $senha = $user->password;

        $this->actingAs($this->administrador())->put("/administracao/utilizadores/{$user->id}", [
            'name' => 'Nome Novo', 'email' => $user->email, 'phone' => null, 'perfil' => 'Direcção', 'password' => null, 'password_confirmation' => null,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nome Novo', $user->name);
        $this->assertSame($senha, $user->password);
        $this->assertTrue($user->hasRole('Direcção'));
        $this->assertFalse($user->hasRole('Docente'));
    }

    public function test_desactivar_termina_as_sessoes_e_impede_a_entrada(): void
    {
        $user = $this->utilizadorComPerfil('Tesouraria', ['email' => 'joao@instituicao.ac.mz']);
        DB::table('sessions')->insert(['id' => 'sessao-joao', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->administrador())->post("/administracao/utilizadores/{$user->id}/estado")->assertSessionHas('sucesso');

        $this->assertFalse($user->fresh()?->active);
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-joao']);

        auth()->logout();
        $this->post('/login', ['email' => 'joao@instituicao.ac.mz', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_ninguem_se_desactiva_a_si_proprio(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->post("/administracao/utilizadores/{$admin->id}/estado")->assertSessionHasErrors('estado');
        $this->assertTrue($admin->fresh()?->active);
    }

    public function test_o_unico_administrador_nao_perde_o_perfil(): void
    {
        $admin = $this->administrador();
        $gestor = $this->utilizadorComPermissoes(['utilizadores.ver', 'utilizadores.gerir']);

        $this->actingAs($gestor)->put("/administracao/utilizadores/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'perfil' => 'Docente',
        ])->assertSessionHasErrors('perfil');

        $this->assertTrue($admin->fresh()?->isAdministrador());
    }

    public function test_pesquisa_por_nome_trata_a_percentagem_como_texto(): void
    {
        $admin = $this->administrador(['name' => 'Admin']);
        $this->utilizadorComPerfil('Docente', ['name' => 'Carlos Tembe']);
        $this->utilizadorComPerfil('Docente', ['name' => 'Rita Sitoe']);

        $this->actingAs($admin)->get('/administracao/utilizadores?pesquisa=tembe')
            ->assertInertia(fn (Assert $page) => $page->has('utilizadores.data', 1)->where('utilizadores.data.0.name', 'Carlos Tembe'));
        $this->get('/administracao/utilizadores?pesquisa=%25')
            ->assertInertia(fn (Assert $page) => $page->has('utilizadores.data', 0));
        $this->get('/administracao/utilizadores?perfil=Docente')
            ->assertInertia(fn (Assert $page) => $page->has('utilizadores.data', 2));
    }
}
