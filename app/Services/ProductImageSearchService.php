<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImageSearchService
{
    public function search(string $query, int $limit = 12): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $apiKey = trim((string) env('SERPAPI_KEY', ''));

        if ($apiKey === '') {
            throw new RuntimeException('Falta configurar SERPAPI_KEY en el archivo .env.');
        }

        $response = Http::timeout(35)
            ->retry(1, 500)
            ->get('https://serpapi.com/search.json', [
                'engine' => 'google_images',
                'q' => $query,
                'api_key' => $apiKey,
                'ijn' => 0,
                'gl' => 'mx',
                'hl' => 'es',
                'safe' => 'active',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo consultar SerpApi. HTTP ' . $response->status());
        }

        $items = $response->json('images_results', []);

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function (array $item, int $index): ?array {
                $url = $item['original'] ?? $item['thumbnail'] ?? null;

                if (! $url) {
                    return null;
                }

                return [
                    'position' => $index + 1,
                    'title' => trim((string) ($item['title'] ?? 'Imagen sugerida')),
                    'url' => $url,
                    'thumbnail' => $item['thumbnail'] ?? $url,
                    'source' => $item['source'] ?? null,
                    'link' => $item['link'] ?? null,
                ];
            })
            ->filter()
            ->unique('url')
            ->take(max(1, min($limit, 24)))
            ->values()
            ->all();
    }

    public function downloadToProductImage(string $url, int $productId): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException('No se recibió URL de imagen.');
        }

        $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 BexiaERP Image Importer',
                'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            ])
            ->timeout(45)
            ->retry(1, 500)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo descargar la imagen. HTTP ' . $response->status());
        }

        $body = $response->body();

        if (! $body || strlen($body) < 500) {
            throw new RuntimeException('La respuesta no parece ser una imagen válida.');
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));

        $extension = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
            default => $this->extensionFromUrl($url),
        };

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $filename = Str::slug('producto-' . $productId . '-' . now()->format('Ymd-His')) . '.' . $extension;
        $path = 'products/internet/' . $productId . '/' . $filename;

        Storage::disk('public')->put($path, $body);

        return $path;
    }

    protected function extensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension ?: 'jpg';
    }
}
