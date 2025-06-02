/**
 * number-test.js - JavaScript cho trang Kiểm Tra Số Đếm tiếng Nhật
 */

// Khai báo biến toàn cục
const ranges = {
    '1-9': [1, 9],
    '10-99': [10, 99],
    '100-999': [100, 999],
    '1000-9999': [1000, 9999],
    '10000-99999': [10000, 99999]
};

let currentRange = null;
let score = 0;
let highScore = 0;
let usedQuestions = new Set();
let currentUser = null;
let quizMode = 'speakJapanese'; // mặc định là "Số → Tiếng Nhật"
let totalQuestionsAsked = 0;
const maxQuestions = 10;

// Khởi tạo khi DOM đã sẵn sàng
document.addEventListener('DOMContentLoaded', function() {
    // Kiểm tra trạng thái đăng nhập
    checkLoginStatus();
    
    // Tải điểm cao từ máy chủ hoặc localStorage
    loadHighScore();
    
    // Khởi tạo các sự kiện
    initializeEventListeners();
    
    console.log('🔢 Number Test page loaded successfully!');
});

/**
 * Khởi tạo các sự kiện cho các phần tử trên trang
 */
function initializeEventListeners() {
    // Nút "Bắt đầu kiểm tra số đếm"
    const startQuizButton = document.getElementById('startQuiz');
    if (startQuizButton) {
        startQuizButton.addEventListener('click', function() {
            document.getElementById('numberInfo').classList.add('hidden');
            document.getElementById('rangeSelect').classList.remove('hidden');
            document.getElementById('restart').classList.remove('hidden');
            JapaneseApp.animations.fadeIn(document.getElementById('rangeSelect'));
        });
    }

    // Các nút chọn phạm vi số
    document.querySelectorAll('.range-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentRange = this.getAttribute('data-range');
            document.getElementById('rangeSelect').classList.add('hidden');
            document.getElementById('quiz').classList.remove('hidden');
            JapaneseApp.animations.fadeIn(document.getElementById('quiz'));
            score = 0;
            totalQuestionsAsked = 0;
            usedQuestions.clear();
            document.getElementById('score').textContent = score;
            showQuestion();
        });
    });

    // Nút bắt đầu lại
    const restartButton = document.getElementById('restart');
    if (restartButton) {
        restartButton.addEventListener('click', function() {
            score = 0;
            document.getElementById('score').textContent = score;
            document.getElementById('numberInfo').classList.remove('hidden');
            document.getElementById('rangeSelect').classList.add('hidden');
            document.getElementById('quiz').classList.add('hidden');
            document.getElementById('restart').classList.add('hidden');
            usedQuestions.clear();
            clearFeedback();
            JapaneseApp.animations.fadeIn(document.getElementById('numberInfo'));
        });
    }

    // Sự kiện thay đổi chế độ quiz
    document.querySelectorAll('input[name="quizMode"]').forEach(radio => {
        radio.addEventListener('change', function() {
            quizMode = this.value;
            if (!document.getElementById('quiz').classList.contains('hidden')) {
                usedQuestions.clear();
                showQuestion();
            }
        });
    });

    // Thêm hiệu ứng hover cho các nút
    addButtonHoverEffects();

    // Thêm phím tắt
    addKeyboardShortcuts();

    // Thêm hiệu ứng cho các ví dụ số
    addExampleItemEffects();
}

/**
 * Chuyển đổi số thành tiếng Nhật (Hiragana và romaji)
 * @param {number} number - Số cần chuyển đổi
 * @returns {Object} - { reading: Hiragana, romaji: Romaji }
 */
function numberToJapanese(number) {
    if (number < 1 || number > 99999) return null;

    const basics = {
        1: { reading: 'いち', romaji: 'ichi' },
        2: { reading: 'に', romaji: 'ni' },
        3: { reading: 'さん', romaji: 'san' },
        4: { reading: 'よん', romaji: 'yon' },
        5: { reading: 'ご', romaji: 'go' },
        6: { reading: 'ろく', romaji: 'roku' },
        7: { reading: 'なな', romaji: 'nana' },
        8: { reading: 'はち', romaji: 'hachi' },
        9: { reading: 'きゅう', romaji: 'kyuu' },
        10: { reading: 'じゅう', romaji: 'juu' }
    };

    const units = [
        { value: 10000, reading: 'まん', romaji: 'man' },
        { value: 1000, reading: 'せん', romaji: 'sen' },
        { value: 100, reading: 'ひゃく', romaji: 'hyaku' }
    ];

    const specialReadings = {
        300: { reading: 'さんびゃく', romaji: 'sanbyaku' },
        600: { reading: 'ろっぴゃく', romaji: 'roppyaku' },
        800: { reading: 'はっぴゃく', romaji: 'happyaku' },
        3000: { reading: 'さんぜん', romaji: 'sanzen' },
        8000: { reading: 'はっせん', romaji: 'hassen' }
    };

    if (specialReadings[number]) {
        return specialReadings[number];
    }

    if (number <= 10) {
        return basics[number] || { reading: 'ぜろ', romaji: 'zero' };
    }

    let reading = '';
    let romaji = '';
    let remaining = number;

    for (const unit of units) {
        if (remaining >= unit.value) {
            const quotient = Math.floor(remaining / unit.value);
            remaining %= unit.value;
            const subReading = numberToJapanese(quotient);
            if (subReading) {
                if (quotient === 1) {
                    reading += unit.reading;
                    romaji += unit.romaji;
                } else {
                    reading += subReading.reading + unit.reading;
                    romaji += subReading.romaji + unit.romaji;
                }
            }
        }
    }

    if (remaining > 0) {
        const subReading = numberToJapanese(remaining);
        if (subReading) {
            reading += subReading.reading;
            romaji += subReading.romaji;
        }
    }

    return { reading, romaji };
}

