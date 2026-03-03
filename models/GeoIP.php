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
	 * IP의 국가를 체크합니다.
	 *
	 * @return array<int, object>
	 */
	public static function getCountry(string $ip): string
	{
		//TODO 기능 구현
        return 'KR';
	}
}
