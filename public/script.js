// ===================================
// CHARGEMENT DYNAMIQUE DU MENU RESTAURANT
// ===================================
function loadRestaurantMenu() {
    const defaultMenu = {
        plats: [
            { id: 1, name: 'Couscous royal', price: 1200 },
            { id: 2, name: 'Tajine poulet', price: 900 },
            { id: 3, name: 'Pizza margherita', price: 800 },
            { id: 4, name: 'Poulet grillé', price: 850 }
        ],
        boissons: [
            { id: 1, name: 'Jus d\'orange frais', price: 200 },
            { id: 2, name: 'Coca-Cola', price: 150 },
            { id: 3, name: 'Eau minérale', price: 80 },
            { id: 4, name: 'Café', price: 120 }
        ],
        desserts: [
            { id: 1, name: 'Baklava', price: 250 },
            { id: 2, name: 'Crème caramel', price: 200 },
            { id: 3, name: 'Glace vanille', price: 180 },
            { id: 4, name: 'Fruits frais', price: 300 }
        ]
    };

    const savedMenu = localStorage.getItem('restaurantMenu');
    const menuData = savedMenu ? JSON.parse(savedMenu) : defaultMenu;

    if (!savedMenu) {
        localStorage.setItem('restaurantMenu', JSON.stringify(defaultMenu));
    }

    const platsContainer = document.getElementById('platsMenu');
    if (platsContainer) {
        platsContainer.innerHTML = '';
        menuData.plats.forEach(item => {
            platsContainer.innerHTML += `
                <div class="menu-item">
                    <span class="item-name">${item.name}</span>
                    <span class="item-dots"></span>
                    <span class="price">${item.price} DA</span>
                </div>
            `;
        });
    }

    const boissonsContainer = document.getElementById('boissonsMenu');
    if (boissonsContainer) {
        boissonsContainer.innerHTML = '';
        menuData.boissons.forEach(item => {
            boissonsContainer.innerHTML += `
                <div class="menu-item">
                    <span class="item-name">${item.name}</span>
                    <span class="item-dots"></span>
                    <span class="price">${item.price} DA</span>
                </div>
            `;
        });
    }

    const dessertsContainer = document.getElementById('dessertsMenu');
    if (dessertsContainer) {
        dessertsContainer.innerHTML = '';
        menuData.desserts.forEach(item => {
            dessertsContainer.innerHTML += `
                <div class="menu-item">
                    <span class="item-name">${item.name}</span>
                    <span class="item-dots"></span>
                    <span class="price">${item.price} DA</span>
                </div>
            `;
        });
    }

    console.log('✅ Menu du restaurant chargé depuis localStorage');
}

// ===================================
// ATTENDRE QUE LE DOM SOIT CHARGÉ
// ===================================
document.addEventListener('DOMContentLoaded', function() {

// Charger le menu au démarrage
loadRestaurantMenu();

// ===================================
// SLIDER AUTOMATIQUE
// ===================================
let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const indicators = document.querySelectorAll('.indicator');
const totalSlides = slides.length;

function showSlide(index) {
    slides.forEach(slide => slide.classList.remove('active'));
    indicators.forEach(indicator => indicator.classList.remove('active'));
    
    if (index >= totalSlides) {
        currentSlide = 0;
    } else if (index < 0) {
        currentSlide = totalSlides - 1;
    } else {
        currentSlide = index;
    }
    
    slides[currentSlide].classList.add('active');
    indicators[currentSlide].classList.add('active');
}

function nextSlide() {
    showSlide(currentSlide + 1);
}

function prevSlide() {
    showSlide(currentSlide - 1);
}

let autoSlide = setInterval(nextSlide, 5000);

document.getElementById('nextSlide').addEventListener('click', () => {
    nextSlide();
    clearInterval(autoSlide);
    autoSlide = setInterval(nextSlide, 5000);
});

document.getElementById('prevSlide').addEventListener('click', () => {
    prevSlide();
    clearInterval(autoSlide);
    autoSlide = setInterval(nextSlide, 5000);
});

indicators.forEach((indicator, index) => {
    indicator.addEventListener('click', () => {
        showSlide(index);
        clearInterval(autoSlide);
        autoSlide = setInterval(nextSlide, 5000);
    });
});

// ===================================
// MENU MOBILE TOGGLE
// ===================================
const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');

menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    
    const spans = menuToggle.querySelectorAll('span');
    if (navLinks.classList.contains('active')) {
        spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
    } else {
        spans[0].style.transform = 'none';
        spans[1].style.opacity = '1';
        spans[2].style.transform = 'none';
    }
});

// ===================================
// FERMER LE MENU AU CLIC SUR UN LIEN + ANIMATION GALAXIE
// ===================================
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', (e) => {
        createGalaxyExplosion(e);
        
        setTimeout(() => {
            navLinks.classList.remove('active');
            
            const spans = menuToggle.querySelectorAll('span');
            spans[0].style.transform = 'none';
            spans[1].style.opacity = '1';
            spans[2].style.transform = 'none';
        }, 100);
    });
});

