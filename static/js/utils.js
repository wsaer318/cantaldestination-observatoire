// Fonctions utilitaires partagées entre les fichiers JavaScript

// Centralised debug logging utility (disabled by default)
if (typeof window !== 'undefined') {
  window.FV_DEBUG_LOGS = Boolean(window.FV_DEBUG_LOGS);
  const fvConsole = window.console || {};
  window.fvLog = function(...args) {
    if (window.FV_DEBUG_LOGS && typeof fvConsole.log === 'function') {
      fvConsole.log(...args);
    }
  };
  window.fvInfo = function(...args) {
    if (window.FV_DEBUG_LOGS && typeof fvConsole.info === 'function') {
      fvConsole.info(...args);
    }
  };
  window.fvWarn = function(...args) {
    if (window.FV_DEBUG_LOGS && typeof fvConsole.warn === 'function') {
      fvConsole.warn(...args);
    }
  };
}

// Configuration par défaut des particules
const particlesConfig = {
  fullScreen: { enable: true, zIndex: 2 },
  background: { color: { value: "transparent" } },
  fpsLimit: 60,
  particles: {
    number: { value: 15, density: { enable: true, area: 400 } },
    color: { 
      value: ["#F1C40F", "#1ABC9C", "#9B59B6", "#3498DB", "#E74C3C", "#F9E79F", "#58D68D", "#BB8FCE"] 
    },
    shape: { type: "circle" },
    opacity: {
      value: { min: 0, max: 0.6 },
      random: { enable: true, minimumValue: 0 },
      animation: { enable: true, startValue: "min", speed: 1, sync: false }
    },
    size: { value: { min: 0.5, max: 3 }, random: true },
    move: { 
      enable: true, 
      speed: { min: 0.25, max: 1 }, 
      direction: "none", 
      random: true, 
      straight: false, 
      outModes: { default: "out" } 
    },
    position: { x: { min: 0, max: 100 }, y: { min: 0, max: 100 } },
    links: {
      enable: true,
      distance: 120,
      color: "random",
      opacity: 0.3,
      width: 1
    },
    twinkle: {
      particles: {
        enable: true,
        frequency: 0.05,
        opacity: 1
      }
    }
  },
  detectRetina: true,
  interactivity: {
    events: {
      onHover: {
        enable: true,
        mode: "grab"
      },
      onClick: {
        enable: false,
        mode: "push"
      }
    },
    modes: {
      grab: {
        distance: 140,
        links: {
          opacity: 0.5,
          color: "#ffffff"
        }
      },
      push: {
        quantity: 3
      }
    }
  }
};

// Fonction pour initialiser les particules
function initParticles(elementId = "tsparticles", config = {}) {
  // Fusionner la configuration par défaut avec les options personnalisées
  const mergedConfig = { ...particlesConfig, ...config };
  return tsParticles.load(elementId, mergedConfig);
}

