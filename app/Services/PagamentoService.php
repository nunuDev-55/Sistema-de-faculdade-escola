<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Estudante;
use App\Models\Pagamento;
use App\Models\User;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pagamentos e recibos (RN-11, RN-13, RN-17): o pagamento abate as cobranças em aberto da mais antiga para a mais
 * recente; o que sobra fica como adiantamento e abate as cobranças seguintes, quando forem geradas. Um recibo não se
 * apaga: anula-se, com o motivo, e o que tinha abatido volta a estar em dívida. Contas em bcmath, nunca float.
 */
class PagamentoService
{
    public function __construct(private Numeracao $numeracao) {}

    public function registar(Estudante $estudante, string $valor, CarbonImmutable $data, string $metodo, ?string $referencia, User $autor): Pagamento
    {
        $valor = Dinheiro::arredondar($valor);
        if (bccomp($valor, '0', 2) <= 0) {
            throw ValidationException::withMessages(['valor' => 'O valor tem de ser maior do que zero.']);
        }
        if (! array_key_exists($metodo, Pagamento::METODOS)) {
            throw ValidationException::withMessages(['metodo' => 'Escolha a forma de pagamento.']);
        }
        if ($data->isFuture()) {
            throw ValidationException::withMessages(['data' => 'A data do pagamento não pode ser no futuro.']);
        }

        return DB::transaction(function () use ($estudante, $valor, $data, $metodo, $referencia, $autor) {
            $pagamento = Pagamento::create([
                'numero' => $this->numeracao->proximo('recibo', $data->year),
                'estudante_id' => $estudante->id,
                'data' => $data->toDateString(),
                'valor' => $valor,
                'metodo' => $metodo,
                'referencia' => $referencia,
                'estado' => 'valido',
                'registado_por' => $autor->id,
            ]);
            $this->alocar($estudante);
            activity('financas')->performedOn($pagamento)
                ->log("Recibo {$pagamento->numero}: {$estudante->numero} pagou {$valor} MT (".Pagamento::METODOS[$metodo].')');

            return $pagamento;
        });
    }

    /** Anula o recibo: o que abateu volta a estar em dívida (e o crédito de outros pagamentos pode voltar a abatê-lo). */
    public function anular(Pagamento $pagamento, string $motivo, User $autor): void
    {
        if (! $pagamento->valido()) {
            throw ValidationException::withMessages(['pagamento' => "O recibo {$pagamento->numero} já está anulado."]);
        }

        DB::transaction(function () use ($pagamento, $motivo, $autor) {
            foreach ($pagamento->cobrancas()->get() as $cobranca) {
                $cobranca->pago = bcsub($cobranca->pago, (string) $cobranca->getRelationValue('pivot')->getAttribute('valor'), 2);
                $cobranca->estado = $this->estado($cobranca);
                $cobranca->save();
            }
            $pagamento->cobrancas()->detach();
            $pagamento->update(['estado' => 'anulado', 'motivo_anulacao' => $motivo, 'anulado_por' => $autor->id, 'anulado_em' => now()]);
            $this->alocar($pagamento->estudante);
            activity('financas')->performedOn($pagamento)->withProperties(['motivo' => $motivo])->log("Recibo {$pagamento->numero} anulado");
        });
    }

    /** Abate o que sobra dos pagamentos válidos às cobranças em aberto, da que vence primeiro para a última. */
    public function alocar(Estudante $estudante): void
    {
        $pagamentos = Pagamento::where('estudante_id', $estudante->id)->where('estado', 'valido')->orderBy('data')->orderBy('id')->get();
        $cobrancas = Cobranca::where('estudante_id', $estudante->id)->whereIn('estado', ['aberta', 'parcial'])->orderBy('vencimento')->orderBy('id')->get();

        foreach ($pagamentos as $pagamento) {
            $livre = $this->livre($pagamento);
            foreach ($cobrancas as $cobranca) {
                if (bccomp($livre, '0', 2) <= 0) {
                    break;
                }
                $falta = $cobranca->emDivida();
                if (bccomp($falta, '0', 2) <= 0) {
                    continue;
                }
                $abate = bccomp($livre, $falta, 2) < 0 ? $livre : $falta;
                $pagamento->cobrancas()->attach($cobranca->id, ['valor' => $abate]);
                $cobranca->pago = bcadd($cobranca->pago, $abate, 2);
                $cobranca->estado = $this->estado($cobranca);
                $cobranca->save();
                $livre = bcsub($livre, $abate, 2);
            }
        }
    }

    /** O que ainda não abateu nenhuma cobrança. */
    public function livre(Pagamento $pagamento): string
    {
        $usado = Dinheiro::normalizar(DB::table('cobranca_pagamento')->where('pagamento_id', $pagamento->id)->sum('valor'));

        return bcsub($pagamento->valor, $usado, 2);
    }

    /** O adiantamento do estudante: o que sobra de todos os pagamentos válidos. */
    public function credito(Estudante $estudante): string
    {
        return Dinheiro::somar(Pagamento::where('estudante_id', $estudante->id)->where('estado', 'valido')->get()
            ->map(fn (Pagamento $p) => $this->livre($p)));
    }

    private function estado(Cobranca $cobranca): string
    {
        if (bccomp($cobranca->pago, '0', 2) <= 0) {
            return 'aberta';
        }

        return bccomp($cobranca->pago, $cobranca->valor, 2) < 0 ? 'parcial' : 'paga';
    }
}
