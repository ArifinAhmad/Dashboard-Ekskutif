<?php

namespace App\Services\Simrs;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReadOnlySimrs
{
    public function select(string $sql, array $bindings = []): Collection
    {
        $normalized = ltrim(preg_replace('/\A(?:\s|\/\*.*?\*\/|--[^\r\n]*(?:\r?\n|\z))+/s', '', $sql) ?? $sql);

        if (! preg_match('/\A(?:select|with)\b/i', $normalized) || preg_match('/\b(insert|update|delete|replace|alter|drop|truncate|create|grant|revoke|call|load)\b/i', $normalized)) {
            throw new InvalidArgumentException('Koneksi SIMRS hanya menerima query SELECT atau CTE read-only.');
        }

        return collect(DB::connection(config('simrs.connection'))->select($sql, $bindings));
    }

    public function healthy(): bool
    {
        try {
            return (int) ($this->select('SELECT 1 AS ok')->first()->ok ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
