<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
        protected $fillable = ['name', 'email', 'is_active', 'image'];

}
