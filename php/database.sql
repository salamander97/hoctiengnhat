-- php/database.sql - Script tạo cơ sở dữ liệu cho ứng dụng học tiếng Nhật

-- Tạo database (chạy với quyền superuser)
-- CREATE DATABASE japanese_learning WITH ENCODING 'UTF8' LC_COLLATE='en_US.UTF-8' LC_CTYPE='en_US.UTF-8';

-- Sử dụng database japanese_learning
-- \c japanese_learning;

-- Tạo extension cho UUID (nếu cần)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Bảng users - Lưu thông tin người dùng
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    last_login TIMESTAMP WITH TIME ZONE
);

-- Index cho users
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_active ON users(is_active);

-- Bảng user_progress - Lưu tiến độ học tập
CREATE TABLE user_progress (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    
    -- Tiến độ Hiragana
    hiragana_score INTEGER DEFAULT 0,
    hiragana_total INTEGER DEFAULT 0,
    
    -- Tiến độ Katakana
    katakana_score INTEGER DEFAULT 0,
    katakana_total INTEGER DEFAULT 0,
    
    -- Tiến độ số đếm
    numbers_score INTEGER DEFAULT 0,
    numbers_total INTEGER DEFAULT 0,
    
    -- Tiến độ từ vựng N5
    vocabulary_n5_score INTEGER DEFAULT 0,
    vocabulary_n5_total INTEGER DEFAULT 0,
    
    -- Tiến độ từ vựng N4
    vocabulary_n4_score INTEGER DEFAULT 0,
    vocabulary_n4_total INTEGER DEFAULT 0,
    
    -- Tiến độ từ vựng N3
    vocabulary_n3_score INTEGER DEFAULT 0,
    vocabulary_n3_total INTEGER DEFAULT 0,
    
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    last_updated TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(user_id)
);

-- Index cho user_progress
CREATE INDEX idx_user_progress_user_id ON user_progress(user_id);
CREATE INDEX idx_user_progress_updated ON user_progress(last_updated);

-- Bảng user_activities - Lưu lịch sử hoạt động học tập
CREATE TABLE user_activities (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    activity_type VARCHAR(50) NOT NULL, -- 'hiragana', 'katakana', 'numbers', 'vocabulary_n5', etc.
    score INTEGER NOT NULL DEFAULT 0,
    total_questions INTEGER NOT NULL DEFAULT 0,
    time_spent INTEGER, -- thời gian làm bài (giây)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Index cho user_activities
CREATE INDEX idx_user_activities_user_id ON user_activities(user_id);
CREATE INDEX idx_user_activities_type ON user_activities(activity_type);
CREATE INDEX idx_user_activities_date ON user_activities(created_at);

-- Bảng quiz_sessions - Lưu thông tin chi tiết các phiên quiz
CREATE TABLE quiz_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    quiz_type VARCHAR(50) NOT NULL,
    total_questions INTEGER NOT NULL,
    correct_answers INTEGER NOT NULL DEFAULT 0,
    time_spent INTEGER, -- thời gian (giây)
    started_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    completed_at TIMESTAMP WITH TIME ZONE,
    is_completed BOOLEAN DEFAULT FALSE
);

-- Index cho quiz_sessions
CREATE INDEX idx_quiz_sessions_user_id ON quiz_sessions(user_id);
CREATE INDEX idx_quiz_sessions_type ON quiz_sessions(quiz_type);
CREATE INDEX idx_quiz_sessions_completed ON quiz_sessions(is_completed);

-- Bảng user_achievements - Lưu thành tích/huy hiệu
CREATE TABLE user_achievements (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    achievement_type VARCHAR(50) NOT NULL, -- 'first_quiz', 'perfect_score', 'streak_7', etc.
    achievement_data JSONB, -- dữ liệu bổ sung cho achievement
    earned_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(user_id, achievement_type)
);

-- Index cho user_achievements
CREATE INDEX idx_user_achievements_user_id ON user_achievements(user_id);
CREATE INDEX idx_user_achievements_type ON user_achievements(achievement_type);

-- Function để tự động update updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Trigger cho bảng users
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Trigger cho bảng user_progress
CREATE TRIGGER update_user_progress_updated_at BEFORE UPDATE ON user_progress
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert dữ liệu mẫu
INSERT INTO users (username, email, password_hash, display_name) VALUES
('admin', 'trunghieu.bomm@gmail.com', '$2y$10$tKjK.RLJ1q2pnTcH2q1c6.O4tIY8HboH8ThWqV7OAHbfmL47DRrMa', 'Phương Thảo');

-- Mật khẩu mặc định cho tất cả account trên là: "password"

