<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
    return [

    'user' => [
        'id'    => $this->user->id,
        'name'  => $this->user->name,
        'email' => $this->user->email,
        'role'  => $this->user->role,
    ],

    'phone'         => $this->phone,
    'bio'           => $this->bio,
    'date_of_birth' => $this->date_of_birth,
    'gender'        => $this->gender,
    'country'       => $this->country,
    'city'          => $this->city,

    'avatar'        => $this->avatar_path,

    'github'        => $this->github_url,
    'linkedin'      => $this->linkedin_url,
    'website'       => $this->website_url,
];
    }
}
