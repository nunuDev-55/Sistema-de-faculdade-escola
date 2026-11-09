import { Bloco, CabecalhoFicha, LinhaForm, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type Opcao } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';

interface Estudante {
    id: number;
    numero: string;
    nome: string;
    sexo: string | null;
    data_nascimento: string | null;
    documento_tipo: string | null;
    documento_numero: string | null;
    nuit: string | null;
    nacionalidade: string | null;
    naturalidade: string | null;
    provincia: string | null;
    morada: string | null;
    telemovel: string | null;
    email: string | null;
    necessidades_especiais: string | null;
    categoria: string;
    estado: string;
    nota_privada: string | null;
}

interface Props {
    estudante: Estudante | null;
    sexos: Opcao[];
    documentos: Opcao[];
    categorias: Opcao[];
    estados: Opcao[];
    provincias: Opcao[];
}

type Campo = Exclude<keyof Estudante, 'id' | 'numero'>;

function Escolha({
    id,
    valor,
    mudar,
    opcoes,
    vazio = '—',
    className,
}: {
    id: string;
    valor: string;
    mudar: (v: string) => void;
    opcoes: Opcao[];
    vazio?: string | null;
    className?: string;
}) {
    return (
        <CaixaEscolha id={id} value={valor} onChange={(e) => mudar(e.target.value)} className={className}>
            {vazio !== null && <option value="">{vazio}</option>}
            {opcoes.map((o) => (
                <option key={o.valor} value={o.valor}>
                    {o.rotulo}
                </option>
            ))}
        </CaixaEscolha>
    );
}

