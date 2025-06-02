// js/katakana-test.js - Logic cho trang Katakana Test

document.addEventListener('DOMContentLoaded', function() {
    // Kiểm tra đăng nhập
    if (!requireAuth()) return;

    // Dữ liệu Katakana
    const katakanaBasic = {
        'ア': 'a', 'イ': 'i', 'ウ': 'u', 'エ': 'e', 'オ': 'o',
        'カ': 'ka', 'キ': 'ki', 'ク': 'ku', 'ケ': 'ke', 'コ': 'ko',
        'サ': 'sa', 'シ': 'shi', 'ス': 'su', 'セ': 'se', 'ソ': 'so',
        'タ': 'ta', 'チ': 'chi', 'ツ': 'tsu', 'テ': 'te', 'ト': 'to',
        'ナ': 'na', 'ニ': 'ni', 'ヌ': 'nu', 'ネ': 'ne', 'ノ': 'no',
        'ハ': 'ha', 'ヒ': 'hi', 'フ': 'fu', 'ヘ': 'he', 'ホ': 'ho',
        'マ': 'ma', 'ミ': 'mi', 'ム': 'mu', 'メ': 'me', 'モ': 'mo',
        'ヤ': 'ya', 'ユ': 'yu', 'ヨ': 'yo',
        'ラ': 'ra', 'リ': 'ri', 'ル': 'ru', 'レ': 're', 'ロ': 'ro',
        'ワ': 'wa', 'ヲ': 'wo', 'ン': 'n'
    };

    const katakanaAdvanced = {
        'キャ': 'kya', 'キュ': 'kyu', 'キョ': 'kyo',
        'シャ': 'sha', 'シュ': 'shu', 'ショ': 'sho',
        'チャ': 'cha', 'チュ': 'chu', 'チョ': 'cho',
        'ニャ': 'nya', 'ニュ': 'nyu', 'ニョ': 'nyo',
        'ヒャ': 'hya', 'ヒュ': 'hyu', 'ヒョ': 'hyo',
        'ミャ': 'mya', 'ミュ': 'myu', 'ミョ': 'myo',
        'リャ': 'rya', 'リュ': 'ryu', 'リョ': 'ryo',
        'ガ': 'ga', 'ギ': 'gi', 'グ': 'gu', 'ゲ': 'ge', 'ゴ': 'go',
        'ザ': 'za', 'ジ': 'ji', 'ズ': 'zu', 'ゼ': 'ze', 'ゾ': 'zo',
        'ダ': 'da', 'ヂ': 'ji', 'ヅ': 'zu', 'デ': 'de', 'ド': 'do',
        'バ': 'ba', 'ビ': 'bi', 'ブ': 'bu', 'ベ': 'be', 'ボ': 'bo',
        'パ': 'pa', 'ピ': 'pi', 'プ': 'pu', 'ペ': 'pe', 'ポ': 'po',
        'ギャ': 'gya', 'ギュ': 'gyu', 'ギョ': 'gyo',
        'ジャ': 'ja', 'ジュ': 'ju', 'ジョ': 'jo',
        'ビャ': 'bya', 'ビュ': 'byu', 'ビョ': 'byo',
        'ピャ': 'pya', 'ピュ': 'pyu', 'ピョ': 'pyo'
    };

    let currentData = {};
    let currentMode = 'katakanaToRomaji';
    let score = 0;
    let highScore = 0;
    let usedQuestions = new Set();
    let currentUser = null;

    // DOM elements
    const elements = {
        modeSelect: document.getElementById('modeSelect'),
        quiz: document.getElementById('quiz'),
        score: document.getElementById('score'),
        highScore: document.getElementById('highScore'),
        question: document.getElementById('question'),
        options: document.getElementById('options'),
        feedback: document.getElementById('feedback'),
        basicMode: document.getElementById('basicMode'),
        advancedMode: document.getElementById('advancedMode'),
        fullMode: document.getElementById('fullMode'),
        restart: document.getElementById('restart')
    };

    // Initialize
    checkLoginStatus();
    loadHighScore();
    initializeEventListeners();

    function initializeEventListeners() {
        elements.basicMode.onclick = () => {
            currentData = katakanaBasic;
            elements.modeSelect.classList.add('hidden');
            elements.quiz.classList.remove('hidden');
            showQuestion();
        };

        elements.advancedMode.onclick = () => {
            currentData = katakanaAdvanced;
            elements.modeSelect.classList.add('hidden');
            elements.quiz.classList.remove('hidden');
            showQuestion();
        };

        elements.fullMode.onclick = () => {
            currentData = { ...katakanaBasic, ...katakanaAdvanced };
            elements.modeSelect.classList.add('hidden');
            elements.quiz.classList.remove('hidden');
            showQuestion();
        };

        elements.restart.onclick = () => {
            score = 0;
            elements.score.textContent = score;
            elements.modeSelect.classList.remove('hidden');
            elements.quiz.classList.add('hidden');
            usedQuestions.clear();
            clearFeedback();
        };

        document.querySelectorAll('input[name="quizMode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                currentMode = this.value;
                if (!elements.quiz.classList.contains('hidden')) {
                    showQuestion();
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (!elements.quiz.classList.contains('hidden')) {
                const options = document.querySelectorAll('.option-btn:not([disabled])');
                if (e.key >= '1' && e.key <= '4') {
                    const index = parseInt(e.key) - 1;
                    if (options[index]) {
                        options[index].click();
                    }
                }
            }
            if (e.key.toLowerCase() === 'r' && e.ctrlKey) {
                e.preventDefault();
                elements.restart.click();
            }
        });

        document.addEventListener('mouseover', function(e) {
            if (e.target.classList.contains('mode-btn') || e.target.classList.contains('option-btn')) {
                e.target.style.transform = 'scale(1.05) translateY(-2px)';
            }
        });

        document.addEventListener('mouseout', function(e) {
            if (e.target.classList.contains('mode-btn') || e.target.classList.contains('option-btn')) {
                e.target.style.transform = 'scale(1) translateY(0)';
            }
        });
    }

    function getRandomItem(obj) {
        const keys = Object.keys(obj);
        return keys[Math.floor(Math.random() * keys.length)];
    }

    function generateOptions(correctAnswer, isKatakanaToRomaji) {
        const options = [correctAnswer];
        while (options.length < 4) {
            let randomAnswer;
            if (isKatakanaToRomaji) {
                randomAnswer = currentData[getRandomItem(currentData)];
            } else {
                randomAnswer = getRandomItem(currentData);
            }
            if (!options.includes(randomAnswer) && randomAnswer !== correctAnswer) {
                options.push(randomAnswer);
            }
        }
        return JapaneseApp.utils.shuffleArray(options);
    }

    function showQuestion() {
        let question, correctAnswer;
        const isKatakanaToRomaji = currentMode === 'katakanaToRomaji';

        if (isKatakanaToRomaji) {
            question = getRandomItem(currentData);
            while (usedQuestions.has(question) && usedQuestions.size < Object.keys(currentData).length) {
                question = getRandomItem(currentData);
            }
            correctAnswer = currentData[question];
        } else {
            const romaji = currentData[getRandomItem(currentData)];
            while (usedQuestions.has(romaji) && usedQuestions.size < Object.keys(currentData).length) {
                romaji = currentData[getRandomItem(currentData)];
            }
            question = romaji;
            for (const [katakana, rom] of Object.entries(currentData)) {
                if (rom === question) {
                    correctAnswer = katakana;
                    break;
                }
            }
        }

        usedQuestions.add(question);
        if (usedQuestions.size >= Object.keys(currentData).length) {
            usedQuestions.clear();
        }

        elements.question.textContent = question;
        const options = generateOptions(correctAnswer, isKatakanaToRomaji);
        elements.options.innerHTML = '';

        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'option-btn';
            btn.textContent = opt;
            btn.onclick = () => checkAnswer(opt, correctAnswer);
            elements.options.appendChild(btn);
        });

        clearFeedback();
        animateQuestion();
        updateProgressIndicator();
    }

    function checkAnswer(selected, correct) {
        const buttons = document.querySelectorAll('.option-btn');
        buttons.forEach(btn => {
            btn.disabled = true;
            if (btn.textContent === correct) {
                btn.classList.add('correct');
            }
            if (btn.textContent === selected && selected !== correct) {
                btn.classList.add('wrong');
            }
        });

        if (selected === correct) {
            score += 10;
            elements.feedback.textContent = 'Đúng rồi! 🎉';
            elements.feedback.className = 'feedback-correct';
            elements.score.textContent = score;

            if (score > highScore) {
                highScore = score;
                elements.highScore.textContent = highScore;
                saveHighScore();
            }

            saveProgressToServer();
        } else {
            elements.feedback.textContent = `Sai rồi! 😔 Đáp án đúng là: ${correct}`;
            elements.feedback.className = 'feedback-wrong';
        }

        setTimeout(() => {
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('correct', 'wrong');
            });
            showQuestion();
        }, 2000);
    }

    function clearFeedback() {
        elements.feedback.textContent = '';
        elements.feedback.className = '';
    }

    function animateQuestion() {
        elements.question.style.opacity = '0';
        elements.question.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            elements.question.style.transition = 'all 0.5s ease';
            elements.question.style.opacity = '1';
            elements.question.style.transform = 'translateY(0)';
        }, 100);

        const optionButtons = document.querySelectorAll('.option-btn');
        optionButtons.forEach((btn, index) => {
            btn.style.opacity = '0';
            btn.style.transform = 'translateY(20px)';
            setTimeout(() => {
                btn.style.transition = 'all 0.5s ease';
                btn.style.opacity = '1';
                btn.style.transform = 'translateY(0)';
            }, 200 + index * 100);
        });
    }

    async function checkLoginStatus() {
        try {
            const response = await JapaneseApp.api.get('auth.php?action=check');
            if (response.success && response.user) {
                currentUser = response.user;
                JapaneseApp.notifications.success(`Chào ${currentUser.username}!`);
            } else {
                JapaneseApp.notifications.warning('Vui lòng đăng nhập để sử dụng tính năng này!');
                setTimeout(() => {
                    JapaneseApp.transition.navigateTo('index.html');
                }, 3000);
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            JapaneseApp.notifications.error('Lỗi kiểm tra đăng nhập. Chuyển về trang chủ...');
            setTimeout(() => {
                JapaneseApp.transition.navigateTo('index.html');
            }, 3000);
        }
    }

    async function loadHighScore() {
        if (currentUser) {
            try {
                const response = await JapaneseApp.api.get('user-progress.php?action=get');
                if (response.success) {
                    const progress = response.progress;
                    const katakanaScore = Math.round((progress.katakana || 0) * 10);
                    highScore = katakanaScore;
                    elements.highScore.textContent = highScore;
                }
            } catch (error) {
                console.error('Failed to load high score:', error);
            }
        } else {
            highScore = JapaneseApp.storage.get('katakana_high_score', 0);
            elements.highScore.textContent = highScore;
        }
    }

    async function saveHighScore() {
        if (currentUser) {
            await saveProgressToServer();
        } else {
            JapaneseApp.storage.set('katakana_high_score', highScore);
        }
    }

    async function saveProgressToServer() {
        if (!currentUser) return;
        try {
            const totalQuestions = usedQuestions.size || 1;
            const correctAnswers = Math.floor(score / 10);
            const response = await JapaneseApp.api.post('user-progress.php', {
                action: 'save',
                type: 'katakana',
                score: correctAnswers,
                total: totalQuestions
            });
            if (response.success) {
                console.log('Progress saved successfully');
            }
        } catch (error) {
            console.error('Error saving progress:', error);
        }
    }

    function updateProgressIndicator() {
        if (currentUser && usedQuestions.size > 0 && currentData) {
            const totalChars = Object.keys(currentData).length;
            const progress = Math.round((usedQuestions.size / totalChars) * 100);
            let progressBar = document.getElementById('progress-bar');
            if (!progressBar) {
                progressBar = document.createElement('div');
                progressBar.id = 'progress-bar';
                progressBar.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: ${progress}%;
                    height: 4px;
                    background: linear-gradient(135deg, #ff9a8b 0%, #ffecd2 100%);
                    transition: width 0.3s ease;
                    z-index: 9999;
                `;
                document.body.appendChild(progressBar);
            } else {
                progressBar.style.width = progress + '%';
            }
        }
    }
});
