<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateImageJob;
use App\Jobs\ProcessChunksJob;
use App\Models\UserImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg',
        ]);

        $userId = auth()->id();

        if (!$userId) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $filePath = $request->file('image')->store('public/images');
        $userImage = UserImage::create([
            'user_id' => $userId,
            'image_path' => $filePath,
        ]);

        // Dispatch job for background processing
        dispatch(new GenerateImageJob($userImage->id, $filePath));
        return response()->json([
            'message' => 'Image uploaded successfully! Image processing is in the background.',
            'image_path' => $filePath,
            'image_id' => $userImage->id,
        ], 201);
    }

    public function uploadChunk(Request $request)
    {
        $request->validate([
            'chunk' => 'required|file',
            'file_name' => 'required|string',
            'chunk_number' => 'required|integer',
            'total_chunks' => 'required|integer',
        ]);

        $tempDirectory = storage_path('app/temp/uploads');
        if (!file_exists($tempDirectory)) {
            mkdir($tempDirectory, 0755, true);
        }

        // Save the current chunk
        $chunkPath = $tempDirectory . '/' . $request->file_name . '.part' . $request->chunk_number;
        $request->file('chunk')->move($tempDirectory, $chunkPath);

        // If all chunks are uploaded, dispatch a job to process them
        $uploadedChunks = glob($tempDirectory . '/' . $request->file_name . '.part*');
        if (count($uploadedChunks) === $request->total_chunks) {
            ProcessChunksJob::dispatch($request->file_name, $tempDirectory);
        }

        return response()->json([
            'message' => 'Chunk uploaded successfully!',
        ]);
    }

    public function getUserImages(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        if (!$userId) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        if ($user->role === 'admin') {
            $images = UserImage::all();
        } else {
            $images = UserImage::where('user_id', $userId)->get();
        }

        $images = $images->map(function ($image) {
            $image->url = asset('storage/' . str_replace('public/', '', $image->image_path));
            return $image;
        });

        return response()->json($images);
    }



    public function getImageStatus($imageId)
    {
        $userImage = UserImage::findOrFail($imageId);
        $url = url('storage/' . str_replace('public/', '', $userImage->image_path));

        if ($userImage->image_path !== null) {
            return response()->json([
                'status' => 'completed',
                'generated_images' => $url
            ]);
        }

        if ($userImage->image_path === null) {
            $lastUpdated = $userImage->updated_at;
            $timeElapsed = now()->diffInMinutes($lastUpdated);

            if ($timeElapsed > 10) {
                $userImage->processing_status = 'completed';
                $userImage->save();
            }
        }
        $status = $userImage->image_path === null ? 'processing' : 'completed';

        return response()->json([
            'status' => $status,
            'generated_images' => $url,
        ]);
    }
}
