<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    use HasFactory;

    /** الباسورد لا يظهر بأي تحويل لـ array/JSON. */
    protected $hidden = ['password'];
}