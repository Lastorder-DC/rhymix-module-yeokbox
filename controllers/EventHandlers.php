<?php

namespace Rhymix\Modules\Yeokbox\Controllers;
use Rhymix\Modules\Yeokbox\Models\Config as ConfigModel;
use Rhymix\Framework\Cache;

/**
 * 역박스 커스텀
 * 
 * Copyright (c) Lastorder-DC
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class EventHandlers extends Base
{
	/**
	 * 여까 추천 글 체크
	 * 
	 * @param object $obj
	 */
	public function afterGetDocumentList($obj)
	{
		$config = ConfigModel::getConfig();
		$config->yeokka_member_srl = $config->yeokka_member_srl ?: 4;
		$cacheKey = 'yeokbox_vote_' . $config->yeokka_member_srl;
		$voteData = Cache::get($cacheKey);
		if($voteData === null) {
			$voteData = [];
		}
		debugPrint($voteData);

		$docList = $obj->data;
		foreach ($obj->data as &$doc) {
			$docSrl = $doc->get('document_srl');

			if(!array_key_exists($docSrl, $voteData)) {
				$args = new \stdClass();
				$args->member_srl = $config->yeokka_member_srl;
				$args->document_srl = $docSrl;
				$output = executeQuery('document.getDocumentVotedLogInfo', $args);
				$voteData[$docSrl] = ($output->data->count >= 1);
			}
			$doc->add('voted_heart', $voteData[$docSrl]);
		}

		Cache::set($cacheKey, $voteData);
		debugPrint($voteData);
	}
}
