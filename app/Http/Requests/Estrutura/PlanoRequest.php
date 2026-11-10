<?php

namespace App\Http\Requests\Estrutura;

use App\Models\PlanoCurricular;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Criar um plano curricular (ou uma versão nova): a versão e o método de avaliação. */
class PlanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'versao' => ['required', 'string', 'max:20'],
            'metodo_avaliacao' => ['nullable', Rule::in(array_keys(PlanoCurricular::METODOS))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['versao.required' => 'Escreva a versão do plano (por exemplo, o ano: 2027).'];
    }
}
