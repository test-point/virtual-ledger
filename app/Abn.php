<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Abn extends Model
{
    protected $fillable = ['abn'];

    public $table = 'abn';
}
