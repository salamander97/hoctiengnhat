/**
 * vocabulary-study.js - Flashcard study system with database integration
 * Fixed version that properly loads data from API instead of sample data
 */

class VocabularyStudy {
    constructor() {
        // Core data
        this.words = [];
        this.currentWords = [];
        this.currentWordIndex = 0;
        this.studiedWords = [];

        // Session info
        this.currentCategoryId = null;
        this.sessionType = 'new';
        this.sessionStartTime = Date.now();
        this.isCardFlipped = false;
        this.categoryInfo = null;

        // API configuration
        this.apiBase = 'php/vocabulary-api.php';

        // Debug mode
        this.debugMode = false;

        // Data source tracking
        this.dataSource = 'unknown';

        // Sample data for emergency fallback only
        this.sampleWords = [
            {
                id: 1,
                japanese_word: 'こんにちは',
                kanji: '',
                romaji: 'konnichiwa',
                vietnamese_meaning: 'Xin chào',
                word_type: 'expression',
                example_sentence_jp: 'こんにちは、田中さん。',
                example_sentence_vn: 'Xin chào anh Tanaka.',
                usage_note: 'Lời chào phổ biến vào ban ngày',
                frequency_rank: 1,
                knowledge_level: 0,
                category: {
                    name: 'Chào hỏi & Giao tiếp',
                    icon: '👋',
                    color: '#FF6B6B'
                }
            },
            {
                id: 2,
                japanese_word: 'ありがとう',
                kanji: '',
                romaji: 'arigatou',
                vietnamese_meaning: 'Cảm ơn',
                word_type: 'expression',
                example_sentence_jp: 'ありがとうございます。',
                example_sentence_vn: 'Cảm ơn anh.',
                usage_note: 'Cách cảm ơn thông thường',
                frequency_rank: 2,
                knowledge_level: 0,
                category: {
                    name: 'Chào hỏi & Giao tiếp',
                    icon: '👋',
                    color: '#FF6B6B'
                }
            },
            {
                id: 3,
                japanese_word: 'すみません',
                kanji: '',
                romaji: 'sumimasen',
                vietnamese_meaning: 'Xin lỗi / Xin phép',
                word_type: 'expression',
                example_sentence_jp: 'すみません、ちょっと質問があります。',
                example_sentence_vn: 'Xin lỗi, tôi có một câu hỏi.',
                usage_note: 'Dùng để xin lỗi hoặc xin phép',
                frequency_rank: 3,
                knowledge_level: 0,
                category: {
                    name: 'Chào hỏi & Giao tiếp',
                    icon: '👋',
                    color: '#FF6B6B'
                }
            }
        ];
    }

    /**
     * Initialize the study session
     */
    async init() {
        console.log('🎌 Khởi tạo Vocabulary Study Manager...');

        // Parse URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        this.currentCategoryId = urlParams.get('category_id');
        this.sessionType = urlParams.get('type') || 'new';

        // Validate required parameters
        if (!this.currentCategoryId) {
            this.showError('Không tìm thấy ID chủ đề! Vui lòng quay lại danh sách chủ đề.', true);
            return;
        }

        // Check authentication
        if (!await this.checkAuth()) {
            this.showError('Vui lòng đăng nhập để sử dụng tính năng này!', true);
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 3000);
            return;
        }

        // Load vocabulary data
        await this.loadWords();

        // Setup UI event listeners
        this.setupEventListeners();

