function create_conf() {
	let date = getVal('date'),
		start = getVal('start'),
		end = getVal('end');

	if(date !== '' && start !== '' && end !== '') {
		$('#btn-submit').attr('disabled', true);
		$.get('/create', {d: date, s: start, e: end})
			.done(function (data) {
				if(data.indexOf("OK") !== -1) {
					location.reload()
				} else {
					$.toast({
						heading: 'Erreur',
						text: 'La conférence est planifiée dans le passé. Merci de rectifier la date',
						position: 'mid-center',
						stack: false,
						hideAfter: 7000,
						icon: 'error'
					})
				}
			});
	}
}

function getVal(el) {
	return $("#" + el).valueOf()[0].value;
}
