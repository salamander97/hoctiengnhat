// js/dashboard.js - Dashboard frontend logic - FIXED VERSION

class DashboardManager {
    constructor() {
        this.charts = {};
        this.apiBase = 'php/dashboard.php';
        this.currentUser = null;
        this.refreshInterval = null;
        this.isInitialized = false;
        this.init();
    }

    async init() {
        console.log('🎌 Initializing Dashboard...');
        
        // Check authentication first
        if (!await this.checkAuth()) {
            this.showNotification('Vui lòng đăng nhập để truy cập dashboard!', 'warning');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 2000);
            return;
        }

        // Wait for DOM to be fully ready
        if (document.readyState !== 'complete') {
            window.addEventListener('load', () => this.initializeDashboard());
        } else {
            this.initializeDashboard();
        }
    }

    async initializeDashboard() {
        try {
            // Load all dashboard data
            await this.loadDashboard();
            
            // Initialize components
            this.initializeComponents();
            
            // Set up auto-refresh
            this.setupAutoRefresh();
            
            // Add keyboard shortcuts
            this.setupKeyboardShortcuts();
            
            this.isInitialized = true;
            console.log('🎌 Dashboard loaded successfully!');
        } catch (error) {
            console.error('Dashboard initialization error:', error);
            this.showError('Có lỗi xảy ra khi khởi tạo dashboard');
        }
    }

    async checkAuth() {
        try {
            const response = await fetch('php/auth.php?action=check');
            const data = await response.json();
            
            if (data.success && data.user) {
                this.currentUser = data.user;
                return true;
            }
            return false;
        } catch (error) {
            console.error('Auth check failed:', error);
            return false;
        }
    }

    async loadDashboard() {
        try {
            // Show loading
            this.showLoading(true);
            
            console.log('Loading dashboard data...');
            
            // Load dashboard overview
            await this.loadOverview();
            
            // Wait a bit to prevent simultaneous chart creation
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Load charts data with proper timing
            await this.loadProgressChart();
            
            await new Promise(resolve => setTimeout(resolve, 100));
            
            await this.loadSkillsRadar();
            
            // Load activities and achievements
            await this.loadRecentActivities();
            await this.loadAchievements();
            
            // Load heatmap
            await this.loadActivityHeatmap();
            
            console.log('Dashboard data loaded successfully');
            
        } catch (error) {
            console.error('Dashboard load error:', error);
            this.showError('Có lỗi xảy ra khi tải dashboard');
        } finally {
            this.showLoading(false);
        }
    }

    async loadOverview() {
        try {
            const response = await fetch(`${this.apiBase}?action=overview`);
            const result = await response.json();
            
            if (result.success) {
                this.updateOverview(result.data);
            } else {
                console.warn('Overview API returned error:', result.message);
                // Use fallback data
                this.updateOverview(this.getFallbackOverviewData());
            }
        } catch (error) {
            console.error('Overview load error:', error);
            // Use fallback data
            this.updateOverview(this.getFallbackOverviewData());
        }
    }

    getFallbackOverviewData() {
        return {
            user: this.currentUser || { display_name: 'User', username: 'user' },
            streak: 0,
            total_score: 0,
            level: { code: 'N5-', name: 'Beginner', color: '#f093fb' },
            progress: {
                hiragana: { percentage: 0, score: 0, total: 1000, accuracy: 0 },
                katakana: { percentage: 0, score: 0, total: 1000, accuracy: 0 },
                numbers: { percentage: 0, score: 0, total: 1000, accuracy: 0 },
                vocabulary_n5: { percentage: 0, score: 0, total: 1000, accuracy: 0 }
            }
        };
    }

    updateOverview(data) {
        console.log('Updating overview with data:', data);
        
        // Update user info
        const usernameEl = document.querySelector('.user-info h2');
        if (usernameEl) {
            usernameEl.textContent = `こんにちは、${data.user.display_name || data.user.username}さん！`;
        }
        
        // Update streak with animation
        const streakEl = document.querySelector('.streak-number');
        if (streakEl) {
            this.animateNumber(streakEl, data.streak);
            if (data.streak > 0) {
                streakEl.closest('.streak-counter').style.animation = 'pulse 2s infinite';
            }
        }
        
        // Update total score
        const scoreEl = document.querySelector('.score-number');
        if (scoreEl) {
            this.animateNumber(scoreEl, data.total_score);
        }
        
        // Update level
        const levelEl = document.querySelector('.level-circle');
        const levelTextEl = document.querySelector('.level-text');
        if (levelEl && levelTextEl) {
            levelEl.textContent = data.level.code;
            levelEl.style.borderColor = data.level.color;
            levelTextEl.textContent = data.level.name;
            levelTextEl.style.color = data.level.color;
        }
        
        // Update progress cards
        this.updateProgressCards(data.progress);
    }

    updateProgressCards(progress) {
        const cards = {
            'hiragana': document.querySelector('.progress-card.hiragana'),
            'katakana': document.querySelector('.progress-card.katakana'), 
            'numbers': document.querySelector('.progress-card.numbers'),
            'vocabulary': document.querySelector('.progress-card.vocabulary')
        };

        Object.entries(progress).forEach(([type, data]) => {
            const card = cards[type] || cards[type.replace('_n5', '')];
            if (!card) return;

            // Update circular progress with animation
            const progressEl = card.querySelector('.circular-progress');
            const percentageEl = card.querySelector('.percentage');
            
            if (progressEl && percentageEl) {
                const percentage = data.percentage || 0;
                const degree = (percentage / 100) * 360;
                
                // Animate the circular progress
                setTimeout(() => {
                    progressEl.style.setProperty('--percentage', `${degree}deg`);
                }, 100);
                
                this.animateNumber(percentageEl, percentage, '%');
                
                // Update progress color based on percentage
                const color = this.getProgressColor(percentage);
                progressEl.style.background = `conic-gradient(from 0deg, ${color} 0deg, ${color} var(--percentage, 0deg), #e0e0e0 var(--percentage, 0deg), #e0e0e0 360deg)`;
            }

            // Update stats
            const statsEl = card.querySelector('.stats');
            if (statsEl) {
                const scoreSpan = statsEl.children[0];
                const accuracySpan = statsEl.children[1];
                
                if (scoreSpan) {
                    if (type === 'vocabulary_n5' || type === 'vocabulary') {
                        scoreSpan.textContent = `${data.score || 0}/500 từ`;
                    } else if (type === 'numbers') {
                        const level = this.getNumberLevel(data.score || 0);
                        scoreSpan.textContent = `Level: ${level}`;
                    } else {
                        scoreSpan.textContent = `${data.score || 0}/${data.total || 50} ký tự`;
                    }
                }
                
                if (accuracySpan) {
                    accuracySpan.textContent = `Độ chính xác: ${data.accuracy || 0}%`;
                    accuracySpan.style.color = this.getAccuracyColor(data.accuracy || 0);
                }
            }

            // Add hover effect
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });
    }

    getProgressColor(percentage) {
        if (percentage >= 80) return '#4CAF50';
        if (percentage >= 60) return '#FF9800';
        if (percentage >= 40) return '#FFC107';
        return '#9E9E9E';
    }

    getAccuracyColor(accuracy) {
        if (accuracy >= 90) return '#4CAF50';
        if (accuracy >= 75) return '#FF9800';
        if (accuracy >= 60) return '#FFC107';
        return '#F44336';
    }

    getNumberLevel(score) {
        if (score >= 1000) return '1000+';
        if (score >= 100) return '100-999';
        if (score >= 10) return '10-99';
        if (score > 0) return '1-9';
        return 'Chưa bắt đầu';
    }

    async loadProgressChart() {
        try {
            // Make sure canvas exists and is visible
            const canvas = document.getElementById('progressChart');
            if (!canvas) {
                console.warn('Progress chart canvas not found');
                return;
            }

            // Wait for container to be properly sized
            await new Promise(resolve => {
                const checkSize = () => {
                    const container = canvas.closest('.chart-container');
                    if (container && container.offsetWidth > 0) {
                        resolve();
                    } else {
                        setTimeout(checkSize, 50);
                    }
                };
                checkSize();
            });

            const response = await fetch(`${this.apiBase}?action=progress_chart`);
            const result = await response.json();
            
            if (result.success) {
                this.createProgressChart(result.data);
            } else {
                this.createProgressChart(this.getFallbackChartData());
            }
        } catch (error) {
            console.error('Progress chart error:', error);
            this.createProgressChart(this.getFallbackChartData());
        }
    }

    getFallbackChartData() {
        return {
            labels: ['Tuần 1', 'Tuần 2', 'Tuần 3', 'Tuần 4', 'Tuần 5', 'Tuần 6', 'Tuần 7', 'Tuần 8'],
            datasets: [{
                label: 'Hiragana',
                data: [0, 10, 15, 20, 25, 30, 35, 40],
                borderColor: '#ff9a8b',
                backgroundColor: 'rgba(255, 154, 139, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Katakana',
                data: [0, 0, 5, 10, 15, 20, 25, 30],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Số đếm',
                data: [0, 0, 0, 5, 10, 15, 20, 25],
                borderColor: '#56ab2f',
                backgroundColor: 'rgba(86, 171, 47, 0.1)',
                tension: 0.4,
                fill: true
            }]
        };
    }

    createProgressChart(data) {
        const ctx = document.getElementById('progressChart');
        if (!ctx) {
            console.error('Canvas element not found for progress chart');
            return;
        }

        // Destroy existing chart if exists
        if (this.charts.progress) {
            this.charts.progress.destroy();
            this.charts.progress = null;
        }

        try {
            // Set fixed canvas size to prevent reflow
            const container = ctx.closest('.chart-container');
            const containerWidth = container.offsetWidth - 60; // Account for padding
            const chartHeight = 300; // Fixed height
            
            ctx.width = containerWidth;
            ctx.height = chartHeight;
            ctx.style.width = containerWidth + 'px';
            ctx.style.height = chartHeight + 'px';

            this.charts.progress = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets.map(dataset => ({
                        ...dataset,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        pointBorderWidth: 2,
                        pointBorderColor: '#fff'
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    devicePixelRatio: window.devicePixelRatio || 1,
                    layout: {
                        padding: {
                            top: 20,
                            right: 20,
                            bottom: 20,
                            left: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.2)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                },
                                font: {
                                    size: 11
                                },
                                maxTicksLimit: 6
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                maxTicksLimit: 8
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart',
                        onComplete: () => {
                            console.log('Progress chart animation completed');
                        }
                    },
                    // Performance optimizations
                    elements: {
                        point: {
                            radius: function(context) {
                                return Math.min(Math.max(context.chart.width / 100, 2), 6);
                            }
                        }
                    }
                }
            });

            console.log('Progress chart created successfully');
        } catch (error) {
            console.error('Error creating progress chart:', error);
        }
    }

    async loadSkillsRadar() {
        try {
            // Make sure canvas exists
            const canvas = document.getElementById('skillsRadar');
            if (!canvas) {
                console.warn('Skills radar canvas not found');
                return;
            }

            // Wait for container to be properly sized
            await new Promise(resolve => {
                const checkSize = () => {
                    const container = canvas.closest('.chart-container');
                    if (container && container.offsetWidth > 0) {
                        resolve();
                    } else {
                        setTimeout(checkSize, 50);
                    }
                };
                checkSize();
            });

            const response = await fetch(`${this.apiBase}?action=skills_analysis`);
            const result = await response.json();
            
            if (result.success) {
                this.createSkillsRadar(result.data);
            } else {
                this.createSkillsRadar(this.getFallbackRadarData());
            }
        } catch (error) {
            console.error('Skills radar error:', error);
            this.createSkillsRadar(this.getFallbackRadarData());
        }
    }

    getFallbackRadarData() {
        return {
            labels: ['Hiragana', 'Katakana', 'Số đếm', 'Từ vựng', 'Tốc độ', 'Độ chính xác'],
            data: [40, 20, 15, 10, 25, 35]
        };
    }

    createSkillsRadar(data) {
        const ctx = document.getElementById('skillsRadar');
        if (!ctx) {
            console.error('Canvas element not found for skills radar');
            return;
        }

        // Destroy existing chart if exists
        if (this.charts.radar) {
            this.charts.radar.destroy();
            this.charts.radar = null;
        }

        try {
            // Set fixed canvas size
            const container = ctx.closest('.chart-container');
            const containerWidth = container.offsetWidth - 60;
            const chartHeight = 300;
            
            ctx.width = containerWidth;
            ctx.height = chartHeight;
            ctx.style.width = containerWidth + 'px';
            ctx.style.height = chartHeight + 'px';

            this.charts.radar = new Chart(ctx.getContext('2d'), {
                type: 'radar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Kỹ năng hiện tại',
                        data: data.data,
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: '#667eea',
                        borderWidth: 3,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#667eea',
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    devicePixelRatio: window.devicePixelRatio || 1,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.2)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.r}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20,
                                color: '#6c757d',
                                backdropColor: 'transparent',
                                font: {
                                    size: 10
                                },
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            angleLines: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            pointLabels: {
                                font: {
                                    size: 11,
                                    weight: 'bold'
                                },
                                color: '#2c3e50'
                            }
                        }
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart',
                        onComplete: () => {
                            console.log('Radar chart animation completed');
                        }
                    }
                }
            });

            console.log('Skills radar chart created successfully');
        } catch (error) {
            console.error('Error creating skills radar:', error);
        }
    }

    async loadActivityHeatmap() {
        try {
            const response = await fetch(`${this.apiBase}?action=activity_heatmap`);
            const result = await response.json();
            
            if (result.success) {
                this.createActivityHeatmap(result.data);
            } else {
                this.generateDummyHeatmap();
            }
        } catch (error) {
            console.error('Activity heatmap error:', error);
            this.generateDummyHeatmap();
        }
    }

    createActivityHeatmap(data) {
        const container = document.getElementById('activityHeatmap');
        if (!container) return;

        container.innerHTML = '';
        
        // Use document fragment for better performance
        const fragment = document.createDocumentFragment();
        
        data.forEach(day => {
            const cell = document.createElement('div');
            cell.className = 'heatmap-cell';
            
            if (day.level > 0) {
                cell.classList.add(`level-${day.level}`);
            }
            
            // Tooltip
            const date = new Date(day.date);
            const activityText = day.count === 0 ? 'Không có hoạt động' : 
                                day.count === 1 ? '1 hoạt động' : 
                                `${day.count} hoạt động`;
            cell.title = `${date.toLocaleDateString('vi-VN')}: ${activityText}`;
            
            // Click event for day details
            cell.addEventListener('click', () => {
                this.showDayDetails(day);
            });
            
            fragment.appendChild(cell);
        });
        
        container.appendChild(fragment);
    }

    generateDummyHeatmap() {
        const container = document.getElementById('activityHeatmap');
        if (!container) return;

        container.innerHTML = '';
        
        const fragment = document.createDocumentFragment();
        
        // Generate 365 days of dummy data
        for (let i = 0; i < 365; i++) {
            const cell = document.createElement('div');
            cell.className = 'heatmap-cell';
            
            // Random activity level with more zeros (realistic)
            const rand = Math.random();
            let level = 0;
            if (rand > 0.7) level = 1;
            if (rand > 0.85) level = 2;
            if (rand > 0.95) level = 3;
            if (rand > 0.98) level = 4;
            
            if (level > 0) {
                cell.classList.add(`level-${level}`);
            }
            
            const date = new Date();
            date.setDate(date.getDate() - i);
            cell.title = `${date.toLocaleDateString('vi-VN')}: ${level} hoạt động`;
            
            // Click event
            cell.addEventListener('click', () => {
                this.showDayDetails({
                    date: date.toISOString().split('T')[0],
                    count: level,
                    level: level
                });
            });
            
            fragment.appendChild(cell);
        }
        
        container.appendChild(fragment);
    }

    async loadRecentActivities() {
        try {
            const response = await fetch(`${this.apiBase}?action=recent_activities`);
            const result = await response.json();
            
            if (result.success) {
                this.updateRecentActivities(result.data);
            } else {
                this.updateRecentActivities([]);
            }
        } catch (error) {
            console.error('Recent activities error:', error);
            this.updateRecentActivities([]);
        }
    }

    updateRecentActivities(activities) {
        const container = document.querySelector('.activity-list');
        if (!container) return;

        if (activities.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📚</div>
                    <h5>Chưa có hoạt động nào</h5>
                    <p>Hãy bắt đầu làm quiz để xem tiến độ!</p>
                    <div class="mt-3">
                        <a href="hiragana-test.html" class="btn btn-primary me-2">Quiz Hiragana</a>
                        <a href="hiragana-rules.html" class="btn btn-outline-primary">Học quy tắc</a>
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = '';
        
        activities.forEach((activity, index) => {
            const item = document.createElement('div');
            item.className = 'activity-item';
            item.style.animationDelay = `${index * 0.1}s`;
            
            item.innerHTML = `
                <div class="activity-icon">${activity.icon}</div>
                <div class="activity-text">
                    <span><strong>${activity.title}</strong></span>
                    <span class="activity-score">+${activity.score} điểm (${activity.percentage}%)</span>
                </div>
                <div class="activity-time">${activity.time_ago}</div>
            `;
            
            container.appendChild(item);
        });
    }

    async loadAchievements() {
        try {
            const response = await fetch(`${this.apiBase}?action=achievements`);
            const result = await response.json();
            
            if (result.success) {
                this.updateAchievements(result.data);
            } else {
                this.updateAchievements(this.getFallbackAchievements());
            }
        } catch (error) {
            console.error('Achievements error:', error);
            this.updateAchievements(this.getFallbackAchievements());
        }
    }

    getFallbackAchievements() {
        return [
            { type: 'hiragana_master', icon: '🌸', title: 'Hiragana Master', description: 'Hoàn thành 50 câu hỏi Hiragana', earned: false },
            { type: 'week_streak', icon: '🔥', title: 'Week Streak', description: 'Học liên tục 7 ngày', earned: false },
            { type: 'sharp_shooter', icon: '🎯', title: 'Sharp Shooter', description: 'Đạt 90% độ chính xác', earned: false },
            { type: 'katakana_expert', icon: '🎌', title: 'Katakana Expert', description: 'Hoàn thành Katakana', earned: false },
            { type: 'high_scorer', icon: '⭐', title: 'High Scorer', description: 'Đạt 1000 điểm', earned: false },
            { type: 'n5_graduate', icon: '📖', title: 'N5 Graduate', description: 'Hoàn thành N5', earned: false }
        ];
    }

    updateAchievements(achievements) {
        const container = document.querySelector('.achievement-grid');
        if (!container) return;

        container.innerHTML = '';
        
        achievements.forEach((achievement, index) => {
            const item = document.createElement('div');
            item.className = `achievement ${achievement.earned ? 'earned' : 'locked'}`;
            item.style.animationDelay = `${index * 0.1}s`;
            
            item.innerHTML = `
                <div class="achievement-icon">${achievement.icon}</div>
                <div class="achievement-title">${achievement.title}</div>
            `;
            
            item.title = achievement.description;
            if (achievement.earned && achievement.earned_at) {
                item.title += `\nĐạt được: ${new Date(achievement.earned_at).toLocaleDateString('vi-VN')}`;
            }
            
            // Click event for earned achievements
            if (achievement.earned) {
                item.addEventListener('click', () => {
                    this.showAchievementDetails(achievement);
                });
            }
            
            container.appendChild(item);
        });
    }

    // Helper functions với performance optimization
    animateNumber(element, targetValue, suffix = '') {
        if (!element) return;
        
        const startValue = parseInt(element.textContent) || 0;
        const duration = 1000; // Giảm thời gian animation
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const currentValue = Math.floor(startValue + (targetValue - startValue) * easeOutQuart);
            
            // Batch DOM updates
            element.textContent = currentValue.toLocaleString('vi-VN') + suffix;
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    showDayDetails(day) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">📅 ${new Date(day.date).toLocaleDateString('vi-VN')}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <div class="heatmap-cell level-${day.level}" style="width: 40px; height: 40px; display: inline-block; margin-bottom: 15px;"></div>
                        </div>
                        <h6><strong>Số hoạt động:</strong> ${day.count}</h6>
                        <p><strong>Mức độ:</strong> ${this.getLevelName(day.level)}</p>
                        ${day.count > 0 ? `
                            <div class="mt-3">
                                <small class="text-muted">Tiếp tục phát huy nhé! 💪</small>
                            </div>
                        ` : `
                            <div class="mt-3">
                                <small class="text-muted">Hãy bắt đầu học để có hoạt động! 📚</small>
                            </div>
                        `}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    showAchievementDetails(achievement) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">${achievement.icon} ${achievement.title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="achievement-icon mb-3" style="font-size: 4rem;">${achievement.icon}</div>
                        <h5><strong>${achievement.description}</strong></h5>
                        <p class="text-muted">Đạt được: ${new Date(achievement.earned_at).toLocaleDateString('vi-VN')}</p>
                        <div class="mt-4">
                            <button class="btn btn-primary" onclick="window.dashboardManager.shareAchievement('${achievement.title}')">
                                📤 Chia sẻ thành tích
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    shareAchievement(title) {
        if (navigator.share) {
            navigator.share({
                title: 'Thành tích học tiếng Nhật',
                text: `Tôi vừa đạt được thành tích "${title}" trong việc học tiếng Nhật! 🎌`,
                url: window.location.origin
            }).catch(console.error);
        } else {
            // Fallback: copy to clipboard
            const text = `Tôi vừa đạt được thành tích "${title}" trong việc học tiếng Nhật! 🎌 ${window.location.origin}`;
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('Đã copy link chia sẻ!', 'success');
            }).catch(() => {
                this.showNotification('Không thể copy link', 'error');
            });
        }
    }

    getLevelName(level) {
        const levels = {
            0: 'Không hoạt động',
            1: 'Ít hoạt động', 
            2: 'Trung bình',
            3: 'Tích cực',
            4: 'Rất tích cực'
        };
        return levels[level] || 'Không xác định';
    }

    initializeComponents() {
        console.log('Initializing dashboard components...');
        
        // Progress cards click events với debouncing
        document.querySelectorAll('.progress-card').forEach(card => {
            let clickTimeout;
            card.addEventListener('click', () => {
                clearTimeout(clickTimeout);
                clickTimeout = setTimeout(() => {
                    const type = Array.from(card.classList).find(cls => 
                        ['hiragana', 'katakana', 'numbers', 'vocabulary'].includes(cls)
                    );
                    this.navigateToQuiz(type);
                }, 150);
            });
        });

        // Refresh button với loading state
        const refreshBtn = document.getElementById('refreshDashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', async () => {
                if (refreshBtn.disabled) return; // Prevent double click
                
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = '🔄 Đang tải...';
                
                try {
                    this.showNotification('Đang làm mới dữ liệu...', 'info');
                    await this.loadDashboard();
                    this.showNotification('Đã cập nhật thành công!', 'success');
                } catch (error) {
                    this.showNotification('Lỗi khi làm mới dữ liệu', 'error');
                } finally {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '🔄 Làm mới';
                }
            });
        }

        // Export button
        const exportBtn = document.getElementById('exportStats');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportData();
            });
        }

        console.log('Dashboard components initialized');
    }

    navigateToQuiz(type) {
        const pages = {
            'hiragana': 'hiragana-test.html',
            'katakana': 'katakana-test.html',
            'numbers': 'number-test.html',
            'vocabulary': 'vocabulary-test.html'
        };
        
        if (pages[type]) {
            // Add loading transition
            document.body.style.transition = 'opacity 0.3s ease';
            document.body.style.opacity = '0.7';
            setTimeout(() => {
                window.location.href = pages[type];
            }, 300);
        } else {
            this.showNotification('Tính năng này sắp ra mắt!', 'info');
        }
    }

    async exportData() {
        try {
            this.showNotification('Đang export dữ liệu...', 'info');
            
            const [overview, activities] = await Promise.all([
                fetch(`${this.apiBase}?action=overview`).then(r => r.json()).catch(() => ({ data: this.getFallbackOverviewData() })),
                fetch(`${this.apiBase}?action=recent_activities`).then(r => r.json()).catch(() => ({ data: [] }))
            ]);
            
            const data = {
                exported_at: new Date().toISOString(),
                user: overview.data?.user || this.currentUser,
                stats: overview.data || this.getFallbackOverviewData(),
                recent_activities: activities.data || []
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `japanese-learning-stats-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            this.showNotification('Đã export dữ liệu thành công!', 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.showNotification('Lỗi khi export dữ liệu', 'error');
        }
    }

    setupAutoRefresh() {
        // Refresh data every 5 minutes - chỉ reload data, không reload charts
        this.refreshInterval = setInterval(() => {
            console.log('Auto-refreshing dashboard data...');
            this.loadRecentActivities();
            this.loadActivityHeatmap();
            this.loadOverview(); // Chỉ refresh data, không tạo lại charts
        }, 5 * 60 * 1000);
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + R: Refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                document.getElementById('refreshDashboard')?.click();
            }
            
            // Ctrl/Cmd + E: Export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                this.exportData();
            }
            
            // ESC: Close any open modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                });
            }
        });
    }

    showLoading(show) {
        const loader = document.getElementById('dashboardLoader');
        if (loader) {
            loader.style.display = show ? 'flex' : 'none';
        }
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        // Performance optimized notification
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} position-fixed top-0 start-50 translate-middle-x mt-3`;
        notification.style.cssText = `
            z-index: 9999;
            max-width: 400px;
            transform: translateX(-50%) translateY(-100px);
            transition: transform 0.3s ease;
        `;
        
        const icons = {
            success: '✅',
            error: '❌', 
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <span class="me-2">${icons[type] || icons.info}</span>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Trigger animation
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(-50%) translateY(0)';
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transform = 'translateX(-50%) translateY(-100px)';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    // Performance monitoring và cleanup
    destroy() {
        console.log('Destroying dashboard...');
        
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
        
        // Destroy charts to prevent memory leaks
        Object.entries(this.charts).forEach(([key, chart]) => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
                delete this.charts[key];
            }
        });
        
        // Remove event listeners
        document.removeEventListener('keydown', this.keydownHandler);
        
        console.log('Dashboard cleaned up successfully');
    }
}

// Throttle resize events để tránh reflow
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        if (window.dashboardManager && window.dashboardManager.isInitialized) {
            // Chỉ resize charts, không reload data
            Object.values(window.dashboardManager.charts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }
    }, 250);
});

