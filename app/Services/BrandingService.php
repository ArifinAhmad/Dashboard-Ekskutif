<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BrandingService
{
    private const CACHE_KEY = 'branding.logo.metadata';

    public function logoPath(): ?string
    {
        return $this->metadata()['path'];
    }

    public function logoUrl(): ?string
    {
        $metadata = $this->metadata();

        return $metadata['path'] ? route('branding.logo', ['v' => $metadata['version']]) : null;
    }

    public function logoDataUri(): ?string
    {
        $path = $this->exportLogoPath();
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path));
    }

    public function exportLogoPath(): ?string
    {
        return $this->metadata()['export_path'] ?: $this->logoPath();
    }

    public function hospitalProfile(): array
    {
        return $this->metadata()['hospital'];
    }

    public function updateHospitalProfile(array $profile): void
    {
        foreach ($profile as $key => $value) {
            AppSetting::query()->updateOrCreate(['key' => 'hospital_'.$key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public function replaceLogo(UploadedFile $logo): void
    {
        $oldPath = $this->logoPath();
        $oldExportPath = $this->exportLogoPath();
        $token = bin2hex(random_bytes(8));
        $path = 'branding/application-logo-'.$token.'.png';
        $exportPath = 'branding/application-logo-export-'.$token.'.png';
        $source = imagecreatefromstring((string) file_get_contents($logo->getRealPath()));
        abort_unless($source !== false, 422, 'Logo tidak dapat diproses.');
        Storage::disk('local')->put($path, $this->resizePng($source, 320));
        Storage::disk('local')->put($exportPath, $this->resizePng($source, 180));
        imagedestroy($source);

        AppSetting::query()->updateOrCreate(['key' => 'application_logo'], ['value' => $path]);
        AppSetting::query()->updateOrCreate(['key' => 'application_logo_export'], ['value' => $exportPath]);
        Cache::forget(self::CACHE_KEY);

        collect([$oldPath, $oldExportPath])->filter()->unique()->reject(fn ($old) => in_array($old, [$path, $exportPath], true))->each(fn ($old) => Storage::disk('local')->delete($old));
    }

    private function metadata(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $settings = AppSetting::query()->whereIn('key', [
                'application_logo', 'application_logo_export', 'hospital_name', 'hospital_address',
                'hospital_phone', 'hospital_email', 'hospital_website',
            ])->get()->keyBy('key');
            $logo = $settings->get('application_logo');

            return [
                'path' => $logo?->value,
                'export_path' => $settings->get('application_logo_export')?->value,
                'version' => $logo?->updated_at?->getTimestamp() ?? 0,
                'hospital' => [
                    'name' => $settings->get('hospital_name')?->value ?: config('app.name'),
                    'address' => $settings->get('hospital_address')?->value,
                    'phone' => $settings->get('hospital_phone')?->value,
                    'email' => $settings->get('hospital_email')?->value,
                    'website' => $settings->get('hospital_website')?->value,
                ],
            ];
        });
    }

    private function resizePng(\GdImage $source, int $maximum): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maximum / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        imagepng($target, null, 8);
        $contents = (string) ob_get_clean();
        imagedestroy($target);

        return $contents;
    }
}