        console.log('✅ Vocabulary Study Manager đã sẵn sàng!');
        this.updateDebugInfo();
    }

    /**
     * Check user authentication
     */
    async checkAuth() {
        try {
            const response = await fetch('php/auth.php?action=check');
            const data = await response.json();
            return data.success && data.user;
        } catch (error) {
            console.error('Auth check failed:', error);
            return false;
        }
    }

    /**
     * Load vocabulary words from database
     */
    async loadWords() {
        try {
            this.showLoading(true);
            console.log('🔍 Loading words for category:', this.currentCategoryId, 'Type:', this.sessionType);
    
            // Construct API URL - thử mode hiện tại trước
            let mode = this.sessionType;
            const apiUrl = `${this.apiBase}?action=get_category_words&category_id=${this.currentCategoryId}&mode=${mode}&limit=50`;
            console.log('🌐 API URL:', apiUrl);
    
            // Fetch data from API
            const response = await fetch(apiUrl);
            const data = await response.json();
    
            console.log('📊 Full API Response:', data);
            this.updateDebugInfo('API Response: ' + JSON.stringify({
                success: data.success,
                count: data.data?.length || 0,
                message: data.message
            }));
    
            if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                // ✅ SUCCESS: Use database data
                this.words = data.data;
                this.currentWords = data.data;
                this.dataSource = 'database';
    
                if (this.words[0] && this.words[0].category) {
                    this.categoryInfo = this.words[0].category;
                }
    
                console.log(`✅ Loaded ${this.words.length} words from database`);
                this.renderWords();
                return;
            }
    
            // ❌ No data, thử fallback nếu mode là 'new'
            if (mode === 'new') {
                console.warn('⚠️ No new words found, switching to mode=all automatically...');
                const fallbackUrl = `${this.apiBase}?action=get_category_words&category_id=${this.currentCategoryId}&mode=all&limit=50`;
                const fallbackResponse = await fetch(fallbackUrl);
                const fallbackData = await fallbackResponse.json();
    
                if (fallbackData.success && fallbackData.data && fallbackData.data.length > 0) {
                    this.words = fallbackData.data;
                    this.currentWords = fallbackData.data;
                    this.dataSource = 'database';
                    this.sessionType = 'all'; // ✨ Update để UI phản ánh
    
                    if (this.words[0] && this.words[0].category) {
                        this.categoryInfo = this.words[0].category;
                    }
    
                    console.log(`✅ Loaded ${this.words.length} words (all mode) from database`);
                    this.showNotification('🎓 Bạn đã học hết từ mới! Đang hiển thị lại toàn bộ từ để ôn tập.', 'info');
                    this.renderWords();
                    return;
                }
            }
    
            // Nếu vẫn không có từ nào
            console.warn('⚠️ API failed or returned no data:', data);
            throw new Error(data.message || 'No words found in database');
    
        } catch (error) {
            console.error('❌ Load words error:', error);
    
            if (error.message && error.message.includes('No words found')) {
                this.showNoNewWordsScreen();
            } else {
                this.showErrorModal(`Không thể tải từ vựng từ database: ${error.message}`, error);
            }
        } finally {
            this.showLoading(false);
        }
    }
    
    /**
     * Show no new words screen
     */
    showNoNewWordsScreen() {
        // Hide study UI
        document.querySelector('.study-header').style.display = 'none';
        document.querySelector('.flashcard-container').style.display = 'none';
        document.getElementById('studyControls').style.display = 'none';

        // Show no new words screen
        document.getElementById('noNewWords').style.display = 'block';
    }

    /**
     * Switch to review mode (all words)
     */
    reviewMode() {
        this.sessionType = 'all';
        this.showLoading(true);
        this.loadWords();

        // Show study UI again
        document.querySelector('.study-header').style.display = 'block';
        document.querySelector('.flashcard-container').style.display = 'flex';
        document.getElementById('studyControls').style.display = 'block';
        document.getElementById('noNewWords').style.display = 'none';
    }

    /**
     * Reset progress for current category
     */
    async resetProgress() {
        if (!confirm('Bạn có chắc muốn reset toàn bộ tiến độ học chủ đề này? Điều này sẽ xóa tất cả từ đã học!')) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}?action=reset_category_progress`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    category_id: this.currentCategoryId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('✅ Đã reset tiến độ! Bắt đầu học lại từ đầu.', 'success');

                // Reload with new mode
                this.sessionType = 'new';
                setTimeout(() => {
                    this.loadWords();

                    // Show study UI again
                    document.querySelector('.study-header').style.display = 'block';
                    document.querySelector('.flashcard-container').style.display = 'flex';
                    document.getElementById('studyControls').style.display = 'block';
                    document.getElementById('noNewWords').style.display = 'none';
                }, 1000);

            } else {
                this.showNotification('❌ Không thể reset tiến độ: ' + data.message, 'error');
            }

        } catch (error) {
            console.error('Reset progress error:', error);
            this.showNotification('❌ Có lỗi xảy ra khi reset tiến độ!', 'error');
        }
    }

    /**
     * Use sample data as fallback
     */
    useSampleData() {
        console.log('🔧 Using sample data as fallback');
        this.currentWords = [...this.sampleWords];
        this.words = [...this.sampleWords];
        this.dataSource = 'sample';

        // Extract category info from sample data
        if (this.sampleWords[0] && this.sampleWords[0].category) {
            this.categoryInfo = this.sampleWords[0].category;
        }

        this.updateDebugInfo('Using sample data');
        this.renderWords();

        // Hide error modal
        const errorModal = bootstrap.Modal.getInstance(document.getElementById('errorModal'));
        if (errorModal) {
            errorModal.hide();
        }

        this.showNotification('Đang sử dụng dữ liệu mẫu. Một số tính năng có thể bị hạn chế.', 'warning');
    }

    /**
     * Render the loaded words
     */
    renderWords() {
        if (!this.currentWords || this.currentWords.length === 0) {
            this.showError('Không có từ vựng nào để học!');
            return;
        }

        console.log('🎌 Rendering words. Total:', this.currentWords.length);
        console.log('📝 Sample word structure:', this.currentWords[0]);

        // Update category display
        this.updateCategoryDisplay();

        // Update data source indicator
        this.updateDataSourceDisplay();

        // Reset session state
        this.currentWordIndex = 0;
        this.studiedWords = [];
        this.sessionStartTime = Date.now();

        // Show first word
        this.showCurrentWord();
    }

    /**
     * Update category display in header
     */
    updateCategoryDisplay() {
        const categoryNameEl = document.getElementById('categoryName');
        const categoryIconEl = document.getElementById('categoryIcon');
        const categoryTextEl = document.getElementById('categoryText');

        if (this.categoryInfo) {
            // Use category info from word data
            if (categoryIconEl) categoryIconEl.textContent = this.categoryInfo.icon || '📚';
            if (categoryTextEl) categoryTextEl.textContent = this.categoryInfo.name || 'Từ vựng N5';
            if (categoryNameEl && this.categoryInfo.color) {
                categoryNameEl.style.background = `linear-gradient(135deg, ${this.categoryInfo.color} 0%, ${this.categoryInfo.color}99 100%)`;
            }
        } else {
            // Fallback display
            if (categoryIconEl) categoryIconEl.textContent = '📚';
            if (categoryTextEl) categoryTextEl.textContent = 'Từ vựng N5';
        }
    }

    /**
     * Update data source display
     */
    updateDataSourceDisplay() {
        const dataSourceEl = document.getElementById('dataSource');
        if (dataSourceEl) {
            const sourceText = {
                'database': '📊 Dữ liệu từ database',
                'sample': '🔧 Dữ liệu mẫu',
                'unknown': '❓ Nguồn không xác định'
            };
            dataSourceEl.textContent = sourceText[this.dataSource] || sourceText.unknown;

            // Add color coding
            if (this.dataSource === 'database') {
                dataSourceEl.style.color = '#28a745';
            } else if (this.dataSource === 'sample') {
                dataSourceEl.style.color = '#ffc107';
            } else {
                dataSourceEl.style.color = '#dc3545';
            }
        }
    }

    /**
     * Update session information display
     */
    updateSessionInfo() {
        // Update counters
        document.getElementById('currentCard').textContent = this.currentWordIndex + 1;
        document.getElementById('totalCards').textContent = this.currentWords.length;
        document.getElementById('studiedToday').textContent = this.studiedWords.length;
        document.getElementById('reviewsDue').textContent = 0; // TODO: Implement review logic

        // Update progress bar
        const progress = ((this.currentWordIndex) / this.currentWords.length) * 100;
        document.getElementById('studyProgress').style.width = progress + '%';

        // Update progress text
        const progressTextEl = document.getElementById('progressText');
        if (progressTextEl) {
            progressTextEl.textContent = `${this.currentWordIndex}/${this.currentWords.length}`;
        }

        // Update session type
        const sessionTypeText = {
            'new': 'Từ mới',
            'review': 'Ôn tập',
            'mixed': 'Học và ôn tập',
            'all': 'Tất cả từ'
        };
        document.getElementById('sessionType').textContent = sessionTypeText[this.sessionType] || 'Học từ vựng';

        this.updateDebugInfo();
    }

    /**
     * Show current word in flashcard
     */
    showCurrentWord() {
        if (this.currentWordIndex >= this.currentWords.length) {
            this.completeSession();
            return;
        }
    
        const word = this.currentWords[this.currentWordIndex];
        console.log('🔄 Displaying word:', word);
    
        if (!word) {
            this.showNotification('❌ Không có dữ liệu từ vựng!', 'error');
            return;
        }
    
        // Front: Từ tiếng Nhật
        const wordJapanese = word.kanji && word.japanese_word && word.kanji !== word.japanese_word
            ? `${word.kanji} (${word.japanese_word})`
            : (word.kanji || word.japanese_word || 'N/A');
        const wordReading = word.romaji || word.japanese_word || '';
    
        // Kiểm tra element tồn tại trước khi set
        const wordJapaneseEl = document.getElementById('wordJapanese');
        const wordReadingEl = document.getElementById('wordReading');
        const wordMeaningEl = document.getElementById('wordMeaning');
        const wordTypeEl = document.getElementById('wordType');
        const wordExampleJpEl = document.getElementById('wordExampleJp');
        const wordExampleVnEl = document.getElementById('wordExampleVn');
        const usageNoteEl = document.getElementById('usageNote');
    
        if (wordJapaneseEl) wordJapaneseEl.textContent = wordJapanese;
        if (wordReadingEl) wordReadingEl.textContent = wordReading;
    
        // Back: Nghĩa tiếng Việt & ví dụ
        if (wordMeaningEl) wordMeaningEl.textContent = word.vietnamese_meaning || 'Không có nghĩa';
        
        // Sửa phần ví dụ - không dùng wordExample mà dùng wordExampleJp và wordExampleVn
        if (wordExampleJpEl) {
            wordExampleJpEl.textContent = word.example_sentence_jp || 'Không có ví dụ';
        }
        if (wordExampleVnEl) {
            wordExampleVnEl.textContent = word.example_sentence_vn || 'Không có dịch';
        }
    
        // Word type
        if (wordTypeEl) {
            wordTypeEl.textContent = this.formatWordType(word.word_type);
        }
    
        // Usage note
        if (usageNoteEl) {
            usageNoteEl.textContent = word.usage_note || 'Không có ghi chú';
        }
    
        // Difficulty
        this.updateDifficultyIndicator(word);
    
        // Intervals
        this.updateIntervalPredictions(word);
    
        // Reset card state
        const flashcard = document.getElementById('flashcard');
        if (flashcard) {
            flashcard.classList.remove('flipped');
            this.isCardFlipped = false;
            flashcard.focus();
        }
    
        // Update info
        this.updateSessionInfo();
        this.animateCardEntrance();
    }
    updateDifficultyIndicator(word) {
        const currentKnowledgeLevelEl = document.getElementById('currentKnowledgeLevel');
        if (currentKnowledgeLevelEl && word.knowledge_level !== undefined) {
            currentKnowledgeLevelEl.textContent = this.getKnowledgeLevelText(word.knowledge_level);
        }
    
        // Update frequency rank if available
        const frequencyRankEl = document.getElementById('frequencyRank');
        if (frequencyRankEl && word.frequency_rank) {
            frequencyRankEl.textContent = `Tần suất: #${word.frequency_rank}`;
            frequencyRankEl.style.display = 'block';
        } else if (frequencyRankEl) {
            frequencyRankEl.style.display = 'none';
        }
    }
    
    /**
     * Update interval predictions (placeholder for spaced repetition)
     */
    updateIntervalPredictions(word) {
        // TODO: Implement spaced repetition interval predictions
        // This would show how long until next review based on current knowledge level
        console.log('Interval predictions for word:', word.id, 'level:', word.knowledge_level);
    }
    
    /**
     * Update review info display
     */
    updateReviewInfo() {
        // Update session counters
        this.updateSessionInfo();
        
        // Update controls state
        this.updateControlsState();
    }
    

    /**
     * Format word type for display
     */
    formatWordType(type) {
        const types = {
            'noun': '🏷️ Danh từ',
            'verb': '⚡ Động từ',
            'adjective': '🎨 Tính từ',
            'adverb': '💫 Trạng từ',
            'particle': '🔗 Trợ từ',
            'expression': '💬 Cụm từ',
            'number': '🔢 Số',
            'pronoun': '👤 Đại từ',
            'conjunction': '🔗 Liên từ',
            'interjection': '❗ Thán từ',
            'greeting': '👋 Lời chào'
        };
        return types[type] || `📝 ${type || 'Khác'}`;
    }

    /**
     * Get knowledge level text
     */
    getKnowledgeLevelText(level) {
        const levels = {
            0: 'Chưa học',
            1: 'Mới học',
            2: 'Đang học',
            3: 'Quen thuộc',
            4: 'Thành thạo',
            5: 'Thạo vào'
        };
        return levels[level] || 'Không xác định';
    }

    /**
     * Update controls state based on card flip
     */
    updateControlsState() {
        const controls = document.getElementById('studyControls');
        if (controls) {
            if (this.isCardFlipped) {
                controls.classList.remove('disabled');
                controls.style.opacity = '1';
                controls.style.pointerEvents = 'auto';
                controls.style.filter = 'none';
            } else {
                controls.classList.add('disabled');
                controls.style.opacity = '0.6';
                controls.style.pointerEvents = 'none';
                controls.style.filter = 'grayscale(30%)';
            }
        }
    }

    /**
     * Flip the flashcard
     */
    flipCard() {
        const flashcard = document.getElementById('flashcard');
        if (!flashcard) {
            console.error('❌ Flashcard element not found!');
            return;
        }

        this.isCardFlipped = !this.isCardFlipped;
        flashcard.classList.toggle('flipped');

        // Update controls state
        this.updateControlsState();

        // Show notification

        this.updateDebugInfo();
    }

    /**
     * Rate the current word's difficulty
     */
    async rateWord(rating) {
        if (!this.isCardFlipped) {
            this.showNotification('Hãy xem nghĩa của từ trước khi đánh giá!', 'warning');
            return;
        }

        const word = this.currentWords[this.currentWordIndex];
        if (!word) {
            console.error('❌ No current word to rate!');
            return;
        }

        try {
            console.log('💾 Rating word:', word.id, 'Rating:', rating);

            // Save to studied words
            this.studiedWords.push({
                ...word,
                rating: rating,
                studied_at: new Date().toISOString(),
                time_spent: Date.now() - this.sessionStartTime
            });

            // Update word knowledge via API (only if using database)
            if (this.dataSource === 'database') {
                await this.updateWordKnowledge(word.id, rating);
            }

            // Show feedback

            // Move to next word after delay
            setTimeout(() => this.nextWord(), 1000);

        } catch (error) {
            console.error('❌ Rate word error:', error);
            this.showNotification('Có lỗi xảy ra khi lưu đánh giá!', 'error');
        }
    }

    /**
     * Update word knowledge via API
     */
    async updateWordKnowledge(wordId, rating) {
        try {
            const requestData = {
                word_id: wordId,
                is_correct: rating >= 3, // Consider 3+ as correct
                difficulty_rating: rating,
                study_time: Math.floor((Date.now() - this.sessionStartTime) / 1000)
            };

            const response = await fetch(`${this.apiBase}?action=update_word_knowledge`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();
            if (!data.success) {
                console.warn('⚠️ Failed to update word knowledge:', data.message);
            }
        } catch (error) {
            console.error('❌ Update word knowledge error:', error);
        }
    }


    /**
     * Move to next word
     */
    nextWord() {
        this.currentWordIndex++;

        if (this.currentWordIndex >= this.currentWords.length) {
            this.completeSession();
        } else {
            this.showCurrentWord();
        }
    }

    /**
     * Skip current word
     */
    skipWord() {
        if (confirm('Bạn có chắc muốn bỏ qua từ này không?')) {
            this.showNotification('⏭️ Đã bỏ qua từ này', 'info');
            this.nextWord();
        }
    }

    /**
     * Show hint for current word
     */
    showHint() {
        if (!this.isCardFlipped) {
            const word = this.currentWords[this.currentWordIndex];
            if (word && word.vietnamese_meaning) {
                const meaning = word.vietnamese_meaning;
                const firstChar = meaning.charAt(0);
                const wordLength = meaning.length;
                const hint = `${firstChar}${'*'.repeat(wordLength - 1)} (${wordLength} ký tự)`;
                this.showNotification(`💡 Gợi ý: "${hint}"`, 'info');
            } else {
                this.showNotification('💡 Hãy lật thẻ để xem đáp án!', 'info');
            }
        } else {
            this.showNotification('💡 Bạn đã thấy đáp án rồi. Hãy đánh giá độ khó!', 'info');
        }
    }

    /**
     * Play audio pronunciation
     */
    playAudio() {
        const word = this.currentWords[this.currentWordIndex];
        if (!word) return;

        const textToSpeak = word.japanese_word || word.kanji || '';

        if ('speechSynthesis' in window && textToSpeak) {
            // Cancel any ongoing speech
            speechSynthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(textToSpeak);
            utterance.lang = 'ja-JP';
            utterance.rate = 0.8;
            utterance.pitch = 1;
            utterance.volume = 0.8;

            utterance.onstart = () => {
                this.showNotification('🔊 Đang phát âm...', 'info');
            };

            utterance.onerror = () => {
                this.showNotification('❌ Không thể phát âm!', 'error');
            };

            speechSynthesis.speak(utterance);
        } else {
            this.showNotification('Trình duyệt không hỗ trợ phát âm hoặc không có dữ liệu âm thanh!', 'warning');
        }
    }

    /**
     * Complete study session
     */
    async completeSession() {
        const totalWords = this.studiedWords.length;
        const sessionTime = Date.now() - this.sessionStartTime;
        const accuracy = this.calculateAccuracy();
        const experience = totalWords * 10;

        // Update completion display
        document.getElementById('completedWords').textContent = totalWords;
        document.getElementById('sessionAccuracy').textContent = accuracy + '%';
        document.getElementById('sessionTime').textContent = this.formatTime(sessionTime);
        document.getElementById('experienceGained').textContent = '+' + experience;

        // Update rating summary
        this.updateRatingSummary();

        // Save session time to API
        if (this.dataSource === 'database') {
            await this.saveStudyTime(Math.floor(sessionTime / 1000));
        }

        // Hide study UI and show completion
        document.querySelector('.study-header').style.display = 'none';
        document.querySelector('.flashcard-container').style.display = 'none';
        document.getElementById('studyControls').style.display = 'none';
        document.getElementById('sessionComplete').style.display = 'block';

        // Show celebration
        this.showCelebration();

        // Log completion
        console.log('🎉 Session completed:', {
            totalWords,
            accuracy,
            sessionTime: this.formatTime(sessionTime),
            experience
        });
    }

    /**
     * Calculate session accuracy
     */
    calculateAccuracy() {
        if (this.studiedWords.length === 0) return 0;
        const goodRatings = this.studiedWords.filter(w => w.rating >= 3).length;
        return Math.round((goodRatings / this.studiedWords.length) * 100);
    }

    /**
     * Update rating summary display
     */
    updateRatingSummary() {
        const summary = document.getElementById('ratingSummary');
        if (!summary) return;

        const ratingCounts = { 1: 0, 2: 0, 3: 0, 4: 0 };
        this.studiedWords.forEach(word => {
            if (word.rating && ratingCounts.hasOwnProperty(word.rating)) {
                ratingCounts[word.rating]++;
            }
        });

        const ratingLabels = {
            1: { label: 'Lại', color: '#ef4444', emoji: '😰' },
            2: { label: 'Khó', color: '#f59e0b', emoji: '🤔' },
            3: { label: 'Tốt', color: '#10b981', emoji: '😊' },
            4: { label: 'Dễ', color: '#3b82f6', emoji: '😄' }
        };

        summary.innerHTML = Object.entries(ratingCounts)
            .map(([rating, count]) => {
                const info = ratingLabels[rating];
                return `
                    <div class="rating-count" style="background: ${info.color}20; color: ${info.color}; border: 1px solid ${info.color}40;">
                        ${info.emoji} ${info.label}: ${count}
                    </div>
                `;
            })
            .join('');
    }

    /**
     * Format time in mm:ss format
     */
    formatTime(milliseconds) {
        const seconds = Math.floor(milliseconds / 1000);
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    /**
     * Save study time via API
     */
    async saveStudyTime(seconds) {
        try {
            const requestData = {
                category_id: this.currentCategoryId,
                study_time: seconds
            };

            const response = await fetch(`${this.apiBase}?action=save_study_time`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();
            if (data.success) {
                console.log('✅ Study time saved successfully');
            } else {
                console.warn('⚠️ Failed to save study time:', data.message);
            }
        } catch (error) {
            console.error('❌ Save study time error:', error);
        }
    }

    /**
     * Show celebration animation
     */
    showCelebration() {
        const celebration = document.createElement('div');
        celebration.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9998;
        `;

        const emojis = ['🎉', '🌟', '✨', '🎊', '👏', '🥳', '🏆', '💯'];

        for (let i = 0; i < 60; i++) {
            const confetti = document.createElement('div');
            confetti.textContent = emojis[Math.floor(Math.random() * emojis.length)];
            confetti.style.cssText = `
                position: absolute;
                font-size: ${15 + Math.random() * 10}px;
                left: ${Math.random() * 100}%;
                top: -30px;
                animation: confettiFall ${3 + Math.random() * 2}s linear forwards;
                animation-delay: ${Math.random() * 2}s;
            `;
            celebration.appendChild(confetti);
        }

        document.body.appendChild(celebration);
        setTimeout(() => celebration.remove(), 5000);
    }

    /**
     * Start new study session
     */
    startNewSession() {
        // Reset session state
        this.currentWordIndex = 0;
        this.studiedWords = [];
        this.sessionStartTime = Date.now();

        // Show loading and reload words
        this.showLoading(true);
        this.loadWords();

        // Show study UI
        document.querySelector('.study-header').style.display = 'block';
        document.querySelector('.flashcard-container').style.display = 'flex';
        document.getElementById('studyControls').style.display = 'block';
        document.getElementById('sessionComplete').style.display = 'none';

        this.showNotification('🔄 Bắt đầu phiên học mới!', 'success');
    }

    /**
     * Go to quiz (placeholder)
     */
    goToQuiz() {
        const quizUrl = `vocabulary-quiz.html?category_id=${this.currentCategoryId}`;
        this.showNotification('Chuyển đến quiz...', 'info');
        setTimeout(() => {
            window.location.href = quizUrl;
        }, 1000);
    }

    /**
     * Go to stats (placeholder)
     */
    goToStats() {
        const statsUrl = `vocabulary-stats.html?category_id=${this.currentCategoryId}`;
        this.showNotification('Chuyển đến thống kê...', 'info');
        setTimeout(() => {
            window.location.href = statsUrl;
        }, 1000);
    }

    /**
     * End current session
     */
    endSession() {
        const studiedCount = this.studiedWords.length;
        const confirmMessage = studiedCount > 0
            ? `Bạn đã học ${studiedCount} từ. Có chắc muốn kết thúc phiên học?`
            : 'Bạn có chắc muốn kết thúc phiên học?';

        if (confirm(confirmMessage)) {
            if (studiedCount > 0) {
                this.completeSession();
            } else {
                window.location.href = 'vocabulary-categories.html';
            }
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        console.log('🎮 Setting up event listeners...');

        const flashcard = document.getElementById('flashcard');
        if (!flashcard) {
            console.error('❌ Flashcard element not found for event listeners!');
            return;
        }

        // Flashcard click/touch events
        flashcard.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.flipCard();
        });

        flashcard.addEventListener('touchend', (e) => {
            e.preventDefault();
            this.flipCard();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Prevent default for handled keys
            const handledKeys = [' ', 'Enter', '1', '2', '3', '4', 'ArrowRight', 'n', 's', 'h', 'p', 'f', 'Escape'];
            if (handledKeys.includes(e.key)) {
                e.preventDefault();
            }

            switch (e.key) {
                case ' ':
                case 'Enter':
                case 'f':
                    this.flipCard();
                    break;
                case '1':
                    if (this.isCardFlipped) this.rateWord(1);
                    break;
                case '2':
                    if (this.isCardFlipped) this.rateWord(2);
                    break;
                case '3':
                    if (this.isCardFlipped) this.rateWord(3);
                    break;
                case '4':
                    if (this.isCardFlipped) this.rateWord(4);
                    break;
                case 'ArrowRight':
                case 'n':
                    this.nextWord();
                    break;
                case 's':
                    this.skipWord();
                    break;
                case 'h':
                    this.showHint();
                    break;
                case 'p':
                    this.playAudio();
                    break;
                case 'Escape':
                    this.endSession();
                    break;
                case 'd':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        this.toggleDebug();
                    }
                    break;
            }
        });

        // Touch gestures
        this.setupTouchGestures();

        console.log('✅ All event listeners ready');
    }

    /**
     * Setup touch gestures for mobile
     */
    setupTouchGestures() {
        let startX = null;
        let startY = null;
        const flashcard = document.getElementById('flashcard');

        flashcard.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });

        flashcard.addEventListener('touchend', (e) => {
            if (!startX || !startY) return;

            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const diffX = startX - endX;
            const diffY = startY - endY;

            // If tap (small movement), flip card
            if (Math.abs(diffX) < 30 && Math.abs(diffY) < 30) {
                return; // Let click handler deal with it
            }

            // Swipe gestures
            if (Math.abs(diffX) > Math.abs(diffY)) {
                if (diffX > 50) {
                    // Swipe left - next word
                    this.nextWord();
                } else if (diffX < -50) {
                    // Swipe right - hint
                    this.showHint();
                }
            } else {
                if (diffY > 50) {
                    // Swipe up - play audio
                    this.playAudio();
                } else if (diffY < -50) {
                    // Swipe down - skip word
                    this.skipWord();
                }
            }

            startX = null;
            startY = null;
        }, { passive: true });
    }

    /**
     * Animate card entrance
     */
    animateCardEntrance() {
        const flashcard = document.getElementById('flashcard');
        if (!flashcard) return;

        flashcard.style.transform = 'translateY(30px) scale(0.95)';
        flashcard.style.opacity = '0.5';

        setTimeout(() => {
            flashcard.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            flashcard.style.transform = 'translateY(0) scale(1)';
            flashcard.style.opacity = '1';

            setTimeout(() => {
                flashcard.style.transition = 'transform 0.8s cubic-bezier(0.4, 0.0, 0.2, 1)';
            }, 600);
        }, 100);
    }

    /**
     * Toggle debug mode
     */
    toggleDebug() {
        this.debugMode = !this.debugMode;
        const debugInfo = document.getElementById('debugInfo');
        if (debugInfo) {
            debugInfo.style.display = this.debugMode ? 'block' : 'none';
        }
        this.updateDebugInfo();

        this.showNotification(`Debug mode: ${this.debugMode ? 'ON' : 'OFF'}`, 'info');
    }

    /**
     * Update debug information
     */
    updateDebugInfo(apiStatus = null) {
        if (!this.debugMode) return;

        const elements = {
            debugCategoryId: this.currentCategoryId || 'N/A',
            debugIndex: this.currentWordIndex,
            debugTotal: this.currentWords.length,
            debugFlipped: this.isCardFlipped,
            debugDataSource: this.dataSource
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });

        if (apiStatus) {
            const apiElement = document.getElementById('debugAPI');
            if (apiElement) apiElement.textContent = apiStatus;
        }
    }

    /**
     * Show loading overlay
     */
    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }

    /**
     * Show error message
     */
    showError(message, critical = false) {
        console.error('❌ Error:', message);

        if (critical) {
            // Show error modal for critical errors
            this.showErrorModal(message);
        } else {
            this.showNotification(message, 'error');
        }
    }

    /**
     * Show error modal
     */
    showErrorModal(message, error = null) {
        const errorMessageEl = document.getElementById('errorMessage');
        if (errorMessageEl) {
            errorMessageEl.textContent = message;
        }

        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();

        console.error('❌ Critical error:', message, error);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} position-fixed top-0 start-50 translate-middle-x mt-3`;
        notification.style.zIndex = '9999';
        notification.style.maxWidth = '500px';
        notification.style.animation = 'slideInDown 0.5s ease-out';
        notification.style.boxShadow = '0 4px 20px rgba(0,0,0,0.15)';

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

        // Auto remove after delay
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slideOutUp 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }
        }, type === 'error' ? 5000 : 3000);
    }
}