/**
 * Chuyển số thành chữ tiếng Việt
 * @param {number} number - Số cần chuyển đổi
 * @returns {string} - Chuỗi chữ tiếng Việt
 */
function numberToVietnameseText(number) {
    if (number < 1 || number > 99999) return 'Số không hợp lệ';

    const units = ['', 'nghìn', 'triệu'];
    const digits = [
        '', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'
    ];
    const tens = ['lẻ', 'mười', 'hai mươi', 'ba mươi', 'yan', 'năm mươi', 'sáu mươi', 'bảy mươi', 'tám mươi', 'chín mươi'];

    let result = '';
    let numStr = number.toString();
    let groups = [];

    while (numStr.length > 0) {
        groups.unshift(numStr.slice(-3));
        numStr = numStr.slice(0, -3);
    }

    for (let i = 0; i < groups.length; i++) {
        let group = parseInt(groups[i]);
        if (group === 0) continue;
        let groupText = '';
        let hundred = Math.floor(group / 100);
        let ten = Math.floor((group % 100) / 10);
        let one = group % 10;

        if (hundred > 0) {
            groupText += digits[hundred] + ' trăm ';
        }

        if (ten > 0) {
            groupText += tens[ten] + ' ';
        } else if (one > 0 && hundred > 0) {
            groupText += 'lẻ ';
        }

        if (one > 0) {
            if (one === 1 && ten > 1) {
                groupText += 'mốt';
            } else if (one === 5 && (ten > 0 || hundred > 0)) {
                groupText += 'lăm';
            } else {
                groupText += digits[one];
            }
        }

        if (groupText.trim()) {
            result = groupText.trim() + ' ' + units[groups.length - 1 - i] + ' ' + result;
        }
    }

    return result.trim() || 'không';
}

/**
 * Kiểm tra trạng thái đăng nhập
 */
function checkLoginStatus() {
    if (!requireAuth()) {
        JapaneseApp.notifications.warning('Vui lòng đăng nhập để chơi quiz!');
        setTimeout(() => JapaneseApp.transition.navigateTo('index.html'), 2000);
        return;
    }
    currentUser = getCurrentUser();
}

/**
 * Tải điểm cao từ máy chủ hoặc localStorage
 */
async function loadHighScore() {
    if (currentUser) {
        try {
            const response = await JapaneseApp.api.get('user-progress.php?action=get');
            if (response.success && response.progress.numbers_score) {
                highScore = response.progress.numbers_score;
                document.getElementById('highScore').textContent = highScore;
            }
        } catch (error) {
            console.error('Lỗi tải điểm cao:', error);
            highScore = JapaneseApp.storage.get('numberHighScore', 0);
            document.getElementById('highScore').textContent = highScore;
        }
    } else {
        highScore = JapaneseApp.storage.get('numberHighScore', 0);
        document.getElementById('highScore').textContent = highScore;
    }
}

/**
 * Hiển thị câu hỏi mới
 */
function showQuestion() {
    if (totalQuestionsAsked >= maxQuestions) {
        endQuiz();
        return;
    }

    const [min, max] = ranges[currentRange];
    let number;
    do {
        number = Math.floor(Math.random() * (max - min + 1)) + min;
    } while (usedQuestions.has(number) && usedQuestions.size < (max - min + 1));

    usedQuestions.add(number);
    const japanese = numberToJapanese(number);
    if (!japanese) {
        console.error('Không tạo được câu hỏi cho số:', number);
        showQuestion();
        return;
    }

    const formats = ['numeric', 'vietnamese'];
    const format = formats[Math.floor(Math.random() * formats.length)];
    let questionText = '';
    if (format === 'vietnamese') {
        questionText = numberToVietnameseText(number);
    } else {
        questionText = JapaneseApp.utils.formatNumber(number);
    }

    const questionElement = document.getElementById('question');
    if (quizMode === 'speakJapanese') {
        questionElement.textContent = `${format === 'numeric' ? 'Số ' : ''}${questionText} đọc là gì?`;
    } else {
        questionElement.textContent = `${japanese.reading} là số nào?`;
    }

    const answers = document.getElementById('answers');
    answers.innerHTML = '';
    let correctAnswer = quizMode === 'speakJapanese' ? japanese.reading : number.toString();
    const options = JapaneseApp.quiz.generateOptions(
        correctAnswer,
        quizMode === 'speakJapanese' ? 
            Array.from({length: max - min + 1}, (_, i) => min + i)
                .map(num => numberToJapanese(num).reading)
                .filter((v, i, a) => a.indexOf(v) === i) :
            Array.from({length: max - min + 1}, (_, i) => min + i).map(String),
        4
    );

    options.forEach(option => {
        const button = document.createElement('button');
        button.className = 'btn btn-outline-primary m-2 answer-btn';
        button.textContent = option;
        button.addEventListener('click', () => handleAnswer(option, correctAnswer, button));
        answers.appendChild(button);
    });

    totalQuestionsAsked++;
    clearFeedback();
}

