// Carosello automatico delle foto
let currentSlide = 0;
const slides = document.querySelectorAll('.photo-slide');
const indicators = document.querySelectorAll('.indicator');
const totalSlides = slides.length;

function showSlide(index) {
    // Rimuovi classe active da tutti gli slide e indicatori
    slides.forEach(slide => slide.classList.remove('active'));
    indicators.forEach(indicator => indicator.classList.remove('active'));

    // Aggiungi classe active al slide e indicatore corrente
    slides[index].classList.add('active');
    indicators[index].classList.add('active');

    currentSlide = index;
}

function nextSlide() {
    const next = (currentSlide + 1) % totalSlides;
    showSlide(next);
}

function goToSlide(index) {
    showSlide(index);
}

// Avvia il carosello automatico
setInterval(nextSlide, 4000); // Cambia slide ogni 4 secondi

// Slider prodotti
const sliders = {};

function initSlider(sliderId) {
    const slider = document.getElementById(sliderId);
    if (!slider) return;

    const items = slider.querySelectorAll('.item');
    const containerWidth = slider.parentElement.offsetWidth - 120; // Sottrae spazio per i bottoni
    const firstItem = items[0];
    const itemStyles = window.getComputedStyle(firstItem);
    const itemWidth = firstItem.offsetWidth + parseInt(itemStyles.marginRight || 0); // larghezza reale con margine
    const visibleItems = Math.floor(containerWidth / itemWidth);


    sliders[sliderId] = {
        currentIndex: 0,
        totalItems: items.length,
        visibleItems: Math.max(1, visibleItems),
        maxIndex: Math.max(0, items.length - Math.max(1, visibleItems)),
        itemWidth: itemWidth
    };

    // Reset della posizione
    slider.style.transform = 'translateX(0px)';
    sliders[sliderId].currentIndex = 0;

    updateSliderButtons(sliderId);

    console.log(`Slider ${sliderId} initialized:`, sliders[sliderId]);
}

function slideProducts(sliderId, direction) {
    const sliderData = sliders[sliderId];
    if (!sliderData) {
        console.log(`Slider data not found for ${sliderId}`);
        return;
    }

    const slider = document.getElementById(sliderId);
    if (!slider) {
        console.log(`Slider element not found: ${sliderId}`);
        return;
    }

    const newIndex = sliderData.currentIndex + direction;

    console.log(`Sliding ${sliderId}: current=${sliderData.currentIndex}, new=${newIndex}, max=${sliderData.maxIndex}`);

    if (newIndex >= 0 && newIndex <= sliderData.maxIndex) {
        sliderData.currentIndex = newIndex;
        const translateX = -(newIndex * sliderData.itemWidth);
        slider.style.transform = `translateX(${translateX}px)`;

        updateSliderButtons(sliderId);
        console.log(`Slider moved to position: ${translateX}px`);
    } else {
        console.log(`Movement blocked: newIndex=${newIndex}, maxIndex=${sliderData.maxIndex}`);
    }
}

function updateSliderButtons(sliderId) {
    const sliderData = sliders[sliderId];
    if (!sliderData) return;

    const prevBtn = document.getElementById(sliderId.replace('Slider', 'Prev'));
    const nextBtn = document.getElementById(sliderId.replace('Slider', 'Next'));

    if (prevBtn) {
        prevBtn.disabled = sliderData.currentIndex === 0;
        prevBtn.style.opacity = sliderData.currentIndex === 0 ? '0.3' : '1';
    }

    if (nextBtn) {
        nextBtn.disabled = sliderData.currentIndex >= sliderData.maxIndex;
        nextBtn.style.opacity = sliderData.currentIndex >= sliderData.maxIndex ? '0.3' : '1';
    }

    console.log(`Buttons updated for ${sliderId}: prev disabled=${sliderData.currentIndex === 0}, next disabled=${sliderData.currentIndex >= sliderData.maxIndex}`);
}

// Inizializza gli slider al caricamento della pagina
document.addEventListener('DOMContentLoaded', function() {
    initSlider('mysteryBoxSlider');
    initSlider('oggettiSlider');

    // Reinizializza gli slider quando la finestra viene ridimensionata
    window.addEventListener('resize', function() {
        setTimeout(() => {
            initSlider('mysteryBoxSlider');
            initSlider('oggettiSlider');
        }, 100);
    });
});

// Supporto per il touch sui dispositivi mobili
let startX = 0;
let currentX = 0;
let activeSlider = null;

document.addEventListener('touchstart', function(e) {
    const slider = e.target.closest('.product-slider');
    if (slider) {
        startX = e.touches[0].clientX;
        activeSlider = slider.id;
    }
});

document.addEventListener('touchmove', function(e) {
    if (!activeSlider) return;
    e.preventDefault();
    currentX = e.touches[0].clientX;
});

document.addEventListener('touchend', function(e) {
    if (!activeSlider) return;

    const diffX = startX - currentX;
    const threshold = 50;

    if (Math.abs(diffX) > threshold) {
        if (diffX > 0) {
            slideProducts(activeSlider, 1); // Slide a destra
        } else {
            slideProducts(activeSlider, -1); // Slide a sinistra
        }
    }

    activeSlider = null;
});