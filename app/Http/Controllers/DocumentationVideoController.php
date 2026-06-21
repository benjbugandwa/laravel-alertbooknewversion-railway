<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentationVideoController extends Controller
{
    private const EXTENSIONS = ['mp4', 'm4v', 'mov', 'webm'];

    public function index()
    {
        $directory = $this->documentationPath();
        $videos = $this->videos();

        return view('documentation.videos', [
            'directory' => $directory,
            'directoryExists' => File::isDirectory($directory),
            'videos' => $videos,
            'selectedVideo' => $videos[0] ?? null,
        ]);
    }

    public function stream(Request $request, string $video): BinaryFileResponse
    {
        $item = collect($this->videos())->firstWhere('key', $video);
        abort_if(!$item, 404);

        return response()
            ->file($item['path'], [
                'Content-Type' => File::mimeType($item['path']) ?: 'video/mp4',
                'Content-Disposition' => 'inline; filename="' . addslashes($item['filename']) . '"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=3600',
            ]);
    }

    private function videos(): array
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
                    'size' => $this->formatBytes($file->getSize()),
                    'updated_at' => date('d/m/Y H:i', $file->getMTime()),
                ];
            })
            ->all();
    }

    private function documentationPath(): string
    {
        return (string) config('alertbook.documentation_path');
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
}
