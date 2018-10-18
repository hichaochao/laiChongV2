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


class SetTime extends Frame
{


    protected $instructions = 0x1306;


    /**
     * 时间
     * @var int
     */
    protected $date = [BCD::class,6,TRUE];

    


}