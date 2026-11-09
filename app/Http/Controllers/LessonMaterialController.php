<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonMaterialRequest;
use App\Http\Resources\LessonMaterialResource;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Notification;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class LessonMaterialController extends Controller
{
    public function store(StoreLessonMaterialRequest $request, Lesson $lesson)
    {
        $this->authorize('create', [LessonMaterial::class, $lesson]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('lesson-materials', 'public');

            $material = LessonMaterial::create([
                'lesson_id' => $lesson->id,
                'title' => $request->title,
                'type' => $this->detectType($file),
                'path' => $path,
                'external_url' => null,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        } else {
            $material = LessonMaterial::create([
                'lesson_id' => $lesson->id,
                'title' => $request->title,
                'type' => $this->detectLinkType($request->external_url),
                'path' => null,
                'external_url' => $request->external_url,
                'mime_type' => null,
                'file_size' => null,
            ]);
        }

        $this->notifyCourseStudents($lesson, $material);

        return new LessonMaterialResource($material);
    }

    private function detectType($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'pdf', 'doc', 'docx' => 'document',
            'ppt', 'pptx' => 'presentation',
            'zip' => 'archive',
            'jpg', 'jpeg', 'png' => 'image',
            default => 'document',
        };
    }

    private function detectLinkType(string $url): string
    {
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return 'youtube';
        }

        return 'link';
    }

    public function index(Request $request, Lesson $lesson)
    {
        $this->authorize('viewAny', [LessonMaterial::class, $lesson]);

        $materials = $lesson->materials()->latest()->paginate($this->perPage($request));

        return LessonMaterialResource::collection($materials);
    }

    public function update(StoreLessonMaterialRequest $request, LessonMaterial $material)
    {
        $this->authorize('update', $material);

        if ($request->hasFile('file')) {
            if ($material->path) {
                Storage::disk('public')->delete($material->path);
            }

            $file = $request->file('file');
            $path = $file->store('lesson-materials', 'public');

            $material->update([
                'title' => $request->title ?? $material->title,
                'type' => $this->detectType($file),
                'path' => $path,
                'external_url' => null,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        } elseif ($request->filled('external_url')) {
            if ($material->path) {
                Storage::disk('public')->delete($material->path);
            }

            $material->update([
                'title' => $request->title ?? $material->title,
                'type' => $this->detectLinkType($request->external_url),
                'path' => null,
                'external_url' => $request->external_url,
                'mime_type' => null,
                'file_size' => null,
            ]);
        } else {
            $material->update([
                'title' => $request->title ?? $material->title,
            ]);
        }

        return new LessonMaterialResource($material);
    }

    public function destroy(LessonMaterial $material)
    {
        $this->authorize('delete', $material);

        if ($material->path) {
            Storage::disk('public')->delete($material->path);
        }

        $material->delete();

        return response()->json([
            'message' => 'Material deleted successfully.',
        ]);
    }

    public function download(LessonMaterial $material)
    {
        $this->authorize('view', $material);

        if ($material->external_url) {
            return response()->json([
                'url' => $material->external_url,
            ]);
        }

        if (! $material->path || ! Storage::disk('public')->exists($material->path)) {
            return response()->json([
                'message' => 'Material file not found.',
            ], 404);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->download(
            $material->path,
            $material->title.'.'.pathinfo($material->path, PATHINFO_EXTENSION)
        );
    }

    private function notifyCourseStudents(Lesson $lesson, LessonMaterial $material): void
    {
        $now = now();

        $rows = $lesson->course->students()
            ->pluck('users.id')
            ->map(fn ($id) => [
                'user_id' => $id,
                'title' => 'New Lesson Material',
                'message' => "A new material '{$material->title}' has been added to lesson '{$lesson->title}'.",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if ($rows->isNotEmpty()) {
            Notification::insert($rows->all());
        }
    }
}
