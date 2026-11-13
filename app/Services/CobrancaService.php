<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Cobranca;
use App\Models\InscricaoPeriodo;
use App\Models\Matricula;
use App\Models\Preco;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cobranças ao estudante (RN-17): por cada inscrição confirmada, a taxa de inscrição do período e as propinas
 * mensais. Gera-se uma vez (a inscrição fica com as cobranças "geradas"); o número sai da série CB do ano lectivo.
 */
class CobrancaService
{
    public const MESES = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    public function __construct(private Numeracao $numeracao, private PagamentoService $pagamentos) {}

    /** O preço que vale para o curso: o do próprio curso, se houver, senão o geral. */
    public function preco(int $anoLectivoId, int $cursoId, string $tipo): ?Preco
    {
        return Preco::where('ano_lectivo_id', $anoLectivoId)->where('tipo', $tipo)
            ->where(fn ($q) => $q->where('curso_id', $cursoId)->orWhereNull('curso_id'))
            ->orderByRaw('curso_id is null')
            ->first();
    }

    /** As cobranças de uma inscrição (taxa de inscrição e propinas do período). Devolve quantas criou. */
    public function gerarParaInscricao(InscricaoPeriodo $inscricao, ?User $autor): int
    {
        if ($inscricao->estado_facturacao === 'geradas' || $inscricao->estado !== InscricaoPeriodo::CONFIRMADA) {
            return 0;
        }
        $matricula = $inscricao->matricula;
        $ano = $matricula->anoLectivo;
        $taxa = $this->preco($ano->id, $matricula->curso_id, 'inscricao');
        $propina = $this->preco($ano->id, $matricula->curso_id, 'propina');
        if ($taxa === null && $propina === null) {
            return 0;
        }
        $inicio = CarbonImmutable::parse(($inscricao->periodo->inicio ?? $ano->inicio)->toDateString());

        return DB::transaction(function () use ($inscricao, $matricula, $ano, $taxa, $propina, $inicio, $autor) {
            $criadas = 0;
            if ($taxa !== null) {
                $this->criar($matricula, $inscricao, 'inscricao', 'Taxa de inscrição, '.$inscricao->nomePeriodo().' de '.$ano->nome, $taxa->valor, $inicio, $autor);
                $criadas++;
            }
            if ($propina !== null) {
                for ($i = 0; $i < $propina->prestacoes; $i++) {
                    $mes = $inicio->startOfMonth()->addMonths($i);
                    $vencimento = $mes->setDay(min($propina->dia_vencimento, $mes->daysInMonth));
                    $this->criar($matricula, $inscricao, 'propina', 'Propina de '.self::MESES[$mes->month].' de '.$mes->year, $propina->valor, $vencimento, $autor);
                    $criadas++;
                }
            }
            $inscricao->update(['estado_facturacao' => 'geradas']);
            // Um adiantamento que o estudante já tenha abate logo as cobranças novas
            $this->pagamentos->alocar($matricula->estudante);
            activity('financas')->performedOn($inscricao)->log("{$criadas} cobranças geradas para a inscrição {$inscricao->codigo}");

            return $criadas;
        });
    }

    /**
     * Gera as cobranças de todas as inscrições confirmadas do ano que ainda não as têm.
     *
     * @return array{inscricoes: int, cobrancas: int, sem_preco: int}
     */
    public function gerarDoAno(AnoLectivo $ano, ?User $autor): array
    {
        $resultado = ['inscricoes' => 0, 'cobrancas' => 0, 'sem_preco' => 0];
        $pendentes = InscricaoPeriodo::with(['matricula.anoLectivo', 'periodo'])
            ->where('estado', InscricaoPeriodo::CONFIRMADA)->where('estado_facturacao', 'por_gerar')
            ->whereHas('matricula', fn ($q) => $q->where('ano_lectivo_id', $ano->id)->where('estado', Matricula::ACTIVA))
            ->get();
        foreach ($pendentes as $inscricao) {
            $n = $this->gerarParaInscricao($inscricao, $autor);
            if ($n === 0) {
                $resultado['sem_preco']++;
            } else {
                $resultado['inscricoes']++;
                $resultado['cobrancas'] += $n;
            }
        }

        return $resultado;
    }

    private function criar(Matricula $matricula, InscricaoPeriodo $inscricao, string $tipo, string $descricao, string $valor, CarbonImmutable $vencimento, ?User $autor): void
    {
        Cobranca::create([
            'numero' => $this->numeracao->proximo('cobranca', (int) $matricula->anoLectivo->inicio->format('Y')),
            'estudante_id' => $matricula->estudante_id,
            'matricula_id' => $matricula->id,
            'inscricao_periodo_id' => $inscricao->id,
            'tipo' => $tipo,
            'descricao' => $descricao,
            'valor' => $valor,
            'pago' => '0.00',
            'vencimento' => $vencimento->toDateString(),
            'estado' => 'aberta',
            'criado_por' => $autor?->id,
        ]);
    }
}
