// Counter Animation (animate every time visible)
const counters = document.querySelectorAll('.counter');
const counterOptions = { threshold: 0.5 };

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if(entry.isIntersecting){
      const counter = entry.target;
      const target = +counter.getAttribute('data-target');
      let count = 0;
      const increment = target / 200;
      const update = () => {
        count += increment;
        if(count < target){
          counter.innerText = Math.ceil(count);
          requestAnimationFrame(update);
        } else {
          counter.innerText = target;
        }
      }
      update();
    }
  });
}, counterOptions);

counters.forEach(counter => counterObserver.observe(counter));


// Fade-in sections on scroll (every time)
const sections = document.querySelectorAll('section');
const sectionObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if(entry.isIntersecting){
      entry.target.style.opacity = 1;
      entry.target.style.transform = 'translateY(0)';
    } else {
      // Reset when out of view for repeat animation
      entry.target.style.opacity = 0;
      entry.target.style.transform = 'translateY(50px)';
    }
  });
}, { threshold: 0.1 });

sections.forEach(section => {
  section.style.opacity = 0;
  section.style.transform = 'translateY(50px)';
  section.style.transition = 'all 0.8s ease-out';
  sectionObserver.observe(section);
});
const slider = document.getElementById('officialsSlider')
const cards = Array.from(slider.children)
cards.forEach(card => slider.appendChild(card.cloneNode(true)))
let scrollAmount = 0
function autoSlide() {
    scrollAmount += 1
    if (scrollAmount >= slider.scrollWidth / 2) scrollAmount = 0
    slider.scrollLeft = scrollAmount
    requestAnimationFrame(autoSlide)
}
autoSlide()
