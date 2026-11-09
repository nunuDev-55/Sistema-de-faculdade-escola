import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Os termos da instituição: "Curso" ou "Classe", "Semestre" ou "Trimestre"…
 * Vêm do servidor (App\Support\TipoInstituicao) e mudam com o tipo escolhido em Configuração.
 */
export function useTermos() {
    const { termos } = usePage<SharedData>().props;
    return {
        curso: termos.curso ?? 'Curso',
        periodo: termos.periodo ?? 'Semestre',
        ano_curricular: termos.ano_curricular ?? 'Ano',
        disciplina: termos.disciplina ?? 'Disciplina',
        turma: termos.turma ?? 'Turma',
    };
}
