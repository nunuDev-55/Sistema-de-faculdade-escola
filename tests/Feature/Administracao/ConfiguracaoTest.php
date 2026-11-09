<?php

namespace Tests\Feature\Administracao;

use App\Support\Escala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfiguracaoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function dadosInstituicao(array $mais = []): array
    {
        return [
            'nome' => 'Instituto Superior Exemplo',
            'sigla' => 'ISE',
            'nuit' => '400 123 456',
            'tipo' => 'superior',
            'cor' => '#1f3a8a',
            'cor_destaque' => '#0ea5b7',
            ...$mais,
        ];
    }

    public function test_o_administrador_guarda_os_dados_da_instituicao(): void
    {
        $this->actingAs($this->administrador())
            ->post('/administracao/configuracao/instituicao', $this->dadosInstituicao())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // O NUIT guarda-se só com os algarismos
        $this->assertDatabaseHas('definicoes', ['chave' => 'instituicao.nuit', 'valor' => '400123456']);
        $this->assertDatabaseHas('definicoes', ['chave' => 'instituicao.sigla', 'valor' => 'ISE']);
    }

    public function test_um_nuit_com_menos_algarismos_e_recusado(): void
    {
        $this->actingAs($this->administrador())
            ->post('/administracao/configuracao/instituicao', $this->dadosInstituicao(['nuit' => '1234']))
            ->assertSessionHasErrors('nuit');
    }

    public function test_o_logotipo_carregado_fica_disponivel_e_tira_o_aviso(): void
    {
        Storage::fake('local');
        $svg = UploadedFile::fake()->createWithContent('logotipo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');

        $this->actingAs($this->administrador())
            ->post('/administracao/configuracao/instituicao', $this->dadosInstituicao(['logotipo' => $svg]))
            ->assertSessionHasNoErrors();

        $this->get('/instituicao/logotipo')->assertOk();
    }

    public function test_quem_nao_tem_a_permissao_nao_entra_na_configuracao(): void
    {
        $this->actingAs($this->utilizadorComPerfil('Secretaria académica'))
            ->get('/administracao/configuracao/instituicao')
            ->assertForbidden();
    }

    public function test_a_escala_guardada_passa_a_valer(): void
    {
        $this->actingAs($this->administrador())
            ->put('/administracao/configuracao/avaliacao', [
                'escala_maxima' => '20',
                'casas_decimais' => 2,
                'nota_minima_aprovacao' => '10',
                'minimo_admissao_exame' => '10',
                'minimo_dispensa_exame' => '14,5',
                'assiduidade_minima' => 80,
                'intervalos' => [
                    ['min' => '0', 'max' => '9,49', 'grau' => 'Reprovado'],
                    ['min' => '9,5', 'max' => '20', 'grau' => 'Aprovado'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->app->forgetScopedInstances();
        $this->assertSame('Aprovado', Escala::grau('12'));
        $this->assertSame('admitido', Escala::situacaoFrequencia('14.2'));
        $this->assertSame('80', (string) Escala::parametro('assiduidade_minima'));
    }

    public function test_uma_escala_com_notas_sem_intervalo_e_recusada(): void
    {
        $this->actingAs($this->administrador())
            ->put('/administracao/configuracao/avaliacao', [
                'escala_maxima' => '20',
                'casas_decimais' => 2,
                'nota_minima_aprovacao' => '10',
                'minimo_admissao_exame' => '10',
                'minimo_dispensa_exame' => '14',
                'assiduidade_minima' => 75,
                'intervalos' => [
                    ['min' => '0', 'max' => '9', 'grau' => 'Reprovado'],
                    ['min' => '10', 'max' => '20', 'grau' => 'Aprovado'],
                ],
            ])
            ->assertSessionHasErrors('intervalos');
    }
}
