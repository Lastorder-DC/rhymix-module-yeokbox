<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

use Rhymix\Modules\Yeokbox\Models\Config as ConfigModel;
use BaseObject;
use Context;
use DateTime;

/**
 * 역박스 커스텀
 * 
 * Copyright (c) Lastorder-DC
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Admin extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		// 관리자 화면 템플릿 경로 지정
		$this->setTemplatePath($this->module_path . 'views/admin/');
	}
	
	/**
	 * 관리자 설정 화면 예제
	 */
	public function dispYeokboxAdminConfig()
	{
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
		
		// Context에 세팅
		Context::set('yeokbox_config', $config);

		$args = new \stdClass();
		$args->member_srl = $config->yeokka_member_srl;
		$output = executeQuery('yeokbox.getFriendList', $args, ['member.member_srl', 'nick_name']);
		
		// 스킨 파일 지정
		$this->setTemplateFile('config');
	}
	
	/**
	 * 관리자 설정 저장 액션 예제
	 */
	public function procYeokboxAdminInsertConfig()
	{
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
		
		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();
		$yeokka_member_srl = intval($vars->yeokka_member_srl);
		
		// 제출받은 데이터를 각각 적절히 필터링하여 설정 변경
		if (!\MemberModel::getMemberInfoByMemberSrl($yeokka_member_srl))
		{
			return new BaseObject(-1, 'msg_yeokbox_invalid_member_srl');
		}
		else
		{
			$config->yeokka_member_srl = $yeokka_member_srl;
		}
		$config->vote_count = intval($vars->vote_count);
		
		// 변경된 설정을 저장
		$output = ConfigModel::setConfig($config);
		if (!$output->toBool())
		{
			return $output;
		}
		
		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

	public function procYeokboxAdminFixAttendance() {
		$vars = Context::getRequestVars();
		$member_srl = intval($vars->member_srl);

		// member 모듈의 getMembers 쿼리 호출
		$args = new \stdClass();
		$output = executeQueryArray('member.getMembers', $args);
		if (!$output->toBool()) {
			return $output;
		}
		$member_list = array_column($output->data, 'member_srl');

		$debugArray = [];

		// 모든 회원에 대해 연속 출석 일수 재계산
		foreach ($member_list as $member_srl) {
			$args = new \stdClass();
			$args->member_srl = $member_srl;
			$output = executeQueryArray('attendance.getAttendanceDataMemberSrl', $args);

			// 쿼리 실패 시 다음 회원으로 넘어감
			if (!$output->toBool()) {
				continue;
			}

			// 출석 기록이 없으면 다음 회원으로 넘어감
			if(empty($output->data)) {
				continue;
			}
			
			// 가장 최근 출석일이 오늘이 아니면 다음 회원으로 넘어감
			if($output->data[0]->regdate != date('Ymd')) {
				continue;
			}

			// 연속 출석 일수 계산
			$consecutive_days = calculateConsecutiveAttendance($output->data, date('Ymd'));

			// DB상 연속 출석일을 가져옴
			$oAttendanceModel = getModel('attendance');
			$data = $oAttendanceModel->getContinuityDataByMemberSrl($member_srl, date('Ymd'));
			if($data->continuity >= $consecutive_days) {
				// 이미 연속 출석 일수가 같거나 크면 다음 회원으로 넘어감
				continue;
			}

			$debugArray['att_' . $member_srl] = $data->continuity . '->' . $consecutive_days;

			// 연속 출석 일수 업데이트
			$args = new \stdClass();
			$args->member_srl = $member_srl;
			$args->continuity = $consecutive_days;
			$args->regdate = date('Ymd').'235959';
			$output = executeQuery('attendance.updateTotal', $args);
			if (!$output->toBool()) {
				continue;
			}

			$oAttendanceModel = getModel('attendance');
			$oAttendanceModel->clearCacheByMemberSrl($member_srl);
		}

		$this->add('attendance_debug', $debugArray);
		$this->add('attendance_debug_count', count($debugArray));
	}
}

/**
 * 특정 날짜를 기준으로 연속 출석 일수를 계산합니다.
 *
 * @param string $jsonString 출석 기록이 담긴 JSON 데이터 문자열
 * @param string $todayDateString 기준 날짜 ('Ymd' 형식, 예: '20250828')
 * @return int 연속 출석 일수
 */
function calculateConsecutiveAttendance($data, $todayDateString) {
    // 1. 검색 효율을 위해 모든 출석 날짜('regdate')만 추출하여 새 배열을 만듭니다.
    // in_array() 함수는 큰 배열에서 검색할 때 array_search()보다 효율적일 수 있습니다.
    $attendanceDates = array_column($data, 'regdate');
    $attendanceDateSet = array_flip($attendanceDates); // 값과 키를 뒤집어 검색 속도를 O(1)로 만듭니다.

    // 2. 기준 날짜로부터 루프를 시작합니다.
    $consecutiveDays = 0;
    // DateTime 객체를 사용하여 날짜를 하루씩 쉽게 빼도록 합니다.
    $currentDate = DateTime::createFromFormat('Ymd', $todayDateString);

    if (!$currentDate) {
        return 0; // 날짜 형식이 잘못된 경우
    }

    while (true) {
        // 현재 날짜를 'Ymd' 형식의 문자열로 변환합니다.
        $dateToCheck = $currentDate->format('Ymd');

        // 4. 출석 기록에 해당 날짜가 있는지 확인합니다.
        if (isset($attendanceDateSet[$dateToCheck])) {
            // 출석한 경우: 카운터를 1 증가시키고, 날짜를 하루 전으로 이동합니다.
            $consecutiveDays++;
            $currentDate->modify('-1 day');
        } else {
            // 출석하지 않은 경우: 루프를 중단합니다.
            break;
        }
    }

    // 5. 계산된 연속 출석 일수를 반환합니다.
    return $consecutiveDays;
}