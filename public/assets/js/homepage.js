// JobFind Homepage Interactive Features
(function () {
    'use strict';

    const observerOptions = { root: null, rootMargin: '0px', threshold: 0.12 };

    const formatMetric = (value) => {
        if (value >= 1000000) {
            return `${(value / 1000000).toFixed(1).replace(/\.0$/, '')}M+`;
        }
        if (value >= 1000) {
            return `${(value / 1000).toFixed(1).replace(/\.0$/, '')}K+`;
        }
        return `${value.toLocaleString('vi-VN')}+`;
    };

    const animateCounter = (element) => {
        const target = parseInt(element.getAttribute('data-value') || '0', 10);
        const finalLabel = element.getAttribute('data-format') || formatMetric(target);
        const duration = 1700;
        const startTime = performance.now();

        const update = (currentTime) => {
            const progress = Math.min((currentTime - startTime) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 4);
            const currentValue = Math.floor(target * ease);
            element.textContent = currentValue.toLocaleString('vi-VN');

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = finalLabel;
            }
        };

        requestAnimationFrame(update);
    };

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (event) => {
            const href = anchor.getAttribute('href');
            if (!href || href === '#' || href === '#!') {
                return;
            }
            const target = document.querySelector(href);
            if (target) {
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Fade-in animation
    const fadeObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                fadeObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.fade-in-element').forEach((element) => {
        fadeObserver.observe(element);
    });

    // Hero metrics counter
    const metricsObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            const valueElement = entry.target.querySelector('.hero-metric-value');
            if (valueElement && !valueElement.dataset.animated) {
                valueElement.dataset.animated = 'true';
                animateCounter(valueElement);
            }
            metricsObserver.unobserve(entry.target);
        });
    }, observerOptions);

    document.querySelectorAll('.hero-metric-card').forEach((card) => {
        metricsObserver.observe(card);
    });

    // Search form handler
    document.querySelectorAll('.home-search-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const keywordInput = form.querySelector('input[name="keyword"]');
            const locationSelect = form.querySelector('select[name="location"]');
            const keyword = keywordInput ? keywordInput.value.trim() : '';
            const location = locationSelect ? locationSelect.value.trim() : '';
            const params = new URLSearchParams();
            if (keyword) params.append('keyword', keyword);
            if (location) params.append('location', location);
            const searchUrl = form.getAttribute('data-search-url') || '/job/share/index.php';
            const url = params.toString() ? `${searchUrl}?${params.toString()}` : searchUrl;
            window.location.href = url;
        });
    });

    // Job card hover
    document.querySelectorAll('.jf-job-card').forEach((card) => {
        card.addEventListener('mouseenter', () => {
            card.classList.add('is-hovered');
        });
        card.addEventListener('mouseleave', () => {
            card.classList.remove('is-hovered');
        });
    });

    // Sticky header
    const header = document.querySelector('.jf-header');
    let lastScroll = 0;
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        if (!header) {
            return;
        }
        if (currentScroll <= 0) {
            header.classList.remove('scroll-down', 'scroll-up', 'is-scrolled');
            lastScroll = 0;
            return;
        }
        header.classList.toggle('is-scrolled', currentScroll > 40);
        if (currentScroll > lastScroll) {
            header.classList.add('scroll-down');
            header.classList.remove('scroll-up');
        } else {
            header.classList.add('scroll-up');
            header.classList.remove('scroll-down');
        }
        lastScroll = currentScroll;
    });

    // Toast helper (global)
    window.showToast = function (message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `jf-toast jf-toast-${type}`;
        toast.innerHTML = `
            <i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'} me-2"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 250);
        }, 3200);
    };

    // Save job toggle
    document.querySelectorAll('.save-job-btn').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const icon = button.querySelector('i');
            if (!icon) return;
            const saved = icon.classList.contains('fa-solid');
            icon.classList.toggle('fa-solid', !saved);
            icon.classList.toggle('fa-regular', saved);
            showToast(saved ? 'Đã bỏ lưu việc làm' : 'Đã lưu việc làm thành công', saved ? 'info' : 'success');
        });
    });

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        if (window.bootstrap?.Tooltip) {
            new bootstrap.Tooltip(el);
        }
    });

    // Back to top
    const backToTop = document.createElement('button');
    backToTop.className = 'jf-back-to-top';
    backToTop.setAttribute('aria-label', 'Lên đầu trang');
    backToTop.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
    document.body.appendChild(backToTop);

    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    });

    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Lazy images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
                img.classList.add('loaded');
                imageObserver.unobserve(img);
            });
        }, { rootMargin: '120px' });

        document.querySelectorAll('img[data-src]').forEach((img) => imageObserver.observe(img));
    }

    // Rotating suggestions
    const searchInput = document.querySelector('#keyword');
    if (searchInput) {
        const suggestions = ['Marketing Manager', 'Frontend Developer', 'UI/UX Designer', 'Data Analyst', 'Sales Executive'];
        let index = 0;
        setInterval(() => {
            if (document.activeElement === searchInput || searchInput.value) {
                return;
            }
            searchInput.placeholder = suggestions[index];
            index = (index + 1) % suggestions.length;
        }, 3200);
    }

    window.addEventListener('load', () => {
        document.body.classList.add('page-loaded');
    });

    console.log('JobFind Homepage JS loaded successfully ✓');
})();
