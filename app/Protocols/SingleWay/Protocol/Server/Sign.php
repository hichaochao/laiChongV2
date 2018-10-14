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
use Wormhole\Protocols\Library\BCD;

class Sign extends Frame
{


    protected $instructions = 0x1101;
    
    /**
     * 响应结果：0x01 接受，0x00拒绝
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];


    /**
     * 时间 年月日时分秒
     * @var int
     */
    protected $date = [BCD::class,6,TRUE];


}