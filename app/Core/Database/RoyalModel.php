<?php

namespace App\Core\Database;

use Illuminate\Database\Eloquent\Model;

abstract class RoyalModel extends Model
{
    protected $guarded = ['*'];

    public $timestamps = false;

    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }
}