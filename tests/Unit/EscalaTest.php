<?php

namespace Tests\Unit;

use App\Support\Escala;
use Tests\TestCase;

/**
 * A escala de classificação por intervalos (ver docs/08-estudo-sistemas-academicos.md).
 * Usa a configuração (config/nsedu.php), por isso arranca a aplicação; não toca na base de dados.
 */
class EscalaTest extends TestCase
{
    public function test_le_notas_a_mocambicana(): void
    {
        $this->assertSame('12.50', Escala::lerNumero('12,5'));
        $this->assertSame('17.00', Escala::lerNumero(' 17 '));
        $this->assertNull(Escala::lerNumero(''));
        $this->assertNull(Escala::lerNumero('abc'));
    }

    public function test_o_grau_sai_do_intervalo(): void
    {
        $this->assertSame('Reprovado', Escala::grau('8'));
        $this->assertSame('Suficiente', Escala::grau('12.5'));
        $this->assertSame('Muito Bom', Escala::grau('16'));
        $this->assertSame('Excelente', Escala::grau('19'));
    }

    public function test_situacao_de_frequencia(): void
    {
        $this->assertSame('excluido', Escala::situacaoFrequencia('8'));
        $this->assertSame('admitido', Escala::situacaoFrequencia('12'));
        $this->assertSame('dispensado', Escala::situacaoFrequencia('15'));
    }

    public function test_uma_escala_contigua_nao_tem_problemas(): void
    {
        $intervalos = [
            ['min' => 0.0, 'max' => 9.49, 'grau' => 'Reprovado'],
            ['min' => 9.5, 'max' => 20.0, 'grau' => 'Aprovado'],
        ];

        $this->assertSame([], Escala::problemasDosIntervalos($intervalos));
    }

    public function test_a_escala_de_origem_e_valida(): void
    {
        $this->assertSame([], Escala::problemasDosIntervalos(Escala::intervalos()));
    }

    public function test_uma_nota_entre_dois_intervalos_arredonda_antes_do_grau(): void
    {
        // 9,495 arredonda para 9,50: cai em "Suficiente", não fica sem grau
        $this->assertSame('Suficiente', Escala::grau('9.495'));
    }

    public function test_uma_escala_sobreposta_e_apanhada(): void
    {
        $intervalos = [
            ['min' => 0.0, 'max' => 10.0, 'grau' => 'Reprovado'],
            ['min' => 9.5, 'max' => 20.0, 'grau' => 'Aprovado'],
        ];

        $this->assertNotEmpty(Escala::problemasDosIntervalos($intervalos));
    }

    public function test_uma_escala_com_falha_e_apanhada(): void
    {
        // Falta de 9,5 a 10 (falha) e não começa em 0.
        $intervalos = [
            ['min' => 1.0, 'max' => 9.49, 'grau' => 'Reprovado'],
            ['min' => 10.0, 'max' => 20.0, 'grau' => 'Aprovado'],
        ];

        $this->assertNotEmpty(Escala::problemasDosIntervalos($intervalos));
    }
}
