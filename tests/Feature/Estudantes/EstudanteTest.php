<?php

namespace Tests\Feature\Estudantes;

use App\Models\Encarregado;
use App\Models\Estudante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EstudanteTest extends TestCase
{
    use Cenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->administrador();
    }

    public function test_regista_o_estudante_com_numero_sequencial(): void
    {
        $ano = now()->year;

        $this->actingAs($this->admin)
            ->post('/estudantes', ['nome' => '  Ana   Macuácua ', 'categoria' => 'regular', 'documento_tipo' => 'bi', 'documento_numero' => '110100000001a', 'nuit' => '400 123 456'])
            ->assertSessionHasNoErrors();
        $this->post('/estudantes', ['nome' => 'Bento Chissano', 'categoria' => 'bolseiro'])->assertSessionHasNoErrors();

        $ana = Estudante::where('nome', 'Ana Macuácua')->firstOrFail();
        $this->assertSame("{$ano}/00001", $ana->numero);
        $this->assertSame('110100000001A', $ana->documento_numero);
        $this->assertSame('400123456', $ana->nuit);
        $this->assertSame('activo', $ana->estado);
        $this->assertSame("{$ano}/00002", Estudante::where('nome', 'Bento Chissano')->value('numero'));
        // Sem número de documento, o tipo não fica gravado
        $this->assertNull(Estudante::where('nome', 'Bento Chissano')->value('documento_tipo'));
    }

    public function test_nao_regista_a_mesma_pessoa_duas_vezes(): void
    {
        $dados = ['nome' => 'Ana Macuácua', 'categoria' => 'regular', 'documento_tipo' => 'bi', 'documento_numero' => '110100000001A'];
        $this->actingAs($this->admin)->post('/estudantes', $dados)->assertSessionHasNoErrors();

        $this->post('/estudantes', $dados)->assertSessionHasErrors('documento_numero');
        $this->assertSame(1, Estudante::count());
    }

    public function test_a_ficha_e_a_lista_abrem(): void
    {
        $this->actingAs($this->admin)->post('/estudantes', ['nome' => 'Ana Macuácua', 'categoria' => 'regular', 'telemovel' => '841234567']);
        $this->post('/estudantes', ['nome' => 'Bento Chissano', 'categoria' => 'regular']);
        $ana = Estudante::where('nome', 'Ana Macuácua')->firstOrFail();

        $this->get("/estudantes/{$ana->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('estudantes/ficha')->where('estudante.numero', $ana->numero)->has('matriculas', 0));

        $this->get('/estudantes?q=macu')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('estudantes/index')->has('estudantes.data', 1)->where('estudantes.data.0.nome', 'Ana Macuácua'));
        $this->get('/estudantes?q=841234')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('estudantes.data', 1));
        $this->get('/estudantes/novo')->assertOk()->assertInertia(fn (Assert $page) => $page->component('estudantes/formulario'));
    }

    public function test_altera_os_dados_e_o_estado(): void
    {
        $estudante = $this->estudante();

        $this->actingAs($this->admin)
            ->put("/estudantes/{$estudante->id}", ['nome' => 'Ana Maria Macuácua', 'categoria' => 'regular', 'estado' => 'suspenso', 'provincia' => 'Gaza'])
            ->assertRedirect("/estudantes/{$estudante->id}");

        $estudante->refresh();
        $this->assertSame('Ana Maria Macuácua', $estudante->nome);
        $this->assertSame('suspenso', $estudante->estado);
        $this->assertSame('Gaza', $estudante->provincia);

        $this->put("/estudantes/{$estudante->id}", ['nome' => 'Ana', 'categoria' => 'regular', 'estado' => 'activo', 'provincia' => 'Lisboa'])
            ->assertSessionHasErrors('provincia');
    }

    public function test_um_encarregado_para_dois_irmaos(): void
    {
        $ana = $this->estudante('Ana Macuácua');
        $rui = $this->estudante('Rui Macuácua');

        $this->actingAs($this->admin)
            ->post("/estudantes/{$ana->id}/encarregados", ['nome' => 'Joaquim Macuácua', 'parentesco' => 'pai', 'telemovel' => '84 123 4567'])
            ->assertSessionHasNoErrors();
        $this->post("/estudantes/{$rui->id}/encarregados", ['nome' => 'Joaquim Macuácua', 'parentesco' => 'pai', 'telemovel' => '+258 841234567'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Encarregado::count());
        $joaquim = Encarregado::firstOrFail();
        $this->assertSame('841234567', $joaquim->telemovel);
        $this->assertSame(2, $joaquim->estudantes()->count());
        // O primeiro encarregado de cada estudante é o principal
        $this->assertTrue((bool) $ana->encarregados()->first()?->pivot->getAttribute('principal'));

        $this->delete("/estudantes/{$ana->id}/encarregados/{$joaquim->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, $ana->encarregados()->count());
        $this->assertSame(1, $rui->encarregados()->count());
    }

    public function test_cada_perfil_ve_o_que_lhe_cabe(): void
    {
        $estudante = $this->estudante();

        $docente = $this->utilizadorComPerfil('Docente');
        $this->actingAs($docente)->get('/estudantes')->assertForbidden();

        // A tesouraria vê os estudantes, mas não os regista nem os altera
        $tesouraria = $this->utilizadorComPerfil('Tesouraria');
        $this->actingAs($tesouraria)->get('/estudantes')->assertOk();
        $this->get("/estudantes/{$estudante->id}")->assertOk();
        $this->get('/estudantes/novo')->assertForbidden();
        $this->post('/estudantes', ['nome' => 'X', 'categoria' => 'regular'])->assertForbidden();
    }
}
