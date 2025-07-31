<div class="simple-slider">
    <div class="slides">
        <div class="slide">
            <img src="../../images/tshirt-img.png" alt="Slide 1">
        </div>
        <div class="slide">
            <img src="../../images/tshirt-img.png" alt="Slide 2">
        </div>
        <div class="slide">
            <img src="../../images/tshirt-img.png" alt="Slide 3">
        </div>
    </div>
    <button class="slider-button prev" aria-label="Previous slide">‹</button>
    <button class="slider-button next" aria-label="Next slide">›</button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const slider = document.querySelector('.simple-slider');
        const slides = slider.querySelectorAll('.slide');
        const track  = slider.querySelector('.slides');
        const prev   = slider.querySelector('.slider-button.prev');
        const next   = slider.querySelector('.slider-button.next');
        const total  = slides.length;
        let index    = 0;

        function update() {
            track.style.transform = `translateX(-${index * 100}%)`;
        }

        prev.addEventListener('click', () => {
            index = (index - 1 + total) % total;
            update();
        });

        next.addEventListener('click', () => {
            index = (index + 1) % total;
            update();
        });

        // Autoplay (opzionale): scatta ogni 5s
        // setInterval(() => {
        //   next.click();
        // }, 5000);

        // inizializza
        update();
    });
</script>
