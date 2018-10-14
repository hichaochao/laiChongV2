<?php

namespace Wormhole\Protocols\QianNiu\Models;

use Gbuckingham89\EloquentUuid\Traits\UuidForKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Port extends Model
{
    use UuidForKey;
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quchong_ports';
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
    protected $fillable = ['id','evse_id','code', 'monitor_code', 'port_number','monitor_order_id','order_id','work_status','is_fuse',
        'is_flow','is_connect','is_full','start_time','end_time', 'charged_power','charge_type','charge_args',
        'renew', 'is_response', 'operation_time', 'left_time','current','response_time'

    ];

    /**
     * 禁止自动赋值的
     * @var array
     */
    protected $guarded = [


    ];

    public function evse(){
        return $this->belongsTo(\Wormhole\Protocols\QuChong\Models\Evse::class);
    }
}
