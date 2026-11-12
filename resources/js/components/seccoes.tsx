import { NavSeccao } from '@/components/erp';
import { usePode } from '@/hooks/use-pode';

/** Páginas da Estrutura académica */
export function NavEstrutura() {
    return (
        <NavSeccao
            itens={[
                { href: '/estrutura/cursos', label: 'Cursos' },
                { href: '/estrutura/disciplinas', label: 'Disciplinas' },
                { href: '/estrutura/unidades', label: 'Unidades orgânicas' },
                { href: '/estrutura/calendario', label: 'Calendário' },
                { href: '/estrutura/salas', label: 'Salas' },
            ]}
        />
    );
}

/** Páginas da Docência: cada uma só aparece a quem a pode abrir */
export function NavDocencia() {
    const pode = usePode();
    const itens = [
        { href: '/docencia/turmas', label: 'Turmas', permissao: 'turmas.ver' },
        { href: '/docencia/docentes', label: 'Docentes', permissao: 'turmas.ver' },
        { href: '/docencia/minhas-turmas', label: 'As minhas turmas', permissao: 'aulas.registar' },
    ].filter((i) => pode(i.permissao));
    return <NavSeccao itens={itens} />;
}

/** Páginas da Configuração */
export function NavConfiguracao() {
    return (
        <NavSeccao
            itens={[
                { href: '/administracao/configuracao/instituicao', label: 'Instituição' },
                { href: '/administracao/configuracao/avaliacao', label: 'Avaliação' },
            ]}
        />
    );
}

/** Número para mostrar num campo, à moçambicana: 12.5 → "12,5" */
export const numeroCampo = (n: number | null | undefined) => (n === null || n === undefined ? '' : String(n).replace('.', ','));

/** Créditos sem casas de dinheiro: 6 → "6", 4.5 → "4,5" */
export const formatCreditos = (n: number) => (Number.isInteger(n) ? String(n) : String(Math.round(n * 10) / 10).replace('.', ','));
