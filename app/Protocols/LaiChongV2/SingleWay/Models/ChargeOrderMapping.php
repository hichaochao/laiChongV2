<?php

namespace Wormhole\Protocols\QianNiu\Models;

use Gbuckingham89\EloquentUuid\Traits\UuidForKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ChargeOrderMapping extends Model
{
    use SoftDeletes;
    use UuidForKey;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quchong_charge_order_mappings';
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
    protected $fillable = ['id','evse_id','port_id',
        'code',         'monitor_order_id',
        'order_id','charge_type','charge_args','is_start_success'

    ];

    /**
     * 禁止批量赋值的
     * @var array
     */
    protected $guarded = [

    ];

}
