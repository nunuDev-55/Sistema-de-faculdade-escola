import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

/** Entrada no portal do estudante: número de estudante e senha (a secretaria entrega a senha). */
export default function EntrarPortal({ instituicao }: { instituicao: { nome: string; logotipo: string | null } }) {
    const { data, setData, post, processing, errors } = useForm({ numero: '', password: '' });
    const entrar = (e: FormEvent) => {
        e.preventDefault();
        post(route('portal.entrar.store'));
    };

    return (
        <div className="bg-muted flex min-h-screen items-center justify-center px-4 py-10">
            <Head title="Portal do estudante" />
            <form onSubmit={entrar} className="bg-background w-full max-w-sm rounded-xl border p-6 shadow-sm">
                <div className="mb-6 flex flex-col items-center gap-2 text-center">
                    {instituicao.logotipo && <img src={instituicao.logotipo} alt="" className="h-14 w-14 object-contain" />}
                    <div className="text-[15px] font-semibold">{instituicao.nome}</div>
                    <h1 className="text-muted-foreground text-[13.5px]">Portal do estudante</h1>
                </div>
                <div className="flex flex-col gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="numero">Número de estudante</Label>
                        <Input
                            id="numero"
                            value={data.numero}
                            onChange={(e) => setData('numero', e.target.value)}
                            placeholder="2027/00123"
                            autoComplete="username"
                            autoFocus
                        />
                        <InputError message={errors.numero} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="password">Senha</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                        />
                        <InputError message={errors.password} />
                    </div>
                    <Button type="submit" disabled={processing} className="w-full">
                        Entrar
                    </Button>
                    <p className="text-muted-foreground text-center text-[12.5px]">Não tem senha ou esqueceu-a? Peça-a na secretaria académica.</p>
                </div>
            </form>
        </div>
    );
}
