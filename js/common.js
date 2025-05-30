// js/common.js - JavaScript chung cho toàn bộ website

// Global utilities và helper functions
window.JapaneseApp = {
    // Configuration
    config: {
        apiBase: 'php/',
        animationDuration: 300,
        toastDuration: 3000
    },
    
    // Utility functions
    utils: {
        // Sanitize HTML để tránh XSS
        sanitizeHTML: function(str) {
            const temp = document.createElement('div');
            temp.textContent = str;
            return temp.innerHTML;
        },
        
        // Format số với dấu phẩy
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        // Format thời gian
        formatTime: function(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },
        
        // Tạo random array
        shuffleArray: function(array) {
            const newArray = [...array];
            for (let i = newArray.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
            }
            return newArray;
        },
        
        // Get random items from array
        getRandomItems: function(array, count) {
            const shuffled = this.shuffleArray(array);
            return shuffled.slice(0, count);
        },
        
        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    },
    
    // Animation helpers
    animations: {
        // Fade in element
        fadeIn: function(element, duration = 300) {
            element.style.opacity = '0';
            element.style.display = 'block';
            
            const start = performance.now();
            const animate = (currentTime) => {
                const elapsed = currentTime - start;
                const progress = Math.min(elapsed / duration, 1);
                
                element.style.opacity = progress;
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };
            
            requestAnimationFrame(animate);
        },
        
        // Fade out element
        fadeOut: function(element, duration = 300) {
            const start = performance.now();
            const initialOpacity = parseFloat(getComputedStyle(element).opacity);
            
            const animate = (currentTime) => {
                const elapsed = currentTime - start;
                const progress = Math.min(elapsed / duration, 1);
                
                element.style.opacity = initialOpacity * (1 - progress);
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.style.display = 'none';
                }
            };
            
            requestAnimationFrame(animate);
        },
        
        // Slide down element
        slideDown: function(element, duration = 300) {
            element.style.height = '0';
            element.style.overflow = 'hidden';
            element.style.display = 'block';
            
            const targetHeight = element.scrollHeight;
            const start = performance.now();
            
            const animate = (currentTime) => {
                const elapsed = currentTime - start;
                const progress = Math.min(elapsed / duration, 1);
                
                element.style.height = `${targetHeight * progress}px`;
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.style.height = '';
                    element.style.overflow = '';
                }
            };
            
            requestAnimationFrame(animate);
        },
        
        // Bounce effect
        bounce: function(element) {
            element.style.transform = 'scale(0.9)';
            setTimeout(() => {
                element.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 100);
            }, 100);
        },
        
        // Shake effect
        shake: function(element) {
            element.classList.add('shake');
            setTimeout(() => {
                element.classList.remove('shake');
            }, 500);
        }
    },
    
    // Notification system
    notifications: {
        show: function(message, type = 'info') {
            const toast = this.createToast(message, type);
            this.showToast(toast);
        },
        
        success: function(message) {
            this.show(message, 'success');
        },
        
        error: function(message) {
            this.show(message, 'error');
        },
        
        warning: function(message) {
            this.show(message, 'warning');
        },
        
        info: function(message) {
            this.show(message, 'info');
        },
        
        createToast: function(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            // Set background color based on type
            const bgClass = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info'
            }[type] || 'bg-info';
            
            toast.classList.add(bgClass);
            
            // Add icon based on type
            const icon = {
                'success': '✅',
                'error': '❌',
                'warning': '⚠️',
                'info': 'ℹ️'
            }[type] || 'ℹ️';
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${icon} ${JapaneseApp.utils.sanitizeHTML(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            return toast;
        },
        
        showToast: function(toast) {
            // Create container if not exists
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }
            
            container.appendChild(toast);
            
            // Initialize and show toast
            const bsToast = new bootstrap.Toast(toast, {
                autohide: true,
                delay: JapaneseApp.config.toastDuration
            });
            
            bsToast.show();
            
            // Remove from DOM when hidden
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
    },
    
    // Loading system
    loading: {
        show: function(element, text = 'Loading...') {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            
            if (!element) return;
            
            element.disabled = true;
            element.dataset.originalText = element.innerHTML;
            element.innerHTML = `<span class="loading-spinner"></span> ${text}`;
        },
        
        hide: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            
            if (!element) return;
            
            element.disabled = false;
            element.innerHTML = element.dataset.originalText || element.innerHTML;
        },
        
        // Full page loading
        showFullPage: function(text = 'Đang tải...') {
            let overlay = document.getElementById('loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loading-overlay';
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `
                    <div class="loading-content">
                        <div class="loading-spinner-large"></div>
                        <p class="loading-text">${text}</p>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            
            overlay.style.display = 'flex';
            JapaneseApp.animations.fadeIn(overlay);
        },
        
        hideFullPage: function() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                JapaneseApp.animations.fadeOut(overlay);
            }
        }
    },
    
    // API helpers
    api: {
        async request(url, options = {}) {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            };
            
            const config = { ...defaultOptions, ...options };
            
            try {
                const response = await fetch(JapaneseApp.config.apiBase + url, config);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Network error');
                }
                
                return data;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        },
        
        async get(url) {
            return this.request(url, { method: 'GET' });
        },
        
        async post(url, data) {
            const body = data instanceof FormData ? data : JSON.stringify(data);
            const headers = data instanceof FormData ? {} : { 'Content-Type': 'application/json' };
            
            return this.request(url, {
                method: 'POST',
                body,
                headers
            });
        }
    },
    
    // Quiz utilities
    quiz: {
        // Tạo câu hỏi với các lựa chọn sai
        generateOptions: function(correctAnswer, allOptions, count = 4) {
            const options = [correctAnswer];
            const availableOptions = allOptions.filter(opt => opt !== correctAnswer);
            
            while (options.length < count && availableOptions.length > 0) {
                const randomIndex = Math.floor(Math.random() * availableOptions.length);
                const randomOption = availableOptions.splice(randomIndex, 1)[0];
                options.push(randomOption);
            }
            
            return JapaneseApp.utils.shuffleArray(options);
        },
        
        // Tính điểm dựa trên thời gian và độ chính xác
        calculateScore: function(correctAnswers, totalQuestions, timeSpent, maxTime = 300) {
            const accuracyScore = (correctAnswers / totalQuestions) * 70; // 70% dựa trên độ chính xác
            const timeBonus = Math.max(0, (maxTime - timeSpent) / maxTime) * 30; // 30% bonus theo thời gian
            
            return Math.round(accuracyScore + timeBonus);
        },
        
        // Format kết quả quiz
        formatResult: function(score, total, timeSpent) {
            const percentage = Math.round((score / total) * 100);
            const timeFormatted = JapaneseApp.utils.formatTime(timeSpent);
            
            return {
                score,
                total,
                percentage,
                timeSpent: timeFormatted,
                grade: this.getGrade(percentage)
            };
        },
        
        getGrade: function(percentage) {
            if (percentage >= 90) return { grade: 'A+', message: 'Xuất sắc! 🌟', color: 'success' };
            if (percentage >= 80) return { grade: 'A', message: 'Rất tốt! 👏', color: 'success' };
            if (percentage >= 70) return { grade: 'B', message: 'Tốt! 👍', color: 'info' };
            if (percentage >= 60) return { grade: 'C', message: 'Khá! 📚', color: 'warning' };
            return { grade: 'D', message: 'Cần cố gắng thêm! 💪', color: 'danger' };
        }
    },
    
    // Japanese text utilities
    japanese: {
        // Kiểm tra xem có phải Hiragana không
        isHiragana: function(char) {
            return /[\u3040-\u309F]/.test(char);
        },
        
        // Kiểm tra xem có phải Katakana không
        isKatakana: function(char) {
            return /[\u30A0-\u30FF]/.test(char);
        },
        
        // Kiểm tra xem có phải Kanji không
        isKanji: function(char) {
            return /[\u4E00-\u9FAF]/.test(char);
        },
        
        // Convert Hiragana to Katakana
        hiraganaToKatakana: function(str) {
            return str.replace(/[\u3040-\u309F]/g, function(match) {
                const chr = match.charCodeAt(0) + 0x60;
                return String.fromCharCode(chr);
            });
        },
        
        // Convert Katakana to Hiragana
        katakanaToHiragana: function(str) {
            return str.replace(/[\u30A0-\u30FF]/g, function(match) {
                const chr = match.charCodeAt(0) - 0x60;
                return String.fromCharCode(chr);
            });
        }
    },
    
    // Local storage utilities (fallback for session data)
    storage: {
        set: function(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
            } catch (e) {
                console.warn('LocalStorage not available:', e);
            }
        },
        
        get: function(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (e) {
                console.warn('LocalStorage not available:', e);
                return defaultValue;
            }
        },
        
        remove: function(key) {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                console.warn('LocalStorage not available:', e);
            }
        },
        
        clear: function() {
            try {
                localStorage.clear();
            } catch (e) {
                console.warn('LocalStorage not available:', e);
            }
        }
    },
    
    // Page transition utilities
    transition: {
        navigateTo: function(url, fadeOut = true) {
            if (fadeOut) {
                document.body.style.transition = 'opacity 0.3s ease';
                document.body.style.opacity = '0.7';
                
                setTimeout(() => {
                    window.location.href = url;
                }, 300);
            } else {
                window.location.href = url;
            }
        },
        
        reload: function(fadeOut = true) {
            if (fadeOut) {
                document.body.style.transition = 'opacity 0.3s ease';
                document.body.style.opacity = '0.7';
                
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            } else {
                window.location.reload();
            }
        }
    }
};

// Initialize common functionality when DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add loading overlay CSS if not exists
    if (!document.getElementById('loading-overlay-styles')) {
        const style = document.createElement('style');
        style.id = 'loading-overlay-styles';
        style.textContent = `
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                backdrop-filter: blur(5px);
            }
            
            .loading-content {
                background: white;
                padding: 40px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            }
            
            .loading-spinner-large {
                width: 50px;
                height: 50px;
                border: 5px solid #f3f3f3;
                border-top: 5px solid #667eea;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            .loading-text {
                font-size: 1.2rem;
                color: #333;
                margin: 0;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Add global error handler
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        JapaneseApp.notifications.error('Có lỗi xảy ra. Vui lòng thử lại!');
    });
    
    // Add unhandled promise rejection handler
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled promise rejection:', e.reason);
        JapaneseApp.notifications.error('Có lỗi xảy ra khi xử lý dữ liệu!');
    });
});

// Export for use in other files
window.App = JapaneseApp;
