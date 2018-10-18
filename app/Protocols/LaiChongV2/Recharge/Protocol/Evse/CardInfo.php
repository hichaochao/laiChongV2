<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\QianNiu\Protocol\Evse;

use Wormhole\Protocols\QianNiu\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class CardInfo extends Frame
{
    protected $instructions = 0x1107;

    /**
     * 卡片id
     * @var int
     */
    protected $card_id = [BIN::class,4,TRUE];

    /**
     * 卡片数据
     * @var int
     */
    protected $card_data = [BIN::class,16,TRUE];

}