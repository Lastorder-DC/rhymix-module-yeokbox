<?php

namespace Rhymix\Modules\Yeokbox\Models;

use Rhymix\Framework\Cache;

/**
 * 역박스 커스텀
 *
 * Copyright (c) Lastorder-DC
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Badge
{
	/**
	 * 첨부파일 뱃지 캐시 목록을 가져옵니다.
	 *
	 * @return array<int, object>
	 */
	public static function getBadgeList(): array
	{
		$cacheKey = 'attach_badge_v3';
		return Cache::get($cacheKey) ?? [];
	}
}
