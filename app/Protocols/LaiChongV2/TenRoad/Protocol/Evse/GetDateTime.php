<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Evse;



use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BCD;


class GetDateTime extends Frame
{


    protected $instructions = 0x1409;

    /**
     * 响应结果 时间 年月日时分秒
     * @var int
     */
    protected $date_time = [BCD::class,6,TRUE];




}