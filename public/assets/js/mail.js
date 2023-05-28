let confID = 0,
	confStart,
	confEnd,
	confPin;

function setConf(id, start, end, pin) {
	confID = id;
	confStart = start;
	confEnd = end;
	confPin = pin;

	//On affiche de le formulaire de recherche
	$("#form-mail").show()
}

function search(username) {
	fetch(`/api/search_user?username=${username}`)
		.then(res => res.json())
		.then((data) => {
			let result = $("#results");

			result.append('<option disabled selected>-- Sélectionner une valeur --</option>')

			for(let i in data) {
				if(data.hasOwnProperty(i)) {
					if(data[i].mail !== "") {
						//On ajoute cette personne dans le select
						result.append("<option value='" + data[i].mail + "'>" + data[i].nom + "</option>");
					}
				}
			}
		})
		.catch(err => {
			console.error('Error occured: ', err)
		})
}

function sendForm() {
	//On envoi le formulaire
	let adresses = encodeURI($("#adresses").val());

	document.location.href = `/pdf?id=${confID}&start=${confStart}&end=${confEnd}&pin=${confPin}&sendMail=1&addr=${adresses}`;
}

document.getElementById("srch").addEventListener("keydown", (e) => {
	if(e.key === "Enter") {
		onSearchBtnClick()
	}
})

function onSearchBtnClick() {
	let userToSearch = $("#srch").val();

	$("#results").empty();

	search(userToSearch);
}

function addPerson(val, index) {
	if(index !== 0) {
		document.getElementById("adresses").value += (val.value + ";")
	}
}

function showHelp() {
	introJs().start();
}
