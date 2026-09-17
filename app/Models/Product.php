<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
        protected $fillable = ['name', 'image', 'shop_id', 'price', 'stock'];


    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Shop::class);
    }
}
