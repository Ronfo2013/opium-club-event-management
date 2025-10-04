
document.addEventListener('DOMContentLoaded', () => {
    // Animazione di ingresso del form
    gsap.from('.form-container', {
        opacity: 0,
        y: 50,
        duration: 1,
        ease: 'power3.out'
    });

    // Animazioni per i campi del form al caricamento
    gsap.from('.form-container input, .form-container select, .form-container button', {
        opacity: 0,
        y: 20,
        duration: 0.5,
        ease: 'power2.out',
        stagger: 0.2
    });

    // Effetto hover sui pulsanti
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            gsap.to(button, { scale: 1.1, duration: 0.3, ease: 'power2.out' });
        });
        button.addEventListener('mouseleave', () => {
            gsap.to(button, { scale: 1, duration: 0.3, ease: 'power2.in' });
        });
    });
});
document.addEventListener('DOMContentLoaded', () => {
    const hamburgerMenu = document.querySelector('.hamburger-menu');
    const mobileMenu = document.querySelector('.mobile-menu');

    hamburgerMenu.addEventListener('click', () => {
        mobileMenu.style.display = mobileMenu.style.display === 'block' ? 'none' : 'block';
    });
});