// js/auth.js - Xử lý đăng nhập và xác thực người dùng

class AuthManager {
    constructor() {
        this.currentUser = null;
        this.apiBase = 'php/';
        this.init();
    }

    init() {
        // Kiểm tra session hiện tại
        this.checkSession();
        
        // Bind events
        this.bindEvents();
    }

    bindEvents() {
        // Form đăng nhập
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Nút đăng xuất (nếu có)
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }
    }

    // Kiểm tra session hiện tại
    async checkSession() {
        try {
            const response = await fetch(this.apiBase + 'auth.php?action=check');
            const data = await response.json();
            
            if (data.success && data.user) {
                this.currentUser = data.user;
                this.updateUI(true);
                this.loadUserProgress();
            } else {
                this.updateUI(false);
            }
        } catch (error) {
            console.error('Lỗi kiểm tra session:', error);
            this.updateUI(false);
        }
    }

    // Xử lý đăng nhập
    async handleLogin(event) {
        event.preventDefault();
        
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        const errorDiv = document.getElementById('loginError');
        const submitBtn = event.target.querySelector('button[type="submit"]');
        
        // Hiển thị loading
        this.showLoading(submitBtn, true);
        this.hideError(errorDiv);

        try {
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);

            const response = await fetch(this.apiBase + 'auth.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.currentUser = data.user;
                this.showSuccess('Đăng nhập thành công! 🎉');
                
                // Đóng modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                if (modal) {
                    modal.hide();
                }
                
                // Cập nhật UI
                this.updateUI(true);
                this.loadUserProgress();
                
                // Reset form
                event.target.reset();
                
            } else {
                this.showError(errorDiv, data.message || 'Tên đăng nhập hoặc mật khẩu không đúng!');
            }
        } catch (error) {
            console.error('Lỗi đăng nhập:', error);
            this.showError(errorDiv, 'Có lỗi xảy ra, vui lòng thử lại!');
        } finally {
            this.showLoading(submitBtn, false);
        }
    }

    // Đăng xuất
    async logout() {
        try {
            const response = await fetch(this.apiBase + 'auth.php?action=logout');
            const data = await response.json();
            
            this.currentUser = null;
            this.updateUI(false);
            this.showSuccess('Đã đăng xuất thành công!');
            
            // Redirect về trang chủ nếu không phải trang chủ
            if (window.location.pathname !== '/' && window.location.pathname !== '/index.html') {
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 1500);
            }
            
        } catch (error) {
            console.error('Lỗi đăng xuất:', error);
        }
    }

    // Cập nhật giao diện theo trạng thái đăng nhập
    updateUI(isLoggedIn) {
        const welcomeUser = document.getElementById('welcomeUser');
        const loginPrompt = document.getElementById('loginPrompt');
        const username = document.getElementById('username');

        if (isLoggedIn && this.currentUser) {
            // Hiển thị thông tin người dùng
            if (welcomeUser) {
                welcomeUser.classList.remove('d-none');
                if (username) {
                    username.textContent = this.currentUser.display_name || this.currentUser.username;
                }
            }
            if (loginPrompt) {
                loginPrompt.classList.add('d-none');
            }
            
            // Mở khóa các menu
            this.unlockMenuItems();
            
        } else {
            // Hiển thị prompt đăng nhập
            if (welcomeUser) {
                welcomeUser.classList.add('d-none');
            }
            if (loginPrompt) {
                loginPrompt.classList.remove('d-none');
            }
            
            // Khóa các menu
            this.lockMenuItems();
        }
    }

    // Mở khóa menu items
    unlockMenuItems() {
        const menuItems = document.getElementById('menuItems');
        if (menuItems) {
            menuItems.style.filter = 'none';
            menuItems.style.pointerEvents = 'auto';
        }
    }

    // Khóa menu items
    lockMenuItems() {
        const menuItems = document.getElementById('menuItems');
        if (menuItems) {
            menuItems.style.filter = 'grayscale(50%) opacity(0.7)';
            menuItems.style.pointerEvents = 'none';
        }
    }

    // Load tiến độ học tập của user
    async loadUserProgress() {
        if (!this.currentUser) return;

        try {
            const response = await fetch(this.apiBase + 'user-progress.php?action=get');
            const data = await response.json();

            if (data.success) {
                this.updateProgressDisplay(data.progress);
            }
        } catch (error) {
            console.error('Lỗi load tiến độ:', error);
        }
    }

    // Cập nhật hiển thị tiến độ
    updateProgressDisplay(progress) {
        const progressElements = {
            'hiragana-progress': progress.hiragana || 0,
            'katakana-progress': progress.katakana || 0,
            'number-progress': progress.numbers || 0
        };

        Object.entries(progressElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value + '%';
                
                // Thêm animation cho progress
                element.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 300);
            }
        });
    }

    // Lưu tiến độ học tập
    async saveProgress(type, score, totalQuestions) {
        if (!this.currentUser) return false;

        try {
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('type', type);
            formData.append('score', score);
            formData.append('total', totalQuestions);

            const response = await fetch(this.apiBase + 'user-progress.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Lỗi lưu tiến độ:', error);
            return false;
        }
    }

    // Utility functions
    showLoading(button, show) {
        if (show) {
            button.disabled = true;
            button.innerHTML = '<span class="loading-spinner"></span> Đang xử lý...';
        } else {
            button.disabled = false;
            button.innerHTML = '🔑 Đăng nhập';
        }
    }

    showError(element, message) {
        if (element) {
            element.textContent = message;
            element.classList.remove('d-none');
            element.classList.add('shake');
            
            setTimeout(() => {
                element.classList.remove('shake');
            }, 500);
        }
    }

    hideError(element) {
        if (element) {
            element.classList.add('d-none');
        }
    }

    showSuccess(message) {
        // Tạo toast notification
        this.showToast(message, 'success');
    }

    showToast(message, type = 'info') {
        // Tạo toast element
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        // Thêm vào container hoặc tạo container
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        toastContainer.appendChild(toast);

        // Show toast
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        bsToast.show();

        // Remove element after hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    // Check if user is logged in
    isLoggedIn() {
        return this.currentUser !== null;
    }

    // Get current user
    getCurrentUser() {
        return this.currentUser;
    }

    // Require authentication before accessing page
    requireAuth() {
        if (!this.isLoggedIn()) {
            this.showToast('Vui lòng đăng nhập để tiếp tục!', 'warning');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 2000);
            return false;
        }
        return true;
    }
}

// Global functions để sử dụng ở các trang khác
function isLoggedIn() {
    return window.authManager && window.authManager.isLoggedIn();
}

function getCurrentUser() {
    return window.authManager ? window.authManager.getCurrentUser() : null;
}

function requireAuth() {
    return window.authManager ? window.authManager.requireAuth() : false;
}

function saveProgress(type, score, total) {
    return window.authManager ? window.authManager.saveProgress(type, score, total) : false;
}

function checkAuthStatus() {
    if (window.authManager) {
        window.authManager.checkSession();
    }
}

// Initialize AuthManager when DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    window.authManager = new AuthManager();
});

// Export for ES6 modules (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthManager;
}
