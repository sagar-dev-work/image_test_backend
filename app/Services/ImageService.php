<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageService
{
    public function generate($imagePath)
    {
        $image = Storage::get($imagePath);
        Storage::put('processed_images/processed_image.jpg', $image);
    }
}
