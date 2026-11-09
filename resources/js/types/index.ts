import { type LucideIcon } from 'lucide-react';

export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    email_verified_at?: string | null;
}

export interface Auth {
    user: User;
    perfil: string | null;
    /** Permissões do utilizador: só para mostrar/esconder menus e botões (o backend verifica sempre) */
    permissoes: string[];
}

/** Dados partilhados em todas as páginas (ver app/Http/Middleware/HandleInertiaRequests.php) */
export interface SharedData {
    sistema: string;
    instituicao: {
        nome: string;
        logotipo: string | null;
    };
    /** Termos conforme o tipo de instituição: Curso/Classe, Semestre/Trimestre... */
    termos: Record<string, string>;
    auth: Auth;
    flash: {
        sucesso?: string;
        erro?: string;
    };
    [key: string]: unknown;
}

/** Entrada de menu (usada no menu lateral e nas definições) */
export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
}

/** Opção de uma caixa de escolha (vem do servidor: App\Support\Opcoes) */
export interface Opcao {
    valor: string;
    rotulo: string;
}
