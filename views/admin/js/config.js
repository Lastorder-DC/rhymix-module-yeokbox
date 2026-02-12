function fixAttendance() {
	const confirmed = confirm('연속 출석 데이터를 재정비합니다. 진행하시겠습니까?');
	if (!confirmed) {
		return false;
	}

	exec_json('yeokbox.procYeokboxAdminFixAttendance', {}, function (ret) {
		if (ret && ret.error) {
			console.error('연속 출석 재정비 실패', ret);
			return;
		}

		console.info('연속 출석 재정비 완료', ret);
	});

	return true;
}
