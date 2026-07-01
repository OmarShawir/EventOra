<?php

namespace App\Controllers;

use App\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UploadController
{
    /**
     * POST /upload
     * Uploads an event cover image. Stores it in the public/uploads directory.
     */
    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        
        // The frontend will send the file under the key 'image'
        $uploadedFile = $uploadedFiles['image'] ?? null;

        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return JsonResponse::error($response, 'No image file uploaded or upload error occurred.', 400);
        }

        // Validate file type
        $contentType = $uploadedFile->getClientMediaType();
        if (!str_starts_with($contentType, 'image/')) {
            return JsonResponse::error($response, 'Invalid file type. Only images are allowed.', 400);
        }

        // Validate size (max 5MB)
        if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
            return JsonResponse::error($response, 'File is too large. Max size is 5MB.', 400);
        }

        // Target directory: backend/public/uploads/
        $targetDir = __DIR__ . '/../../public/uploads';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Generate a safe unique filename
        $filename = uniqid('event_', true);
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '.jpg';
        $fullFilename = $filename . $extension;

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fullFilename;

        try {
            $uploadedFile->moveTo($targetPath);
        } catch (\Throwable $e) {
            return JsonResponse::error($response, 'Failed to save the uploaded image.', 500, ['debug' => $e->getMessage()]);
        }

        // Return the public URL
        $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
        $imageUrl = $appUrl . '/uploads/' . $fullFilename;

        return JsonResponse::send($response, [
            'imageUrl' => $imageUrl
        ], 201);
    }
}
