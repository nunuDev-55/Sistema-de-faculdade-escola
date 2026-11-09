<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Registos simples, que se gravam sem regras de negócio: ficam na auditoria sozinhos, só com os campos que mudaram
 * (docs/05-arquitectura.md, regra 5). Os modelos com ciclo de vida são registados pelos seus serviços.
 */
trait RegistaAuditoria
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->nomeAuditoria())
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function nomeAuditoria(): string
    {
        return 'estrutura';
    }
}