-- Insert dữ liệu tiến độ mẫu
INSERT INTO user_progress (user_id, hiragana_score, hiragana_total, katakana_score, katakana_total, numbers_score, numbers_total) VALUES
(1, 45, 50, 38, 50, 25, 30);

-- Insert hoạt động mẫu
INSERT INTO user_activities (user_id, activity_type, score, total_questions) VALUES
(1, 'hiragana', 45, 50),
(1, 'katakana', 38, 50),
(1, 'numbers', 25, 30);


-- View để xem thống kê tổng quan
CREATE VIEW user_stats AS
SELECT 
    u.id,
    u.username,
    u.display_name,
    COALESCE(up.hiragana_score, 0) + COALESCE(up.katakana_score, 0) + COALESCE(up.numbers_score, 0) as total_score,
    COALESCE(up.hiragana_total, 0) + COALESCE(up.katakana_total, 0) + COALESCE(up.numbers_total, 0) as total_questions,
    CASE 
        WHEN COALESCE(up.hiragana_total, 0) + COALESCE(up.katakana_total, 0) + COALESCE(up.numbers_total, 0) > 0 
        THEN ROUND((COALESCE(up.hiragana_score, 0) + COALESCE(up.katakana_score, 0) + COALESCE(up.numbers_score, 0)) * 100.0 / 
                   (COALESCE(up.hiragana_total, 0) + COALESCE(up.katakana_total, 0) + COALESCE(up.numbers_total, 0)), 1)
        ELSE 0 
    END as overall_percentage,
    up.last_updated,
    COUNT(ua.id) as total_activities
FROM users u
LEFT JOIN user_progress up ON u.id = up.user_id
LEFT JOIN user_activities ua ON u.id = ua.user_id
WHERE u.is_active = TRUE
GROUP BY u.id, u.username, u.display_name, up.hiragana_score, up.hiragana_total, 
         up.katakana_score, up.katakana_total, up.numbers_score, up.numbers_total, up.last_updated;

-- Function để tính learning streak
CREATE OR REPLACE FUNCTION calculate_learning_streak(p_user_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    streak INTEGER := 0;
    current_date DATE := CURRENT_DATE;
    activity_dates DATE[];
    i INTEGER;
BEGIN
    -- Lấy danh sách các ngày có hoạt động (distinct)
    SELECT ARRAY_AGG(DISTINCT DATE(created_at) ORDER BY DATE(created_at) DESC)
    INTO activity_dates
    FROM user_activities
    WHERE user_id = p_user_id;
    
    -- Kiểm tra streak từ ngày hiện tại
    FOR i IN 1..COALESCE(array_length(activity_dates, 1), 0) LOOP
        IF activity_dates[i] = current_date - INTERVAL '1 day' * (i - 1) THEN
            streak := streak + 1;
        ELSE
            EXIT;
        END IF;
    END LOOP;
    
    RETURN streak;
END;
$$ LANGUAGE plpgsql;

-- Indexes để tối ưu performance
CREATE INDEX idx_user_activities_user_created_at ON user_activities(user_id, created_at);
CREATE INDEX idx_quiz_sessions_user_type_completed ON quiz_sessions(user_id, quiz_type, is_completed);

-- Ràng buộc để đảm bảo dữ liệu hợp lệ
ALTER TABLE user_progress ADD CONSTRAINT chk_hiragana_score CHECK (hiragana_score >= 0 AND hiragana_score <= hiragana_total);
ALTER TABLE user_progress ADD CONSTRAINT chk_katakana_score CHECK (katakana_score >= 0 AND katakana_score <= katakana_total);
ALTER TABLE user_progress ADD CONSTRAINT chk_numbers_score CHECK (numbers_score >= 0 AND numbers_score <= numbers_total);

ALTER TABLE user_activities ADD CONSTRAINT chk_activity_score CHECK (score >= 0 AND score <= total_questions);
ALTER TABLE user_activities ADD CONSTRAINT chk_activity_total CHECK (total_questions > 0);

-- Comments cho documentation
COMMENT ON TABLE users IS 'Bảng lưu thông tin người dùng';
COMMENT ON TABLE user_progress IS 'Bảng lưu tiến độ học tập của người dùng';
COMMENT ON TABLE user_activities IS 'Bảng lưu lịch sử hoạt động học tập';
COMMENT ON TABLE quiz_sessions IS 'Bảng lưu thông tin chi tiết các phiên quiz';
COMMENT ON TABLE user_achievements IS 'Bảng lưu thành tích/huy hiệu của người dùng';

-- Grant permissions (adjust as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO your_app_user;
-- GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO your_app_user;
