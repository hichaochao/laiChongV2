<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Server;



use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;
class GetChannelStatus extends Frame
{

    protected $instructions = 0x1404;
    
    /**
     * 通道号
     * @var int
     */
    protected $channel_num = [BIN::class,1,TRUE];





}