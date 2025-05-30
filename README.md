# 🌸 Tài liệu Website Học Tiếng Nhật - "学習の庭" (Vườn Học Tập)

## 📋 Thông tin chung

- **Tên website**: 学習の庭 (Gakushuu no Niwa) - Vườn học tiếng Nhật
- **URL**: http://158.101.156.191/japanese-learning/
- **Mô tả**: Website học tiếng Nhật interactive với quiz Hiragana, Katakana và số đếm
- **Công nghệ**: HTML/CSS/JavaScript + PHP + PostgreSQL
- **Target**: Người học tiếng Nhật từ cơ bản đến trung cấp

---

## 🏗️ Cấu trúc thư mục

```
japanese-learning/
├── index.html                    # Trang chủ
├── hiragana-rules.html          # Hướng dẫn quy tắc Hiragana
├── hiragana-test.html           # Quiz Hiragana
├── katakana-test.html           # Quiz Katakana (sắp có)
├── number-test.html             # Quiz số đếm (sắp có)
├── register.html                # Đăng ký tài khoản
├── create-user.php              # Admin panel tạo user
├── css/
│   ├── common.css              # CSS chung
│   ├── home.css                # CSS trang chủ
│   ├── hiragana-rules.css      # CSS trang quy tắc
│   └── test.css                # CSS trang quiz
├── js/
│   ├── common.js               # JavaScript utilities
│   ├── auth.js                 # Xử lý đăng nhập
│   ├── hiragana-rules.js       # Logic trang quy tắc
│   └── hiragana-test.js        # Logic quiz
├── php/
│   ├── config.php              # Cấu hình database
│   ├── auth.php                # API xác thực
│   ├── user-progress.php       # API tiến độ học tập
│   ├── register.php            # API đăng ký
│   ├── check-username.php      # API kiểm tra username
│   └── database.sql            # Script tạo database
└── assets/
    └── images/                 # Hình ảnh (nếu có)
```

---

## 🎨 Thiết kế giao diện

