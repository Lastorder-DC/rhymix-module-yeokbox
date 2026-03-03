<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

use Rhymix\Modules\Yeokbox\Models\Config as ConfigModel;
use BaseObject;
use Context;
use DateTime;
use MemberModel;
use stdClass;
use attendanceModel;

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
	 * 출석 연속일을 계산할 때 사용하는 날짜 포맷.
	 */
	private const DATE_FORMAT = 'Ymd';

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
		if (!MemberModel::getMemberInfoByMemberSrl($yeokka_member_srl))
		{
			return new BaseObject(-1, 'msg_yeokbox_invalid_member_srl');
		}
		else
		{
			$config->yeokka_member_srl = $yeokka_member_srl;
		}
		$config->vote_count = intval($vars->vote_count);
		$config->super_vote_count = intval($vars->super_vote_count);
		$config->read_count = intval($vars->read_count);
		
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

	/**
	 * 모든 회원의 오늘 기준 연속 출석 일수를 재계산합니다.
	 *
	 * @return BaseObject|void
	 */
	public function procYeokboxAdminFixAttendance()
	{
		$today = date(self::DATE_FORMAT);

		// member 모듈의 getMembers 쿼리 호출
		$args = new stdClass();
		$output = executeQueryArray('member.getMembers', $args);
		if (!$output->toBool()) {
			return $output;
		}
		$member_list = array_column($output->data, 'member_srl');

		$debugArray = [];

		// 모든 회원에 대해 연속 출석 일수 재계산
		foreach ($member_list as $member_srl) {
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$output = executeQueryArray('attendance.getAttendanceDataMemberSrl', $args);

			// 쿼리 실패 시 다음 회원으로 넘어감
			if (!$output->toBool()) {
				continue;
			}

			// 출석 기록이 없으면 다음 회원으로 넘어감
			if (empty($output->data)) {
				continue;
			}
			
			// 가장 최근 출석일이 오늘이 아니면 다음 회원으로 넘어감
			if ($output->data[0]->regdate !== $today) {
				continue;
			}

			// 연속 출석 일수 계산
			$consecutive_days = $this->calculateConsecutiveAttendance($output->data, $today);

			// DB상 연속 출석일을 가져옴
			$oAttendanceModel = attendanceModel::getInstance();
			$data = $oAttendanceModel->getContinuityDataByMemberSrl($member_srl, $today);
			if ($data->continuity >= $consecutive_days) {
				// 이미 연속 출석 일수가 같거나 크면 다음 회원으로 넘어감
				continue;
			}

			$debugArray['att_' . $member_srl] = $data->continuity . '->' . $consecutive_days;

			// 연속 출석 일수 업데이트
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$args->continuity = $consecutive_days;
			$args->regdate = $today . '235959';
			$output = executeQuery('attendance.updateTotal', $args);
			if (!$output->toBool()) {
				continue;
			}

			$oAttendanceModel = attendanceModel::getInstance();
			$oAttendanceModel->clearCacheByMemberSrl($member_srl);
		}

		$this->add('attendance_debug', $debugArray);
		$this->add('attendance_debug_count', count($debugArray));
	}

	/**
	 * 특정 날짜를 기준으로 연속 출석 일수를 계산합니다.
	 *
	 * @param array<int, object> $attendanceData 출석 데이터 목록
	 * @param string             $todayDate      기준 날짜 (Ymd)
	 * @return int
	 */
	private function calculateConsecutiveAttendance(array $attendanceData, string $todayDate): int
	{
		$attendanceDateSet = array_flip(array_column($attendanceData, 'regdate'));
		$currentDate = DateTime::createFromFormat(self::DATE_FORMAT, $todayDate);

		if (!$currentDate) {
			return 0;
		}

		$consecutiveDays = 0;
		while (isset($attendanceDateSet[$currentDate->format(self::DATE_FORMAT)])) {
			$consecutiveDays++;
			$currentDate->modify('-1 day');
		}

		return $consecutiveDays;
	}
}
