import { Bloco, Estado, LinhaVazia, Paginacao, TituloPagina, type CorEstado, type Pagina } from '@/components/erp';
import { formatMT, NavFinancas } from '@/components/seccoes';
import { Button } from '@/components/ui/button';
import { CaixaEscolha } from '@/components/ui/caixa-escolha';
import { Input } from '@/components/ui/input';
import { useFiltros } from '@/hooks/use-filtros';
import { usePode } from '@/hooks/use-pode';
import AppLayout from '@/layouts/app-layout';
import { formatData } from '@/lib/format';
import { type Opcao } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Receipt, Search } from 'lucide-react';

interface Linha {
    id: number;
    numero: string;
    estudante_id: number;
    estudante: string;
    estudante_numero: string;
    descricao: string;
    valor: string;
    pago: string;
    vencimento: string;
    estado: string;
    estado_rotulo: string;
    vencida: boolean;
}

interface Props {
    cobrancas: Pagina<Linha>;
    totais: { valor: string; pago: string; vencido: string };
    filtros: { ano: string; estado: string; q: string };
    anos: Opcao[];
    estados: Opcao[];
}

const COR: Record<string, CorEstado> = { aberta: 'laranja', parcial: 'azul', paga: 'verde', anulada: 'cinza' };

export default function Cobrancas({ cobrancas, totais, filtros: iniciais, anos, estados }: Props) {
    const pode = usePode();
    const { filtros, mudar } = useFiltros(route('financas.index'), iniciais);
    const gerar = () => {
        if (confirm('Gerar a taxa de inscrição e as propinas de todas as inscrições do ano que ainda não as têm?')) {
            router.post(route('financas.gerar'), { ano_lectivo_id: filtros.ano }, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Finanças" />
            <TituloPagina
                titulo="Finanças"
                descricao="Taxas de inscrição e propinas de cada estudante, com o que já foi pago e o que está vencido."
                accoes={
                    pode('cobrancas.gerar') &&
                    filtros.ano !== '' && (
                        <Button size="sm" onClick={gerar}>
                            <Receipt /> Gerar cobranças em falta
                        </Button>
                    )
                }
            />
            <NavFinancas />
            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                {[
                    ['Cobrado', totais.valor, ''],
                    ['Pago', totais.pago, 'text-green-700 dark:text-green-400'],
                    ['Vencido por pagar', totais.vencido, 'text-red-700 dark:text-red-400'],
                ].map(([rotulo, valor, cor]) => (
                    <div key={rotulo} className="painel px-4 py-3">
                        <div className="text-muted-foreground text-[12.5px]">{rotulo}</div>
                        <div className={`text-[20px] font-semibold tabular-nums ${cor}`}>{formatMT(valor)}</div>
                    </div>
                ))}
            </div>
            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                <CaixaEscolha value={filtros.ano} onChange={(e) => mudar('ano', e.target.value)} className="sm:max-w-36" aria-label="Ano lectivo">
                    {anos.map((a) => (
                        <option key={a.valor} value={a.valor}>
                            {a.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <CaixaEscolha value={filtros.estado} onChange={(e) => mudar('estado', e.target.value)} className="sm:max-w-48" aria-label="Estado">
                    <option value="">Todos os estados</option>
                    {estados.map((e) => (
                        <option key={e.valor} value={e.valor}>
                            {e.rotulo}
                        </option>
                    ))}
                </CaixaEscolha>
                <div className="relative w-full sm:max-w-sm">
                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                    <Input
                        value={filtros.q}
                        onChange={(e) => mudar('q', e.target.value)}
                        placeholder="Nome ou número do estudante"
                        className="pl-8"
                        aria-label="Pesquisar"
                    />
                </div>
            </div>
            <Bloco>
                <table className="tabela">
                    <thead>
                        <tr>
                            <th>Cobrança</th>
                            <th>Estudante</th>
                            <th>Descrição</th>
                            <th>Vencimento</th>
                            <th className="text-right">Valor</th>
                            <th className="text-right">Pago</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cobrancas.data.length === 0 && <LinhaVazia colunas={7} texto="Nenhuma cobrança com estes filtros." />}
                        {cobrancas.data.map((c) => (
                            <tr key={c.id}>
                                <td className="font-mono text-[12.5px]">{c.numero}</td>
                                <td>
                                    <Link href={route('estudantes.show', c.estudante_id)} className="font-medium hover:underline">
                                        {c.estudante}
                                    </Link>
                                    <div className="text-muted-foreground font-mono text-[11.5px]">{c.estudante_numero}</div>
                                </td>
                                <td>{c.descricao}</td>
                                <td className={c.vencida ? 'text-red-700 tabular-nums dark:text-red-400' : 'tabular-nums'}>
                                    {formatData(c.vencimento)}
                                </td>
                                <td className="text-right tabular-nums">{formatMT(c.valor)}</td>
                                <td className="text-right tabular-nums">{formatMT(c.pago)}</td>
                                <td>
                                    <Estado cor={c.vencida ? 'vermelho' : (COR[c.estado] ?? 'cinza')}>
                                        {c.vencida ? 'Vencida' : c.estado_rotulo}
                                    </Estado>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Paginacao pagina={cobrancas} />
            </Bloco>
        </AppLayout>
    );
}
