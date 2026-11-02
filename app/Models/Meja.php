<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meja extends Model
{
    protected $table = 'tbl_meja';

    protected $fillable = ['kode', 'nama', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function orders()
    {
        return $this->hasMany(Order::class, 'meja_id');
    }
}
