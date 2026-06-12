/**
 * Dashboard Interactive Features - TopCV Style
 */

document.addEventListener('DOMContentLoaded', function() {
  
  // ========================================
  // 1. Counter Animation for Stats
  // ========================================
  function animateCounter(element) {
    const target = parseInt(element.getAttribute('data-target') || element.textContent);
    const duration = 1500;
    const start = 0;
    const startTime = performance.now();
    
    function updateCounter(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Easing function for smooth animation
      const easeOutQuart = 1 - Math.pow(1 - progress, 4);
      const current = Math.floor(start + (target - start) * easeOutQuart);
      
      element.textContent = current.toLocaleString('vi-VN');
      
      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      } else {
        element.textContent = target.toLocaleString('vi-VN');
      }
    }
    
    requestAnimationFrame(updateCounter);
  }
  
  // Trigger counter animation when stats are visible
  const statValues = document.querySelectorAll('.stat-value');
  
  if ('IntersectionObserver' in window) {
    const statsObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
          entry.target.classList.add('animated');
          animateCounter(entry.target);
        }
      });
    }, { threshold: 0.5 });
    
    statValues.forEach(stat => {
      const value = parseInt(stat.textContent);
      stat.setAttribute('data-target', value);
      stat.textContent = '0';
      statsObserver.observe(stat);
    });
  } else {
    // Fallback for browsers without IntersectionObserver
    statValues.forEach(stat => animateCounter(stat));
  }
  
  
  // ========================================
  // 2. Progress Bar Animation
  // ========================================
  const progressBars = document.querySelectorAll('.progress-bar-fill');
  
  if ('IntersectionObserver' in window) {
    const progressObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
          entry.target.classList.add('animated');
          const targetWidth = entry.target.getAttribute('data-width') || '0%';
          entry.target.style.width = targetWidth;
        }
      });
    }, { threshold: 0.5 });
    
    progressBars.forEach(bar => {
      const width = bar.style.width || '0%';
      bar.setAttribute('data-width', width);
      bar.style.width = '0%';
      progressObserver.observe(bar);
    });
  }
  
  
  // ========================================
  // 3. Quick Action Card Ripple Effect
  // ========================================
  const actionCards = document.querySelectorAll('.quick-action-card');
  
  actionCards.forEach(card => {
    card.addEventListener('click', function(e) {
      const ripple = document.createElement('div');
      ripple.className = 'ripple-effect';
      
      const rect = card.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        border-radius: 50%;
        background: rgba(0, 177, 79, 0.3);
        left: ${x}px;
        top: ${y}px;
        animation: ripple 0.6s ease-out;
        pointer-events: none;
        z-index: 1;
      `;
      
      card.style.position = 'relative';
      card.style.overflow = 'hidden';
      card.appendChild(ripple);
      
      setTimeout(() => ripple.remove(), 600);
    });
  });
  
  // Add ripple animation to CSS dynamically
  if (!document.querySelector('#ripple-animation')) {
    const style = document.createElement('style');
    style.id = 'ripple-animation';
    style.textContent = `
      @keyframes ripple {
        from {
          transform: scale(0);
          opacity: 1;
        }
        to {
          transform: scale(2);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  }
  
  
  // ========================================
  // 4. Welcome Banner Time-based Greeting
  // ========================================
  const welcomeTitle = document.querySelector('.welcome-title');
  if (welcomeTitle) {
    const hour = new Date().getHours();
    let greeting = 'Xin chào';
    
    if (hour >= 5 && hour < 12) {
      greeting = 'Chào buổi sáng';
    } else if (hour >= 12 && hour < 18) {
      greeting = 'Chào buổi chiều';
    } else if (hour >= 18 && hour < 22) {
      greeting = 'Chào buổi tối';
    } else {
      greeting = 'Chúc ngủ ngon';
    }
    
    const userName = welcomeTitle.textContent.replace(/^.*,\s*/, '').replace(/!\s*👋.*$/, '');
    welcomeTitle.innerHTML = `${greeting}, <span style="color: #fff;">${userName}</span>! 👋`;
  }
  
  
  // ========================================
  // 5. Notification Badge Animation
  // ========================================
  const notificationBadges = document.querySelectorAll('.notification-badge');
  notificationBadges.forEach(badge => {
    badge.style.animation = 'pulse 2s infinite';
  });
  
  
  // ========================================
  // 6. Activity Timeline Auto-scroll
  // ========================================
  const activityTimeline = document.querySelector('.activity-timeline');
  if (activityTimeline) {
    const items = activityTimeline.querySelectorAll('.activity-item');
    items.forEach((item, index) => {
      item.style.animationDelay = `${index * 0.1}s`;
    });
  }
  
  
  // ========================================
  // 7. Tooltips for Quick Actions
  // ========================================
  const actionCards2 = document.querySelectorAll('.quick-action-card');
  actionCards2.forEach(card => {
    card.addEventListener('mouseenter', function() {
      const description = this.querySelector('.action-description');
      if (description) {
        description.style.color = 'var(--jf-primary)';
      }
    });
    
    card.addEventListener('mouseleave', function() {
      const description = this.querySelector('.action-description');
      if (description) {
        description.style.color = 'var(--jf-text-light)';
      }
    });
  });
  
  
  // ========================================
  // 8. Smooth Scroll to Sections
  // ========================================
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (href !== '#' && document.querySelector(href)) {
        e.preventDefault();
        document.querySelector(href).scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
  
  
  // ========================================
  // 9. Loading State for Action Cards
  // ========================================
  actionCards.forEach(card => {
    card.addEventListener('click', function(e) {
      // Check if card has data-no-loading attribute
      if (this.hasAttribute('data-no-loading')) return;
      
      const icon = this.querySelector('.action-icon-box i');
      if (icon && !this.classList.contains('loading')) {
        const originalIcon = icon.className;
        this.classList.add('loading');
        icon.className = 'fa-solid fa-spinner fa-spin';
        
        // Simulate loading (remove this in production)
        setTimeout(() => {
          this.classList.remove('loading');
          icon.className = originalIcon;
        }, 1000);
      }
    });
  });
  
  
  // ========================================
  // 10. Auto-refresh Stats (Optional)
  // ========================================
  function refreshStats() {
    // This would make an AJAX call to get updated stats
    // For now, just add a subtle animation to show refresh
    statValues.forEach(stat => {
      stat.style.animation = 'none';
      setTimeout(() => {
        stat.style.animation = 'countUp 0.5s ease-out';
      }, 10);
    });
  }
  
  // Uncomment to enable auto-refresh every 5 minutes
  // setInterval(refreshStats, 5 * 60 * 1000);
  
  
  // ========================================
  // 11. Keyboard Shortcuts
  // ========================================
  document.addEventListener('keydown', function(e) {
    // Alt + H: Go to home
    if (e.altKey && e.key === 'h') {
      e.preventDefault();
      const homeLink = document.querySelector('a[href*="index.php"]');
      if (homeLink) homeLink.click();
    }
    
    // Alt + P: Go to profile
    if (e.altKey && e.key === 'p') {
      e.preventDefault();
      const profileLink = document.querySelector('a[href*="profile"]');
      if (profileLink) profileLink.click();
    }
    
    // Alt + J: Go to jobs
    if (e.altKey && e.key === 'j') {
      e.preventDefault();
      const jobsLink = document.querySelector('a[href*="jobs.php"]');
      if (jobsLink) jobsLink.click();
    }
  });
  
  
  // ========================================
  // 12. Welcome Message Animation
  // ========================================
  const welcomeBanner = document.querySelector('.dashboard-welcome-banner');
  if (welcomeBanner) {
    // Add parallax effect on scroll
    window.addEventListener('scroll', function() {
      const scrolled = window.pageYOffset;
      const rate = scrolled * 0.3;
      welcomeBanner.style.transform = `translateY(${rate}px)`;
    });
  }
  
  
  // ========================================
  // 13. Console Easter Egg
  // ========================================
  console.log('%c🚀 JobFind Dashboard', 'font-size: 20px; font-weight: bold; color: #00b14f;');
  console.log('%cBuilt with ❤️ by JobFind Team', 'font-size: 12px; color: #6c757d;');
  console.log('%cKeyboard Shortcuts:', 'font-size: 14px; font-weight: bold; color: #00b14f;');
  console.log('Alt + H: Trang chủ');
  console.log('Alt + P: Hồ sơ');
  console.log('Alt + J: Tìm việc');
  
});
