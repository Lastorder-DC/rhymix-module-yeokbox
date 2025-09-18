function fixAttendance() {
	if (!confirm('연속 출석 데이터를 재정비합니다. 진행하시겠습니까?')) return false;
	exec_json('yeokbox.procYeokboxAdminFixAttendance', {}, function(ret) {
		console.log(ret);
	});
}