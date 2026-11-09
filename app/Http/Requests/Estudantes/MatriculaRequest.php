<?php

namespace App\Http\Requests\Estudantes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id'],
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
            'ano_curricular' => ['required', 'integer', 'min:1', 'max:13'],
            'regime' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['ano_lectivo_id' => 'ano lectivo', 'curso_id' => 'curso', 'ano_curricular' => 'ano'];
    }
}
