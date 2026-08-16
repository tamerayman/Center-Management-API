<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\LessonResource;
use Illuminate\Support\Facades\Storage;

class LessonMaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [

    'id' => $this->id,

    'title' => $this->title,

    'type' => $this->type,

    'url' => $this->external_url
    ? $this->external_url
    : Storage::url($this->path),

    'size' => $this->file_size
        ? number_format($this->file_size / 1024 / 1024, 2) . ' MB'
        : null,

    'is_external' => !is_null($this->external_url),


//   'lesson' => $this->whenLoaded('lesson', fn() => new LessonResource($this->lesson)),

       ];
    }


    
}

