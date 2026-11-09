<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de fumo do esqueleto: a entrada, as permissões e o acesso aos módulos por perfil.
 * Cada módulo acrescenta os seus próprios testes em tests/Feature/<Módulo> (ver docs/05-arquitectura.md).
 */
class EsqueletoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_raiz_leva_a_quem_nao_entrou_para_o_login(): void
    {
        $this->get('/')->assertRedirect('/painel');
        $this->get('/painel')->assertRedirect('/login');
    }

    public function test_o_primeiro_registo_cria_o_administrador_e_so_funciona_uma_vez(): void
    {
        $this->post('/register', [
            'name' => 'Admin',
            'email' => 'admin@instituicao.co.mz',
            'password' => 'password-forte-123',
            'password_confirmation' => 'password-forte-123',
        ])->assertRedirect('/painel');

        $this->assertDatabaseHas('users', ['email' => 'admin@instituicao.co.mz']);
        $this->assertTrue(User::first()->isAdministrador());

        // Depois do primeiro, o registo fecha-se.
        $this->get('/register')->assertRedirect('/login');
    }

    public function test_o_administrador_ve_o_painel(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->get('/painel')->assertOk();
    }

    public function test_cada_modulo_exige_a_sua_permissao(): void
    {
        // A secretaria vê estudantes mas não a configuração.
        $secretaria = $this->utilizadorComPerfil('Secretaria académica');

        $this->actingAs($secretaria)->get('/estudantes')->assertOk();
        $this->actingAs($secretaria)->get('/administracao/configuracao')->assertForbidden();
    }

    public function test_o_docente_so_ve_os_seus_modulos(): void
    {
        $docente = $this->utilizadorComPerfil('Docente');

        // O docente lança notas (pautas) mas não gere finanças.
        $this->actingAs($docente)->get('/pautas')->assertOk();
        $this->actingAs($docente)->get('/financas')->assertForbidden();
    }

    public function test_sincronizar_cria_os_perfis_de_origem(): void
    {
        Permissoes::sincronizar();

        foreach (array_keys(Permissoes::PERFIS_INICIAIS) as $perfil) {
            $this->assertDatabaseHas('roles', ['name' => $perfil, 'guard_name' => 'web']);
        }
    }
}