export default function FormularioEstudante({ estudante, sexos, documentos, categorias, estados, provincias }: Props) {
    const novo = estudante === null;
    const { data, setData, post, put, processing, errors } = useForm<Record<Campo, string>>({
        nome: estudante?.nome ?? '',
        sexo: estudante?.sexo ?? '',
        data_nascimento: estudante?.data_nascimento ?? '',
        documento_tipo: estudante?.documento_tipo ?? 'bi',
        documento_numero: estudante?.documento_numero ?? '',
        nuit: estudante?.nuit ?? '',
        nacionalidade: estudante?.nacionalidade ?? (novo ? 'Moçambicana' : ''),
        naturalidade: estudante?.naturalidade ?? '',
        provincia: estudante?.provincia ?? '',
        morada: estudante?.morada ?? '',
        telemovel: estudante?.telemovel ?? '',
        email: estudante?.email ?? '',
        necessidades_especiais: estudante?.necessidades_especiais ?? '',
        categoria: estudante?.categoria ?? 'regular',
        estado: estudante?.estado ?? 'activo',
        nota_privada: estudante?.nota_privada ?? '',
    });

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        if (estudante) put(route('estudantes.update', estudante.id));
        else post(route('estudantes.store'));
    };

    const texto = (
        campo: Campo,
        rotulo: string,
        extra: { obrigatorio?: boolean; ajuda?: ReactNode; className?: string; tipo?: string; modo?: 'tel' | 'email' | 'numeric' } = {},
    ) => (
        <LinhaForm rotulo={rotulo} htmlFor={campo} obrigatorio={extra.obrigatorio} ajuda={extra.ajuda}>
            <Input
                id={campo}
                type={extra.tipo ?? 'text'}
                inputMode={extra.modo}
                value={data[campo]}
                onChange={(e) => setData(campo, e.target.value)}
                className={extra.className}
            />
            <InputError message={errors[campo]} className="mt-1" />
        </LinhaForm>
    );

    return (
        <AppLayout>
            <Head title={estudante ? `Alterar ${estudante.nome}` : 'Novo estudante'} />
            {estudante ? (
                <CabecalhoFicha
                    voltar={{ href: route('estudantes.show', estudante.id), label: 'Voltar à ficha' }}
                    referencia={estudante.numero}
                    titulo={`Alterar ${estudante.nome}`}
                />
            ) : (
                <TituloPagina titulo="Novo estudante" descricao="O número de estudante é atribuído ao gravar e nunca se repete." />
            )}

            <form onSubmit={guardar} className="flex flex-col gap-5">
                <Bloco titulo="Identificação">
                    {texto('nome', 'Nome completo', { obrigatorio: true })}
                    <LinhaForm rotulo="Sexo" htmlFor="sexo">
                        <Escolha id="sexo" valor={data.sexo} mudar={(v) => setData('sexo', v)} opcoes={sexos} className="max-w-48" />
                        <InputError message={errors.sexo} className="mt-1" />
                    </LinhaForm>
                    {texto('data_nascimento', 'Data de nascimento', { tipo: 'date', className: 'max-w-48' })}
                    <LinhaForm
                        rotulo="Documento de identificação"
                        htmlFor="documento_numero"
                        ajuda="Serve para não registar a mesma pessoa duas vezes."
                    >
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Escolha
                                id="documento_tipo"
                                valor={data.documento_tipo}
                                mudar={(v) => setData('documento_tipo', v)}
                                opcoes={documentos}
                                className="sm:max-w-56"
                            />
                            <Input
                                id="documento_numero"
                                value={data.documento_numero}
                                onChange={(e) => setData('documento_numero', e.target.value)}
                                className="uppercase sm:max-w-56"
                                aria-label="Número do documento"
                            />
                        </div>
                        <InputError message={errors.documento_numero ?? errors.documento_tipo} className="mt-1" />
                    </LinhaForm>
                    {texto('nuit', 'NUIT', { modo: 'numeric', className: 'max-w-40 tabular-nums' })}
                    {texto('nacionalidade', 'Nacionalidade', { className: 'max-w-64' })}
                    {texto('naturalidade', 'Naturalidade', { ajuda: 'Distrito ou cidade onde nasceu.', className: 'max-w-80' })}
                </Bloco>

                <Bloco titulo="Contactos e morada">
                    {texto('telemovel', 'Telemóvel', { tipo: 'tel', modo: 'tel', className: 'max-w-56 tabular-nums' })}
                    {texto('email', 'E-mail', { tipo: 'email', modo: 'email', className: 'max-w-96' })}
                    <LinhaForm rotulo="Província" htmlFor="provincia">
                        <Escolha
                            id="provincia"
                            valor={data.provincia}
                            mudar={(v) => setData('provincia', v)}
                            opcoes={provincias}
                            className="max-w-64"
                        />
                        <InputError message={errors.provincia} className="mt-1" />
                    </LinhaForm>
                    {texto('morada', 'Morada', { ajuda: 'Bairro, quarteirão, número da casa.' })}
                </Bloco>

                <Bloco titulo="Situação">
                    <LinhaForm
                        rotulo="Categoria"
                        htmlFor="categoria"
                        obrigatorio
                        ajuda="Os bolseiros e os funcionários podem ter descontos nas propinas."
                    >
                        <Escolha
                            id="categoria"
                            valor={data.categoria}
                            mudar={(v) => setData('categoria', v)}
                            opcoes={categorias}
                            vazio={null}
                            className="max-w-80"
                        />
                        <InputError message={errors.categoria} className="mt-1" />
                    </LinhaForm>
                    {!novo && (
                        <LinhaForm rotulo="Estado" htmlFor="estado" obrigatorio ajuda="Um estudante que não está activo não se pode matricular.">
                            <Escolha
                                id="estado"
                                valor={data.estado}
                                mudar={(v) => setData('estado', v)}
                                opcoes={estados}
                                vazio={null}
                                className="max-w-56"
                            />
                            <InputError message={errors.estado} className="mt-1" />
                        </LinhaForm>
                    )}
                    <LinhaForm
                        rotulo="Necessidades especiais"
                        htmlFor="necessidades_especiais"
                        ajuda="Para os docentes e a secretaria terem em conta (salas, exames)."
                    >
                        <Textarea
                            id="necessidades_especiais"
                            value={data.necessidades_especiais}
                            onChange={(e) => setData('necessidades_especiais', e.target.value)}
                            rows={2}
                        />
                        <InputError message={errors.necessidades_especiais} className="mt-1" />
                    </LinhaForm>
                    <LinhaForm rotulo="Nota interna" htmlFor="nota_privada" ajuda="Só a equipa vê; nunca aparece no portal nem nos documentos.">
                        <Textarea id="nota_privada" value={data.nota_privada} onChange={(e) => setData('nota_privada', e.target.value)} rows={2} />
                        <InputError message={errors.nota_privada} className="mt-1" />
                    </LinhaForm>
                </Bloco>

                <div className="flex justify-end gap-2">
                    <Button asChild variant="outline">
                        <Link href={estudante ? route('estudantes.show', estudante.id) : route('estudantes.index')}>Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {novo ? 'Registar estudante' : 'Guardar'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
