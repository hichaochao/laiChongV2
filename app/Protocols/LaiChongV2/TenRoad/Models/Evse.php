<?php
namespace Wormhole\Protocols\TenRoad\Models;

use Illuminate\Database\Eloquent\Model;
use Gbuckingham89\EloquentUuid\Traits\UuidForKey;
use Illuminate\Database\Eloquent\SoftDeletes;
class Evse extends Model
{
    use UuidForKey;
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quchong_evses';
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
    protected $fillable = ['id','code','worker_id','protocol_name','channel_num', 'identification_number', 'serial_number',
        'online_status','last_update_status_time','signal_intensity', 'heartbeat_cycle','domain_name','port_number',
        'parameter','device_id', 'operation_time', 'request_result','version'

    ];

    /**
     * 禁止批量赋值的
     * @var array
     */
    protected $guarded = [
           
    ];

    public function ports(){
        return $this->hasMany(\Wormhole\Protocols\QuChong\Models\Port::class);
    }

}
