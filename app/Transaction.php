<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = ['id'];

    protected $dates = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    /**
     * Get created_at date mutator
     *
     * @param $value
     * @return static
     */
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone('Australia/Sydney');
    }
}
