<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    //
    protected $table = 'snap_category';
    protected $fillable = [
        'cat_name_en',
        'cat_name_ar',
        'cat_description_en',
        'cat_description_ar',
        'cat_image',
        'cat_bg_color',
    ];
}
