<?php

namespace Tests\Feature\Documentos;

use App\Models\DocumentoEmitido;
use App\Models\Estudante;
use App\Models\Inscricao;
use App\Models\Matricula;
use App\Models\PlanoDisciplina;
use App\Models\User;
use App\Services\EstudanteService;
use App\Services\InscricaoService;
use App\Services\MatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Estudantes\Cenario;
use Tests\TestCase;

class DocumentosTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private Estudante $ana;

    private Matricula $matricula;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCurso();
        $ano = $this->anoLectivo('2027');
        $this->ana = app(EstudanteService::class)->registar([
            'nome' => 'Ana Macuácua', 'categoria' => 'regular', 'sexo' => 'F', 'documento_tipo' => 'bi', 'documento_numero' => '110100000001A',
        ], $this->admin);
        $this->matricula = app(MatriculaService::class)->matricular($this->ana, $this->curso, $ano, 1, 'laboral', $this->admin);
        app(InscricaoService::class)->inscrever($this->matricula, $ano->periodos[0], $this->ids('prog1', 'mat1', 'ingles'), $this->admin);
        $this->secretaria = $this->utilizadorComPerfil('Secretaria académica');
    }

    private function emitir(string $tipo, array $extra = []): DocumentoEmitido
    {
        $this->actingAs($this->secretaria)
            ->post("/estudantes/{$this->ana->id}/documentos", ['tipo' => $tipo, 'matricula_id' => $this->matricula->id, ...$extra])
            ->assertSessionHasNoErrors();

        return DocumentoEmitido::latest('id')->firstOrFail();
    }

    private function aprovar(string $chave, float $nota): void
    {
        Inscricao::where('disciplina_id', $this->disciplinas[$chave]->id)->update(['estado' => Inscricao::APROVADO, 'nota_final' => $nota]);
    }

    public function test_declaracao_de_matricula_numerada_com_codigo_e_verificacao_publica(): void
    {
        $resposta = $this->actingAs($this->secretaria)->post("/estudantes/{$this->ana->id}/documentos", [
            'tipo' => 'declaracao_matricula', 'matricula_id' => $this->matricula->id, 'finalidade' => 'efeitos de bolsa de estudo',
        ]);
        $documento = DocumentoEmitido::firstOrFail();
        $resposta->assertRedirect(route('documentos.show', $documento));

        $this->assertMatchesRegularExpression('#^DC\d{4}/0001$#', $documento->numero);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{12}$/', $documento->codigo_verificacao);
        $texto = implode(' ', $documento->dados()['texto']);
        $this->assertStringContainsString('Ana Macuácua, portadora do Bilhete de identidade n.º 110100000001A', $texto);
        $this->assertStringContainsString('está matriculada no 1.º ano do curso de Licenciatura em Informática de Gestão', $texto);
        $this->assertStringContainsString('destina-se a efeitos de bolsa de estudo.', $texto);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'documentos', 'subject_id' => $documento->id]);

        $this->get(route('documentos.show', $documento))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('documentos/documento')
            ->where('documento.confere', true)
            ->where('documento.codigo', $documento->codigoFormatado())
            ->where('documento.qr', fn ($svg) => str_starts_with((string) $svg, '<svg'))
            ->where('conteudo.titulo', 'Declaração de matrícula'));

        // Quem recebe o papel verifica sem entrar no sistema: com traços, sem traços ou em minúsculas
        auth()->guard('web')->logout();
        foreach ([$documento->codigoFormatado(), strtolower($documento->codigo_verificacao)] as $codigo) {
            $this->get('/verificar/'.$codigo)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('documentos/verificar')
                ->where('resultado.estado', 'valido')
                ->where('resultado.titular', 'Ana Macuácua')
                ->where('resultado.numero', $documento->numero));
        }
        $this->get('/verificar/AAAA-BBBB-CCCC')->assertInertia(fn (Assert $page) => $page->where('resultado.estado', 'inexistente'));
        $this->get('/verificar?codigo=abc')->assertRedirect('/verificar/abc');
    }

    public function test_o_conteudo_fica_congelado_e_uma_alteracao_fora_do_sistema_e_denunciada(): void
    {
        $documento = $this->emitir('declaracao_matricula');

        // A ficha muda depois: o documento continua a dizer o que dizia, e continua válido
        $this->ana->update(['nome' => 'Ana Maria Macuácua']);
        $this->get('/verificar/'.$documento->codigo_verificacao)->assertInertia(fn (Assert $page) => $page
            ->where('resultado.estado', 'valido')
            ->where('resultado.titular', 'Ana Macuácua'));

        // Alguém muda o conteúdo directamente na base de dados: a assinatura deixa de conferir
        DB::table('documentos_emitidos')->where('id', $documento->id)
            ->update(['conteudo' => str_replace('1.º ano', '4.º ano', $documento->conteudo)]);
        $this->get('/verificar/'.$documento->codigo_verificacao)->assertInertia(fn (Assert $page) => $page
            ->where('resultado.estado', 'nao_confere')
            ->missing('resultado.titular'));
        $this->get(route('documentos.show', $documento))->assertInertia(fn (Assert $page) => $page->where('documento.confere', false));
    }

    public function test_anular_com_motivo_e_a_verificacao_diz_que_foi_anulado(): void
    {
        $documento = $this->emitir('declaracao_frequencia');
        $this->assertCount(3, $documento->dados()['disciplinas']);

        $this->post(route('documentos.anular', $documento), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post(route('documentos.anular', $documento), ['motivo' => 'Nome mal escrito; emitida outra.'])->assertSessionHasNoErrors();
        $this->assertSame('anulado', $documento->fresh()?->estado);
        $this->post(route('documentos.anular', $documento), ['motivo' => 'outra vez'])->assertSessionHasErrors('motivo');

        $this->get('/verificar/'.$documento->codigo_verificacao)->assertInertia(fn (Assert $page) => $page
            ->where('resultado.estado', 'anulado')
            ->where('resultado.motivo_anulacao', 'Nome mal escrito; emitida outra.'));
    }

    public function test_declaracoes_so_com_a_matricula_activa_e_frequencia_so_com_inscricao(): void
    {
        $outro = $this->estudante('Bento Chissano');
        $matricula = app(MatriculaService::class)->matricular($outro, $this->curso, $this->matricula->anoLectivo, 1, 'laboral', $this->admin);
        $this->actingAs($this->secretaria)
            ->post("/estudantes/{$outro->id}/documentos", ['tipo' => 'declaracao_frequencia', 'matricula_id' => $matricula->id])
            ->assertSessionHasErrors('tipo');

        app(MatriculaService::class)->suspender($matricula, 'Doença prolongada');
        $this->post("/estudantes/{$outro->id}/documentos", ['tipo' => 'declaracao_matricula', 'matricula_id' => $matricula->id])
            ->assertSessionHasErrors('matricula_id');

        // A matrícula de outro estudante não serve
        $this->post("/estudantes/{$this->ana->id}/documentos", ['tipo' => 'declaracao_matricula', 'matricula_id' => $matricula->id])
            ->assertSessionHasErrors('matricula_id');
        $this->assertSame(0, DocumentoEmitido::count());
    }

    public function test_historico_so_com_notas_publicadas_e_certificado_so_com_o_curso_concluido(): void
    {
        $this->actingAs($this->secretaria)
            ->post("/estudantes/{$this->ana->id}/documentos", ['tipo' => 'historico'])
            ->assertSessionHasErrors('tipo');

        $this->aprovar('prog1', 14);
        $this->aprovar('mat1', 11);
        $historico = $this->emitir('historico');
        $dados = $historico->dados();
        $this->assertNull($historico->matricula_id);
        $this->assertCount(2, $dados['disciplinas']);
        $this->assertSame('12.50', $dados['resumo']['media']); // (14 × 6 + 11 × 6) / 12
        $this->assertSame('12', $dados['resumo']['creditos']);

        // Faltam Inglês, Programação II e Base de Dados I
        $this->post("/estudantes/{$this->ana->id}/documentos", ['tipo' => 'certificado'])
            ->assertSessionHasErrors(['tipo' => 'Ana Macuácua ainda não concluiu o curso: faltam aprovar 3 disciplinas (Inglês, Programação II, Base de Dados I). Pode emitir o histórico escolar.']);

        PlanoDisciplina::where('plano_id', $this->plano->id)->whereIn('disciplina_id', $this->ids('prog2', 'bd1'))->update(['obrigatoria' => false]);
        $this->aprovar('ingles', 16);
        $certificado = $this->emitir('certificado');
        $dados = $certificado->dados();
        $this->assertSame('13.45', $dados['resumo']['media']); // (84 + 66 + 72) / 16,5
        $this->assertSame('13', $dados['resumo']['media_final']);
        $this->assertSame('Suficiente', $dados['resumo']['grau']);
        $this->assertStringContainsString('concluiu o curso de Licenciatura em Informática de Gestão (Licenciatura), com a média final de 13 (treze) valores, Suficiente', $dados['texto'][0]);

        $this->get('/verificar/'.$certificado->codigo_verificacao)->assertInertia(fn (Assert $page) => $page
            ->where('resultado.tipo', 'Certificado de habilitações')
            ->where('resultado.resumo.media_final', '13'));
    }

    public function test_quem_ve_e_quem_emite(): void
    {
        $documento = $this->emitir('declaracao_matricula');

        $this->actingAs($this->utilizadorComPerfil('Docente'))->get('/documentos')->assertForbidden();
        $tesouraria = $this->utilizadorComPerfil('Tesouraria');
        $this->actingAs($tesouraria)->get(route('documentos.show', $documento))->assertForbidden();
        $this->actingAs($tesouraria)->post("/estudantes/{$this->ana->id}/documentos", ['tipo' => 'declaracao_matricula'])->assertForbidden();

        // A Direcção vê, mas não emite nem anula
        $direccao = $this->utilizadorComPerfil('Direcção');
        $this->actingAs($direccao)->get('/documentos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('documentos/index')
            ->has('documentos.data', 1)
            ->where('documentos.data.0.numero', $documento->numero));
        $this->actingAs($direccao)->post(route('documentos.anular', $documento), ['motivo' => 'teste'])->assertForbidden();

        // Na ficha, o separador só vem a quem pode ver os documentos
        $this->actingAs($this->secretaria)->get("/estudantes/{$this->ana->id}")->assertInertia(fn (Assert $page) => $page->has('documentos', 1));
        $this->actingAs($tesouraria)->get("/estudantes/{$this->ana->id}")->assertInertia(fn (Assert $page) => $page->where('documentos', null));
    }
}
