<?php
namespace Rhymix\Modules\Yeokbox\Controllers;

require 'DiceCalc/Calc.php';
require 'DiceCalc/CalcSet.php';
require 'DiceCalc/CalcDice.php';
require 'DiceCalc/CalcOperation.php';
require 'DiceCalc/Random.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Rhymix\Modules\Yeokbox\Models\Config as ConfigModel;
use Rhymix\Framework\Cache;
use Rhymix\Framework\Lang;
use thiagoalessio\TesseractOCR\TesseractOCR;
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

		// 첨부파일 검사: 이미지와 비디오가 모두 발견되면 루프 중단
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

	/**
	 * 추천 수 업데이트 후 트리거
	 * 
	 * @param object $obj
	 */
	public function afterUpdateVotedCount($obj) {
		$config = ConfigModel::getConfig();
		if ($config->yeokka_member_srl != \MemberModel::getLoggedMemberSrl()) {
			return;
		}

		$this->updateVoteCache($obj, "Y");
	}

	/**
	 * 추천 취소 후 트리거
	 * 
	 * @param object $obj
	 */
	public function afterUpdateVotedCountCancel($obj) {
		$config = ConfigModel::getConfig();
		if ($config->yeokka_member_srl != \MemberModel::getLoggedMemberSrl()) {
			return;
		}

		$this->updateVoteCache($obj, "N");
	}
	
	/**
	 * 추천 캐시 업데이트
	 * 
	 * @param object $obj
	 * @param string $vote
	 */
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

	/**
	 * 새 글 알림 발송 (큐 작업용)
	 * 
	 * @param object $obj
	 */
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

	/**
	 * 글 등록 후 트리거
	 * 
	 * @param object $obj
	 */
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

	/**
	 * 내용 입력 전 트리거 (다이스 롤링 처리)
	 * 
	 * @param object $obj
	 */
	public function beforeInsertContent(&$obj) {
		if($obj->document_srl == 1264782) return; // 공지글은 롤링 안함

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

	/**
	 * 파일 첨부 전 트리거 (OCR 스팸 감지)
	 * 
	 * @param object $obj
	 * @return BaseObject|void
	 */
	public function beforeInsertFile($obj) {
		$logged_info = \Context::get('logged_info');

		$ocr = new TesseractOCR($obj->file_info['tmp_name']);
		try {
			$text = $ocr->userWords(__DIR__ . '/user-words.txt')->psm(6)->lang('eng', 'kor')->run();
		} catch (\Exception $e) {
			return;
		}
		if (preg_match('/brrsim\s*[_\-]\s*77/i', $text)) {
			if($logged_info->is_admin !== 'Y') {
				$this->_spammerMember($logged_info, 'brrsim_77 이미지', $text);
				return new \BaseObject(-1, '서버 오류로 첨부할 수 없습니다.');
			} else {
				return new \BaseObject(-1, '[업로드 불가] brrsim_77 이미지');
			}
		}
		if (preg_match('/뽀로로\s*통신/iu', $text)) {
			if($logged_info->is_admin !== 'Y') {
				$this->_spammerMember($logged_info, '뽀로로 통신 이미지', $text);
				return new \BaseObject(-1, '서버 오류로 첨부할 수 없습니다.');
			} else {
				return new \BaseObject(-1, '[업로드 불가] 뽀로로 통신 이미지');
			}
		}
		if (preg_match('/선\s*불\s*유\s*심/iu', $text)) {
			if($logged_info->is_admin !== 'Y') {
				$this->_spammerMember($logged_info, '선불유심 이미지', $text);
				return new \BaseObject(-1, '서버 오류로 첨부할 수 없습니다.');
			} else {
				return new \BaseObject(-1, '[업로드 불가] 선불유심 이미지');
			}
		}
	}

	/**
	 * 스팸 회원 처리
	 * 
	 * @param object $logged_info
	 * @param string $description
	 * @param string $text
	 */
	protected function _spammerMember($logged_info, $description, $text) {
		if($logged_info->is_admin === 'Y') return;
		if(!$logged_info->member_srl) return;

		$oNcenterliteController = getController('ncenterlite');
		$oNcenterliteController->sendNotification($logged_info->member_srl, 4, '스팸 이미지 OCR 자동 차단됨 - ' . trim($description), 'https://fanbinit.us/index.php?module=admin&act=dispMemberAdminInsert&member_srl=' . $logged_info->member_srl, $logged_info->member_srl);

		$oMemberController = getController('member');
		$args = new \stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->email_address = $logged_info->email_address;
		$args->user_id = $logged_info->user_id;
		$args->nick_name = $logged_info->nick_name;
		$args->denied = 'Y';
		$args->status = 'DENIED';
		$args->group_srl_list = "1771291";
		$args->description = trim(vsprintf("%s\n%s [%s %s]\n\n[OCR Result]\n%s", [
			trim($description),
			'이미지 OCR 자동 차단',
			date("Y-m-d H:i:s"),
			$logged_info->nick_name,
			trim($text)
		]));

		$output = executeQuery('member.getMemberInfoByMemberSrl', ['member_srl' => $args->member_srl], ['extra_vars']);
		$extra_vars = ($output->data && $output->data->extra_vars) ? unserialize($output->data->extra_vars) : new \stdClass();
		if (!is_object($extra_vars))
		{
			$extra_vars = new \stdClass();
		}
		$extra_vars->refused_reason = '스팸 이미지 OCR 자동차단. 문의: webmaster@fanbinit.us';
		$args->extra_vars = serialize($extra_vars);

		$output = $oMemberController->updateMember($args, true, true);
		\MemberController::clearMemberCache($args->member_srl);
		\Context::setValidatorMessage('layouts/rx-flextagram', '스팸 이미지 OCR로 자동 차단되었습니다. 문의: webmaster@fanbinit.us', 'error');
	}

	/**
	 * 모듈 동작 후 트리거 (신고 사유 설정)
	 */
	public function triggerAddMemberMenu($oModule)
	{
		$oMemberController = getController('member');
		$oMemberController->addMemberMenu('dispYeokboxPickLog', '내 댓글 추첨 목록');
 	}
}