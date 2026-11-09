import { Bloco, BotaoIcone, ErroFicha, LinhaForm, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { NavConfiguracao, numeroCampo } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { type FormEvent } from 'react';

interface Intervalo {
    min: number;
    max: number;
    grau: string;
}

interface Props {
    dados: {
        escala_maxima: number;
        casas_decimais: number;
        nota_minima_aprovacao: number;
        minimo_admissao_exame: number;
        minimo_dispensa_exame: number;
        assiduidade_minima: number;
        intervalos: Intervalo[];
    };
}

interface Formulario {
    escala_maxima: string;
    casas_decimais: string;
    nota_minima_aprovacao: string;
    minimo_admissao_exame: string;
    minimo_dispensa_exame: string;
    assiduidade_minima: string;
    intervalos: { min: string; max: string; grau: string }[];
}

export default function Avaliacao({ dados }: Props) {
    const { data, setData, put, processing, errors } = useForm<Formulario>({
        escala_maxima: numeroCampo(dados.escala_maxima),
        casas_decimais: String(dados.casas_decimais),
        nota_minima_aprovacao: numeroCampo(dados.nota_minima_aprovacao),
        minimo_admissao_exame: numeroCampo(dados.minimo_admissao_exame),
        minimo_dispensa_exame: numeroCampo(dados.minimo_dispensa_exame),
        assiduidade_minima: String(dados.assiduidade_minima),
        intervalos: dados.intervalos.map((i) => ({ min: numeroCampo(i.min), max: numeroCampo(i.max), grau: i.grau })),
    });
    const erros = errors as Record<string, string | undefined>;

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        put(route('configuracao.avaliacao.guardar'), { preserveScroll: true });
    };
    const mudarIntervalo = (i: number, campo: 'min' | 'max' | 'grau', valor: string) =>
        setData(
            'intervalos',
            data.intervalos.map((x, n) => (n === i ? { ...x, [campo]: valor } : x)),
        );

    const numero = (campo: keyof Omit<Formulario, 'intervalos' | 'casas_decimais'>, rotulo: string, ajuda: string, sufixo?: string) => (
        <LinhaForm rotulo={rotulo} htmlFor={campo} ajuda={ajuda} obrigatorio>
            <div className="flex items-center gap-2">
                <Input
                    id={campo}
                    inputMode="decimal"
                    value={data[campo]}
                    onChange={(e) => setData(campo, e.target.value)}
                    className="w-28 tabular-nums"
                />
                {sufixo && <span className="text-muted-foreground text-[13px]">{sufixo}</span>}
            </div>
            <InputError message={erros[campo]} className="mt-1" />
        </LinhaForm>
    );

    return (
        <AppLayout>
            <Head title="Configuração · Avaliação" />
            <TituloPagina
                titulo="Configuração"
                descricao="As regras pedagógicas da instituição. Ajuste-as ao regulamento antes de lançar as primeiras notas."
            />
            <NavConfiguracao />

            <form onSubmit={guardar} className="flex flex-col gap-5">
                <Bloco titulo="Escala e limites">
                    {numero('escala_maxima', 'Nota máxima da escala', 'Normalmente 20.')}
                    <LinhaForm rotulo="Casas decimais" htmlFor="casas_decimais" ajuda="As médias arredondam-se a estas casas." obrigatorio>
                        <CaixaEscolha
                            id="casas_decimais"
                            value={data.casas_decimais}
                            onChange={(e) => setData('casas_decimais', e.target.value)}
                            className="w-28"
                        >
                            <option value="0">0 (12)</option>
                            <option value="1">1 (12,5)</option>
                            <option value="2">2 (12,46)</option>
                        </CaixaEscolha>
                    </LinhaForm>
                    {numero('nota_minima_aprovacao', 'Nota mínima para aprovar', 'Na nota final da disciplina.')}
                    {numero('minimo_admissao_exame', 'Mínimo para ir a exame', 'Abaixo disto, a média de frequência exclui o estudante.')}
                    {numero('minimo_dispensa_exame', 'Mínimo para dispensar o exame', 'A partir disto, o estudante fica dispensado.')}
                    {numero('assiduidade_minima', 'Assiduidade mínima', 'Abaixo disto, o estudante fica excluído por faltas.', '%')}
                </Bloco>

                <Bloco
                    titulo="Graus da escala"
                    accoes={
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setData('intervalos', [...data.intervalos, { min: '', max: '', grau: '' }])}
                        >
                            <Plus /> Intervalo
                        </Button>
                    }
                >
                    <p className="text-muted-foreground px-4 pt-3 text-[13px]">
                        Cada nota cai num intervalo e recebe o seu grau. Os intervalos começam em 0, seguem-se sem falhas (com 2 casas: 9,49 → 9,50) e
                        acabam na nota máxima.
                    </p>
                    <div className="p-4">
                        <ErroFicha mensagem={erros.intervalos} />
                        <table className="tabela" data-tabela-fixa="">
                            <thead>
                                <tr>
                                    <th>De</th>
                                    <th>Até</th>
                                    <th>Grau</th>
                                    <th className="w-10" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.intervalos.map((i, n) => (
                                    <tr key={n}>
                                        <td>
                                            <Input
                                                inputMode="decimal"
                                                value={i.min}
                                                onChange={(e) => mudarIntervalo(n, 'min', e.target.value)}
                                                className="w-20 tabular-nums"
                                                aria-label={`Mínimo do intervalo ${n + 1}`}
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                inputMode="decimal"
                                                value={i.max}
                                                onChange={(e) => mudarIntervalo(n, 'max', e.target.value)}
                                                className="w-20 tabular-nums"
                                                aria-label={`Máximo do intervalo ${n + 1}`}
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                value={i.grau}
                                                onChange={(e) => mudarIntervalo(n, 'grau', e.target.value)}
                                                className="min-w-32"
                                                aria-label={`Grau do intervalo ${n + 1}`}
                                            />
                                        </td>
                                        <td>
                                            <BotaoIcone
                                                rotulo={`Tirar o intervalo ${i.grau || n + 1}`}
                                                disabled={data.intervalos.length === 1}
                                                onClick={() =>
                                                    setData(
                                                        'intervalos',
                                                        data.intervalos.filter((_, x) => x !== n),
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </BotaoIcone>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Bloco>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        Guardar
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
