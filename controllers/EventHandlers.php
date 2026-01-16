<?php
namespace Rhymix\Modules\Yeokbox\Controllers;

require 'DiceCalc/Calc.php';
require 'DiceCalc/CalcSet.php';
require 'DiceCalc/CalcDice.php';
require 'DiceCalc/CalcOperation.php';
require 'DiceCalc/Random.php';

use Rhymix\Modules\Yeokbox\Models\Config as ConfigModel;
use Rhymix\Framework\Cache;
use DiceCalc\Calc as Calc;

/**
 * 역박스 커스텀
 * * Copyright (c) Lastorder-DC
 * * Generated with https://www.poesis.org/tools/modulegen/
 */
class EventHandlers extends Base
{
	/**
	 * 첨부파일 뱃지정보 생성용 함수
	 * * @param DocumentItem $doc
	 */
	private function getMediaBadgeData($doc) {
		$attach_list = $doc->getUploadedFiles();
		$content = $doc->get('content');
		
		$image_attach = false;
		$video_attach = false;
		$youtube_attach = false;
		$chzzk_attach = false;

		// 첨부파일 검사: 이미지와 비디오가 모두 발견되면 루프 중단 (최적화)
		if ($attach_list && count($attach_list) > 0) {
			foreach ($attach_list as $val) {
				if (!$image_attach && strpos($val->mime_type, 'image') !== false) {
					$image_attach = true;
				}
				if (!$video_attach && strpos($val->mime_type, 'video') !== false) {
					$video_attach = true;
				}
				if ($image_attach && $video_attach) {
					break;
				}
			}
		}

		// iframe 태그 검사
		if (preg_match('/<iframe[^>]*src=["\'](?:https?:)?\/\/(?:[\w-]+\.)*(?:youtube\.com|youtu\.be)/i', $content)) {
			$youtube_attach = true;
		}

		if (preg_match('/<iframe[^>]*src=["\'](?:https?:)?\/\/(?:[\w-]+\.)*chzzk\.naver\.com/i', $content)) {
			$chzzk_attach = true;
		}

		$badgeInfo = new \stdClass();
		$badgeInfo->image_attach = $image_attach;
		$badgeInfo->video_attach = $video_attach;
		$badgeInfo->youtube_attach = $youtube_attach;
		$badgeInfo->chzzk_attach = $chzzk_attach;

		return $badgeInfo;
	}

	/**
	 * 여까 추천 글 체크 및 첨부파일 뱃지정보 생성
	 * * @param object $obj
	 */
	public function afterGetDocumentList($obj) {
		if (empty($obj->data)) {
			return;
		}

		$config = ConfigModel::getConfig();
		$cacheKeyVote = 'yeokbox_vote_' . $config->yeokka_member_srl;
		$cacheKeyAttachBadge = 'attach_badge_v3'; 

		// 캐시 가져오기
		$voteData = Cache::get($cacheKeyVote) ?? [];
		$badgeData = Cache::get($cacheKeyAttachBadge) ?? [];

		$voteUpdated = false;
		$badgeUpdated = false;

		foreach ($obj->data as &$doc) {
			$docSrl = $doc->get('document_srl');

			if (!isset($voteData[$docSrl])) {
				$args = new \stdClass();
				$args->member_srl = $config->yeokka_member_srl;
				$args->document_srl = $docSrl;
				$output = executeQuery('document.getDocumentVotedLogInfo', $args);
				
				$voteData[$docSrl] = ($output->data->count >= 1 ? "Y" : "N");
				$voteUpdated = true;
			}

			if (!isset($badgeData[$docSrl])) {
				$badgeData[$docSrl] = $this->getMediaBadgeData($doc);
				$badgeUpdated = true;
			}
		}

		// 변경사항이 있을 때만 캐시 저장
		if ($voteUpdated) {
			Cache::set($cacheKeyVote, $voteData);
		}
		if ($badgeUpdated) {
			Cache::set($cacheKeyAttachBadge, $badgeData);
		}
	}

