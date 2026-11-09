<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
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
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role,

                // كود ربط ولي الأمر — يظهر لصاحب البروفايل فقط لو طالب
                'guardian_code' => $this->user->role === 'student'
                    ? $this->user->guardian_code
                    : null,
            ],

            'profile' => [
                'phone' => $this->phone,
                'bio' => $this->bio,
                'date_of_birth' => $this->date_of_birth,
                'gender' => $this->gender,
                'country' => $this->country,
                'city' => $this->city,

                'avatar' => $this->avatar_path
                    ? asset('storage/'.$this->avatar_path)
                    : asset('images/default-avatar.jpg'),

                'linkedin' => $this->linkedin_url,
                'github' => $this->github_url,
                'website' => $this->website_url,
            ],
        ];
    }
}
