function getPickInfo() {
	var pick_srl = document.getElementById('pick_srl_input').value;
	if (!pick_srl) {
		alert('추첨 번호를 입력해주세요.');
		return;
	}

	exec_json('yeokbox.procYeokboxGetPickInfo', { pick_srl: pick_srl }, function (response) {
		var resultDiv = document.getElementById('pickInfoResult');
		var errorDiv = document.getElementById('pickInfoError');

		if (response.error) {
			resultDiv.style.display = 'none';
			errorDiv.style.display = 'block';
			document.getElementById('pickInfoErrorMsg').textContent = response.message;
			return;
		}

		var info = response.pick_info;
		if (!info) {
			resultDiv.style.display = 'none';
			errorDiv.style.display = 'block';
			document.getElementById('pickInfoErrorMsg').textContent = '추첨 정보가 없습니다.';
			return;
		}

		errorDiv.style.display = 'none';
		resultDiv.style.display = 'block';

		document.getElementById('result_pick_srl').textContent = info.pick_srl;

		var docLink = document.createElement('a');
		var docUrl = new URL(request_uri, window.location.origin);
		docUrl.searchParams.set('document_srl', info.document_srl);
		docLink.href = docUrl.pathname + docUrl.search;
		docLink.textContent = info.document_srl;
		var docCell = document.getElementById('result_document_srl');
		docCell.textContent = '';
		docCell.appendChild(docLink);

		document.getElementById('result_picker_nick_name').textContent = info.picker_nick_name;
		document.getElementById('result_comment_nick_name').textContent = info.comment_nick_name;
		document.getElementById('result_comment_content').textContent = info.comment_content;

		// 20250827121634 형태의 regdate를 YYYY-MM-DD HH:MM:SS 형태로 변환
		var regdate = info.regdate.replace(
			/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,
			'$1-$2-$3 $4:$5:$6'
		);
		document.getElementById('result_regdate').textContent = regdate;
	});
}