/**
 * Xử lý câu trả lời
 * @param {string} selected - Đáp án được chọn
 * @param {string} correct - Đáp án đúng
 * @param {HTMLElement} button - Nút được nhấn
 */
function handleAnswer(selected, correct, button) {
    const isCorrect = selected === correct;
    const feedback = document.getElementById('feedback');

    if (isCorrect) {
        score += 10;
        document.getElementById('score').textContent = score;
        JapaneseApp.notifications.success('Đúng rồi! 🎉');
        JapaneseApp.animations.bounce(button);
        feedback.textContent = 'Tuyệt vời!';
        feedback.className = 'text-success';
    } else {
        JapaneseApp.notifications.error(`Sai rồi! Đáp án đúng là "${correct}" 😅`);
        JapaneseApp.animations.shake(button);
        feedback.textContent = `Đáp án đúng: ${correct}`;
        feedback.className = 'text-danger';
    }

    document.querySelectorAll('.answer-btn').forEach(btn => btn.disabled = true);
    setTimeout(showQuestion, 1000);
}

/**
 * Kết thúc quiz
 */
async function endQuiz() {
    document.getElementById('quiz').classList.add('hidden');
    const resultElement = document.getElementById('result');
    const result = JapaneseApp.quiz.formatResult(score, maxQuestions * 10, totalQuestionsAsked * 5);
    resultElement.innerHTML = `
        <h3>Hoàn thành! 🎉</h3>
        <p>Điểm: ${result.score}/${result.total} (${result.percentage}%)</p>
        <p>${result.grade.message} (${result.grade.grade})</p>
    `;
    JapaneseApp.animations.fadeIn(resultElement);

    if (score > highScore) {
        highScore = score;
        document.getElementById('highScore').textContent = highScore;
        JapaneseApp.storage.set('numberHighScore', highScore);
        if (currentUser) {
            const saved = await saveProgress('numbers', score, maxQuestions * 10);
            if (saved) {
                JapaneseApp.notifications.success('Đã lưu điểm cao mới!');
            }
        }
    }

    const replayButton = document.createElement('button');
    replayButton.className = 'btn btn-primary mt-3';
    replayButton.textContent = 'Chơi lại';
    replayButton.addEventListener('click', () => {
        JapaneseApp.animations.fadeOut(resultElement);
        score = 0;
        document.getElementById('score').textContent = score;
        document.getElementById('numberInfo').classList.remove('hidden');
        document.getElementById('rangeSelect').classList.add('hidden');
        document.getElementById('restart').classList.add('hidden');
        usedQuestions.clear();
        resultElement.innerHTML = '';
        JapaneseApp.animations.fadeIn(document.getElementById('numberInfo'));
    });
    resultElement.appendChild(replayButton);
}

/**
 * Xóa phản hồi
 */
function clearFeedback() {
    const feedback = document.getElementById('feedback');
    feedback.textContent = '';
    feedback.className = '';
}

/**
 * Thêm hiệu ứng hover cho các nút
 */
function addButtonHoverEffects() {
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('mouseenter', () => {
            btn.style.transform = 'scale(1.05)';
            btn.style.transition = 'transform 0.2s ease';
        });
        btn.addEventListener('mouseleave', () => {
            btn.style.transform = 'scale(1)';
        });
    });
}

/**
 * Thêm phím tắt
 */
function addKeyboardShortcuts() {
    document.addEventListener('keydown', function(event) {
        if (document.getElementById('quiz').classList.contains('hidden')) return;

        const answerButtons = document.querySelectorAll('.answer-btn');
        if (event.key >= '1' && event.key <= '4') {
            const index = parseInt(event.key) - 1;
            if (answerButtons[index]) {
                answerButtons[index].click();
            }
        } else if (event.ctrlKey && event.key.toLowerCase() === 'r') {
            event.preventDefault();
            document.getElementById('restart').click();
        }
    });
}

/**
 * Thêm hiệu ứng cho các ví dụ số
 */
function addExampleItemEffects() {
    document.querySelectorAll('.example-item').forEach(item => {
        item.addEventListener('click', () => {
            JapaneseApp.animations.bounce(item);
            const number = parseInt(item.getAttribute('data-number'));
            const japanese = numberToJapanese(number);
            JapaneseApp.notifications.info(`Số ${number} là "${japanese.reading}"`);
        });
    });
}
