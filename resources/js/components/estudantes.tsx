import { type CorEstado } from '@/components/erp';
import { formatCreditos } from '@/components/seccoes';

/** Cores dos estados do estudante, da matrícula, da inscrição e das disciplinas na inscrição */
export const COR_ESTUDANTE: Record<string, CorEstado> = {
    activo: 'verde',
    suspenso: 'laranja',
    desistente: 'cinza',
    transferido: 'cinza',
    graduado: 'azul',
};

export const COR_MATRICULA: Record<string, CorEstado> = {
    activa: 'verde',
    suspensa: 'laranja',
    anulada: 'vermelho',
    transferida: 'cinza',
    concluida: 'azul',
};

export const COR_SITUACAO: Record<string, CorEstado> = {
    disponivel: 'cinza',
    inscrita: 'verde',
    aprovada: 'azul',
    bloqueada: 'vermelho',
    em_curso: 'laranja',
};

/** "6 cr." ou "1 crédito" */
export const creditos = (n: number) => `${formatCreditos(n)} ${n === 1 ? 'crédito' : 'créditos'}`;
