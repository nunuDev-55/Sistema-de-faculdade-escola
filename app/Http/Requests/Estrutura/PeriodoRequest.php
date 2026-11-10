<?php

namespace App\Http\Requests\Estrutura;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Datas de um semestre ou trimestre: aulas, inscrições e exames. */
class PeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after:inicio'],
            'inscricoes_inicio' => ['nullable', 'date_format:Y-m-d'],
            'inscricoes_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inscricoes_inicio'],
            'exames_inicio' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'exames_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:exames_inicio'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fim.after' => 'O fim tem de ser depois do início.',
            'inscricoes_fim.after_or_equal' => 'O fim das inscrições não pode ser antes do início.',
            'exames_inicio.after_or_equal' => 'Os exames não podem começar antes do período.',
            'exames_fim.after_or_equal' => 'O fim dos exames não pode ser antes do início.',
        ];
    }
}
