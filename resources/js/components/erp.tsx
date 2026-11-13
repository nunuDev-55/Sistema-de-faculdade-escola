/**
 * Peças de interface comuns às páginas do sistema interno (herdadas do nsERP, sem as partes comerciais):
 * título, estados, cabeçalho de ficha, navegação de secção, blocos, campos e mensagens.
 */
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, ChevronRight, CircleAlert, X, type LucideIcon } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

/* ---------------------------------------------------------------- Título */

export function TituloPagina({
    titulo,
    contagem,
    descricao,
    accoes,
}: {
    titulo: string;
    contagem?: number;
    descricao?: ReactNode;
    accoes?: ReactNode;
}) {
    return (
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div className="flex min-w-0 flex-col gap-1">
                <h1 className="text-[22px] leading-tight font-semibold tracking-[-0.015em]">
                    {titulo}
                    {contagem !== undefined && <span className="text-muted-foreground ml-2 text-[15px] font-normal tabular-nums">{contagem}</span>}
                </h1>
                {descricao && <p className="text-muted-foreground text-[13.5px]">{descricao}</p>}
            </div>
            {accoes && <div className="flex flex-wrap gap-2">{accoes}</div>}
        </div>
    );
}

/* ---------------------------------------------------------------- Estados */

const CORES_ESTADO = {
    cinza: 'bg-neutral-400 dark:bg-neutral-500',
    laranja: 'bg-amber-500',
    verde: 'bg-green-600 dark:bg-green-500',
    vermelho: 'bg-red-600 dark:bg-red-500',
    azul: 'bg-blue-600 dark:bg-blue-500',
} as const;

export type CorEstado = keyof typeof CORES_ESTADO;

/** Estado discreto: um ponto de cor e o texto na cor normal */
export function Estado({ cor, children, className }: { cor: CorEstado; children: ReactNode; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-[13px] whitespace-nowrap', className)}>
            <span className={cn('size-[7px] shrink-0 rounded-full', CORES_ESTADO[cor])} aria-hidden="true" />
            {children}
        </span>
    );
}

/* ---------------------------------------------------------------- Fichas e secções */

export function CabecalhoFicha({
    referencia,
    titulo,
    subtitulo,
    estado,
    voltar,
}: {
    referencia: string;
    titulo?: ReactNode;
    subtitulo?: ReactNode;
    estado?: ReactNode;
    voltar?: { href: string; label?: string };
}) {
    return (
        <div className="mb-5 flex flex-col gap-2">
            {voltar && (
                <Link href={voltar.href} className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1 text-[13px]">
                    <ChevronLeft className="size-3.5" />
                    {voltar.label ?? 'Voltar à lista'}
                </Link>
            )}
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                <h1 className="text-[22px] leading-tight font-semibold tracking-[-0.015em]">{referencia}</h1>
                {estado}
            </div>
            {titulo && <div className="text-[14px] font-medium">{titulo}</div>}
            {subtitulo && <div className="text-muted-foreground text-[13px]">{subtitulo}</div>}
        </div>
    );
}

/** Navegação entre as páginas de uma secção (Estrutura: Cursos, Disciplinas…), como separadores */
export function NavSeccao({ itens }: { itens: { href: string; label: string }[] }) {
    const { url } = usePage();
    const caminho = url.split('?')[0];
    const corresponde = (href: string) => caminho === href || caminho.startsWith(href + '/');
    // O separador mais específico ganha: em /financas/precos fica activo "Tabela de preços", não "Cobranças"
    const escolhido = itens.filter((i) => corresponde(i.href)).sort((a, b) => b.href.length - a.href.length)[0]?.href;
    return (
        <nav
            className="mb-5 flex [scrollbar-width:none] gap-5 overflow-x-auto overflow-y-hidden border-b [&::-webkit-scrollbar]:hidden"
            aria-label="Secção"
        >
            {itens.map((i) => {
                const activo = i.href === escolhido;
                return (
                    <Link
                        key={i.href}
                        href={i.href}
                        aria-current={activo ? 'page' : undefined}
                        className={cn(
                            '-mb-px border-b-2 pb-2.5 text-[13.5px] whitespace-nowrap transition-colors',
                            activo
                                ? 'border-foreground text-foreground font-medium'
                                : 'text-muted-foreground hover:text-foreground border-transparent',
                        )}
                    >
                        {i.label}
                    </Link>
                );
            })}
        </nav>
    );
}

