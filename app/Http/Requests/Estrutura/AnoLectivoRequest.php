<?php

namespace App\Http\Requests\Estrutura;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnoLectivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:20', 'unique:anos_lectivos,nome'],
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after:inicio'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nome.unique' => 'Esse ano lectivo já existe.',
            'fim.after' => 'O fim tem de ser depois do início.',
        ];
    }
}
