<?php
/**
 * Created by PhpStorm.
 * User: lingf
 * Date: 2017-03-02
 * Time: 18:20
 */

namespace Wormhole\Protocols\TenRoad\Protocol\Server;



use Wormhole\Protocols\TenRoad\Protocol\Frame;
use Wormhole\Protocols\Library\ASCII;


class ServerInfo extends Frame
{


    protected $instructions = 0x1302;

    /**
     * 域名
     * @var string
     */
    protected $domain_name = [ASCII::class,30,TRUE];

    /**
     * 端口号
     * @var string
     */
    protected $result = [ASCII::class,6,TRUE];



}