<?php

namespace App\Http\Requests\Administracao;

use App\Support\TipoInstituicao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstituicaoRequest extends FormRequest
{
    /** A permissão verifica-se na rota (can:configuracao.gerir). */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // O NUIT escreve-se muitas vezes com espaços ("400 123 456")
        if ($this->filled('nuit')) {
            $this->merge(['nuit' => preg_replace('/\D/', '', (string) $this->input('nuit'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'sigla' => ['nullable', 'string', 'max:20'],
            'nuit' => ['nullable', 'digits:9'],
            'morada' => ['nullable', 'string', 'max:200'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'provincia' => ['nullable', 'string', 'max:80'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'tipo' => ['required', Rule::in([TipoInstituicao::SUPERIOR, TipoInstituicao::GERAL])],
            'cor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cor_destaque' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logotipo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            'remover_logotipo' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nome.required' => 'Escreva o nome da instituição.',
            'nuit.digits' => 'O NUIT tem 9 algarismos.',
            'cor.regex' => 'Escolha uma cor válida.',
            'cor_destaque.regex' => 'Escolha uma cor válida.',
            'logotipo.mimes' => 'O logótipo tem de ser uma imagem PNG, JPG, WEBP ou SVG.',
            'logotipo.max' => 'O logótipo pode ter no máximo 1 MB.',
        ];
    }
}
