import {
    CalendarDays,
    ClipboardList,
    DatabaseBackup,
    FileText,
    GraduationCap,
    LayoutDashboard,
    Network,
    ScrollText,
    Settings,
    ShieldCheck,
    UserCog,
    UserPlus,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';

export interface ItemNav {
    label: string;
    href: string;
    icone: LucideIcon;
    /** Permissão necessária; sem ela a entrada não aparece (o backend verifica sempre) */
    permissao?: string;
}

export interface GrupoNav {
    titulo?: string;
    itens: ItemNav[];
}

/**
 * O menu do sistema interno, por grupos. Cada entrada só aparece a quem tem a permissão (usePode).
 * Os endereços seguem App\Support\Modulos; no esqueleto abrem no ecrã "em construção".
 */
export const NAVEGACAO: GrupoNav[] = [
    {
        itens: [{ label: 'Início', href: '/painel', icone: LayoutDashboard }],
    },
    {
        titulo: 'Académico',
        itens: [
            { label: 'Candidaturas', href: '/candidaturas', icone: UserPlus, permissao: 'candidaturas.ver' },
            { label: 'Estudantes', href: '/estudantes', icone: Users, permissao: 'estudantes.ver' },
            { label: 'Matrículas', href: '/matriculas', icone: ClipboardList, permissao: 'matriculas.criar' },
            { label: 'Avaliação', href: '/pautas', icone: GraduationCap, permissao: 'pautas.ver' },
            { label: 'Docência', href: '/docencia', icone: CalendarDays, permissao: 'aulas.registar' },
            { label: 'Estrutura', href: '/estrutura', icone: Network, permissao: 'estrutura.ver' },
        ],
    },
    {
        titulo: 'Gestão',
        itens: [
            { label: 'Finanças', href: '/financas', icone: Wallet, permissao: 'financas.ver' },
            { label: 'Documentos', href: '/documentos', icone: FileText, permissao: 'documentos.ver' },
        ],
    },
    {
        titulo: 'Administração',
        itens: [
            { label: 'Utilizadores', href: '/administracao/utilizadores', icone: UserCog, permissao: 'utilizadores.ver' },
            { label: 'Perfis e permissões', href: '/administracao/perfis', icone: ShieldCheck, permissao: 'perfis.gerir' },
            { label: 'Auditoria', href: '/administracao/auditoria', icone: ScrollText, permissao: 'auditoria.ver' },
            { label: 'Configuração', href: '/administracao/configuracao', icone: Settings, permissao: 'configuracao.gerir' },
            { label: 'Cópias de segurança', href: '/administracao/copias', icone: DatabaseBackup, permissao: 'copias.gerir' },
        ],
    },
];

/** O item do menu a que o endereço actual pertence (para o marcar como activo). */
export function itemActivo(caminho: string): ItemNav | null {
    let encontrado: ItemNav | null = null;
    for (const grupo of NAVEGACAO) {
        for (const item of grupo.itens) {
            if (caminho === item.href || caminho.startsWith(item.href + '/')) {
                if (encontrado === null || item.href.length > encontrado.href.length) {
                    encontrado = item;
                }
            }
        }
    }
    return encontrado;
}
