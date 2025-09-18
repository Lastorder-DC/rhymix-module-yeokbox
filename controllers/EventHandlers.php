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

	public static function sendNewDocNotification($obj) {
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
		$nick = "여까";

		// 작성자가 여까/핫산이 아니면 리턴 
		if($obj->member_srl == intval($config->yeokka_member_srl)) {
			$nick = "여까";
		} elseif($obj->member_srl == 4) {
			$nick = "핫산";
		} else {
			return;
		}

		$args = new \stdClass();
		$args->member_srl = $obj->member_srl;
		$output = executeQueryArray('yeokbox.getFriendList', $args, ['member.member_srl', 'nick_name']);

		$oNcenterliteController = getController('ncenterlite');
		foreach($output->data as $friend) {
			$message = new \stdClass();
			$message->summary = $nick . " 새 글 알림! - " . $obj->title;
			$message->subject = $message->summary;
			$msg = $oNcenterliteController->sendNotification(4, $friend->member_srl, $message, "/" . $obj->document_srl, $obj->document_srl);
		}
	}

	public function afterInsertDocument($obj) {
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();

		// 작성자가 여까/핫산이 아니면 리턴 
		if($obj->member_srl != intval($config->yeokka_member_srl) && $obj->member_srl != 4) {
			return;
		}

		if (config('queue.enabled') && !defined('RXQUEUE_CRON')) {
			\Rhymix\Framework\Queue::addTask(self::class . '::' . 'sendNewDocNotification', $obj);
		}
	}

	public function beforeInsertContent(&$obj) {
		$content = $obj->content;

		// 댓글 내용에서 {3d6+6} 형태의 문자열을 찾아서 다이스 롤링
		$pattern = '/\{(\d*)d(\d+)([+-]\d+)?\}/';
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$originalRollString = $match[0];
			$numDice = intval($match[1]) ?: 1; // d 이전 숫자가 없으면 1로 설정
			$numSides = intval($match[2]);
			$modifier = isset($match[3]) ? intval($match[3]) : 0;
			$modifierText = $modifier !== 0 ? (($modifier > 0 ? '+' : '') . $modifier) : '';
			$diceText = $numDice . 'd' . $numSides . $modifierText;

			if ($numSides <= 0 || $numDice <= 0 || $numDice > 100 || $numSides > 1000) {
				// 유효하지 않은 경우 오류 메세지 출력
				$content = str_replace($originalRollString, '[(' . $diceText . ') 잘못된 사용법]', $content);
				continue;
			}

			$rolls = [];
			for ($i = 0; $i < $numDice; $i++) {
				$rolls[] = rand(1, $numSides);
			}
			$sum = array_sum($rolls);
			$total = $sum + $modifier;
			$rollResult = '[(' . $diceText . ') ' . implode(' + ', $rolls) . ($modifier !== 0 ? (' (' . ($modifier > 0 ? '+' : '') . $modifier . ')') : '') . ' = ' . $total . ']';

			// 여러 다이스 롤을 올바르게 처리하기 위해 첫번째 다이스만 변환
			$content = preg_replace('/' . preg_quote($originalRollString, '/') . '/', $rollResult, $content, 1);
		}

		$obj->content = $content;
	}
}
