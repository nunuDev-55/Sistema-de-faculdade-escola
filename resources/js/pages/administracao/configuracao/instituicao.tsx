import { Bloco, LinhaForm, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { NavConfiguracao } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { type FormEvent } from 'react';

interface Dados {
    nome: string;
    sigla: string;
    nuit: string;
    morada: string;
    cidade: string;
    provincia: string;
    telefone: string;
    email: string;
    tipo: 'superior' | 'geral';
    cor: string;
    cor_destaque: string;
}

interface Props {
    dados: Dados;
    logotipo: string | null;
    emFalta: string[];
    temCursos: boolean;
}

const TIPOS = [
    { valor: 'superior', titulo: 'Ensino superior', texto: 'Universidade ou instituto: cursos, semestres, créditos, exame normal e recorrência.' },
    { valor: 'geral', titulo: 'Ensino geral', texto: 'Escola primária ou secundária: classes, trimestres e passagem de classe.' },
] as const;

export default function Instituicao({ dados, logotipo, emFalta, temCursos }: Props) {
    const { data, setData, post, processing, errors } = useForm<Dados & { logotipo: File | null; remover_logotipo: boolean }>({
        ...dados,
        logotipo: null,
        remover_logotipo: false,
    });

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        post(route('configuracao.instituicao.guardar'), { forceFormData: true, preserveScroll: true, onSuccess: () => setData('logotipo', null) });
    };

    const texto = (
        campo: keyof Dados,
        rotulo: string,
        opcoes: { obrigatorio?: boolean; ajuda?: string; tipo?: string; placeholder?: string } = {},
    ) => (
        <LinhaForm rotulo={rotulo} htmlFor={campo} obrigatorio={opcoes.obrigatorio} ajuda={opcoes.ajuda}>
            <Input
                id={campo}
                type={opcoes.tipo ?? 'text'}
                value={data[campo]}
                placeholder={opcoes.placeholder}
                onChange={(e) => setData(campo, e.target.value)}
            />
            <InputError message={errors[campo]} className="mt-1" />
        </LinhaForm>
    );

    return (
        <AppLayout>
            <Head title="Configuração · Instituição" />
            <TituloPagina titulo="Configuração" descricao="Os dados da instituição e as regras de avaliação mudam-se aqui, sem mexer no código." />
            <NavConfiguracao />

            {emFalta.length > 0 && (
                <div className="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-[13px] dark:border-amber-900/50 dark:bg-amber-950/30">
                    <p className="flex items-center gap-2 font-medium text-amber-900 dark:text-amber-200">
                        <AlertTriangle className="size-4" /> Falta configurar
                    </p>
                    <ul className="text-muted-foreground mt-1.5 list-inside list-disc">
                        {emFalta.map((a) => (
                            <li key={a}>{a}</li>
                        ))}
                    </ul>
                </div>
            )}

            <form onSubmit={guardar} className="flex flex-col gap-5">
                <Bloco titulo="Instituição">
                    {texto('nome', 'Nome', { obrigatorio: true, placeholder: 'Instituto Superior …' })}
                    {texto('sigla', 'Sigla', { ajuda: 'Aparece no menu e nos documentos quando existe.' })}
                    {texto('nuit', 'NUIT', { ajuda: '9 algarismos; aparece nos recibos e certificados.' })}
                    {texto('morada', 'Morada')}
                    {texto('cidade', 'Cidade')}
                    {texto('provincia', 'Província')}
                    {texto('telefone', 'Telefone')}
                    {texto('email', 'E-mail', { tipo: 'email' })}
                </Bloco>

                <Bloco titulo="Tipo de instituição">
                    <div className="grid gap-3 p-4 sm:grid-cols-2" role="radiogroup" aria-label="Tipo de instituição">
                        {TIPOS.map((t) => (
                            <label
                                key={t.valor}
                                className={cn(
                                    'flex cursor-pointer gap-3 rounded-lg border p-3.5 transition-colors',
                                    data.tipo === t.valor ? 'border-[var(--marca)] bg-[var(--marca-suave)]' : 'hover:bg-muted',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="tipo"
                                    value={t.valor}
                                    checked={data.tipo === t.valor}
                                    onChange={() => setData('tipo', t.valor)}
                                    className="mt-1 accent-[var(--marca)]"
                                />
                                <span className="flex flex-col gap-0.5">
                                    <span className="text-[14px] font-medium">{t.titulo}</span>
                                    <span className="text-muted-foreground text-[13px]">{t.texto}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {temCursos && dados.tipo !== data.tipo && (
                        <p className="px-4 pb-4 text-[13px] text-amber-800 dark:text-amber-300">
                            Já há cursos registados. Mudar o tipo muda os termos e os períodos dos anos lectivos novos; os que já existem ficam como
                            estão.
                        </p>
                    )}
                </Bloco>

                <Bloco titulo="Marca">
                    <LinhaForm rotulo="Logótipo" htmlFor="logotipo" ajuda="PNG, JPG, WEBP ou SVG, até 1 MB.">
                        <div className="flex flex-wrap items-center gap-4">
                            {logotipo && !data.remover_logotipo && (
                                <img src={logotipo} alt="Logótipo actual" className="h-12 w-auto rounded border bg-white p-1" />
                            )}
                            <Input
                                id="logotipo"
                                type="file"
                                accept=".png,.jpg,.jpeg,.webp,.svg"
                                className="max-w-xs"
                                onChange={(e) => setData('logotipo', e.target.files?.[0] ?? null)}
                            />
                            {logotipo && (
                                <label className="flex items-center gap-2 text-[13px]">
                                    <input
                                        type="checkbox"
                                        checked={data.remover_logotipo}
                                        onChange={(e) => setData('remover_logotipo', e.target.checked)}
                                    />
                                    Tirar o logótipo
                                </label>
                            )}
                        </div>
                        <InputError message={errors.logotipo} className="mt-1" />
                    </LinhaForm>
                    {(['cor', 'cor_destaque'] as const).map((campo) => (
                        <LinhaForm
                            key={campo}
                            rotulo={campo === 'cor' ? 'Cor principal' : 'Cor de destaque'}
                            htmlFor={campo}
                            ajuda={campo === 'cor' ? 'Menu, botões e cabeçalhos dos documentos.' : 'Pormenores e realces.'}
                        >
                            <div className="flex items-center gap-3">
                                <input
                                    id={campo}
                                    type="color"
                                    value={data[campo]}
                                    onChange={(e) => setData(campo, e.target.value)}
                                    className="h-9 w-14 cursor-pointer rounded border bg-transparent p-0.5"
                                />
                                <Input
                                    value={data[campo]}
                                    onChange={(e) => setData(campo, e.target.value)}
                                    className="w-32 font-mono"
                                    aria-label={`${campo === 'cor' ? 'Cor principal' : 'Cor de destaque'} em hexadecimal`}
                                />
                            </div>
                            <InputError message={errors[campo]} className="mt-1" />
                        </LinhaForm>
                    ))}
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
