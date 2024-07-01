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
	public function afterGetDocumentList($obj) {
		$config = ConfigModel::getConfig();
		$cacheKey = 'yeokbox_vote_' . $config->yeokka_member_srl;
		$voteData = Cache::get($cacheKey);
		if($voteData === null) {
			$voteData = [];
		}

		$docList = $obj->data;
		foreach ($obj->data as &$doc) {
			$docSrl = $doc->get('document_srl');

			if(!array_key_exists($docSrl, $voteData)) {
				$args = new \stdClass();
				$args->member_srl = $config->yeokka_member_srl;
				$args->document_srl = $docSrl;
				$output = executeQuery('document.getDocumentVotedLogInfo', $args);
				$voteData[$docSrl] = ($output->data->count >= 1 ? "Y" : "N");
			}
			$doc->add('voted_heart', $voteData[$docSrl]);
		}

		Cache::set($cacheKey, $voteData);
	}

	public function afterUpdateVotedCount($obj) {
		$config = ConfigModel::getConfig();
		if($config->yeokka_member_srl != \MemberModel::getLoggedMemberSrl()) {
			return;
		}

		$this->updateVoteCache($obj, "Y");
	}

	public function afterUpdateVotedCountCancel($obj) {
		$config = ConfigModel::getConfig();
		if($config->yeokka_member_srl != \MemberModel::getLoggedMemberSrl()) {
			return;
		}

		$this->updateVoteCache($obj, "N");
	}
	
	public function updateVoteCache($obj, $vote) {
		$config = ConfigModel::getConfig();
		$cacheKey = 'yeokbox_vote_' . $config->yeokka_member_srl;

		$docSrl = $obj->document_srl;
		$voteData = Cache::get($cacheKey);
		if($voteData === null) {
			$voteData = [];
		}

		$voteData[$docSrl] = $vote;
		Cache::set($cacheKey, $voteData);
	}

	public function afterInsertDocument($obj) {
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();

		// 작성자가 여까가 아니면 리턴 
		if($obj->member_srl != $config->yeokka_member_srl) return;

		$args = new \stdClass();
		$args->member_srl = $config->yeokka_member_srl;
		$output = executeQueryArray('yeokbox.getFriendList', $args, ['member.member_srl', 'nick_name']);

		$oNcenterliteController = getController('ncenterlite');
		foreach($output->data as $friend) {
			$message = new \stdClass();
			$message->summary = "여까 새 글 알림! - " . $obj->title;
			$message->subject = "여까 새 글 알림!";
			$msg = $oNcenterliteController->sendNotification(4, $friend->member_srl, $message, getNotEncodedUrl('', 'mid', $module_info->mid, 'document_srl', $obj->document_srl), $obj->document_srl);
		}
	}
}