### **Phong cách tổng thể:**
- **Theme chính**: Sakura (hoa anh đào) - màu hồng, vàng pastel
- **Background**: Gradient tươi sáng (#a1c4fd → #c2e9fb)
- **Cards**: Bo góc 20px, shadow mềm mại, hover effects
- **Typography**: Segoe UI, Noto Sans JP cho tiếng Nhật
- **Animations**: Smooth transitions, fade/slide effects

### **Color Palette:**
- **Primary**: #667eea (xanh tím)
- **Secondary**: #ff9a8b (hồng cam)
- **Success**: #56ab2f (xanh lá)
- **Warning**: #f093fb (hồng tím)
- **Background**: Linear gradients với màu pastel

---

## 📱 Các trang chính

### **1. 🏠 Trang chủ (index.html)**

#### **Nội dung:**
- **Header Hero**: 
  - Tiêu đề "🌸 学習の庭 🌸 - Vườn học tiếng Nhật"
  - Mô tả: "Cùng nhau khám phá thế giới Hiragana, Katakana và con số"
  - Background: Animated gradient với hiệu ứng xoay

- **User Authentication Section**:
  - **Chưa đăng nhập**: Hiển thị nút "🔑 Đăng nhập"
  - **Đã đăng nhập**: "こんにちは、[Tên]さん！Hôm nay chúng ta học gì nhỉ? 📚"

- **Menu Navigation (Grid 2x4)**:
  1. **🌸 Quy tắc Hiragana** - Học cách ghép âm Yoon
  2. **✏️ Kiểm tra Hiragana** - Quiz thử thách kiến thức (có progress %)
  3. **🎌 Kiểm tra Katakana** - Coming soon
  4. **🔢 Kiểm tra Số đếm** - Coming soon
  5. **📚 Từ vựng N5** - Coming soon  
  6. **📖 Từ vựng N4** - Coming soon
  7. **📕 Từ vựng N3** - Coming soon
  8. **📈 Thống kê học tập** - Coming soon

- **Footer**: "🌸 Chúc bạn học tiếng Nhật vui vẻ! がんばって！🌸"

#### **Tính năng:**
- ✅ Responsive design
- ✅ Authentication required để truy cập các tính năng
- ✅ Progress badges hiển thị tiến độ học tập
- ✅ Hover effects cho menu cards
- ✅ Modal đăng nhập với Bootstrap

---

### **2. 🌸 Trang Quy tắc Hiragana (hiragana-rules.html)**

#### **Nội dung:**

**Section 1: Quy tắc ghép âm cơ bản**
- Giải thích âm ghép Yoon (拗音)
- Quy tắc: ký tự "i段" + ゃ/ゅ/ょ (nhỏ)
- Ví dụ trực quan: ち + ゃ = ちゃ

**Section 2: Ví dụ minh họa**  
- Animation process: ち + ゃ = ちゃ
- Cách nhớ: "ちゃ" = "trà" tiếng Việt

**Section 3: Bảng âm ghép đầy đủ**
- **Nhóm K-G (か行・が行)**: きゃ, きゅ, きょ, ぎゃ, ぎゅ, ぎょ
- **Nhóm S-Z (さ行・ざ行)**: しゃ, しゅ, しょ, じゃ, じゅ, じょ  
- **Nhóm T-D (た行・だ行)**: ちゃ, ちゅ, ちょ
- **Nhóm N (な行)**: にゃ, にゅ, にょ
- **Nhóm H-B-P (は行・ば行・ぱ行)**: ひゃ, ひゅ, ひょ, びゃ, びゅ, びょ, ぴゃ, ぴゅ, ぴょ
- **Nhóm M-R (ま行・ら行)**: みゃ, みゅ, みょ, りゃ, りゅ, りょ

**Section 4: Dấu phụ (Dakuten & Handakuten)**
- **Dakuten (゛)**: か → が (âm rung)
- **Handakuten (゜)**: は → ぱ (âm "p")

**Section 5: Ghép âm đặc biệt**
- **🎵 Âm dài (Chouon)**: かあ, きい, すう
- **⚡ Âm ngắt (Sokuon)**: かった, きって, がっこう  
- **👃 Âm mũi (Hatsuon)**: けん, かんと, にほん

**Section 6: Mẹo ghi nhớ**
- 🍵 Liên tưởng với tiếng Việt
- 🎨 Tạo hình ảnh sinh động
- 📚 Học theo nhóm
- ✍️ Luyện viết thường xuyên
- ⚡ Phân biệt các loại dấu
- 🔄 Luyện tập theo cặp

#### **Tính năng:**
- ✅ Fixed navigation dots (scroll giữa sections)
- ✅ Interactive yoon items (hover/click effects)
- ✅ Smooth scroll animations
- ✅ Character hover tooltips
- ✅ Nút "Làm bài kiểm tra" ở cuối trang

---

### **3. ✏️ Trang Quiz Hiragana (hiragana-test.html)**

#### **Nội dung:**

**Game Modes:**
1. **Học Hiragana** - Quiz tất cả ký tự Hiragana
2. **Học số đếm** - Quiz số đếm tiếng Nhật (1-99999)

**Quiz Features:**
- **Score System**: +10 điểm mỗi câu đúng
- **High Score**: Lưu điểm cao nhất
- **Progress Tracking**: Lưu tiến độ vào database
- **Question Pool**: 
  - Hiragana: 70+ ký tự (đơn, ghép, dakuten)
  - Numbers: Tùy chọn range 1-9, 10-99, 100-999, 1000-9999, 10000-99999

**Hiragana Dataset:**
```javascript
- Cơ bản: あ,い,う,え,お, か,き,く,け,こ, さ,し,す,せ,そ...
- Yoon: きゃ,きゅ,きょ, しゃ,しゅ,しょ, ちゃ,ちゅ,ちょ...  
- Dakuten: が,ぎ,ぐ,げ,ご, ざ,じ,ず,ぜ,ぞ...
- Handakuten: ぱ,ぴ,ぷ,ぺ,ぽ
```

#### **Tính năng:**
- ✅ **Authentication required** - redirect nếu chưa đăng nhập
- ✅ **Database integration** - lưu progress qua PHP API
- ✅ **Keyboard shortcuts** - phím 1-4 chọn đáp án, Ctrl+R restart
- ✅ **Visual feedback** - màu xanh (đúng), đỏ (sai) với animations
- ✅ **Progress indicator** - thanh tiến độ top của trang
- ✅ **Responsive design** - mobile friendly

---

### **4. 📝 Trang Đăng ký (register.html)**

#### **Nội dung:**
- **Form fields**:
  - Tên đăng nhập* (3+ ký tự, a-z, 0-9, _)
  - Email* (validation)
  - Tên hiển thị (optional)
  - Mật khẩu* (6+ ký tự)
  - Xác nhận mật khẩu*
  - Checkbox đồng ý điều khoản

#### **Tính năng:**
- ✅ **Real-time validation** - check username trùng lặp
- ✅ **Password strength indicator** - weak/medium/strong
- ✅ **Auto-fill display name** từ username
- ✅ **API integration** - gọi PHP để tạo user
- ✅ **Success redirect** - về trang chủ sau 3s

---

### **5. 🔧 Admin Panel (create-user.php)**

#### **Nội dung:**
- **Form tạo user mới** (dành cho admin)
- **Danh sách tất cả users** với thông tin:
  - ID, Username, Email, Display Name
  - Trạng thái (Active/Inactive)
  - Ngày tạo, Đăng nhập cuối
- **Quick Actions**:
  - Tạo 5 user mẫu
  - Reset tất cả password về "password"
  - Link về trang chủ

#### **Tính năng:**
- ✅ **CRUD operations** cho user management
- ✅ **Validation** đầy đủ 
- ✅ **Batch operations** - tạo nhiều user cùng lúc
- ✅ **Beautiful admin interface** với Bootstrap

---

## 🗄️ Cơ sở dữ liệu PostgreSQL

### **Bảng `users`**
```sql
id SERIAL PRIMARY KEY
username VARCHAR(50) UNIQUE NOT NULL
email VARCHAR(100) UNIQUE  
password_hash VARCHAR(255) NOT NULL
display_name VARCHAR(100)
is_active BOOLEAN DEFAULT TRUE
created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
last_login TIMESTAMP WITH TIME ZONE
```

### **Bảng `user_progress`**
```sql
id SERIAL PRIMARY KEY
user_id INTEGER REFERENCES users(id)
hiragana_score INTEGER DEFAULT 0
hiragana_total INTEGER DEFAULT 0
katakana_score INTEGER DEFAULT 0
katakana_total INTEGER DEFAULT 0
numbers_score INTEGER DEFAULT 0
numbers_total INTEGER DEFAULT 0
vocabulary_n5_score INTEGER DEFAULT 0
vocabulary_n5_total INTEGER DEFAULT 0
vocabulary_n4_score INTEGER DEFAULT 0
vocabulary_n4_total INTEGER DEFAULT 0
vocabulary_n3_score INTEGER DEFAULT 0
vocabulary_n3_total INTEGER DEFAULT 0
created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
last_updated TIMESTAMP WITH TIME ZONE DEFAULT NOW()
```

### **Bảng `user_activities`**
```sql
id SERIAL PRIMARY KEY
user_id INTEGER REFERENCES users(id)
activity_type VARCHAR(50) NOT NULL -- 'hiragana', 'katakana', 'numbers'
score INTEGER NOT NULL DEFAULT 0
total_questions INTEGER NOT NULL DEFAULT 0
time_spent INTEGER -- thời gian (giây)
created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
```

### **Bảng `quiz_sessions`**
```sql
id SERIAL PRIMARY KEY
user_id INTEGER REFERENCES users(id)
quiz_type VARCHAR(50) NOT NULL
total_questions INTEGER NOT NULL
correct_answers INTEGER NOT NULL DEFAULT 0
time_spent INTEGER
started_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
completed_at TIMESTAMP WITH TIME ZONE
is_completed BOOLEAN DEFAULT FALSE
```

### **Bảng `user_achievements`**
```sql
id SERIAL PRIMARY KEY
user_id INTEGER REFERENCES users(id)
achievement_type VARCHAR(50) NOT NULL -- 'first_quiz', 'perfect_score', etc.
achievement_data JSONB
earned_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
```

---

## 👥 Tài khoản người dùng

### **🧪 Tài khoản Test có sẵn**

| Username | Password | Display Name | Email | Vai trò |
|----------|----------|--------------|-------|---------|
| `demo` | `password` | Demo User | demo@example.com | User thường |
| `sakura` | `password` | Sakura Chan | sakura@example.com | User thường |
| `admin` | `password` | Administrator | admin@example.com | Admin |

### **👤 Quyền hạn người dùng**

**User thường:**
- ✅ Đăng nhập/đăng xuất
- ✅ Làm quiz Hiragana/Katakana/Numbers
- ✅ Xem tiến độ học tập cá nhân
- ✅ Lưu high score và progress
- ❌ Không thể tạo/xóa user khác
- ❌ Không thể truy cập admin panel

**Admin:**
- ✅ Tất cả quyền của user thường
- ✅ Truy cập Admin Panel (create-user.php)
- ✅ Tạo/sửa/xóa tài khoản user
- ✅ Xem danh sách tất cả users
- ✅ Reset password cho users
- ✅ Tạo user mẫu batch

### **🔐 Hệ thống Authentication**

**Đăng nhập:**
- Session-based authentication
- Password hashing với PHP `password_hash()`
- Auto-redirect nếu chưa đăng nhập
- Remember login state across pages

**Đăng ký:**
- Username validation (3+ chars, alphanumeric + underscore)
- Email validation
- Password strength checking
- Real-time username availability check
- Auto-create progress record cho user mới

**Security:**
- SQL injection protection với PDO prepared statements
- XSS protection với input sanitization
- Session timeout (2 hours)
- CSRF protection (trong tương lai)

---

## 🚀 API Endpoints

### **Authentication API (php/auth.php)**

**POST /auth.php?action=login**
```json
{
  "username": "demo",
  "password": "password"
}
```

**GET /auth.php?action=check**
- Kiểm tra session hiện tại

**GET /auth.php?action=logout**
- Đăng xuất user

### **User Progress API (php/user-progress.php)**

**GET /user-progress.php?action=get**
- Lấy tiến độ học tập của user

**POST /user-progress.php?action=save**
```json
{
  "type": "hiragana",
  "score": 45,
  "total": 50
}
```

**GET /user-progress.php?action=stats**
- Lấy thống kê tổng quan + learning streak

### **Registration API (php/register.php)**

**POST /register.php**
```json
{
  "action": "register",
  "username": "newuser",
  "email": "user@example.com", 
  "password": "securepass",
  "display_name": "New User"
}
```

### **Username Check API (php/check-username.php)**

**GET /check-username.php?username=testuser**
```json
{
  "exists": false,
  "valid": true
}
```

---

## 📊 Analytics & Metrics

### **User Engagement Metrics**
- Total registered users
- Daily/Monthly active users  
- Quiz completion rates
- Average session duration
- Learning streak tracking

### **Learning Progress Metrics**
- Hiragana mastery percentage by user
- Most difficult characters (high wrong rate)
- User progression over time
- Achievement unlock rates

### **Future Features**
- 📈 **Dashboard analytics** cho admin
- 🏆 **Achievement system** với badges
- 📅 **Learning streaks** và daily goals
- 👥 **Leaderboard** và social features
- 🔊 **Audio pronunciation** cho characters
- 📱 **PWA support** cho mobile app experience

---

## 🛠️ Technical Stack

### **Frontend**
- **HTML5** - Semantic markup
- **CSS3** - Grid, Flexbox, Animations, Gradients
- **JavaScript ES6+** - Async/await, Modules, Classes
- **Bootstrap 5.3** - Responsive components
- **Font**: Segoe UI + Noto Sans JP (Japanese support)

### **Backend**  
- **PHP 7.4+** - Server-side logic
- **PostgreSQL 12+** - Relational database
- **Apache 2.4** - Web server
- **Ubuntu 20.04** - Operating system (Oracle Cloud)

### **Architecture**
- **MVC Pattern** - Separation of concerns
- **RESTful APIs** - JSON responses
- **Session-based Auth** - Secure user management
- **Responsive Design** - Mobile-first approach

---

## 🚀 Deployment

### **Server Requirements**
- Ubuntu 20.04+ trên Oracle Cloud
- Apache 2.4 + PHP 7.4 + PostgreSQL 12
- SSL certificate (Let's Encrypt)
- 1GB RAM, 20GB storage minimum

### **Installation Steps**
1. Setup LAMP stack
2. Create PostgreSQL database 
3. Import database schema
4. Upload website files
5. Configure Apache virtual host
6. Set file permissions
7. Test all functionalities

### **Monitoring**
- Apache access/error logs
- Database query performance
- User activity tracking
- Automated backups (daily)

---

## 📝 Changelog & Roadmap

### **✅ Completed (v1.0)**
- [x] User authentication system
- [x] Hiragana rules tutorial page
- [x] Interactive Hiragana quiz
- [x] User progress tracking
- [x] Admin panel for user management
- [x] Responsive design
- [x] PostgreSQL integration

### **🚧 In Progress (v1.1)**
- [ ] Katakana quiz implementation
- [ ] Number counting quiz  
- [ ] User dashboard với progress charts
- [ ] Achievement system

### **🔮 Future (v2.0+)**
- [ ] Vocabulary N5/N4/N3 modules
- [ ] Audio pronunciation
- [ ] Spaced repetition system
- [ ] Social features & leaderboard
- [ ] Mobile app (PWA)
- [ ] AI-powered adaptive learning

---

## 📞 Support & Contact

- **Website**: http:/xxx/japanese-learning/
- **Admin Panel**: http://xxx/japanese-learning/create-user.php
- **Database**: PostgreSQL trên localhost:5432
- **Server**: Oracle Cloud Ubuntu instance

### **Default Credentials**
- **Test User**: demo / password
- **Database**: Xem trong php/config.php

---

*Tài liệu này cập nhật lần cuối: 2024-12-19*  
*🌸 がんばって！(Ganbatte - Chúc may mắn!) 🌸*
