<?php

namespace App\Http\Requests\Estudantes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EstadoMatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'accao' => ['required', 'in:suspender,reactivar,anular'],
            'motivo' => ['required_unless:accao,reactivar', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['motivo.required_unless' => 'Escreva o motivo: fica no registo da matrícula.'];
    }
}
