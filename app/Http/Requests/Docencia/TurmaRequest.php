<?php

namespace App\Http\Requests\Docencia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Criar uma turma (curso, ano, período) ou alterar o nome, o regime, a lotação e o director. */
class TurmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nome'))) {
            $this->merge(['nome' => mb_strtoupper(trim($this->input('nome')))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $nova = $this->route('turma') === null;

        return [
            'ano_lectivo_id' => [$nova ? 'required' : 'prohibited', 'integer', 'exists:anos_lectivos,id'],
            'periodo_id' => ['nullable', 'integer', 'exists:periodos_lectivos,id'],
            'curso_id' => [$nova ? 'required' : 'prohibited', 'integer', 'exists:cursos,id'],
            'ano_curricular' => [$nova ? 'required' : 'prohibited', 'integer', 'min:1', 'max:13'],
            'nome' => ['required', 'string', 'max:20'],
            'regime' => ['nullable', 'string', 'max:20'],
            'capacidade' => ['required', 'integer', 'min:1', 'max:500'],
            'director_id' => ['nullable', 'integer', 'exists:docentes,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['ano_lectivo_id' => 'ano lectivo', 'periodo_id' => 'período', 'curso_id' => 'curso', 'ano_curricular' => 'ano', 'capacidade' => 'lotação'];
    }
}
