<?php

/*
 * This file is part of the xiaoyun/easy-sms.
 *
 * (c) xiaoyun <i@xiaoyun.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace XiaoYun\EasySms\Tests\Gateways;

use XiaoYun\EasySms\Exceptions\GatewayErrorException;
use XiaoYun\EasySms\Gateways\BaiduGateway;
use XiaoYun\EasySms\Message;
use XiaoYun\EasySms\PhoneNumber;
use XiaoYun\EasySms\Support\Config;
use XiaoYun\EasySms\Tests\TestCase;

class BaiduGatewayTest extends TestCase
{
    public function testSend()
    {
        $config = [
            'ak' => 'mock-ak',
            'sk' => 'mock-sk',
            'invoke_id' => 'mock-invoke-id',
        ];
        $gateway = \Mockery::mock(BaiduGateway::class.'[request]', [$config])->shouldAllowMockingProtectedMethods();
        $expected = [
            'phoneNumber' => 18888888888,
            'templateCode' => 'mock-tpl-id',
            'invokeId' => $config['invoke_id'],
            'contentVar' => ['mock-data-1', 'mock-data-2'],
        ];
        $gateway->shouldReceive('request')->with(
            'post',
            \Mockery::on(function ($api) {
                return 0 == strpos($api, 'http://'.BaiduGateway::ENDPOINT_HOST.BaiduGateway::ENDPOINT_URI);
            }),
            \Mockery::on(function ($params) use ($expected) {
                ksort($params['json']);
                ksort($expected);

                return $params['json'] == $expected;
            })
        )
            ->andReturn(
                ['code' => BaiduGateway::SUCCESS_CODE, 'message' => 'success'],
                ['code' => 100, 'message' => 'mock-msg']
            )
            ->twice();

        $message = new Message([
            'template' => 'mock-tpl-id',
            'data' => ['mock-data-1', 'mock-data-2'],
        ]);

        $config = new Config($config);
        $this->assertSame(['code' => BaiduGateway::SUCCESS_CODE, 'message' => 'success'], $gateway->send(new PhoneNumber(18888888888), $message, $config));

        $this->expectException(GatewayErrorException::class);
        $this->expectExceptionCode(100);
        $this->expectExceptionMessage('mock-msg');

        $gateway->send(new PhoneNumber(18888888888), $message, $config);
    }
}
