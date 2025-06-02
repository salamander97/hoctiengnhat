-- recreate-user-progress.sql - Tạo lại bảng user_progress với cấu trúc đúng

-- Backup dữ liệu cũ (nếu có)
CREATE TABLE IF NOT EXISTS user_progress_backup AS SELECT * FROM user_progress;

-- Xóa bảng cũ
DROP TABLE IF EXISTS user_progress CASCADE;

-- Tạo lại bảng user_progress với cấu trúc đầy đủ
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
    
    -- Timestamps (cả hai đều cần thiết)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    last_updated TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(user_id)
);

-- Tạo indexes
CREATE INDEX idx_user_progress_user_id ON user_progress(user_id);
CREATE INDEX idx_user_progress_updated ON user_progress(last_updated);

-- Khôi phục dữ liệu từ backup (nếu có)
INSERT INTO user_progress (
    user_id, hiragana_score, hiragana_total, katakana_score, katakana_total, 
    numbers_score, numbers_total, vocabulary_n5_score, vocabulary_n5_total,
    vocabulary_n4_score, vocabulary_n4_total, vocabulary_n3_score, vocabulary_n3_total,
    created_at, last_updated, updated_at
)
SELECT 
    user_id, 
    COALESCE(hiragana_score, 0), COALESCE(hiragana_total, 0),
    COALESCE(katakana_score, 0), COALESCE(katakana_total, 0),
    COALESCE(numbers_score, 0), COALESCE(numbers_total, 0),
    COALESCE(vocabulary_n5_score, 0), COALESCE(vocabulary_n5_total, 0),
    COALESCE(vocabulary_n4_score, 0), COALESCE(vocabulary_n4_total, 0),
    COALESCE(vocabulary_n3_score, 0), COALESCE(vocabulary_n3_total, 0),
    COALESCE(created_at, NOW()), 
    COALESCE(last_updated, NOW()),
    NOW()
FROM user_progress_backup
WHERE EXISTS (SELECT 1 FROM user_progress_backup);

-- Tạo trigger
CREATE TRIGGER update_user_progress_updated_at 
    BEFORE UPDATE ON user_progress
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Thêm constraints để đảm bảo dữ liệu hợp lệ
ALTER TABLE user_progress ADD CONSTRAINT chk_hiragana_score CHECK (hiragana_score >= 0 AND (hiragana_total = 0 OR hiragana_score <= hiragana_total));
ALTER TABLE user_progress ADD CONSTRAINT chk_katakana_score CHECK (katakana_score >= 0 AND (katakana_total = 0 OR katakana_score <= katakana_total));
ALTER TABLE user_progress ADD CONSTRAINT chk_numbers_score CHECK (numbers_score >= 0 AND (numbers_total = 0 OR numbers_score <= numbers_total));

-- Xóa backup table (có thể comment dòng này để giữ backup)
-- DROP TABLE IF EXISTS user_progress_backup;

-- Hiển thị cấu trúc bảng mới
SELECT 
    column_name, 
    data_type, 
    is_nullable, 
    column_default,
    character_maximum_length
FROM information_schema.columns 
WHERE table_name = 'user_progress' 
ORDER BY ordinal_position;
