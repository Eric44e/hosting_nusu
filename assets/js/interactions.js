/* ============================================================
   Interactive Effects & Microinteractions
   ============================================================ */

// ── SCROLL REVEAL ANIMATION ──────────────────────────────
const ScrollReveal = {
  observe() {
    if (!('IntersectionObserver' in window)) return;
    
    const elements = document.querySelectorAll('.card, .stat-card, .fade-in, [data-scroll-reveal]');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    
    elements.forEach(el => {
      el.classList.add('scroll-reveal');
      observer.observe(el);
    });
  }
};

// ── ANIMATED COUNTER ─────────────────────────────────────
const Counter = {
  animate(element, target, duration = 2000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        element.textContent = target.toLocaleString();
        clearInterval(timer);
      } else {
        element.textContent = Math.floor(current).toLocaleString();
      }
    }, 16);
  },
  
  animateAll() {
    document.querySelectorAll('[data-counter]').forEach(el => {
      const target = parseInt(el.getAttribute('data-counter')) || 0;
      const prefix = el.getAttribute('data-prefix') || '';
      const originalText = el.textContent;
      
      const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
          this.animate(el, target);
          observer.unobserve(el);
        }
      });
      observer.observe(el);
    });
  }
};

// ── SMOOTH SCROLL ────────────────────────────────────────
const SmoothScroll = {
  init() {
    document.addEventListener('click', (e) => {
      const target = e.target.closest('a[href^="#"]');
      if (!target) return;
      
      e.preventDefault();
      const id = target.getAttribute('href').substring(1);
      const element = document.getElementById(id);
      
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  }
};

// ── RIPPLE EFFECT ────────────────────────────────────────
const Ripple = {
  init() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('button, a, .btn');
      if (!btn) return;
      
      const rect = btn.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      const ripple = document.createElement('span');
      ripple.style.position = 'absolute';
      ripple.style.left = x + 'px';
      ripple.style.top = y + 'px';
      ripple.style.width = '0';
      ripple.style.height = '0';
      ripple.style.borderRadius = '50%';
      ripple.style.background = 'rgba(255,255,255,0.5)';
      ripple.style.pointerEvents = 'none';
      ripple.style.animation = 'rippleEffect 0.6s ease-out';
      
      if (btn.style.position !== 'absolute' && btn.style.position !== 'fixed' && btn.style.position !== 'relative') {
        btn.style.position = 'relative';
      }
      
      btn.appendChild(ripple);
      setTimeout(() => ripple.remove(), 600);
    });
    
    // Add ripple keyframes if not already present
    if (!document.querySelector('style[data-ripple]')) {
      const style = document.createElement('style');
      style.setAttribute('data-ripple', '1');
      style.textContent = `
        @keyframes rippleEffect {
          to { width: 300px; height: 300px; opacity: 0; }
        }
      `;
      document.head.appendChild(style);
    }
  }
};

// ── PARTICLE EFFECT ──────────────────────────────────────
const Particles = {
  create(x, y, color = '#1a6cff') {
    const particle = document.createElement('div');
    particle.style.position = 'fixed';
    particle.style.left = x + 'px';
    particle.style.top = y + 'px';
    particle.style.width = '6px';
    particle.style.height = '6px';
    particle.style.background = color;
    particle.style.borderRadius = '50%';
    particle.style.pointerEvents = 'none';
    particle.style.animation = 'particleFloat 2s ease-out forwards';
    particle.style.zIndex = '9999';
    
    document.body.appendChild(particle);
    
    setTimeout(() => particle.remove(), 2000);
  },
  
  burst(x, y, count = 8, color = '#1a6cff') {
    for (let i = 0; i < count; i++) {
      const angle = (360 / count) * i;
      const rad = (angle * Math.PI) / 180;
      this.create(x, y, color);
    }
  },
  
  init() {
    // Add particle animation if not present
    if (!document.querySelector('style[data-particles]')) {
      const style = document.createElement('style');
      style.setAttribute('data-particles', '1');
      style.textContent = `
        @keyframes particleFloat {
          to {
            opacity: 0;
            transform: translate(var(--tx, 0), var(--ty, -50px));
          }
        }
      `;
      document.head.appendChild(style);
    }
  }
};

