import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Check, Hammer } from 'lucide-react';

interface Props {
    titulo: string;
    descricao: string;
    planeado: string[];
}

/** Ecrã temporário de um módulo ainda por fazer: mostra o que está planeado (ver docs/ROADMAP.md). */
export default function EmConstrucao({ titulo, descricao, planeado }: Props) {
    return (
        <AppLayout>
            <Head title={titulo} />

            <div className="mx-auto max-w-2xl">
                <div className="mb-6 flex items-center gap-3">
                    <span className="flex size-11 items-center justify-center rounded-lg bg-[var(--marca-suave)] text-[var(--marca-texto)]">
                        <Hammer className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-[20px] font-semibold tracking-[-0.015em]">{titulo}</h1>
                        <p className="text-muted-foreground text-[13.5px]">Módulo em construção</p>
                    </div>
                </div>

                <p className="text-[14px]">{descricao}</p>

                <div className="bg-card mt-6 rounded-lg border p-5">
                    <h2 className="text-[13px] font-medium">O que vai ter</h2>
                    <ul className="mt-3 flex flex-col gap-2">
                        {planeado.map((item) => (
                            <li key={item} className="flex items-start gap-2 text-[13.5px]">
                                <Check className="mt-0.5 size-4 shrink-0 text-[var(--marca-texto)]" />
                                <span>{item}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