/** Lista de campos "rótulo: valor" de uma ficha */
export function Campos({ children, className }: { children: ReactNode; className?: string }) {
    return <dl className={cn('ficha painel', className)}>{children}</dl>;
}

export function Campo({ rotulo, children }: { rotulo: string; children?: ReactNode }) {
    return (
        <div>
            <dt>{rotulo}</dt>
            <dd>{children === null || children === undefined || children === '' ? <span className="text-muted-foreground/60">—</span> : children}</dd>
        </div>
    );
}

/** Caixa com título, para agrupar blocos dentro de uma página */
export function Bloco({ titulo, accoes, children, className }: { titulo?: ReactNode; accoes?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={cn('painel overflow-hidden', className)}>
            {(titulo || accoes) && (
                <header className="flex min-h-11 flex-wrap items-center justify-between gap-2 border-b px-4 py-2">
                    <h2 className="text-[14px] font-semibold">{titulo}</h2>
                    {accoes}
                </header>
            )}
            <div className="overflow-x-auto">{children}</div>
        </section>
    );
}

/** Aviso no topo quando o servidor recusou uma acção (aprovar, alterar um plano em vigor…) */
export function ErroFicha({ mensagem }: { mensagem?: string }) {
    if (!mensagem) return null;
    return (
        <div
            role="alert"
            className="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-[13px] text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
        >
            {mensagem}
        </div>
    );
}

/** Linha de formulário: rótulo à esquerda, campo à direita (no telemóvel, um por baixo do outro) */
export function LinhaForm({
    rotulo,
    htmlFor,
    obrigatorio,
    children,
    ajuda,
}: {
    rotulo: string;
    htmlFor?: string;
    obrigatorio?: boolean;
    children: ReactNode;
    ajuda?: ReactNode;
}) {
    return (
        <div className="grid gap-1.5 border-b border-[var(--linha-suave)] px-5 py-3.5 last:border-0 md:grid-cols-[14rem_1fr] md:items-start md:gap-6">
            <label htmlFor={htmlFor} className={cn('text-muted-foreground pt-2 text-[13.5px]', obrigatorio && 'text-foreground font-medium')}>
                {rotulo}
                {obrigatorio && <span className="text-destructive"> *</span>}
            </label>
            <div className="min-w-0">
                {children}
                {ajuda && <p className="text-muted-foreground mt-1 text-xs">{ajuda}</p>}
            </div>
        </div>
    );
}

export function LinhaVazia({ colunas, texto = 'Nenhum registo encontrado' }: { colunas: number; texto?: string }) {
    return (
        <tr>
            <td colSpan={colunas} className="text-muted-foreground py-10 text-center" data-vazia="">
                {texto}
            </td>
        </tr>
    );
}

/** Botão só com ícone para as linhas de uma tabela; o rótulo aparece ao passar o rato e é lido pelos leitores de ecrã */
export function BotaoIcone({
    rotulo,
    onClick,
    disabled,
    className,
    children,
}: {
    rotulo: string;
    onClick: () => void;
    disabled?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={rotulo}
            aria-label={rotulo}
            className={cn(
                'text-muted-foreground hover:text-foreground hover:bg-muted rounded p-1.5 transition-colors disabled:pointer-events-none disabled:opacity-30',
                className,
            )}
        >
            {children}
        </button>
    );
}

export function BotaoLink({
    href,
    icone: Icone,
    children,
    variante = 'default',
}: {
    href: string;
    icone?: LucideIcon;
    children: ReactNode;
    variante?: 'default' | 'outline';
}) {
    return (
        <Button asChild size="sm" variant={variante}>
            <Link href={href}>
                {Icone && <Icone className="size-4" />}
                {children}
            </Link>
        </Button>
    );
}

