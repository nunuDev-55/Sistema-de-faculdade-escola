<?php

namespace App\Http\Requests\Estudantes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InscricaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'disciplinas' => ['required', 'array', 'min:1', 'max:30'],
            'disciplinas.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['disciplinas.required' => 'Escolha pelo menos uma disciplina.', 'disciplinas.min' => 'Escolha pelo menos uma disciplina.'];
    }

    /** @return list<int> */
    public function disciplinas(): array
    {
        return array_values(array_map('intval', (array) $this->validated('disciplinas')));
    }
}
