<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/operational_mode.php';

if (!function_exists('compliance_statuses')) {
    function compliance_statuses(): array
    {
        return ['autorizado', 'pendiente', 'bloqueado', 'documento_vencido', 'fuera_de_horario'];
    }
}

if (!function_exists('compliance_labels')) {
    function compliance_labels(): array
    {
        return [
            'autorizado' => 'Autorizado',
            'pendiente' => 'Pendiente',
            'bloqueado' => 'Bloqueado',
            'documento_vencido' => 'Documento vencido',
            'fuera_de_horario' => 'Fuera de horario',
        ];
    }
}

if (!function_exists('compliance_normalize_status')) {
    function compliance_normalize_status(?string $status): string
    {
        $status = clean_str((string)($status ?? ''));
        return in_array($status, compliance_statuses(), true) ? $status : 'autorizado';
    }
}

if (!function_exists('compliance_label')) {
    function compliance_label(?string $status): string
    {
        $status = compliance_normalize_status($status);
        $labels = compliance_labels();
        return $labels[$status] ?? $status;
    }
}

if (!function_exists('compliance_severity')) {
    function compliance_severity(?string $status): int
    {
        return match (compliance_normalize_status($status)) {
            'bloqueado' => 50,
            'documento_vencido' => 40,
            'fuera_de_horario' => 30,
            'pendiente' => 20,
            default => 0,
        };
    }
}

if (!function_exists('compliance_is_blocking')) {
    function compliance_is_blocking(?string $status): bool
    {
        return compliance_normalize_status($status) === 'bloqueado';
    }
}

if (!function_exists('compliance_requires_confirmation')) {
    function compliance_requires_confirmation(?string $status): bool
    {
        $status = compliance_normalize_status($status);
        return $status !== 'autorizado' && !compliance_is_blocking($status);
    }
}

if (!function_exists('compliance_pick_worst')) {
    function compliance_pick_worst(array $candidates): array
    {
        $winner = [
            'status' => 'autorizado',
            'motivo' => '',
            'source' => 'persona',
            'provider' => null,
        ];
        $winnerSeverity = -1;

        foreach ($candidates as $candidate) {
            $status = compliance_normalize_status((string)($candidate['status'] ?? 'autorizado'));
            $severity = compliance_severity($status);
            if ($severity > $winnerSeverity) {
                $winnerSeverity = $severity;
                $winner = [
                    'status' => $status,
                    'motivo' => trim((string)($candidate['motivo'] ?? '')),
                    'source' => (string)($candidate['source'] ?? 'persona'),
                    'provider' => $candidate['provider'] ?? null,
                ];
            }
        }

        return $winner;
    }
}

if (!function_exists('compliance_provider_rows_for_person')) {
    function compliance_provider_rows_for_person(PDO $pdo, int $residencialId, int $personId): array
    {
        if (!operational_table_exists($pdo, 'proveedores') || !operational_table_exists($pdo, 'proveedor_personas')) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.nombre_comercial,
                p.estatus_cumplimiento,
                p.motivo_bloqueo,
                p.estatus,
                pp.rol
            FROM proveedor_personas pp
            JOIN proveedores p ON p.id = pp.proveedor_id
            WHERE pp.persona_recurrente_id = :person_id
              AND pp.activo = 1
              AND p.residencial_id = :rid
              AND COALESCE(p.activo, 1) = 1
            ORDER BY p.nombre_comercial ASC
        ");
        $stmt->execute(['person_id' => $personId, 'rid' => $residencialId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('compliance_effective_for_person')) {
    function compliance_effective_for_person(PDO $pdo, int $residencialId, int $personId, ?array $person = null): array
    {
        if ($person === null) {
            $stmt = $pdo->prepare("
                SELECT id, estatus_cumplimiento, motivo_bloqueo
                FROM personas_recurrentes
                WHERE id = :id
                  AND residencial_id = :rid
                LIMIT 1
            ");
            $stmt->execute(['id' => $personId, 'rid' => $residencialId]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $personStatus = compliance_normalize_status((string)($person['estatus_cumplimiento'] ?? 'autorizado'));
        $personMotivo = trim((string)($person['motivo_bloqueo'] ?? ''));
        $candidates = [[
            'status' => $personStatus,
            'motivo' => $personMotivo,
            'source' => 'persona',
            'provider' => null,
        ]];

        $providers = compliance_provider_rows_for_person($pdo, $residencialId, $personId);
        foreach ($providers as $provider) {
            $providerPayload = [
                'id' => (int)$provider['id'],
                'nombre' => (string)$provider['nombre_comercial'],
                'estatus_cumplimiento' => compliance_normalize_status((string)($provider['estatus_cumplimiento'] ?? 'autorizado')),
                'motivo_bloqueo' => (string)($provider['motivo_bloqueo'] ?? ''),
                'rol' => (string)($provider['rol'] ?? ''),
            ];
            $candidates[] = [
                'status' => $providerPayload['estatus_cumplimiento'],
                'motivo' => $providerPayload['motivo_bloqueo'],
                'source' => 'proveedor',
                'provider' => $providerPayload,
            ];
        }

        $winner = compliance_pick_worst($candidates);
        $status = compliance_normalize_status($winner['status']);
        $provider = is_array($winner['provider']) ? $winner['provider'] : ($providers[0] ?? null);
        $providerId = $provider ? (int)($provider['id'] ?? 0) : null;
        $providerName = $provider ? (string)($provider['nombre'] ?? $provider['nombre_comercial'] ?? '') : '';
        $motivo = trim((string)$winner['motivo']);

        if ($motivo === '' && $status !== 'autorizado') {
            $motivo = $winner['source'] === 'proveedor' && $providerName !== ''
                ? sprintf('Proveedor %s con estado %s.', $providerName, compliance_label($status))
                : sprintf('Persona con estado %s.', compliance_label($status));
        }

        return [
            'cumplimiento_estado' => $status,
            'cumplimiento_label' => compliance_label($status),
            'cumplimiento_motivo' => $motivo,
            'cumplimiento_origen' => (string)$winner['source'],
            'proveedor_id' => $providerId,
            'proveedor_nombre' => $providerName,
            'proveedores' => array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'nombre' => (string)$row['nombre_comercial'],
                'estatus_cumplimiento' => compliance_normalize_status((string)($row['estatus_cumplimiento'] ?? 'autorizado')),
                'cumplimiento_label' => compliance_label((string)($row['estatus_cumplimiento'] ?? 'autorizado')),
                'motivo_bloqueo' => (string)($row['motivo_bloqueo'] ?? ''),
                'rol' => (string)($row['rol'] ?? ''),
            ], $providers),
            'puede_ingresar' => !compliance_is_blocking($status),
            'requiere_confirmacion' => compliance_requires_confirmation($status),
        ];
    }
}
