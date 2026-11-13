import { Bloco, BotaoIcone, LinhaForm, LinhaVazia, TituloPagina } from '@/components/erp';
import InputError from '@/components/input-error';
import { formatMT, NavFinancas } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { type Opcao } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Preco {
    id: number;
    curso_id: number | null;
    curso: string;
    tipo: string;
    tipo_rotulo: string;
    valor: string;
    prestacoes: number;
    dia_vencimento: number;
}

interface Props {
    precos: Preco[];
    ano: { id: number; nome: string } | null;
    anos: Opcao[];
    cursos: Opcao[];
    tipos: Opcao[];
}

function NovoPreco({ ano, cursos, tipos, fechar }: { ano: { id: number }; cursos: Opcao[]; tipos: Opcao[]; fechar: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        ano_lectivo_id: String(ano.id),
        curso_id: '',
        tipo: 'propina',
        valor: '',
        prestacoes: '5',
        dia_vencimento: '10',
    });
    const guardar = (e: FormEvent) => {
        e.preventDefault();
        post(route('financas.precos.guardar'), { preserveScroll: true, onSuccess: fechar });
    };
    return (
        <form onSubmit={guardar} className="mb-5">
            <Bloco titulo="Preço">
                <LinhaForm rotulo="O quê" htmlFor="tipo" obrigatorio>
                    <CaixaEscolha id="tipo" value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} className="max-w-80">
                        {tipos.map((t) => (
                            <option key={t.valor} value={t.valor}>
                                {t.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                </LinhaForm>
                <LinhaForm rotulo="Curso" htmlFor="curso_id" ajuda="O preço de um curso vale mais do que o de todos os cursos.">
                    <CaixaEscolha id="curso_id" value={data.curso_id} onChange={(e) => setData('curso_id', e.target.value)} className="max-w-96">
                        <option value="">Todos os cursos</option>
                        {cursos.map((c) => (
                            <option key={c.valor} value={c.valor}>
                                {c.rotulo}
                            </option>
                        ))}
                    </CaixaEscolha>
                </LinhaForm>
                <LinhaForm rotulo="Valor (MT)" htmlFor="valor" obrigatorio>
                    <Input
                        id="valor"
                        inputMode="decimal"
                        value={data.valor}
                        onChange={(e) => setData('valor', e.target.value)}
                        placeholder="3 500,00"
                        className="max-w-40 tabular-nums"
                    />
                    <InputError message={errors.valor} className="mt-1" />
                </LinhaForm>
                {data.tipo === 'propina' && (
                    <LinhaForm
                        rotulo="Meses por período"
                        htmlFor="prestacoes"
                        obrigatorio
                        ajuda="Quantas propinas por semestre (ou por ano, no ensino geral)."
                    >
                        <Input
                            id="prestacoes"
                            type="number"
                            min={1}
                            max={12}
                            value={data.prestacoes}
                            onChange={(e) => setData('prestacoes', e.target.value)}
                            className="w-24"
                        />
                        <InputError message={errors.prestacoes} className="mt-1" />
                    </LinhaForm>
                )}
                {data.tipo.startsWith('doc_') ? (
                    <LinhaForm rotulo="Pagamento">
                        <p className="text-muted-foreground pt-1.5 text-[13px]">
                            Cobrado quando o estudante pede o documento; a secretaria emite-o depois de pago.
                        </p>
                    </LinhaForm>
                ) : (
                    <LinhaForm
                        rotulo="Dia de pagamento"
                        htmlFor="dia_vencimento"
                        obrigatorio
                        ajuda="Depois deste dia do mês, a cobrança fica vencida."
                    >
                        <Input
                            id="dia_vencimento"
                            type="number"
                            min={1}
                            max={28}
                            value={data.dia_vencimento}
                            onChange={(e) => setData('dia_vencimento', e.target.value)}
                            className="w-24"
                        />
                        <InputError message={errors.dia_vencimento} className="mt-1" />
                    </LinhaForm>
                )}
                <div className="flex justify-end gap-2 px-5 py-3">
                    <Button type="button" variant="outline" onClick={fechar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Guardar
                    </Button>
                </div>
            </Bloco>
        </form>
    );
}

export default function Precos({ precos, ano, anos, cursos, tipos }: Props) {
    const pode = usePode();
    const [novo, setNovo] = useState(false);
    const { filtros, mudar } = useFiltros(route('financas.precos'), { ano: ano ? String(ano.id) : '' });

    return (
        <AppLayout>
            <Head title="Tabela de preços" />
            <TituloPagina
                titulo="Finanças"
                descricao="A tabela de preços de cada ano lectivo: taxa de inscrição por período e propina mensal."
                accoes={
                    pode('financas.configurar') &&
                    ano &&
                    !novo && (
                        <Button size="sm" onClick={() => setNovo(true)}>
                            <Plus /> Preço
                        </Button>
                    )
                }
            />
            <NavFinancas />
            {novo && ano && <NovoPreco ano={ano} cursos={cursos} tipos={tipos} fechar={() => setNovo(false)} />}
            <CaixaEscolha value={filtros.ano} onChange={(e) => mudar('ano', e.target.value)} className="mb-3 sm:max-w-36" aria-label="Ano lectivo">
                {anos.map((a) => (
                    <option key={a.valor} value={a.valor}>
                        {a.rotulo}
                    </option>
                ))}
            </CaixaEscolha>
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>O quê</th>
                            <th className="text-right">Valor</th>
                            <th className="text-right">Meses</th>
                            <th className="text-right">Dia de pagamento</th>
                            {pode('financas.configurar') && <th className="w-10" />}
                        </tr>
                    </thead>
                    <tbody>
                        {precos.length === 0 && <LinhaVazia colunas={6} texto="Ainda não há preços para este ano lectivo." />}
                        {precos.map((p) => (
                            <tr key={p.id}>
                                <td>{p.curso}</td>
                                <td>{p.tipo_rotulo}</td>
                                <td className="text-right tabular-nums">{formatMT(p.valor)}</td>
                                <td className="text-right tabular-nums">{p.tipo === 'propina' ? p.prestacoes : '—'}</td>
                                <td className="text-right tabular-nums">{p.tipo.startsWith('doc_') ? '—' : p.dia_vencimento}</td>
                                {pode('financas.configurar') && (
                                    <td>
                                        <BotaoIcone
                                            rotulo="Retirar este preço"
                                            onClick={() =>
                                                confirm('Retirar este preço da tabela?') &&
                                                router.delete(route('financas.precos.apagar', p.id), { preserveScroll: true })
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </BotaoIcone>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Bloco>
        </AppLayout>
    );
}
