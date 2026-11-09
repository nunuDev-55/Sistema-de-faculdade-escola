<?php

namespace App\Http\Requests\Estrutura;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** A disciplina só se faz depois de aprovar a precedente; o serviço confirma que a precedente vem antes no plano. */
class PrecedenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'disciplina_id' => ['required', 'integer', 'exists:disciplinas,id'],
            'precedente_id' => ['required', 'integer', 'exists:disciplinas,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'disciplina_id.required' => 'Escolha a disciplina.',
            'precedente_id.required' => 'Escolha a disciplina precedente.',
        ];
    }
}
