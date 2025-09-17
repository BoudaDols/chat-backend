<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|in:image,document,audio'
        ]);

        $file = $request->file('file');
        $type = $request->type;
        
        // Validate file types
        $allowedTypes = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'document' => ['pdf', 'doc', 'docx', 'txt', 'xlsx'],
            'audio' => ['mp3', 'wav', 'ogg', 'm4a']
        ];

        $extension = $file->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowedTypes[$type])) {
            return response()->json(['error' => 'Invalid file type'], 422);
        }

        // Generate unique filename
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs("media/{$type}s", $filename, 'public');

        return response()->json([
            'url' => Storage::url($path),
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'type' => $type
        ]);
    }

    public function serve($type, $filename)
    {
        $path = "media/{$type}s/{$filename}";
        
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}