<?php

namespace TypechoPlugin\Comment2Location;

use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Comment2Location 对外接口
 *
 * @package Comment2Location
 */
class Action extends Widget
{
    /**
     * ISO 3166-1 alpha-2 国家码转国家名（简体中文）
     *
     * @param string $code 两位国家码，如 "AU"
     * @return string 中文国家名，如 "澳大利亚"
     */
    public static function iso2zh(string $code): string
    {
        if (empty($code)) {
            return '';
        }

        return \Locale::getDisplayRegion('-' . $code, 'zh_CN');
    }

    /**
     * IP 归属地查询接口
     * 路由: /comment2geo.json?ip=x.x.x.x
     */
    public function ip2Location(): void
    {
        $ip = $this->request->get('ip', '');

        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->response->throwJson([
                'code' => 400,
                'message' => 'Invalid IP address',
                'data' => null,
            ]);
        }

        $result = json_decode(Plugin::lookupIp($ip), true);

        if (isset($result['error'])) {
            $this->response->throwJson([
                'code' => 500,
                'message' => $result['error'],
                'data' => null,
                'zh_CN' => [
                    'country' => '未知',
                ],
            ]);
        }

        $record = $result['data'];
        $countryCode = $record['country_code'] ?? '';

        $this->response->throwJson([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'ip' => $ip,
                'country' => $record['country'] ?? '',
                'country_code' => $countryCode,
                'continent' => $record['continent'] ?? '',
                'continent_code' => $record['continent_code'] ?? '',
                'region' => $record['region'] ?? '',
                'city' => $record['city'] ?? '',
                'asn' => $record['asn'] ?? '',
                'as_name' => $record['as_name'] ?? '',
                'as_domain' => $record['as_domain'] ?? '',
                'location' => Plugin::formatLocation($record),
            ],
            'zh_CN' => [
                'country' => self::iso2zh($countryCode),
            ],
        ]);
    }
}
