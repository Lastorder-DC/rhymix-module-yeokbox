<?php

namespace Rhymix\Modules\Yeokbox\Controllers;
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
		$docList = $obj->data;
		foreach ($docList as $doc) {
			$docSrl = $doc->get('document_srl');
			$voteData = Cache::get('yeokbox_vote_' . $docSrl);
			if($voteData === null) {
				$args = new \stdClass();
				$args->member_srl = 4;
				$args->document_srl = $doc->get('document_srl');
				$output = executeQuery('document.getDocumentVotedLogInfo', $args);
				debugPrint($output->data);
				//Cache::set('yeokbox_vote_' . $docSrl, $output->data);
			}
		}
	}
}
