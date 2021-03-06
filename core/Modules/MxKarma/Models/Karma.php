<?php

namespace EvoSC\Modules\MxKarma\Models;

use EvoSC\Models\Player;
use Illuminate\Database\Eloquent\Model;

class Karma extends Model
{
    protected $table = 'mx-karma';

    protected $fillable = ['Player', 'Map', 'Rating'];

    public $timestamps = false;

    public function player(){
        return $this->hasOne(Player::class, 'id', 'Player');
    }
}