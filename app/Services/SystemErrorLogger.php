<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SystemErrorLogger
{
    public function record(Throwable $exception, Request $request): void
    {
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        if ($status < 500) {
            return;
        }

        try {
            $message = $exception instanceof QueryException
                ? 'Operasi database gagal. Periksa log server untuk detail teknis.'
                : $this->sanitize($exception->getMessage());
            AuditLog::query()->create([
                'user_id' => $request->user()?->id,
                'event' => 'system.error',
                'module' => 'system',
                'context' => [
                    'error_class' => $exception::class,
                    'message' => $message ?: 'Terjadi error tanpa pesan.',
                    'fingerprint' => substr(hash('sha256', $exception::class.'|'.$exception->getFile().'|'.$exception->getLine()), 0, 16),
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'status' => $status,
                    'file' => basename($exception->getFile()),
                    'line' => $exception->getLine(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'successful' => false,
            ]);
        } catch (Throwable) {
            // Error reporting must never replace the original exception.
        }
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/\b[\w.%+-]+@[\w.-]+\.[A-Z]{2,}\b/i', '[email]', $message);
        $message = preg_replace('/(?i)(password|token|secret|authorization)\s*[=:]\s*[^\s,;]+/', '$1=[redacted]', (string) $message);

        return mb_substr(trim((string) $message), 0, 500);
    }
}
