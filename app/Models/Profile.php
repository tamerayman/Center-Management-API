<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Profile extends Model
{
    
  use HasFactory;
protected $fillable = [
   'user_id',
    'phone',
    'bio',
    'date_of_birth',
    'gender',
    'country',
    'city',
    'avatar_path',
    'github_url',
    'linkedin_url',
    'website_url',
];



public function user()
{
    return $this->belongsTo(User::class);
}
}
