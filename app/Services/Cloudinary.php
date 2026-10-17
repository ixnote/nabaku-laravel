<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

final class Cloudinary
{
    public function upload(UploadedFile $file, string $owner): array
    {
        $cfg = $this->config();
        $params = $this->sign(['folder' => 'nabaku/'.$owner, 'timestamp' => time()]);
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            $res = Http::connectTimeout(5)->timeout(60)->attach('file', $stream, $file->getClientOriginalName())->post('https://api.cloudinary.com/v1_1/'.rawurlencode($cfg['cloud']).'/auto/upload', [...$params, 'api_key' => $cfg['key']]);
        } catch (ConnectionException $e) {
            abort(502, 'Media storage unavailable');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        abort_unless($res->successful() && $res->json('secure_url'), 502, 'Media upload failed');

        return ['url' => $res->json('secure_url'), 'type' => $res->json('resource_type')];
    }

    /**
     * Signs a direct browser-to-Cloudinary upload. Only the folder and timestamp
     * are signed, so the client can't choose any other upload options.
     */
    public function uploadSignature(string $owner): array
    {
        $cfg = $this->config();

        return [...$this->sign(['folder' => 'nabaku/'.$owner, 'timestamp' => time()]), 'cloudName' => $cfg['cloud'], 'apiKey' => $cfg['key']];
    }

    private function config(): array
    {
        $cfg = config('services.cloudinary');
        abort_unless($cfg['cloud'] && $cfg['key'] && $cfg['secret'], 503, 'Media storage is not configured');

        return $cfg;
    }

    // Cloudinary signs with SHA-1 unless the account is set up for SHA-256
    private function sign(array $params): array
    {
        $cfg = $this->config();
        ksort($params);
        $params['signature'] = hash($cfg['algorithm'] ?? 'sha1', urldecode(http_build_query($params)).$cfg['secret']);

        return $params;
    }
}