	/**
	 * 글 업데이트시 첨부파일 뱃지 캐시 삭제
	 * * @param object $obj
	 */
	public function afterUpdateDocument($obj) {
		$document_srl = $obj->document_srl;
		$cacheKeyAttachBadge = 'attach_badge_v3'; 
		
		$badgeData = Cache::get($cacheKeyAttachBadge);
		
		if ($badgeData !== null && isset($badgeData[$document_srl])) {
			unset($badgeData[$document_srl]);
			Cache::set($cacheKeyAttachBadge, $badgeData);
		}
	}

	public function afterUpdateVotedCount($obj) {
		$config = ConfigModel::getConfig();
		if ($config->yeokka_member_srl != \MemberModel::getLoggedMemberSrl()) {
			return;
		}

		$this->updateVoteCache($obj, "Y");
	}

	public function afterUpdateVotedCountCancel($obj) {
		$config = ConfigModel::getConfig();
		if ($config->yeokka_member_srl != \MemberModel::getLoggedMemberSrl()) {
			return;
		}

		$this->updateVoteCache($obj, "N");
	}
	
	public function updateVoteCache($obj, $vote) {
		$config = ConfigModel::getConfig();
		$cacheKey = 'yeokbox_vote_' . $config->yeokka_member_srl;
		$docSrl = $obj->document_srl;

		$voteData = Cache::get($cacheKey) ?? [];

		// 값이 다를 경우에만 캐시 업데이트 수행
		if (!isset($voteData[$docSrl]) || $voteData[$docSrl] !== $vote) {
			$voteData[$docSrl] = $vote;
			Cache::set($cacheKey, $voteData);
		}
	}

	public static function sendNewDocNotification($obj) {
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
		$yeokkaSrl = intval($config->yeokka_member_srl);
		$objMemberSrl = intval($obj->member_srl);

		// 작성자가 여까/핫산이 아니면 리턴 
		if ($objMemberSrl === $yeokkaSrl) {
			$nick = "여까";
		} elseif ($objMemberSrl === 4) {
			$nick = "핫산";
		} else {
			return;
		}

		$args = new \stdClass();
		$args->member_srl = $objMemberSrl;
		$output = executeQueryArray('yeokbox.getFriendList', $args, ['member.member_srl', 'nick_name']);

		if (!count($output->data)) {
			return;
		}

		$oNcenterliteController = getController('ncenterlite');
		$baseMessage = $nick . " 새 글 알림! - " . $obj->title;
		$targetUrl = "/" . $obj->document_srl;

		foreach($output->data as $friend) {
			$message = new \stdClass();
			$message->summary = $baseMessage;
			$message->subject = $baseMessage;
			$oNcenterliteController->sendNotification(4, $friend->member_srl, $message, $targetUrl, $obj->document_srl);
		}
	}

	public function afterPublishDocument($obj) {
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
		$memberSrl = intval($obj->member_srl);

		// 작성자가 여까/핫산이 아니면 리턴 
		if ($memberSrl !== intval($config->yeokka_member_srl) && $memberSrl !== 4) {
			return;
		}

		if (config('queue.enabled') && !defined('RXQUEUE_CRON')) {
			\Rhymix\Framework\Queue::addTask(self::class . '::' . 'sendNewDocNotification', $obj);
		}
	}

	public function beforeInsertContent(&$obj) {
		// 댓글 내용에서 {문자} 형태의 문자열을 찾아서 다이스 롤링
		$pattern = '/\{([^{}]+)\}/';
		
		$obj->content = preg_replace_callback($pattern, function ($match) {
			$originalRollString = $match[0];
			$expression = $match[1];
			
			try {
				$calc = new Calc($expression);
				$diceText = $calc->infix();
				$rollResult = $calc();
			} catch (\Exception $e) {
				// 계산식이 잘못된 경우 원래 문자열 유지
				return $originalRollString;
			}

			// diceText나 rollResult가 비어있으면 원래 문자열 유지
			if (!$diceText || !$rollResult) {
				return $originalRollString;
			}

			// diceText와 rollResult가 동일하면 rollResult만 표시
			if ($diceText == $rollResult) {
				return $rollResult;
			}

			// 계산식과 결과를 함께 표시
			return $diceText . ' = ' . $rollResult;

		}, $obj->content);
	}
}