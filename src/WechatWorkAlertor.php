<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Exception\Alertor;

use Hyperf\Context\ApplicationContext;
use Verdient\Hyperf3\Exception\AlertorInterface;
use Verdient\Hyperf3\Logger\HasLogger;
use Verdient\Hyperf3\Struct\Result;
use Verdient\WechatWork\WechatWork;

use function Hyperf\Config\config;

/**
 * 企业微信报警器
 * @author Verdient。
 */
class WechatWorkAlertor implements AlertorInterface
{
    use HasLogger;

    /**
     * @param string $message 错误信息
     * @author Verdient。
     */
    public static function alert(string $message): Result
    {
        $wechatWorkIds = config('developers.wechatWorkIds');
        if (!empty($wechatWorkIds)) {
            return static::sendMessage($message, $wechatWorkIds);
        }
        $botKeys = config('developers.botKeys');
        $errors = [];
        if (!empty($botKeys)) {
            foreach ($botKeys as $botKey) {
                $result = static::sendBotMessage($message, $botKey);
                if (!$result->getIsOK()) {
                    $errors[] = $result->getMessage();
                }
            }
        }
        if (empty($errors)) {
            return Result::succeed();
        }
        return Result::failed(implode('; ', $errors));
    }

    /**
     * 发送消息
     * @param string $message 消息内容
     * @param array|string $to 接收人
     * @author Verdient。
     */
    protected static function sendMessage(string $message, string|array $to): Result
    {
        if (is_array($to)) {
            $touser = implode('|', $to);
        } else {
            $touser = $to;
        }
        if (!ApplicationContext::hasContainer()) {
            return Result::failed('预警信息发送到企业微信 ' . $touser . ' 失败：找不到容器');
        }
        /** @var WechatWork */
        if (!$wechatWork = ApplicationContext::getContainer()->get(WechatWork::class)) {
            return Result::failed('预警信息发送到企业微信 ' . $touser . ' 失败：' . WechatWork::class . ' 未配置');
        }
        if (!$agentId = config('wechat_work.agentId')) {
            return Result::failed('预警信息发送到企业微信 ' . $touser . ' 失败：agentId 未配置');
        }
        $request = $wechatWork
            ->request('message/send')
            ->setMethod('POST')
            ->setBody([
                'agentid' => $agentId,
                'text' => [
                    'content' => $message
                ],
                'msgtype' => 'text',
                'touser' => $touser
            ])
            ->withToken($agentId);
        try {
            $res = $request->send();
            if (!$res->getIsOK()) {
                return Result::failed('预警信息发送到企业微信 ' . $touser . ' 失败：' . $res->getErrorMessage());
            }
            return Result::succeed();
        } catch (\Throwable $e) {
            return Result::failed('预警信息发送到企业微信 ' . $touser . ' 失败：' . $e->getMessage());
        }
    }

    /**
     * 发送机器人消息
     * @param string $message 消息内容
     * @param string $key 机器人标识
     * @author Verdient。
     */
    protected static function sendBotMessage(string $message, string $key): Result
    {
        if (!ApplicationContext::hasContainer()) {
            return Result::failed('预警信息发送到企业微信机机器人 ' . $key . ' 失败：找不到容器');
        }
        /** @var WechatWork */
        if (!$wechatWork = ApplicationContext::getContainer()->get(WechatWork::class)) {
            return Result::failed('预警信息发送到企业微信机器人 ' . $key . ' 失败：' . WechatWork::class . ' 未配置');
        }
        $request = $wechatWork
            ->request('webhook/send')
            ->addQuery('key', $key)
            ->setMethod('POST')
            ->setBody([
                'msgtype' => 'text',
                'text' => [
                    'content' => $message
                ]
            ]);
        try {
            $res = $request->send();
            if (!$res->getIsOK()) {
                return Result::failed('预警信息发送到企业微信机器人 ' . $key . ' 失败：' . $res->getErrorMessage());
            }
            return Result::succeed();
        } catch (\Throwable $e) {
            return Result::failed('预警信息发送到企业微信机器人 ' . $key . ' 失败：' . $e->getMessage());
        }
    }
}
