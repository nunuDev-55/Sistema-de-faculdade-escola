<?php

namespace App\Http\Requests\Administracao;

use App\Support\Escala;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AvaliacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** As notas escrevem-se à moçambicana ("12,5"): passam a número antes de validar. */
    protected function prepareForValidation(): void
    {
        $numero = fn ($v) => is_string($v) ? (Escala::lerNumero($v) ?? $v) : $v;
        $dados = [];
        foreach (['escala_maxima', 'nota_minima_aprovacao', 'minimo_admissao_exame', 'minimo_dispensa_exame', 'limite_creditos'] as $campo) {
            $dados[$campo] = $numero($this->input($campo));
        }
        $intervalos = $this->input('intervalos');
        if (is_array($intervalos)) {
            $dados['intervalos'] = array_values(array_map(fn ($i) => is_array($i)
                ? ['min' => $numero($i['min'] ?? null), 'max' => $numero($i['max'] ?? null), 'grau' => $i['grau'] ?? null]
                : $i, $intervalos));
        }
        $this->merge($dados);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'escala_maxima' => ['required', 'numeric', 'min:1', 'max:100'],
            'casas_decimais' => ['required', 'integer', 'min:0', 'max:2'],
            'nota_minima_aprovacao' => ['required', 'numeric', 'min:0'],
            'minimo_admissao_exame' => ['required', 'numeric', 'min:0'],
            'minimo_dispensa_exame' => ['required', 'numeric', 'min:0'],
            'assiduidade_minima' => ['required', 'integer', 'min:0', 'max:100'],
            'limite_creditos' => ['nullable', 'numeric', 'min:1', 'max:120'],
            'intervalos' => ['required', 'array', 'min:1', 'max:20'],
            'intervalos.*.min' => ['required', 'numeric', 'min:0'],
            'intervalos.*.max' => ['required', 'numeric', 'min:0'],
            'intervalos.*.grau' => ['required', 'string', 'max:40'],
        ];
    }

    /** Os limites têm de caber na escala e a escala tem de ser contínua (Escala::problemasDosIntervalos). */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $maxima = (float) $this->input('escala_maxima');
            $admissao = (float) $this->input('minimo_admissao_exame');
            $dispensa = (float) $this->input('minimo_dispensa_exame');
            foreach (['nota_minima_aprovacao', 'minimo_admissao_exame', 'minimo_dispensa_exame'] as $campo) {
                if ($maxima < (float) $this->input($campo)) {
                    $validator->errors()->add($campo, 'Não pode passar o máximo da escala ('.$maxima.').');
                }
            }
            if ($admissao > $dispensa) {
                $validator->errors()->add('minimo_dispensa_exame', 'A nota de dispensa tem de ser igual ou maior do que a de admissão a exame.');
            }
            $intervalos = Escala::normalizar((array) $this->input('intervalos'));
            $problemas = Escala::problemasDosIntervalos($intervalos, $maxima, (int) $this->input('casas_decimais'));
            if ($problemas !== []) {
                $validator->errors()->add('intervalos', implode(' ', $problemas));
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'intervalos.*.grau.required' => 'Todos os intervalos precisam do nome do grau.',
            'intervalos.*.min.required' => 'Todos os intervalos precisam do mínimo.',
            'intervalos.*.max.required' => 'Todos os intervalos precisam do máximo.',
        ];
    }
}
