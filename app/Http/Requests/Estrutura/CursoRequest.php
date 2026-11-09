<?php

namespace App\Http\Requests\Estrutura;

use App\Models\Curso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('codigo')) {
            $this->merge(['codigo' => mb_strtoupper(trim((string) $this->input('codigo')))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $curso = $this->route('curso');

        return [
            'codigo' => ['required', 'string', 'max:20', Rule::unique('cursos', 'codigo')->ignore($curso instanceof Curso ? $curso->id : null)],
            'nome' => ['required', 'string', 'max:150'],
            'grau' => ['required', Rule::in(array_keys(Curso::GRAUS))],
            'duracao_anos' => ['required', 'integer', 'min:1', 'max:12'],
            'unidade_id' => ['nullable', 'integer', 'exists:unidades_organicas,id'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.unique' => 'Já existe um curso com esse código.',
            'duracao_anos.max' => 'A duração vai até 12 anos (no ensino geral, conte as classes).',
        ];
    }
}
