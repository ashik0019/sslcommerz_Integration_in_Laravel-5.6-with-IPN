<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $dateFormat = false;
    protected  $table = 'orders';
}
