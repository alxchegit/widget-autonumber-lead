<?php
    namespace App\Model;

    use \Illuminate\Database\Eloquent\Model;

    /**
     * Class Process
     * @package App\Model
     */
    class Process extends Model
    {
        const ID = 'id';
        const ACCOUNT_ID = 'account_id';
        const CONDITIONS = 'conditions';
        const COUNTER = 'counter';

        const TABLE_NAME = 'process';

        public $timestamps = false;
        public $incrementing = true;

        protected $table = self::TABLE_NAME;
        protected $primaryKey = self::ID;

        protected $fillable = [
            self::ID,
            self::ACCOUNT_ID,
            self::CONDITIONS,
            self::COUNTER,
        ];
    }