// Intersection Observer cho performance
const observeChartContainers = () => {
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Chart container is visible, safe to create chart
                    const chartId = entry.target.querySelector('canvas')?.id;
                    if (chartId && window.dashboardManager) {
                        console.log(`Chart container ${chartId} is visible`);
                    }
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '50px'
        });

        document.querySelectorAll('.chart-container').forEach(container => {
            observer.observe(container);
        });
    }
};

// Export stats utility for external use
window.DashboardStats = {
    exportData: function() {
        if (window.dashboardManager) {
            window.dashboardManager.exportData();
        } else {
            alert('Dashboard chưa được khởi tạo!');
        }
    },
    
    refreshData: function() {
        if (window.dashboardManager) {
            window.dashboardManager.loadDashboard();
        }
    }
};

// Initialize dashboard when DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing dashboard...');
    
    // Wait a bit to ensure all resources are loaded
    setTimeout(() => {
        window.dashboardManager = new DashboardManager();
        observeChartContainers();
    }, 100);
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (window.dashboardManager) {
            window.dashboardManager.destroy();
        }
    });
});

// Additional CSS optimizations via JavaScript
const addPerformanceStyles = () => {
    const style = document.createElement('style');
    style.textContent = `
        /* Performance optimizations */
        .chart-container canvas {
            max-height: 300px !important;
            height: 300px !important;
        }
        
        /* Prevent layout shifts */
        #progressChart, #skillsRadar {
            width: 100% !important;
            height: 300px !important;
        }
        
        /* GPU acceleration cho animations */
        .progress-card, .activity-item, .achievement {
            will-change: transform;
            backface-visibility: hidden;
        }
        
        /* Smooth scrolling optimization */
        html {
            scroll-behavior: smooth;
        }
        
        /* Reduce motion cho accessibility */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
            
            .chart-container canvas {
                animation: none !important;
            }
        }
        
        /* Memory efficient heatmap */
        #activityHeatmap {
            contain: layout style paint;
        }
        
        .heatmap-cell {
            contain: layout style paint;
            transform: translateZ(0); /* Force GPU layer */
        }
        
        /* Optimize large lists */
        .activity-list, .achievement-grid {
            contain: layout style paint;
        }
        
        /* Fix chart canvas sizing issues */
        .chart-container {
            position: relative;
            width: 100%;
            min-height: 350px;
            contain: layout;
        }
        
        .chart-container canvas {
            position: absolute !important;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            width: calc(100% - 40px) !important;
            height: calc(100% - 40px) !important;
            max-width: none !important;
            max-height: none !important;
        }
        
        /* Prevent forced reflow */
        .dashboard-container * {
            box-sizing: border-box;
        }
        
        .circular-progress {
            contain: layout style paint;
        }
        
        /* Optimize notifications */
        .alert {
            contain: layout style paint;
            transform: translateZ(0);
        }
    `;
    document.head.appendChild(style);
};

// Initialize performance optimizations
addPerformanceStyles();

// Error boundary cho charts
window.addEventListener('error', (event) => {
    if (event.error && event.error.message && event.error.message.includes('Chart')) {
        console.error('Chart error caught:', event.error);
        
        if (window.dashboardManager) {
            window.dashboardManager.showNotification(
                'Có lỗi với biểu đồ. Đang thử khôi phục...', 
                'warning'
            );
            
            // Try to recover charts
            setTimeout(() => {
                window.dashboardManager.loadProgressChart();
                window.dashboardManager.loadSkillsRadar();
            }, 1000);
        }
        
        event.preventDefault();
    }
});

// Performance monitoring
if (typeof PerformanceObserver !== 'undefined') {
    const perfObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        entries.forEach((entry) => {
            if (entry.entryType === 'measure' && entry.duration > 50) {
                console.warn(`Slow operation detected: ${entry.name} took ${entry.duration}ms`);
            }
        });
    });
    perfObserver.observe({ entryTypes: ['measure'] });
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardManager;
}
