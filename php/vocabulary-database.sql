-- File: php/vocabulary-database.sql

-- 1. Bảng categories (20 chủ đề)
CREATE TABLE vocabulary_categories (
    id SERIAL PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    category_name_en VARCHAR(100) NOT NULL,
    category_icon VARCHAR(10) DEFAULT '📚',
    category_color VARCHAR(7) DEFAULT '#667eea',
    description TEXT,
    difficulty_level INTEGER DEFAULT 1 CHECK (difficulty_level BETWEEN 1 AND 3),
    estimated_hours DECIMAL(3,1) DEFAULT 2.0,
    total_words INTEGER DEFAULT 0,
    display_order INTEGER DEFAULT 0,
    unlock_condition JSONB, -- {"required_categories": [1,2], "min_completion": 80}
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 2. Bảng words (từ vựng chi tiết)  
CREATE TABLE vocabulary_words (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES vocabulary_categories(id) ON DELETE CASCADE,
    japanese_word VARCHAR(100) NOT NULL,
    kanji VARCHAR(100),
    romaji VARCHAR(100) NOT NULL,
    vietnamese_meaning TEXT NOT NULL,
    word_type VARCHAR(50) DEFAULT 'noun', -- noun, verb, adjective, particle, etc.
    example_sentence_jp TEXT,
    example_sentence_vn TEXT,
    usage_note TEXT,
    frequency_rank INTEGER DEFAULT 3 CHECK (frequency_rank BETWEEN 1 AND 5), -- 1=rất phổ biến, 5=ít dùng
    audio_url VARCHAR(255),
    image_url VARCHAR(255),
    jlpt_level VARCHAR(5) DEFAULT 'N5',
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    -- Indexes cho performance
    UNIQUE(category_id, japanese_word)
);

-- 3. Bảng user progress theo category
CREATE TABLE user_category_progress (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    category_id INTEGER REFERENCES vocabulary_categories(id) ON DELETE CASCADE,
    total_words INTEGER DEFAULT 0,
    learned_words INTEGER DEFAULT 0, -- đã xem qua ít nhất 1 lần
    mastered_words INTEGER DEFAULT 0, -- đã trả lời đúng 3+ lần
    quiz_best_score INTEGER DEFAULT 0,
    quiz_attempts INTEGER DEFAULT 0,
    total_study_time INTEGER DEFAULT 0, -- giây
    last_studied_at TIMESTAMP WITH TIME ZONE,
    completion_percentage DECIMAL(5,2) DEFAULT 0.00,
    is_completed BOOLEAN DEFAULT FALSE,
    is_unlocked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(user_id, category_id)
);

-- 4. Bảng knowledge từng từ (spaced repetition)
CREATE TABLE user_word_knowledge (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    word_id INTEGER REFERENCES vocabulary_words(id) ON DELETE CASCADE,
    knowledge_level INTEGER DEFAULT 0 CHECK (knowledge_level BETWEEN 0 AND 5),
    -- 0: chưa học, 1: đã xem, 2: nhớ mơ hồ, 3: nhớ rõ, 4: thuộc lòng, 5: master
    correct_count INTEGER DEFAULT 0,
    wrong_count INTEGER DEFAULT 0,
    ease_factor DECIMAL(3,2) DEFAULT 2.50, -- cho spaced repetition
    interval_days INTEGER DEFAULT 1,
    last_reviewed_at TIMESTAMP WITH TIME ZONE,
    next_review_at TIMESTAMP WITH TIME ZONE,
    difficulty_rating INTEGER DEFAULT 3 CHECK (difficulty_rating BETWEEN 1 AND 5), -- user rating
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(user_id, word_id)
);

-- 5. Bảng quiz sessions chi tiết
CREATE TABLE vocabulary_quiz_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    category_id INTEGER REFERENCES vocabulary_categories(id) ON DELETE CASCADE,
    quiz_type VARCHAR(50) DEFAULT 'category', -- 'category', 'mixed', 'review'
    total_questions INTEGER NOT NULL,
    correct_answers INTEGER DEFAULT 0,
    time_spent INTEGER DEFAULT 0, -- giây
    score INTEGER DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    started_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    completed_at TIMESTAMP WITH TIME ZONE,
    is_completed BOOLEAN DEFAULT FALSE,
    quiz_data JSONB -- lưu chi tiết câu hỏi/đáp án
);

-- 6. Indexes cho performance
CREATE INDEX idx_vocabulary_words_category ON vocabulary_words(category_id);
CREATE INDEX idx_vocabulary_words_active ON vocabulary_words(is_active);
CREATE INDEX idx_user_category_progress_user ON user_category_progress(user_id);
CREATE INDEX idx_user_word_knowledge_user ON user_word_knowledge(user_id);
CREATE INDEX idx_user_word_knowledge_review ON user_word_knowledge(next_review_at);
CREATE INDEX idx_quiz_sessions_user ON vocabulary_quiz_sessions(user_id);

-- 7. Functions và triggers
CREATE OR REPLACE FUNCTION update_vocabulary_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_user_category_progress_updated_at 
    BEFORE UPDATE ON user_category_progress
    FOR EACH ROW EXECUTE FUNCTION update_vocabulary_updated_at();

CREATE TRIGGER update_user_word_knowledge_updated_at 
    BEFORE UPDATE ON user_word_knowledge
    FOR EACH ROW EXECUTE FUNCTION update_vocabulary_updated_at();
