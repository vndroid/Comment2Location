<?php

namespace TypechoPlugin\Comment2Location;

require_once __DIR__ . '/vendor/autoload.php';

use MaxMind\Db\Reader;
use Typecho\Plugin\PluginInterface;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Widget\Helper\Form;
use Utils\Helper;
use Widget\Comments\Archive;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 评论定位显示插件 for Typecho
 *
 * @package Comment2Location
 * @author Vex
 * @version 0.0.1
 * @link https://github.com/vndroid/Comment2Location
 */
class Plugin implements PluginInterface
{
    private const string DB_NAME = 'ipinfo_lite.mmdb';
    private const string DB_FILE = __DIR__ . '/' . self::DB_NAME;

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @throws PluginException
     */
    public static function activate()
    {
        // 验证 MMDB 文件存在
        if (!file_exists(self::DB_FILE)) {
            throw new PluginException(_t('激活失败：数据库文件不存在，请将 %s 放置到插件目录下'), self::DB_NAME);
        }

        // 验证 MMDB 文件格式
        try {
            $reader = new Reader(self::DB_FILE);
            $reader->close();
        } catch (\Exception $e) {
            throw new PluginException(_t('激活失败：数据库文件无法读取 - ') . $e->getMessage());
        }

        if (!extension_loaded('intl')) {
            throw new PluginException(_t('检测到当前 PHP 环境没有 intl 组件, 无法正常使用此插件'));
        }

        Helper::addRoute(
            'comment_to_location',
            '/comment2geo.json',
            '\TypechoPlugin\Comment2Location\Action',
            'ip2Location'
        );

        \Typecho\Plugin::factory(Archive::class)->___location = [__CLASS__, 'render'];
        return _t('插件已激活');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
        Helper::removeRoute('comment_to_location');
        return _t('插件已禁用');
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 通过本地数据库查询 IP 归属地
     *
     * @param string $ip IP 地址
     * @return string JSON 格式结果，成功时包含 data 字段，失败时包含 error 字段
     */
    public static function lookupIp(string $ip): string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return json_encode(['error' => 'Invalid IP address'], JSON_UNESCAPED_UNICODE);
        }

        try {
            $reader = new Reader(self::DB_FILE);
            $record = $reader->get($ip);
            $reader->close();

            if (!is_array($record)) {
                return json_encode(['error' => 'IP not found in database'], JSON_UNESCAPED_UNICODE);
            }

            return json_encode(['data' => $record], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 格式化 mmdb 查询结果为地理位置字符串
     *
     * @param array $record mmdb 查询记录
     * @return string 格式化后的地理位置
     */
    public static function formatLocation(array $record): string
    {
        $region = $record['region'] ?? '';
        $city = $record['city'] ?? '';

        if (!empty($region)) {
            return !empty($city) && $city !== $region ? $region . ' ' . $city : $region;
        }

        return !empty($record['country']) ? $record['country'] : 'null';
    }

    /**
     * 渲染评论地理位置（___location 钩子回调）
     *
     * @param Archive $archive 评论归档对象
     * @param string $template 输出模板
     * @return string
     */
    public static function render($archive, string $template): string
    {
        $template = $template ?? '%s';
        $ip = $archive->ip;

        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return sprintf($template, '未知');
        }

        $result = json_decode(self::lookupIp($ip), true);
        if (isset($result['error'])) {
            return sprintf($template, '未知');
        }

        $record = $result['data'];
        $countryCode = $record['country_code'] ?? '';

        try {
            $countryZh = Action::iso2zh($countryCode);
            if (empty($countryZh)) {
                return sprintf($template, '未知');
            }
            return sprintf($template, $countryZh);
        } catch (\Exception $e) {
            return sprintf($template, '未知');
        }
    }
}
