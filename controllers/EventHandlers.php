<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

/**
 * 역박스 커스텀
 * 
 * Copyright (c) Lastorder-DC
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class EventHandlers extends Base
{
	// TODO 설정으로 분리
	private static $member_srl = 4;

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
			$voteData = \Rhymix\Framework\Cache::get('yeokbox_vote_' . $docSrl);
			if($voteData === null) {
				$args = new \stdClass();
				$args->member_srl = $self::$member_srl;
				$args->document_srl = $doc->get('document_srl');
				$output = executeQuery('document.getDocumentVotedLogInfo', $args);
				debugPrint($output->data);
				//\Rhymix\Framework\Cache::set('yeokbox_vote_' . $docSrl, $output->data);
			}
		}
	}
}
