<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Support\TipoInstituicao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cria o ano lectivo e gera os seus períodos a partir do tipo de instituição (padrão do OpenEduCat,
 * docs/08-estudo-sistemas-academicos.md): 2 semestres no superior, 3 trimestres no geral. Os dias do ano
 * dividem-se por igual; as datas de cada período (inscrições, exames) acertam-se depois no calendário.
 */
class AnoLectivoService
{
    public function criar(string $nome, CarbonImmutable $inicio, CarbonImmutable $fim): AnoLectivo
    {
        return DB::transaction(function () use ($nome, $inicio, $fim) {
            $ano = AnoLectivo::create(['nome' => $nome, 'inicio' => $inicio, 'fim' => $fim, 'estado' => 'planeado']);

            $quantos = TipoInstituicao::periodosPorAno();
            $tipo = TipoInstituicao::tipoPeriodo();
            $dias = (int) $inicio->diffInDays($fim) + 1;
            $porPeriodo = intdiv($dias, $quantos);

            for ($n = 1; $n <= $quantos; $n++) {
                $comeca = $inicio->addDays(($n - 1) * $porPeriodo);
                $acaba = $n === $quantos ? $fim : $inicio->addDays($n * $porPeriodo - 1);
                $ano->periodos()->create(['tipo' => $tipo, 'numero' => $n, 'inicio' => $comeca, 'fim' => $acaba]);
            }

            activity('calendario')->performedOn($ano)->log("Ano lectivo {$nome} criado com {$quantos} {$tipo}s");

            return $ano;
        });
    }
}
