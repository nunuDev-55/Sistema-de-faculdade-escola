<?php

namespace App\Http\Requests\Estrutura;

use App\Models\Disciplina;
use App\Support\Escala;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dados = [];
        if ($this->filled('codigo')) {
            $dados['codigo'] = mb_strtoupper(trim((string) $this->input('codigo')));
        }
        // Os créditos podem ter meio crédito ("4,5")
        if (is_string($this->input('creditos'))) {
            $dados['creditos'] = Escala::lerNumero($this->input('creditos')) ?? $this->input('creditos');
        }
        $this->merge($dados);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $disciplina = $this->route('disciplina');

        return [
            'codigo' => ['required', 'string', 'max:20', Rule::unique('disciplinas', 'codigo')->ignore($disciplina instanceof Disciplina ? $disciplina->id : null)],
            'nome' => ['required', 'string', 'max:150'],
            'creditos' => ['required', 'numeric', 'min:0', 'max:60'],
            'horas_teoricas' => ['required', 'integer', 'min:0', 'max:999'],
            'horas_praticas' => ['required', 'integer', 'min:0', 'max:999'],
            'unidade_id' => ['nullable', 'integer', 'exists:unidades_organicas,id'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['codigo.unique' => 'Já existe uma disciplina com esse código.'];
    }
}
