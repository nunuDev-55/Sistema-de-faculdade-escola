<?php

namespace App\Http\Requests\Estudantes;

use App\Models\Estudante;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstudanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dados = [];
        foreach (['nome', 'documento_numero', 'nuit', 'telemovel', 'email'] as $campo) {
            if (is_string($this->input($campo))) {
                $dados[$campo] = trim(preg_replace('/\s+/', ' ', $this->input($campo)) ?? '');
            }
        }
        if (isset($dados['documento_numero'])) {
            $dados['documento_numero'] = mb_strtoupper($dados['documento_numero']);
        }
        // Sem número, o tipo de documento não diz nada
        if (! $this->filled('documento_numero')) {
            $dados['documento_tipo'] = null;
            $dados['documento_numero'] = null;
        }
        if (isset($dados['nuit'])) {
            $dados['nuit'] = preg_replace('/\D+/', '', $dados['nuit']);
        }
        $this->merge(array_map(fn ($v) => $v === '' ? null : $v, $dados));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $estudante = $this->route('estudante');
        $id = $estudante instanceof Estudante ? $estudante->id : null;

        return [
            'nome' => ['required', 'string', 'max:150'],
            'sexo' => ['nullable', Rule::in(array_keys(Estudante::SEXOS))],
            'data_nascimento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'documento_tipo' => ['nullable', 'required_with:documento_numero', Rule::in(array_keys(Estudante::DOCUMENTOS))],
            'documento_numero' => [
                'nullable', 'string', 'max:30',
                Rule::unique('estudantes', 'documento_numero')->where('documento_tipo', $this->input('documento_tipo'))->ignore($id),
            ],
            'nuit' => ['nullable', 'digits:9'],
            'nacionalidade' => ['nullable', 'string', 'max:60'],
            'naturalidade' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', Rule::in(Estudante::PROVINCIAS)],
            'morada' => ['nullable', 'string', 'max:255'],
            'telemovel' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'necessidades_especiais' => ['nullable', 'string', 'max:2000'],
            'categoria' => ['required', Rule::in(array_keys(Estudante::CATEGORIAS))],
            'estado' => [$id === null ? 'nullable' : 'required', Rule::in(array_keys(Estudante::ESTADOS))],
            'nota_privada' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'documento_numero.unique' => 'Já há um estudante com este documento: procure-o na lista em vez de o registar outra vez.',
            'documento_tipo.required_with' => 'Diga que documento é.',
            'nuit.digits' => 'O NUIT tem 9 algarismos.',
            'data_nascimento.before' => 'A data de nascimento tem de ser anterior a hoje.',
        ];
    }
}