// Fonction pour animer l'entrée des éléments
function animateEntrance() {
  const sidebar = document.querySelector('.sidebar');
  const content = document.querySelector('.content');
  const header = document.querySelector('header');
  const title = document.querySelector('.header-title');
  const subtitle = document.querySelector('.header-subtitle');
  const badges = document.querySelectorAll('.destination-badge');
  
  if (sidebar) {
    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(-40px)';
  }
  
  if (content) {
    content.style.opacity = '0';
    content.style.transform = 'translateX(40px)';
  }
  
  if (header) {
    header.style.opacity = '0';
    header.style.transform = 'translateY(-20px)';
  }
  
  if (title) title.style.opacity = '0';
  if (subtitle) subtitle.style.opacity = '0';
  
  if (badges) {
    badges.forEach(badge => {
      badge.style.opacity = '0';
      badge.style.transform = 'translateY(20px)';
    });
  }
  
  // Animation séquentielle avec des délais différents
  setTimeout(() => {
    if (sidebar) {
      sidebar.style.transition = 'all 0.8s cubic-bezier(0.19, 1, 0.22, 1)';
      sidebar.style.opacity = '1';
      sidebar.style.transform = 'translateX(0)';
    }
    
    if (header) {
      setTimeout(() => {
        header.style.transition = 'all 0.7s cubic-bezier(0.19, 1, 0.22, 1)';
        header.style.opacity = '1';
        header.style.transform = 'translateY(0)';
        
        setTimeout(() => {
          if (title) {
            title.style.transition = 'all 0.6s cubic-bezier(0.19, 1, 0.22, 1)';
            title.style.opacity = '1';
          }
          
          setTimeout(() => {
            if (subtitle) {
              subtitle.style.transition = 'all 0.6s cubic-bezier(0.19, 1, 0.22, 1)';
              subtitle.style.opacity = '1';
            }
            
            // Animer les badges un par un
            if (badges) {
              badges.forEach((badge, index) => {
                setTimeout(() => {
                  badge.style.transition = 'all 0.5s cubic-bezier(0.19, 1, 0.22, 1)';
                  badge.style.opacity = '1';
                  badge.style.transform = 'translateY(0)';
                }, index * 150);
              });
            }
          }, 200);
        }, 200);
      }, 200);
    }
    
    if (content) {
      setTimeout(() => {
        content.style.transition = 'all 0.7s cubic-bezier(0.19, 1, 0.22, 1)';
        content.style.opacity = '1';
        content.style.transform = 'translateX(0)';
      }, 300);
    }
    
  }, 100);
  
  // Animer les formes géométriques
  const shapes = document.querySelectorAll('.geometric-shape, .accent-shape');
  if (shapes) {
    shapes.forEach((shape, index) => {
      shape.style.opacity = '0';
      shape.style.transform = 'scale(0.5)';
      
      setTimeout(() => {
        shape.style.transition = 'all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
        shape.style.opacity = '0.1';
        shape.style.transform = 'scale(1)';
      }, 800 + index * 100);
    });
  }
}

// Fonction pour initialiser les animations basées sur le défilement
function initScrollAnimations() {
  // Créer un observateur d'intersection pour détecter quand les éléments sont visibles
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        // Appliquer une classe d'animation aléatoire
        const animations = [
          'animate-float-in',
          'animate-scale-in',
          'animate-slide-in-right',
          'animate-slide-in-left',
          'animate-rotate-in',
          'animate-bounce-in'
        ];
        const randomAnimation = animations[Math.floor(Math.random() * animations.length)];
        entry.target.classList.add(randomAnimation);
        // Rétablir opacité et transform pour que l'animation soit visible
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'none';
        // Arrêter d'observer l'élément
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  
  // Observer les éléments à animer
  const elementsToObserve = document.querySelectorAll('.section-content, .paragraphe, table, .remarque, ul.nested-list li, ol > li, .home-section, .feature-card, .quick-link-card, .stats-card');
  elementsToObserve.forEach(element => {
    element.style.opacity = '0';
    observer.observe(element);
  });
}

// Gestionnaire d'animations au survol
function handleHoverAnimations(e) {
  const target = e.target;
  
  // Effet de vague pour les éléments cliquables au survol
  if (target.matches('.btn, #fiches-list li, .section-title, .nav-link')) {
    const ripple = document.createElement('span');
    ripple.classList.add('hover-ripple');
    
    // Positionner la vague là où se trouve le pointeur
    const rect = target.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    ripple.style.left = `${x}px`;
    ripple.style.top = `${y}px`;
    
    target.appendChild(ripple);
    
    // Supprimer l'élément de vague après l'animation
    setTimeout(() => {
      ripple.remove();
    }, 1000);
  }
}

// Fonction pour formatter les nombres avec séparateur de milliers
function formatNumber(number) {
  if (number === undefined || number === null) return '';
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

// Exporter les fonctions pour les rendre accessibles
window.utils = {
  initParticles,
  animateEntrance,
  initScrollAnimations,
  handleHoverAnimations,
  formatNumber
}; 