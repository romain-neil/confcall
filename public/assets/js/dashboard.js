function verifyDate() {
	var date = new Date(document.getElementById("date").value),
		now = new Date();

	now.setHours(0, 0, 0, 0)

	if(date.getTime() < now.getTime()) {
		$(".err-date").css({display: "block"})
	} else {
		$(".err-date").css({display: "none"})
	}
}
