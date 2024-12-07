<?php

namespace App\Jobs;

use App\Models\UserImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $imageId;
    protected $filePath;

    public function __construct($imageId, $filePath)
    {
        $this->imageId = $imageId;
        $this->filePath = $filePath;
    }

    // public function handle()
    // {
    //     $userImage = UserImage::find($this->imageId);

    //     if (!$userImage) {
    //         Log::error('UserImage not found for imageId: ' . $this->imageId);
    //         return;
    //     }

    //     if (env('API_CALL_ENABLE') == 1) {
    //         $generatedImages = $this->generateImageVariations($this->filePath);
    //     }

    //     foreach ($generatedImages as $generatedImage) {
    //         $generatedImagePath = 'public/images/generated_' . uniqid() . '.png';
    //         Storage::put($generatedImagePath, file_get_contents($generatedImage['url']));

    //         $userImage->generated_images()->create([
    //             'url' => Storage::url($generatedImagePath),
    //         ]);
    //     }
    // }

    public function handle()
    {
        $userImage = UserImage::find($this->imageId);

        if (!$userImage) {
            Log::error('UserImage not found for imageId: ' . $this->imageId);
            return;
        }

        if (env('API_CALL_ENABLE') == 1) {
            $generatedImages = $this->generateImageVariations($this->filePath);
        }

        foreach ($generatedImages as $generatedImage) {
            $generatedImagePath = 'public/images/generated_' . uniqid() . '.png';
            Storage::put($generatedImagePath, file_get_contents($generatedImage['url']));

            $userImage->generated_images()->create([
                'url' => Storage::url($generatedImagePath),
            ]);
        }
    }


    protected function generateImageVariations($filePath)
    {
        // return $this->getFallbackImages();

        $apiUrl = 'https://api.openai.com/v1/images/generations';
        $apiKey = env('OPENAI_API_KEY');

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'dall-e-3',
                'n' => 1,
                'size' => '1024x1024',
                'image' => new \CURLFile(Storage::path($filePath)),
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($curl);
        Log::info('cURL Response:', ['response' => $response]);

        if (curl_errno($curl)) {
            Log::error('cURL error: ' . curl_error($curl));
            curl_close($curl);
            return $this->getFallbackImages();
        }

        curl_close($curl);
        $data = json_decode($response, true);
        Log::info('Decoded Response Data:', ['data' => $data]);

        if (empty($data['data'])) {
            return $this->getFallbackImages();
        }

        return $data['data'] ?? [];
    }

    protected function getFallbackImages()
    {
        Log::info('Fetching fallback images...');

        $fallbackApiUrl = 'https://jsonplaceholder.typicode.com/albums/1/photos';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $fallbackApiUrl,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            Log::error('Fallback API cURL error: ' . curl_error($curl));
            curl_close($curl);
            return [];
        }

        curl_close($curl);
        $data = json_decode($response, true);

        Log::info('Fallback API Response Data:', ['data' => $data]);

        // Map the fallback API response to match the expected output format
        $mappedData = array_map(function ($item) {
            return [
                'url' => $item['url'],
                'thumbnailUrl' => $item['thumbnailUrl'],
                'title' => $item['title'],
            ];
        }, $data);

        return $mappedData;
    }
}
