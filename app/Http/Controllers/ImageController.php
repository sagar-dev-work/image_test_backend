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
    /**
     * Upload an image and process it in the background.
     */
    public function uploadImage(Request $request)
    {
        try {
            // Validate the image input
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg',
            ]);

            // Retrieve the authenticated user's ID
            $userId = auth()->id();

            if (!$userId) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Store the uploaded image in public storage
            $filePath = $request->file('image')->store('public/images');

            // Create a database entry for the uploaded image
            $userImage = UserImage::create([
                'user_id' => $userId,
                'image_path' => $filePath,
            ]);

            // Dispatch a job to process the image in the background
            dispatch(new GenerateImageJob($userImage->id, $filePath));

            return response()->json([
                'message' => 'Image uploaded successfully! Image processing is in the background.',
                'image_path' => $filePath,
                'image_id' => $userImage->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload image', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload an s3 bucket image and process it in the background.
     */
    public function uploadImageOnS3(Request $request)
    {
        try {
            // Validate the image input
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg',
            ]);

            // Retrieve the authenticated user's ID
            $userId = auth()->id();

            if (!$userId) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Get the uploaded image file
            $file = $request->file('image');

            // Generate a unique file name
            $fileName = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Upload the file to the 'images' folder in the S3 bucket
            $filePath = 'images/' . $fileName;
            $s3 = \Storage::disk('s3');
            $s3->put($filePath, file_get_contents($file), 'public');

            // Store the file URL
            $fileUrl = $s3->url($filePath);

            // Create a database entry for the uploaded image
            $userImage = UserImage::create([
                'user_id' => $userId,
                'image_path' => $filePath, // Store the S3 path in the database
            ]);

            // Dispatch a job to process the image in the background
            dispatch(new GenerateImageJob($userImage->id, $filePath));

            return response()->json([
                'message' => 'Image uploaded successfully to S3! Image processing is in the background.',
                'image_url' => $fileUrl,
                'image_id' => $userImage->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload image to S3', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Upload a chunk of a file for chunked uploads (not in use currently).
     */
    public function uploadChunk(Request $request)
    {
        try {
            // Validate the chunk input
            $request->validate([
                'chunk' => 'required|file',
                'file_name' => 'required|string',
                'chunk_number' => 'required|integer',
                'total_chunks' => 'required|integer',
            ]);

            // Define the temporary directory for storing file chunks
            $tempDirectory = storage_path('app/temp/uploads');
            if (!file_exists($tempDirectory)) {
                mkdir($tempDirectory, 0755, true);
            }

            // Save the current chunk
            $chunkPath = $tempDirectory . '/' . $request->file_name . '.part' . $request->chunk_number;
            $request->file('chunk')->move($tempDirectory, $chunkPath);

            // Check if all chunks are uploaded
            $uploadedChunks = glob($tempDirectory . '/' . $request->file_name . '.part*');
            if (count($uploadedChunks) === $request->total_chunks) {
                // Dispatch a job to process the chunks
                ProcessChunksJob::dispatch($request->file_name, $tempDirectory);
            }

            return response()->json(['message' => 'Chunk uploaded successfully!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload chunk', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve all images of the authenticated user or all images if the user is an admin.
     */
    public function getUserImages(Request $request)
    {
        try {
            $user = Auth::user();
            $userId = $user->id;

            if (!$userId) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Admin retrieves all images; others retrieve only their own images
            $images = $user->role === 'admin' ? UserImage::all() : UserImage::where('user_id', $userId)->get();

            // Generate URLs for the images
            $images = $images->map(function ($image) {
                $image->url = asset('storage/' . str_replace('public/', '', $image->image_path));
                return $image;
            });

            return response()->json($images);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve images', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the status of an image processing task by ID.
     */
    public function getImageStatus($imageId)
    {
        try {
            // Find the image by ID or fail
            $userImage = UserImage::findOrFail($imageId);
            $url = url('storage/' . str_replace('public/', '', $userImage->image_path));

            // Check if the image has been processed
            if ($userImage->image_path !== null) {
                return response()->json([
                    'status' => 'completed',
                    'generated_images' => $url,
                ]);
            }

            // Handle cases where the image is still processing
            if ($userImage->image_path === null) {
                $lastUpdated = $userImage->updated_at;
                $timeElapsed = now()->diffInMinutes($lastUpdated);

                // Mark as completed if processing exceeds the time limit
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
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve image status', 'error' => $e->getMessage()], 500);
        }
    }
}
