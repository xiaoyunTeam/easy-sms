<?php

/*
 * This file is part of the xiaoyun/easy-sms.
 *
 * (c) xiaoyun <i@xiaoyun.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace XiaoYun\EasySms\Gateways;

use XiaoYun\EasySms\Contracts\MessageInterface;
use XiaoYun\EasySms\Contracts\PhoneNumberInterface;
use XiaoYun\EasySms\Exceptions\GatewayErrorException;
use XiaoYun\EasySms\Exceptions\InvalidArgumentException;
use XiaoYun\EasySms\Support\Config;
use XiaoYun\EasySms\Traits\HasHttpRequest;

/**
 * Class ChuanglanGateway.
 *
 * @see https://zz.253.com/v5.html#/api_doc
 */
class ChuanglanGateway extends Gateway
{
    use HasHttpRequest;

    /**
     * URL模板
     */
    const ENDPOINT_URL_TEMPLATE = 'https://%s.253.com/msg/send/json';

    /**
     * 国际短信
     */
    const INT_URL = 'http://intapi.253.com/send/json';

    /**
     * 验证码渠道code.
     */
    const CHANNEL_VALIDATE_CODE = 'smsbj1';

    /**
     * 会员营销渠道code.
     */
    const CHANNEL_PROMOTION_CODE = 'smssh1';

    /**
     * @param PhoneNumberInterface $to
     * @param MessageInterface     $message
     * @param Config               $config
     *
     * @return array
     *
     * @throws GatewayErrorException
     * @throws InvalidArgumentException
     */
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $params = [
            'account' => $config->get('account'),
            'password' => $config->get('password'),
            'phone' => $to->getNumber(),
            'msg' => $this->wrapChannelContent($message->getContent($this), $config),
        ];
        $IDDCode = !empty($to->getIDDCode()) ? $to->getIDDCode() : 86;

        if (86 != $IDDCode) {
            $params['mobile'] = $to->getIDDCode().$to->getNumber();
        }

        $result = $this->postJson($this->buildEndpoint($config, $IDDCode), $params);

        if (!isset($result['code']) || '0' != $result['code']) {
            throw new GatewayErrorException(json_encode($result, JSON_UNESCAPED_UNICODE), isset($result['code']) ? $result['code'] : 0, $result);
        }

        return $result;
    }

    /**
     * @param Config $config
     * @param int    $idDCode
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    protected function buildEndpoint(Config $config, $idDCode = 86)
    {
        $channel = $this->getChannel($config);

        return sprintf(self::ENDPOINT_URL_TEMPLATE, $channel);
    }

    /**
     * @param Config $config
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    protected function getChannel(Config $config)
    {
        if (86 != $idDCode) {
            return self::INT_URL;
        }
        $channel = $config->get('channel', self::CHANNEL_VALIDATE_CODE);

        if (!in_array($channel, [self::CHANNEL_VALIDATE_CODE, self::CHANNEL_PROMOTION_CODE])) {
            throw new InvalidArgumentException('Invalid channel for ChuanglanGateway.');
        }

        return $channel;
    }

    /**
     * @param string $content
     * @param Config $config
     *
     * @return string|string
     *
     * @throws InvalidArgumentException
     */
    protected function wrapChannelContent($content, Config $config)
    {
        $channel = $this->getChannel($config);

        if (self::CHANNEL_PROMOTION_CODE == $channel) {
            $sign = (string) $config->get('sign', '');
            if (empty($sign)) {
                throw new InvalidArgumentException('Invalid sign for ChuanglanGateway when using promotion channel');
            }

            $unsubscribe = (string) $config->get('unsubscribe', '');
            if (empty($unsubscribe)) {
                throw new InvalidArgumentException('Invalid unsubscribe for ChuanglanGateway when using promotion channel');
            }

            $content = $sign.$content.$unsubscribe;
        }

        return $content;
    }
}
