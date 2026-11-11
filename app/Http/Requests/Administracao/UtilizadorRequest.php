<?php

namespace App\Http\Requests\Administracao;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Criar e alterar uma conta da equipa. A permissão verifica-se na rota (can:utilizadores.gerir). */
class UtilizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $utilizador = $this->route('utilizador');
        $id = $utilizador instanceof User ? $utilizador->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'regex:/^(\+?258)?\s?8[2-7]\s?\d{3}\s?\d{4}$/'],
            'perfil' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'password' => [$id === null ? 'required' : 'nullable', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telemóvel', 'password' => 'senha'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Indique um número moçambicano, por exemplo 84 123 4567.',
            'email.unique' => 'Já há uma conta com este e-mail.',
            'perfil.exists' => 'Escolha um perfil da lista.',
        ];
    }
}
