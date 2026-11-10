<?php

namespace App\Http\Requests\Estrutura;

use App\Models\UnidadeOrganica;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadeRequest extends FormRequest
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
        $unidade = $this->route('unidade');
        $id = $unidade instanceof UnidadeOrganica ? $unidade->id : null;

        return [
            'tipo' => ['required', Rule::in(array_keys(UnidadeOrganica::TIPOS))],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('unidades_organicas', 'codigo')->ignore($id)],
            'nome' => ['required', 'string', 'max:150'],
            // Uma unidade não pode pertencer a si própria
            'unidade_pai_id' => ['nullable', 'integer', 'exists:unidades_organicas,id', Rule::notIn(array_filter([$id]))],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.unique' => 'Já existe uma unidade com esse código.',
            'unidade_pai_id.not_in' => 'Uma unidade não pode pertencer a si própria.',
        ];
    }
}
