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


class Report extends Frame
{


    protected $instructions = 0x1105;


    /**
     * 时间
     * @var int
     */
    protected $date = [BCD::class,6,TRUE];


    /**
     * 接收的哪天营业额
     * @var int
     */
    protected $receive_date = [BCD::class,3,TRUE];


    /**
     * 响应结果 成功 0x01 失败 0x00
     * @var int
     */
    protected $result = [BIN::class,1,TRUE];


}