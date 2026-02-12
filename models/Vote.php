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
class Vote
{
	/**
	 * 추천 여부 캐시 목록을 가져옵니다.
	 *
	 * @return array<int, string>
	 */
	public static function getVoteList(): array
	{
		$config = Config::getConfig();
		$cacheKey = 'yeokbox_vote_' . $config->yeokka_member_srl;

		return Cache::get($cacheKey) ?? [];
	}
}
