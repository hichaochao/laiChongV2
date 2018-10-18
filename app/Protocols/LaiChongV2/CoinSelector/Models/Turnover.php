<?php
namespace Wormhole\Protocols\QianNiu\Models;

use Illuminate\Database\Eloquent\Model;
use Gbuckingham89\EloquentUuid\Traits\UuidForKey;
use Illuminate\Database\Eloquent\SoftDeletes;
class Turnover extends Model
{
    use UuidForKey;
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quchong_turnover';
    /**
     * Indicates if the model should be timestamped.
     *  created_at and updated_at
     * @var bool
     */
    public $timestamps = TRUE;


    protected $primaryKey='id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id','code','coin_number','card_free','card_time','charged_power',
        'stat_date','charged_power_time','electricity_meter_number'

    ];

    /**
     * 禁止批量赋值的
     * @var array
     */
    protected $guarded = [

    ];



}
