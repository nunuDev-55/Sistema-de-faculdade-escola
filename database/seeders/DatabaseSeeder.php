<?php

namespace Database\Seeders;

use App\Support\Permissoes;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Cria as permissões e os perfis de origem. Pode correr várias vezes (ver App\Support\Permissoes).
     * A conta do Administrador cria-se no primeiro acesso ao sistema (ecrã de registo).
     */
    public function run(): void
    {
        Permissoes::sincronizar();
    }
}
