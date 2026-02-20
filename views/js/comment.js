// pick random comment
function pickRandomComment(doc_srl) {
	let params = {
		'target_srl': doc_srl
	};

	exec_json('yeokbox.procYeokboxPickComment', params, function (response) {
		if(response.error) {
			alert(response.message);
			return;
		}

		let picked = response.picked_comment;
		if (!picked) {
			alert('추첨 결과가 없습니다.');
			return;
		}

		// 20250827121634 형태의 regdate를 YYYY-MM-DD HH:MM:SS 형태로 변환
		picked.regdate = picked.regdate.replace(
			/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,
			'$1-$2-$3 $4:$5:$6'
		);
		// randCommentResult에 출력
		let result_div = document.querySelector('.randCommentResult');
		result_div.innerHTML = `
			<p><strong>축하합니다! 당첨된 댓글입니다!</strong></p>
			<p>작성자: ${picked.nick_name}</p>
			<p>내용: ${picked.content}</p>
			<p>작성일: ${picked.regdate}</p>
			<p>전체 댓글 수: ${picked.total_comments}개 중 서버 추첨</p>
			<p>추첨 번호: ${picked.pick_srl}</p>
			<p>링크 : <u><a href="${current_url}&comment_srl=${picked.comment_srl}#comment_${picked.comment_srl}" target="_blank">바로가기</a></u></p>
		`;
	});
}
