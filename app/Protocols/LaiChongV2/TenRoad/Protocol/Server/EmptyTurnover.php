<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */
namespace Wormhole\Protocols\TenRoad\Protocol\Server;
use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class EmptyTurnover extends Frame
{
    protected $instructions = 0x46;

    /**
     * 单号
     * @var int
     */
    protected $order_no = [BIN::class,1,TRUE];

    /**
     * 清空选项
     * @var int
     */
    protected $option = [BIN::class,1,TRUE];

}