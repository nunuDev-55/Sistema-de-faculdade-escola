<?php

namespace App\Http\Requests\Estudantes;

use App\Models\Encarregado;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EncarregadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'parentesco' => ['required', Rule::in(array_keys(Encarregado::PARENTESCOS))],
            'telemovel' => ['required', 'string', 'max:20', 'regex:/^[+\d\s-]{9,20}$/'],
            'documento_numero' => ['nullable', 'string', 'max:30'],
            'profissao' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'morada' => ['nullable', 'string', 'max:255'],
            'principal' => ['boolean'],
            've_financas' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['telemovel.regex' => 'Escreva o telemóvel só com algarismos (ex.: 84 123 4567).'];
    }
}
