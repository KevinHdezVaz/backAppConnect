<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\Request;
 use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
   public function index()
   {
       $stories = Story::where('is_active', true)
           ->where('expires_at', '>', now())
           ->with('administrator')
           ->orderBy('created_at', 'desc')
           ->get();

       return response()->json([
           'status' => 'success',
           'data' => $stories
       ]);
   }

   public function store(Request $request)
   {
       $request->validate([
           'title' => 'required|string|max:255',
           'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
           'video' => 'nullable|mimetypes:video/mp4|max:10240'
       ]);

       $imageUrl = $request->file('image')->store('public/stories');
       $videoUrl = null;

       if ($request->hasFile('video')) {
           $videoUrl = $request->file('video')->store('public/stories/videos');
       }

       $story = Story::create([
           'title' => $request->title,
           'image_url' => Storage::url($imageUrl),
           'video_url' => $videoUrl ? Storage::url($videoUrl) : null,
           'administrator_id' => auth()->guard('administrator')->id(),
           'expires_at' => now()->addHours(24)
       ]);

       return response()->json([
           'status' => 'success',
           'data' => $story
       ], 201);
   }

   public function update(Request $request, Story $story)
   {
       if (auth()->guard('administrator')->id() !== $story->administrator_id) {
           return response()->json([
               'status' => 'error',
               'message' => 'Unauthorized'
           ], 403);
       }

       $request->validate([
           'title' => 'string|max:255',
           'is_active' => 'boolean'
       ]);

       $story->update($request->only(['title', 'is_active']));

       return response()->json([
           'status' => 'success',
           'data' => $story
       ]);
   }

   public function destroy(Story $story)
   {
       if (auth()->guard('administrator')->id() !== $story->administrator_id) {
           return response()->json([
               'status' => 'error',
               'message' => 'Unauthorized'
           ], 403);
       }

       Storage::delete(str_replace('/storage/', 'public/', $story->image_url));
       if ($story->video_url) {
           Storage::delete(str_replace('/storage/', 'public/', $story->video_url));
       }

       $story->delete();

       return response()->json([
           'status' => 'success',
           'message' => 'Story deleted successfully'
       ]);
   }
}