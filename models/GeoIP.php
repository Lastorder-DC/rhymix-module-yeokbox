<?php
namespace Rhymix\Modules\Yeokbox\Models;
use GeoIp2\Database\Reader;

/**
 * 역박스 커스텀
 *
 * Copyright (c) Lastorder-DC
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class GeoIP
{
	/**
	 * GeoIP2 Reader 캐시를 위한 변수.
	 */
	protected static ?Reader $_reader = null;

	/**
	 * IP의 국가를 체크합니다.
	 *
	 * @return string
	 */
	public static function getCountry(string $ip): string
	{
		try
		{
			if (self::$_reader === null)
			{
				$dbPath = \RX_BASEDIR . 'files/GeoLite2-Country.mmdb';
				self::$_reader = new Reader($dbPath);
			}
			$record = self::$_reader->country($ip);
			return $record->country->isoCode ?? '';
		}
		catch (\Exception $e)
		{
			return '';
		}
	}

	/**
	 * 국가코드에 따른 국기 이미지 태그를 반환합니다.
	 *
	 * @return string
	 */
	public static function getFlag(string $code): string
	{
		$code = strtolower($code);
		if (!preg_match('/^[a-z]{2}$/', $code))
		{
			return '';
		}
		return '<picture>'
			. '<source type="image/webp" srcset="https://flagcdn.com/16x12/' . $code . '.webp, https://flagcdn.com/32x24/' . $code . '.webp 2x, https://flagcdn.com/48x36/' . $code . '.webp 3x">'
			. '<source type="image/png" srcset="https://flagcdn.com/16x12/' . $code . '.png, https://flagcdn.com/32x24/' . $code . '.png 2x, https://flagcdn.com/48x36/' . $code . '.png 3x">'
			. '<img src="https://flagcdn.com/16x12/' . $code . '.png" alt="' . $code . '">'
			. '</picture>';
	}
}
