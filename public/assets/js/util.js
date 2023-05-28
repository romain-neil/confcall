function flush() {
	$.get('/cron')
		.done(function (d) {
			t('Succès', 'La purge s\'est correctement effectuée', 'info', '#9EC600')
		})
}

function t(head, texte, ico, bg) {
	$.toast({
		heading: head,
		text: texte,
		icon: ico,
		loader: true,        // Change it to false to disable loader
		loaderBg: bg  // To change the background
	})
}