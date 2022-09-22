<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    //
    protected $table = 'snap_customers';
    protected $fillable = [
        'c_name',
        'c_phone_number',
        'c_email',
        'c_full_name',
        'c_password',
        'c_verify_code',
        'c_phone_verified',
        'c_email_verified',
        'c_avatar',
        'c_promoted',
        'c_membership_type'
    ];
}
