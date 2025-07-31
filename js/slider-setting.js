let slideIndex = 0;
let slideInterval;

// Initialize slider
function initSlider() {
	showSlides(slideIndex);
	startSlideShow();

	// Add event listeners for manual navigation
	document.querySelectorAll('.prev, .next').forEach(button => {
		button.addEventListener('click', function(e) {
			e.preventDefault();
			if (this.classList.contains('prev')) {
				plusSlides(-1);
			} else {
				plusSlides(1);
			}
			resetInterval();
		});
	});

	// Add event listeners for dots navigation
	document.querySelectorAll('.dot').forEach((dot, index) => {
		dot.addEventListener('click', () => {
			currentSlide(index);
			resetInterval();
		});
	});
}

// Start automatic slideshow
function startSlideShow() {
	slideInterval = setInterval(() => {
		plusSlides(1);
	}, 5000);
}

// Reset interval when manually navigating
function resetInterval() {
	clearInterval(slideInterval);
	startSlideShow();
}

// Next/previous controls
function plusSlides(n) {
	showSlides(slideIndex += n);
}

// Thumbnail image controls
function currentSlide(n) {
	showSlides(slideIndex = n);
}

function showSlides(n) {
	const slides = document.getElementsByClassName("mySlides");
	const dots = document.getElementsByClassName("dot");

	if (n >= slides.length) { slideIndex = 0; }
	if (n < 0) { slideIndex = slides.length - 1; }

	// Hide all slides
	Array.from(slides).forEach(slide => {
		slide.classList.remove('active');
	});

	// Deactivate all dots
	Array.from(dots).forEach(dot => {
		dot.classList.remove('active');
	});

	// Show current slide and activate corresponding dot
	slides[slideIndex].classList.add('active');
	dots[slideIndex].classList.add('active');
}

// Initialize slider when DOM is loaded
document.addEventListener('DOMContentLoaded', initSlider);