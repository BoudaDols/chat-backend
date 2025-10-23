<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240|mimes:jpeg,jpg,png,gif,webp,pdf,doc,docx,txt,xlsx,mp3,wav,ogg,m4a',
            'type' => 'required|in:image,document,audio'
        ]);

        $file = $request->file('file');
        $type = $request->type;
        
        // Validate MIME types
        $allowedMimes = [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4']
        ];

        if (!in_array($file->getMimeType(), $allowedMimes[$type])) {
            return response()->json(['error' => 'Invalid file type'], 422);
        }

        // Validate file extensions
        $allowedExtensions = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'document' => ['pdf', 'doc', 'docx', 'txt', 'xlsx'],
            'audio' => ['mp3', 'wav', 'ogg', 'm4a']
        ];

        $extension = $file->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowedExtensions[$type])) {
            return response()->json(['error' => 'Invalid file extension'], 422);
        }

        // Generate unique filename
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs("media/{$type}s", $filename, 'public');

        return response()->json([
            'url' => Storage::url($path),
            // amazonq-ignore-next-line
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'type' => $type
        ]);
    }

    public function serve($type, $filename)
    {
        // Validate type parameter
        if (!in_array($type, ['image', 'document', 'audio'])) {
            abort(404);
        }
        
        // Validate filename to prevent path traversal
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename) || str_contains($filename, '..')) {
            abort(404);
        }
        
        $path = "media/{$type}s/{$filename}";
        
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}