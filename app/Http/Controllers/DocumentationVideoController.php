<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentationVideoController extends Controller
{
    private const EXTENSIONS = ['mp4', 'm4v', 'mov', 'webm'];

    public function index()
    {
        $source = $this->sourceLabel();
        $videos = $this->videos();

        return view('documentation.videos', [
            'source' => $source,
            'sourceAvailable' => $this->sourceAvailable(),
            'sourceError' => $this->sourceError(),
            'driver' => $this->driver(),
            'videos' => $videos,
            'selectedVideo' => $videos[0] ?? null,
        ]);
    }

    public function stream(Request $request, string $video): Response
    {
        $item = collect($this->videos())->firstWhere('key', $video);
        abort_if(!$item, 404);

        if ($this->driver() === 's3') {
            return redirect()->away($item['url']);
        }

        return response()
            ->file($item['path'], [
                'Content-Type' => File::mimeType($item['path']) ?: 'video/mp4',
                'Content-Disposition' => 'inline; filename="' . addslashes($item['filename']) . '"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=3600',
            ]);
    }

    public function offline(Request $request, string $video): Response
    {
        $item = collect($this->videos())->firstWhere('key', $video);
        abort_if(!$item, 404);

        if ($this->driver() === 's3') {
            return $this->streamS3File($item);
        }

        return response()
            ->file($item['path'], [
                'Content-Type' => File::mimeType($item['path']) ?: 'video/mp4',
                'Content-Disposition' => 'inline; filename="' . addslashes($item['filename']) . '"',
                'Cache-Control' => 'public, max-age=31536000',
            ]);
    }

    private function videos(): array
    {
        return $this->driver() === 's3'
            ? $this->s3Videos()
            : $this->localVideos();
    }

    private function localVideos(): array
    {
        $directory = $this->documentationPath();

        if (!File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn($file) => in_array(Str::lower($file->getExtension()), self::EXTENSIONS, true))
            ->sortBy(fn($file) => $this->sortKey($file->getFilename()))
            ->values()
            ->map(function ($file, int $index) {
                $filename = $file->getFilename();
                $basename = pathinfo($filename, PATHINFO_FILENAME);
                $key = substr(sha1($filename), 0, 16);
                $description = $this->descriptionFor($file->getPath(), $basename);

                return [
                    'key' => $key,
                    'index' => $index + 1,
                    'filename' => $filename,
                    'title' => $this->titleFor($basename),
                    'description' => $description,
                    'path' => $file->getRealPath(),
                    'url' => route('documentation.videos.stream', $key),
                    'offline_url' => route('documentation.videos.offline', $key),
                    'size' => $this->formatBytes($file->getSize()),
                    'updated_at' => date('d/m/Y H:i', $file->getMTime()),
                ];
            })
            ->all();
    }

    private function s3Videos(): array
    {
        try {
            $disk = Storage::disk($this->diskName());

            return collect($this->s3VideoPaths($disk))
                ->sortBy(fn(string $path) => $this->sortKey(basename($path)))
                ->values()
                ->map(function (string $path, int $index) use ($disk) {
                    $filename = basename($path);
                    $basename = pathinfo($filename, PATHINFO_FILENAME);
                    $key = substr(sha1($path), 0, 16);
                    $description = $this->s3DescriptionFor(dirname($path), $basename);
                    $lastModified = $this->safeLastModified($disk, $path);
                    $size = $this->safeSize($disk, $path);

                    return [
                        'key' => $key,
                        'index' => $index + 1,
                        'filename' => $filename,
                        'title' => $this->titleFor($basename),
                        'description' => $description,
                        'path' => $path,
                        'url' => $this->temporaryUrl($path),
                        'offline_url' => route('documentation.videos.offline', $key),
                        'size' => $this->formatBytes($size),
                        'updated_at' => $lastModified ? date('d/m/Y H:i', $lastModified) : 'N/A',
                    ];
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function sourceAvailable(): bool
    {
        if ($this->driver() === 's3') {
            if ($this->missingS3Config()) {
                return false;
            }

            try {
                Storage::disk($this->diskName())->allFiles($this->prefix());
                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        return File::isDirectory($this->documentationPath());
    }

    private function sourceError(): ?string
    {
        if ($this->driver() !== 's3') {
            return File::isDirectory($this->documentationPath())
                ? null
                : 'Le dossier local configure n existe pas sur ce serveur.';
        }

        if ($missing = $this->missingS3Config()) {
            return 'Configuration S3 incomplete : ' . implode(', ', $missing) . '.';
        }

        try {
            Storage::disk($this->diskName())->allFiles($this->prefix());
            return null;
        } catch (\Throwable $exception) {
            return $this->friendlyS3Error($exception);
        }
    }

    private function missingS3Config(): array
    {
        $diskConfig = config('filesystems.disks.' . $this->diskName(), []);

        return collect([
            'bucket' => $diskConfig['bucket'] ?? null,
            'endpoint' => $diskConfig['endpoint'] ?? null,
            'access key' => $diskConfig['key'] ?? null,
            'secret key' => $diskConfig['secret'] ?? null,
            'region' => $diskConfig['region'] ?? null,
        ])->filter(fn($value) => blank($value))->keys()->all();
    }

    private function driver(): string
    {
        return Str::lower((string) config('alertbook.documentation.driver', 'local')) === 's3'
            ? 's3'
            : 'local';
    }

    private function documentationPath(): string
    {
        return (string) config('alertbook.documentation.local_path');
    }

    private function diskName(): string
    {
        $configuredDisk = (string) config('alertbook.documentation.disk', 's3');

        return array_key_exists($configuredDisk, config('filesystems.disks', []))
            ? $configuredDisk
            : 's3';
    }

    private function prefix(): string
    {
        $prefix = trim((string) config('alertbook.documentation.prefix', 'documentation/videos'), '/');

        if ($prefix === '' || filter_var($prefix, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $prefix;
    }

    private function temporaryUrl(string $path): string
    {
        return Storage::disk($this->diskName())->temporaryUrl(
            $path,
            now()->addSeconds((int) config('alertbook.documentation.temporary_url_ttl', 3600))
        );
    }

    private function sourceLabel(): string
    {
        if ($this->driver() === 's3') {
            $bucket = config('filesystems.disks.' . $this->diskName() . '.bucket') ?: 'bucket non configure';
            $prefix = $this->prefix() ?: 'racine du bucket';

            return $this->diskName() . ':' . $bucket . '/' . $prefix;
        }

        return $this->documentationPath();
    }

    private function friendlyS3Error(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'AccessDenied') || str_contains($message, '403')) {
            return 'Acces refuse par le bucket. Verifiez ACCESS_KEY_ID, SECRET_ACCESS_KEY et que les variables du bucket sont bien liees au service Laravel.';
        }

        if (str_contains($message, 'NoSuchBucket') || str_contains($message, '404')) {
            return 'Bucket introuvable. Verifiez que la variable BUCKET correspond au nom S3 du bucket Railway, pas seulement au nom affiche.';
        }

        if (str_contains($message, 'Could not resolve host') || str_contains($message, 'cURL error 6')) {
            return 'Endpoint S3 introuvable. Verifiez ENDPOINT/AWS_ENDPOINT.';
        }

        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'Erreur SSL avec l endpoint S3. Verifiez le style d URL indique dans les credentials Railway.';
        }

        return 'Erreur S3 : ' . Str::limit($message, 220);
    }

    private function streamS3File(array $item): StreamedResponse
    {
        $disk = Storage::disk($this->diskName());
        $stream = $disk->readStream($item['path']);
        abort_if($stream === false, 404);
        $headers = [
            'Content-Type' => $this->mimeTypeFor($item['filename']),
            'Content-Disposition' => 'inline; filename="' . addslashes($item['filename']) . '"',
            'Cache-Control' => 'public, max-age=31536000',
        ];
        $size = $this->safeSize($disk, $item['path']);

        if ($size > 0) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    private function mimeTypeFor(string $filename): string
    {
        return match (Str::lower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    private function descriptionFor(string $directory, string $basename): ?string
    {
        foreach (['txt', 'md'] as $extension) {
            $path = $directory . DIRECTORY_SEPARATOR . $basename . '.' . $extension;

            if (File::isFile($path)) {
                return trim((string) File::get($path)) ?: null;
            }
        }

        return null;
    }

    private function s3DescriptionFor(string $directory, string $basename): ?string
    {
        $directory = trim($directory, '. /');

        foreach (['txt', 'md'] as $extension) {
            $path = ltrim($directory . '/' . $basename . '.' . $extension, '/');

            try {
                $disk = Storage::disk($this->diskName());

                if ($disk->exists($path)) {
                    return trim((string) $disk->get($path)) ?: null;
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function s3VideoPaths($disk): array
    {
        $prefixes = collect([
            $this->prefix(),
            'documentation/videos',
            '',
        ])->unique()->values();

        foreach ($prefixes as $prefix) {
            try {
                $paths = collect($disk->allFiles($prefix))
                    ->filter(fn(string $path) => in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
                    ->values()
                    ->all();

                if (!empty($paths)) {
                    return $paths;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    private function titleFor(string $basename): string
    {
        $normalized = str_replace(['_', '-'], ' ', $basename);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: $basename;
        $number = null;

        if (preg_match('/^video\s*(\d+)/i', $normalized, $matches)) {
            $number = (int) $matches[1];
            $normalized = trim((string) preg_replace('/^video\s*\d+\s*/i', '', $normalized));
        }

        $title = Str::headline($normalized ?: $basename);

        return $number ? "Video {$number} - {$title}" : $title;
    }

    private function sortKey(string $filename): string
    {
        if (preg_match('/video[_\s-]*(\d+)/i', $filename, $matches)) {
            return str_pad($matches[1], 4, '0', STR_PAD_LEFT) . '_' . $filename;
        }

        return '9999_' . $filename;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1) . ' Go';
        }

        return round($bytes / (1024 * 1024), 1) . ' Mo';
    }

    private function safeSize($disk, string $path): int
    {
        try {
            return (int) $disk->size($path);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeLastModified($disk, string $path): ?int
    {
        try {
            return (int) $disk->lastModified($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
