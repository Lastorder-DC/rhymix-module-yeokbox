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
		// response.comment_list중 랜덤한 댓글 1개 선택
		let comment_list = response.comment_list;
		if (comment_list.length === 0) {
			alert('댓글이 없습니다.');
			return;
		}
		let random_index = Math.floor(Math.random() * comment_list.length);
		let random_comment = comment_list[random_index];
		// 20250827121634 형태의 random_comment.regdate를 YYYY-MM-DD HH:MM:SS 형태로 변환
		random_comment.regdate = random_comment.regdate.replace(
			/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,
			'$1-$2-$3 $4:$5:$6'
		);
		// randCommentResult에 출력
		let result_div = document.querySelector('.randCommentResult');
		result_div.innerHTML = `
			<p><strong>축하합니다! 당첨된 댓글입니다!</strong></p>
			<p>작성자: ${random_comment.nick_name}</p>
			<p>내용: ${random_comment.content}</p>
			<p>작성일: ${random_comment.regdate}</p>
			<p>링크 : <u><a href="${current_url}&comment_srl=${random_comment.comment_srl}#comment_${random_comment.comment_srl}" target="_blank">바로가기</a></u></p>
		`;
	});
}