/* ---------------------------------------------------------------- Mensagens */

/**
 * A mensagem que o servidor deixa depois de gravar (flash "sucesso" ou "erro"), no fundo do ecrã.
 * Desaparece sozinha; a de erro fica mais tempo.
 */
export function MensagemFlash() {
    const { flash } = usePage<SharedData>().props;
    const [visivel, setVisivel] = useState<{ texto: string; erro: boolean } | null>(null);

    useEffect(() => {
        const texto = flash.erro ?? flash.sucesso;
        if (!texto) return;
        setVisivel({ texto, erro: Boolean(flash.erro) });
        const t = setTimeout(() => setVisivel(null), flash.erro ? 7000 : 4500);
        return () => clearTimeout(t);
    }, [flash.sucesso, flash.erro]);

    if (!visivel) return null;
    const Icone = visivel.erro ? CircleAlert : CheckCircle2;
    return (
        <div
            role="status"
            className="bg-background fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] left-1/2 z-50 flex w-[min(34rem,calc(100vw-2rem))] -translate-x-1/2 items-start gap-2.5 rounded-lg border px-4 py-3 text-[13.5px] shadow-lg print:hidden"
        >
            <Icone className={cn('mt-0.5 size-4 shrink-0', visivel.erro ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')} />
            <span className="flex-1">{visivel.texto}</span>
            <button
                type="button"
                onClick={() => setVisivel(null)}
                className="text-muted-foreground hover:text-foreground"
                aria-label="Fechar mensagem"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}

/* ---------------------------------------------------------------- Listas longas */

/** Página de resultados de uma lista paginada no servidor (paginate() do Laravel) */
export interface Pagina<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

/** Anterior / seguinte por baixo de uma tabela paginada; não aparece se tudo couber numa página */
export function Paginacao<T>({ pagina }: { pagina: Pagina<T> }) {
    if (pagina.last_page <= 1) return null;
    const botao = (url: string | null, rotulo: string, Icone: LucideIcon) =>
        url ? (
            <Button asChild size="sm" variant="outline">
                <Link href={url} preserveScroll preserveState>
                    {rotulo === 'Anterior' && <Icone className="size-4" />}
                    {rotulo}
                    {rotulo === 'Seguinte' && <Icone className="size-4" />}
                </Link>
            </Button>
        ) : (
            <Button size="sm" variant="outline" disabled>
                {rotulo}
            </Button>
        );
    return (
        <nav className="flex items-center justify-between gap-3 border-t px-4 py-2.5 text-[13px]" aria-label="Páginas da lista">
            <span className="text-muted-foreground tabular-nums">
                {pagina.from}–{pagina.to} de {pagina.total}
            </span>
            <div className="flex gap-2">
                {botao(pagina.prev_page_url, 'Anterior', ChevronLeft)}
                {botao(pagina.next_page_url, 'Seguinte', ChevronRight)}
            </div>
        </nav>
    );
}

/** Separadores dentro de uma ficha (Percurso, Dados pessoais, Encarregados), sem mudar de endereço */
export function Separadores<T extends string>({
    itens,
    activo,
    mudar,
}: {
    itens: { valor: T; label: ReactNode }[];
    activo: T;
    mudar: (valor: T) => void;
}) {
    return (
        <div
            role="tablist"
            className="mb-5 flex [scrollbar-width:none] gap-5 overflow-x-auto overflow-y-hidden border-b [&::-webkit-scrollbar]:hidden"
        >
            {itens.map((i) => (
                <button
                    key={i.valor}
                    type="button"
                    role="tab"
                    aria-selected={i.valor === activo}
                    onClick={() => mudar(i.valor)}
                    className={cn(
                        '-mb-px border-b-2 pb-2.5 text-[13.5px] whitespace-nowrap transition-colors',
                        i.valor === activo
                            ? 'border-foreground text-foreground font-medium'
                            : 'text-muted-foreground hover:text-foreground border-transparent',
                    )}
                >
                    {i.label}
                </button>
            ))}
        </div>
    );
}
