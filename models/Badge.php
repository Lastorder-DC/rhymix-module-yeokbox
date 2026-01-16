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
	 * 모듈 설정을 가져오는 함수.
	 * 
	 * 캐시 처리되기 때문에 ModuleModel을 직접 호출하는 것보다 효율적이다.
	 * 모듈 내에서 설정을 불러올 때는 가급적 이 함수를 사용하도록 한다. 
	 * 
	 * @return object
	 */
	public static function getBadgeList()
	{
		$cacheKey = 'attach_badge_v3';
		$badgeData = Cache::get($cacheKey);
		if($badgeData === null) {
			$badgeData = [];
		}

        return $badgeData;
	}
}
