<?php

namespace App\Http\Requests\Estrutura;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Acrescentar uma disciplina a um plano: em que ano e período. As regras do plano estão no serviço. */
class PlanoDisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'disciplina_id' => ['required', 'integer', 'exists:disciplinas,id'],
            'ano' => ['required', 'integer', 'min:1', 'max:12'],
            'periodo' => ['required', 'integer', 'min:0', 'max:3'],
            'obrigatoria' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['disciplina_id.required' => 'Escolha a disciplina.'];
    }
}
