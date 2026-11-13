<?php

namespace App\Http\Requests\Estrutura;

use App\Models\Sala;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaRequest extends FormRequest
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
        $sala = $this->route('sala');

        return [
            'codigo' => ['required', 'string', 'max:20', Rule::unique('salas', 'codigo')->ignore($sala instanceof Sala ? $sala->id : null)],
            'nome' => ['nullable', 'string', 'max:100'],
            'tipo' => ['required', Rule::in(array_keys(Sala::TIPOS))],
            'capacidade' => ['required', 'integer', 'min:1', 'max:1000'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['codigo.unique' => 'Já existe uma sala com esse código.'];
    }
}
