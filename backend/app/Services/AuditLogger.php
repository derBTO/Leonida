<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/** Schreibt nachvollziehbar mit, wer was wann geändert hat. */
class AuditLogger
{
    public static function log(string $action, string $entity, int|string|null $entityId, ?string $summary = null, ?array $changes = null): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'summary' => $summary === null ? null : mb_substr($summary, 0, 255),
            'changes' => $changes,
        ]);
    }

    /** Unterschiede zweier Attribut-Arrays, nur geänderte Schlüssel. */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;
            if ($old != $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }

        return $changes;
    }
}