// Global functions for HTML onclick events
let studyManager;

function flipCard() {
    if (studyManager) studyManager.flipCard();
}

function rateWord(rating) {
    if (studyManager) studyManager.rateWord(rating);
}

function skipWord() {
    if (studyManager) studyManager.skipWord();
}

function showHint() {
    if (studyManager) studyManager.showHint();
}

function playAudio() {
    if (studyManager) studyManager.playAudio();
}

function endSession() {
    if (studyManager) studyManager.endSession();
}

function startNewSession() {
    if (studyManager) studyManager.startNewSession();
}

function goToQuiz() {
    if (studyManager) studyManager.goToQuiz();
}

function goToStats() {
    if (studyManager) studyManager.goToStats();
}

function toggleDebug() {
    if (studyManager) studyManager.toggleDebug();
}

function useSampleData() {
    if (studyManager) studyManager.useSampleData();
}

function reviewMode() {
    if (studyManager) studyManager.reviewMode();
}

function resetProgress() {
    if (studyManager) studyManager.resetProgress();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 DOM loaded, khởi tạo Vocabulary Study Manager...');
    studyManager = new VocabularyStudy();
    studyManager.init();
});

// Add CSS for confetti animation
const style = document.createElement('style');
style.textContent = `
@keyframes confettiFall {
    0% {
        transform: translateY(-30px) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

@keyframes slideOutUp {
    from {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    to {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
    }
}
`;
document.head.appendChild(style);
