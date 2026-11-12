<?php

namespace App\Http\Requests\Docencia;

use App\Models\Docente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $docente = $this->route('docente');
        $id = $docente instanceof Docente ? $docente->id : null;

        return [
            'nome' => ['required', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:20', Rule::unique('docentes', 'numero')->ignore($id)],
            'grau_academico' => ['nullable', Rule::in(array_keys(Docente::GRAUS))],
            'categoria' => ['nullable', 'string', 'max:40'],
            'regime' => ['required', Rule::in(array_keys(Docente::REGIMES))],
            'unidade_id' => ['nullable', 'integer', 'exists:unidades_organicas,id'],
            'telemovel' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('docentes', 'user_id')->ignore($id)],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'Essa conta já está ligada a outro docente.',
            'numero.unique' => 'Já há um docente com esse número.',
        ];
    }
}
