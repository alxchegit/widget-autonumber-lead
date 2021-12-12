<?php
namespace App\Model;

use \Illuminate\Database\Eloquent\Model;

/**
 * Class Token
 * @package App\Model
 */

/**
 * @property int    id            //ид
 * @property string token         //токен в JSON формате
 */

class Token extends Model
{
    const ID = 'id';
    const TOKEN = 'token';

    const TABLE_NAME = 'tokens';

    public $timestamps = false;
    public $incrementing = true;

    protected $table = self::TABLE_NAME;
    protected $primaryKey = self::ID;

    protected $fillable = [
        self::ID,
        self::TOKEN
    ];
}