<?php
/**
 * Created by PhpStorm.
 * User: chao
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Evse;
use Wormhole\Protocols\LaiChongV2\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\BIN;

class Answer extends Frame
{
    protected $instructions = 0xE0;

    /**
     * 单号
     * @var int
     */
    protected $order_no = [BIN::class,1,TRUE];

    /**
     * 指令号
     * @var int
     */
    protected $instruct = [BIN::class,1,TRUE];

    /**
     * 响应结果 成功 0x01 失败 0x00
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];

}