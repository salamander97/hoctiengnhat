-- File: php/vocabulary-sample-data.sql (tiếp tục thêm vào phần INSERT)
INSERT INTO vocabulary_categories (
    category_name, category_name_en, category_icon, category_color, 
    description, difficulty_level, estimated_hours, display_order, unlock_condition
) VALUES 

('Chào hỏi & Giao tiếp', 'greetings_communication', '👋', '#FF6B6B', 
 'Các từ vựng cơ bản để chào hỏi và giao tiếp hàng ngày', 1, 2.0, 1, 
 '{"required_categories": [], "min_completion": 0}'),

('Gia đình & Người thân', 'family_relatives', '👨‍👩‍👧‍👦', '#4ECDC4', 
 'Từ vựng về các thành viên trong gia đình và người thân', 1, 1.5, 2, 
 '{"required_categories": [], "min_completion": 0}'),

-- Dễ - luôn mở khóa
('Màu sắc & Hình dáng', 'colors_shapes', '🎨', '#F67280',
 'Từ vựng về màu sắc, hình khối và hình dạng cơ bản', 1, 1.5, 6,
 '{"required_categories": [], "min_completion": 0}'),

('Số đếm & Đơn vị', 'numbers_units', '🔢', '#FFB677',
 'Từ vựng về các số, đơn vị đo và cách đếm trong tiếng Nhật', 1, 2.0, 7,
 '{"required_categories": [], "min_completion": 0}'),

-- Trung bình - yêu cầu hoàn thành tối thiểu một số chủ đề cơ bản
('Cơ thể người', 'human_body', '🧠', '#6C5B7B',
 'Từ vựng về các bộ phận cơ thể và cảm giác', 2, 2.5, 8,
 '{"required_categories": [1,2], "min_completion": 70}'),

('Nhà ở & Đồ vật', 'house_objects', '🏠', '#45B7D1', 
 'Từ vựng về ngôi nhà, phòng ốc và đồ vật trong nhà', 2, 3.0, 3, 
 '{"required_categories": [2], "min_completion": 80}'),

('Thời gian & Ngày tháng', 'time_dates', '⏰', '#96CEB4', 
 'Từ vựng về thời gian, ngày tháng, giờ giấc', 2, 2.5, 4, 
 '{"required_categories": [1,2], "min_completion": 70}'),

('Đồ ăn & Thức uống', 'food_drinks', '🍱', '#FFEAA7', 
 'Từ vựng về đồ ăn, thức uống và các bữa ăn', 2, 3.5, 5, 
 '{"required_categories": [3], "min_completion": 70}'),

('Phương tiện & Giao thông', 'transportation', '🚗', '#355C7D',
 'Từ vựng về xe cộ, phương tiện giao thông, chỉ đường', 2, 3.0, 9,
 '{"required_categories": [6,7], "min_completion": 75}'),

('Trang phục & Phụ kiện', 'clothing_accessories', '👗', '#F8B195',
 'Từ vựng về quần áo, trang phục và phụ kiện', 2, 2.5, 10,
 '{"required_categories": [3,5], "min_completion": 75}'),

-- Khó - yêu cầu độ hoàn thành cao và đã qua nhiều chủ đề
('Cảm xúc & Tính cách', 'emotions_personality', '😄', '#FDCB6E',
 'Từ vựng mô tả cảm xúc, trạng thái tâm lý và tính cách', 3, 3.0, 11,
 '{"required_categories": [4,8], "min_completion": 80}'),

('Môi trường & Thiên nhiên', 'environment_nature', '🌳', '#55EFC4',
 'Từ vựng về thiên nhiên, môi trường và hiện tượng tự nhiên', 3, 3.5, 12,
 '{"required_categories": [9], "min_completion": 80}'),

('Công việc & Nghề nghiệp', 'jobs_occupations', '👔', '#74B9FF',
 'Từ vựng liên quan đến công việc, nghề nghiệp và nơi làm việc', 3, 3.0, 13,
 '{"required_categories": [10], "min_completion": 85}'),

('Sở thích & Hoạt động', 'hobbies_activities', '⚽', '#FAB1A0',
 'Từ vựng về sở thích, giải trí và hoạt động cá nhân', 3, 3.5, 14,
 '{"required_categories": [6,10], "min_completion": 85}'),

('Trường học & Học tập', 'school_education', '🏫', '#A29BFE',
 'Từ vựng về trường lớp, môn học, đồ dùng học tập', 3, 3.0, 15,
 '{"required_categories": [6,7], "min_completion": 85}'),

-- Rất khó - chỉ mở khi đã hoàn thành gần hết
('Du lịch & Khách sạn', 'travel_hotel', '✈️', '#81ECEC',
 'Từ vựng cần thiết khi đi du lịch, đặt phòng, hỏi đường', 3, 3.5, 16,
 '{"required_categories": [9,11,13], "min_completion": 90}'),

('Mua sắm & Thanh toán', 'shopping_payment', '🛒', '#E17055',
 'Từ vựng liên quan đến mua hàng, thanh toán, giao dịch', 3, 3.0, 17,
 '{"required_categories": [5,10,13], "min_completion": 90}'),

('Khẩn cấp & Y tế', 'emergency_medical', '🚑', '#D63031',
 'Từ vựng về y tế, cấp cứu và các tình huống khẩn cấp', 3, 3.0, 18,
 '{"required_categories": [8,12], "min_completion": 90}'),

('Thời tiết & Khí hậu', 'weather_climate', '🌦️', '#00CEC9',
 'Từ vựng mô tả thời tiết, khí hậu, các mùa trong năm', 3, 2.5, 19,
 '{"required_categories": [12,14], "min_completion": 90}'),

('Tin tức & Truyền thông', 'news_media', '📰', '#2D3436',
 'Từ vựng liên quan đến báo chí, truyền thông và tin tức', 3, 3.5, 20,
 '{"required_categories": [13,15], "min_completion": 95}');
