let slideIndex = 0;

// 1) mostro subito
showOne(slideIndex);

// 2) avvio il ciclo automatico con delay
setTimeout(nextSlide, 10000);

function nextSlide() {
	// nascondo l’attuale
	showOne(slideIndex, true);

	// incremento e “avvolgo”
	slideIndex = (slideIndex + 1) % document.getElementsByClassName("mySlides").length;

	// mostro la nuova
	showOne(slideIndex);

	// richiamo dopo 10 s
	setTimeout(nextSlide, 10000);
}

// helper: mostra solo slides[i], opzionalmente nasconde tutte prima
function showOne(i, hideAll = false) {
	const slides = document.getElementsByClassName("mySlides");
	if (hideAll) {
		for (let s of slides) s.style.display = 'none';
	}
	slides[i].style.display = 'block';
}
