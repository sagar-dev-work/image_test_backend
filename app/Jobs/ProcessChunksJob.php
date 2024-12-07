<?php

namespace App\Jobs;

use App\Models\UserImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileName;
    protected $tempDirectory;


    public function __construct($fileName, $tempDirectory)
    {
        $this->fileName = $fileName;
        $this->tempDirectory = $tempDirectory;
    }

    public function handle()
    {
        $finalPath = 'public/images/' . $this->fileName;
        $file = fopen(storage_path('app/' . $finalPath), 'wb');

        // Get all chunks and assemble them
        $chunks = glob($this->tempDirectory . '/' . $this->fileName . '.part*');
        foreach ($chunks as $chunk) {
            fwrite($file, file_get_contents($chunk));
            unlink($chunk); // Delete chunk after appending
        }
        fclose($file);

        // Save the final file path in the database
        $userImage = new UserImage;
        $userImage->image_path = $finalPath;
        $userImage->save();
    }
}