// ===================================
// FONCTION ANIMATION EXPLOSION GALAXIE
// ===================================
function createGalaxyExplosion(e) {
    const colors = [
        '#FF6B35', '#004E89', '#1A659E', '#06D6A0',
        '#FFD23F', '#F72585', '#7209B7', '#3A0CA3'
    ];
    
    const particleCount = 50;
    const clickX = e.clientX;
    const clickY = e.clientY;
    
    console.log('🌟 Explosion galaxie déclenchée à:', clickX, clickY);
    
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'galaxy-particle';
        
        const color = colors[Math.floor(Math.random() * colors.length)];
        particle.style.background = color;
        particle.style.color = color;
        particle.style.position = 'fixed';
        particle.style.left = clickX + 'px';
        particle.style.top = clickY + 'px';
        
        const angle = (Math.PI * 2 * i) / particleCount;
        const velocity = 100 + Math.random() * 150;
        const tx = Math.cos(angle) * velocity;
        const ty = Math.sin(angle) * velocity;
        
        particle.style.setProperty('--tx', tx + 'px');
        particle.style.setProperty('--ty', ty + 'px');
        
        const size = 3 + Math.random() * 5;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        
        document.body.appendChild(particle);
        
        setTimeout(() => particle.remove(), 1500);
    }
    
    createCenterFlash(clickX, clickY);
}

// ===================================
// FLASH LUMINEUX CENTRAL
// ===================================
function createCenterFlash(x, y) {
    const flash = document.createElement('div');
    flash.style.position = 'fixed';
    flash.style.left = x + 'px';
    flash.style.top = y + 'px';
    flash.style.width = '20px';
    flash.style.height = '20px';
    flash.style.borderRadius = '50%';
    flash.style.background = 'radial-gradient(circle, rgba(255,255,255,1) 0%, rgba(255,107,53,0.8) 50%, transparent 100%)';
    flash.style.transform = 'translate(-50%, -50%)';
    flash.style.pointerEvents = 'none';
    flash.style.zIndex = '9999';
    flash.style.animation = 'flashExpand 0.6s ease-out forwards';
    
    document.body.appendChild(flash);
    
    setTimeout(() => flash.remove(), 600);
}

const style = document.createElement('style');
style.textContent = `
    @keyframes flashExpand {
        0% {
            transform: translate(-50%, -50%) scale(0);
            opacity: 1;
        }
        50% {
            opacity: 0.8;
        }
        100% {
            transform: translate(-50%, -50%) scale(20);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// ===================================
// SMOOTH SCROLL POUR NAVIGATION
// ===================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ===================================
// ANIMATIONS AU SCROLL (AOS)
// ===================================
function handleScrollAnimations() {
    const elements = document.querySelectorAll('[data-aos]');
    
    elements.forEach(element => {
        const elementTop = element.getBoundingClientRect().top;
        const elementBottom = element.getBoundingClientRect().bottom;
        const windowHeight = window.innerHeight;
        
        if (elementTop < windowHeight * 0.85 && elementBottom > 0) {
            element.classList.add('aos-animate');
        }
    });
}

window.addEventListener('load', handleScrollAnimations);
window.addEventListener('scroll', handleScrollAnimations);

// ===================================
// HEADER SCROLL EFFECT
// ===================================
let lastScroll = 0;
const header = document.querySelector('header');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    
    if (currentScroll > 100) {
        header.style.boxShadow = '0 8px 24px rgba(0,0,0,0.15)';
        header.style.padding = '0.5rem 0';
    } else {
        header.style.boxShadow = '0 4px 16px rgba(0,0,0,0.1)';
        header.style.padding = '1rem 0';
    }
    
    lastScroll = currentScroll;
});

// ===================================
// PAUSE DU SLIDER AU SURVOL
// ===================================
const sliderContainer = document.querySelector('.slider-container');

sliderContainer.addEventListener('mouseenter', () => {
    clearInterval(autoSlide);
});

sliderContainer.addEventListener('mouseleave', () => {
    autoSlide = setInterval(nextSlide, 5000);
});

// ===================================
// NAVIGATION PAR CLAVIER POUR LE SLIDER
// ===================================
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
        prevSlide();
        clearInterval(autoSlide);
        autoSlide = setInterval(nextSlide, 5000);
    } else if (e.key === 'ArrowRight') {
        nextSlide();
        clearInterval(autoSlide);
        autoSlide = setInterval(nextSlide, 5000);
    }
});

// ===================================
// ANIMATIONS DES CARTES DE SERVICE AU SURVOL
// ===================================
const serviceCards = document.querySelectorAll('.service-card');

serviceCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-10px) scale(1.02)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// ===================================
// LAZY LOADING DES IMAGES
// ===================================
const images = document.querySelectorAll('img[data-src]');

const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            observer.unobserve(img);
        }
    });
});

images.forEach(img => imageObserver.observe(img));

// ===================================
// CONSOLE MESSAGE
// ===================================
console.log('🚗 Station Service & Hôtel - Site web chargé avec succès!');
console.log('✨ Animations actives et slider fonctionnel');
console.log('💥 Animation explosion galaxie activée!');

}); // Fin du DOMContentLoaded