// ── HOVER CARD TILT ──────────────────────────────────────
const CardTilt = {
  init() {
    document.addEventListener('mousemove', (e) => {
      document.querySelectorAll('[data-tilt]').forEach(card => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left - rect.width / 2;
        const y = e.clientY - rect.top - rect.height / 2;
        
        const rotateX = (y / rect.height) * 5;
        const rotateY = (x / rect.width) * -5;
        
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.01)`;
      });
    });
    
    document.addEventListener('mouseleave', () => {
      document.querySelectorAll('[data-tilt]').forEach(card => {
        card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
      });
    });
  }
};

// ── DYNAMIC BACKGROUND GRID ─────────────────────────────
const BackgroundGrid = {
  create() {
    const canvas = document.createElement('canvas');
    canvas.id = 'bg-grid';
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '-1';
    canvas.style.opacity = '0.05';
    
    document.body.insertBefore(canvas, document.body.firstChild);
    
    const ctx = canvas.getContext('2d');
    const resizeCanvas = () => {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    };
    
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    const drawGrid = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.strokeStyle = '#1a6cff';
      ctx.lineWidth = 0.5;
      
      const size = 50;
      for (let x = 0; x < canvas.width; x += size) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, canvas.height);
        ctx.stroke();
      }
      
      for (let y = 0; y < canvas.height; y += size) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(canvas.width, y);
        ctx.stroke();
      }
    };
    
    drawGrid();
  }
};

// ── TOAST NOTIFICATIONS ──────────────────────────────────
const Toast = {
  container: null,
  
  init() {
    this.container = document.createElement('div');
    this.container.id = 'toast-container';
    this.container.style.position = 'fixed';
    this.container.style.top = '20px';
    this.container.style.right = '20px';
    this.container.style.zIndex = '99999';
    this.container.style.pointerEvents = 'none';
    document.body.appendChild(this.container);
  },
  
  show(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.style.background = this._getColor(type);
    toast.style.color = '#fff';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '8px';
    toast.style.marginBottom = '10px';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    toast.style.animation = 'slideInRight 0.3s ease';
    toast.style.fontSize = '0.875rem';
    toast.style.pointerEvents = 'all';
    toast.style.cursor = 'pointer';
    toast.textContent = message;
    
    this.container.appendChild(toast);
    
    setTimeout(() => {
      toast.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },
  
  _getColor(type) {
    const colors = {
      success: '#22c55e',
      error: '#ef4444',
      warning: '#f59e0b',
      info: '#1a6cff'
    };
    return colors[type] || colors.info;
  }
};

// ── LOADING OVERLAY ──────────────────────────────────────
const LoadingOverlay = {
  show(message = 'Loading...') {
    let overlay = document.getElementById('loading-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'loading-overlay';
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(0,0,0,0.7)';
      overlay.style.display = 'flex';
      overlay.style.alignItems = 'center';
      overlay.style.justifyContent = 'center';
      overlay.style.zIndex = '99998';
      overlay.style.backdropFilter = 'blur(2px)';
      
      overlay.innerHTML = `
        <div style="text-align:center;color:#fff">
          <div style="width:40px;height:40px;border:3px solid rgba(255,255,255,0.2);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 15px"></div>
          <p style="font-size:0.9rem">${message}</p>
        </div>
      `;
      document.body.appendChild(overlay);
    }
    overlay.style.display = 'flex';
  },
  
  hide() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.style.display = 'none';
  }
};

// ── PAGE TRANSITION EFFECTS ──────────────────────────────
const PageTransition = {
  init() {
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a:not([target="_blank"]):not([href^="#"])');
      if (!link || link.classList.contains('no-transition')) return;
      
      const href = link.getAttribute('href');
      if (!href || href.startsWith('javascript:')) return;
      
      e.preventDefault();
      
      const transition = document.createElement('div');
      transition.style.position = 'fixed';
      transition.style.inset = '0';
      transition.style.background = 'var(--primary)';
      transition.style.zIndex = '99997';
      transition.style.animation = 'slideOutRight 0.3s ease';
      transition.style.transformOrigin = 'left';
      
      document.body.appendChild(transition);
      
      setTimeout(() => {
        window.location.href = href;
      }, 300);
    });
  }
};

// ── FLOATING ACTION BUTTONS ──────────────────────────────
const FAB = {
  init() {
    document.querySelectorAll('[data-fab]').forEach(fab => {
      fab.style.position = 'relative';
      fab.addEventListener('click', () => {
        fab.classList.toggle('active');
        fab.style.animation = 'buttonBounce 0.5s ease';
      });
    });
  }
};

// ── KEYBOARD SHORTCUTS ───────────────────────────────────
const Shortcuts = {
  init() {
    document.addEventListener('keydown', (e) => {
      // Cmd/Ctrl + K for search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-bar input');
        if (searchInput) searchInput.focus();
      }
      
      // Escape to close modals
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(modal => {
          modal.classList.remove('open');
        });
      }
    });
  }
};

// ── INITIALIZE ALL EFFECTS ───────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  ScrollReveal.observe();
  Counter.animateAll();
  SmoothScroll.init();
  Ripple.init();
  Particles.init();
  CardTilt.init();
  Toast.init();
  Shortcuts.init();
  FAB.init();
  PageTransition.init();
  
  // Optional: Uncomment to enable background grid
  // BackgroundGrid.create();
});

// Export for external use
window.Effects = {
  ScrollReveal,
  Counter,
  Ripple,
  Particles,
  Toast,
  LoadingOverlay,
  PageTransition,
  CardTilt
};
