import { Bloco, Estado, LinhaVazia, Paginacao, TituloPagina, type CorEstado, type Pagina } from '@/components/erp';
import { formatMT, NavDocumentos } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { formatDataHora } from '@/lib/format';
import { type Opcao, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';

interface Pedido {
    id: number;
    numero: string;
    tipo: string;
    finalidade: string | null;
    estudante_id: number;
    estudante: string;
    estudante_numero: string;
    origem: string;
    pedido_em: string;
    emolumento: string | null;
    situacao: string;
    situacao_rotulo: string;
    motivo_recusa: string | null;
    documento_id: number | null;
    documento_numero: string | null;
}

interface Props {
    pedidos: Pagina<Pedido>;
    filtros: { situacao: string; q: string };
    situacoes: Opcao[];
    porEmitir: number;
}

const COR: Record<string, CorEstado> = {
    aguarda_pagamento: 'laranja',
    por_emitir: 'azul',
    emitido: 'verde',
    entregue: 'cinza',
    recusado: 'vermelho',
};

function Accoes({ pedido }: { pedido: Pedido }) {
    const pode = usePode();
    const [recusar, setRecusar] = useState(false);
    const [motivo, setMotivo] = useState('');
    if (!pode('documentos.emitir')) return null;
    if (recusar) {
        return (
            <div className="flex min-w-64 flex-col gap-1.5">
                <Input
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                    placeholder="Motivo (o estudante vê-o no portal)"
                    aria-label="Motivo"
                />
                <div className="flex justify-end gap-1.5">
                    <Button size="sm" variant="outline" onClick={() => setRecusar(false)}>
                        Cancelar
                    </Button>
                    <Button
                        size="sm"
                        variant="destructive"
                        disabled={motivo.trim() === ''}
                        onClick={() => router.post(route('pedidos.recusar', pedido.id), { motivo }, { preserveScroll: true })}
                    >
                        Recusar
                    </Button>
                </div>
            </div>
        );
    }
    return (
        <div className="flex justify-end gap-1.5">
            {pedido.situacao === 'por_emitir' && (
                <Button size="sm" onClick={() => router.post(route('pedidos.emitir', pedido.id))}>
                    Emitir
                </Button>
            )}
            {pedido.situacao === 'emitido' && (
                <Button size="sm" variant="outline" onClick={() => router.post(route('pedidos.entregar', pedido.id), {}, { preserveScroll: true })}>
                    Marcar entregue
                </Button>
            )}
            {(pedido.situacao === 'por_emitir' || pedido.situacao === 'aguarda_pagamento') && (
                <Button size="sm" variant="ghost" onClick={() => setRecusar(true)}>
                    Recusar
                </Button>
            )}
        </div>
    );
}

/** A fila de pedidos de documentos: os pagos emitem-se, os prontos entregam-se; recusa-se com o motivo. */
export default function Pedidos({ pedidos, filtros: iniciais, situacoes, porEmitir }: Props) {
    const { filtros, mudar } = useFiltros(route('pedidos.index'), iniciais);
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;

    return (
        <AppLayout>
            <Head title="Pedidos de documentos" />
            <TituloPagina
                titulo="Documentos académicos"
                descricao={
                    porEmitir > 0
                        ? `${porEmitir} ${porEmitir === 1 ? 'pedido pago espera' : 'pedidos pagos esperam'} pela emissão.`
                        : 'Os pedidos feitos no portal: emitem-se depois de pago o emolumento.'
                }
            />
            <NavDocumentos />
            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                <CaixaEscolha
                    value={filtros.situacao}
                    onChange={(e) => mudar('situacao', e.target.value)}
                    className="sm:max-w-48"
                    aria-label="Situação"
                >
                    {situacoes.map((s) => (
                        <option key={s.valor} value={s.valor}>
                            {s.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <div className="relative w-full sm:max-w-sm">
                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                    <Input
                        value={filtros.q}
                        onChange={(e) => mudar('q', e.target.value)}
                        placeholder="Número do pedido, nome ou número do estudante"
                        className="pl-8"
                        aria-label="Pesquisar"
                    />
                </div>
            </div>
            {errors.pedido && <p className="mb-3 text-[13px] text-red-600">{errors.pedido}</p>}
            {errors.tipo && <p className="mb-3 text-[13px] text-red-600">{errors.tipo}</p>}
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Estudante</th>
                            <th>Documento</th>
                            <th className="text-right">Emolumento</th>
                            <th>Situação</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {pedidos.data.length === 0 && <LinhaVazia colunas={6} texto="Não há pedidos com estes filtros." />}
                        {pedidos.data.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <div className="font-mono text-[12.5px]">{p.numero}</div>
                                    <div className="text-muted-foreground text-[12px] tabular-nums">
                                        {formatDataHora(p.pedido_em)}
                                        {p.origem === 'portal' && ' · portal'}
                                    </div>
                                </td>
                                <td>
                                    <Link href={route('estudantes.show', p.estudante_id)} className="font-medium hover:underline">
                                        {p.estudante}
                                    </Link>
                                    <div className="text-muted-foreground font-mono text-[11.5px]">{p.estudante_numero}</div>
                                </td>
                                <td>
                                    {p.tipo}
                                    {p.finalidade && <div className="text-muted-foreground text-[12px]">{p.finalidade}</div>}
                                </td>
                                <td className="text-right tabular-nums">{p.emolumento ? formatMT(p.emolumento) : '—'}</td>
                                <td>
                                    <Estado cor={COR[p.situacao] ?? 'cinza'}>{p.situacao_rotulo}</Estado>
                                    {p.documento_id && (
                                        <Link
                                            href={route('documentos.show', p.documento_id)}
                                            className="text-muted-foreground block font-mono text-[11.5px] hover:underline"
                                        >
                                            {p.documento_numero}
                                        </Link>
                                    )}
                                    {p.motivo_recusa && <div className="text-muted-foreground text-[12px]">{p.motivo_recusa}</div>}
                                </td>
                                <td>
                                    <Accoes pedido={p} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Paginacao pagina={pedidos} />
            </Bloco>
        </AppLayout>
    );
}
