# Tổng quan project JobFind và cách chạy

## 1. Giới thiệu

JobFind là website tuyển dụng viết bằng PHP và MySQL. Hệ thống hỗ trợ 3 nhóm người dùng chính:

- Ứng viên: đăng ký, đăng nhập, cập nhật hồ sơ, lưu việc làm, nộp đơn ứng tuyển, rút đơn và xem gợi ý việc làm.
- Nhà tuyển dụng: quản lý hồ sơ công ty, đăng tin tuyển dụng, xem danh sách ứng viên và cập nhật trạng thái hồ sơ.
- Admin: quản trị hệ thống, phân quyền, xem thống kê và quản lý dữ liệu nền.

Project hiện chạy tốt bằng Docker với Apache, PHP 8.2, MySQL 8.0 và phpMyAdmin.

## 2. Công nghệ sử dụng

- Backend: PHP thuần.
- Database: MySQL.
- Web server: Apache.
- Giao diện: HTML, CSS, JavaScript, Bootstrap/vendor assets.
- Môi trường chạy: Docker Compose.
- PHP extensions: mysqli, gd, zip.

## 3. Cấu trúc thư mục chính

- `public/`: entry point chính của website dành cho người dùng, gồm trang đăng nhập, dashboard, job, candidate, employer...
- `admin/`: khu vực quản trị admin, được mount qua đường dẫn `/admin` khi chạy Docker.
- `app/controllers/`: xử lý luồng nghiệp vụ như đăng nhập, ứng viên, việc làm.
- `app/models/`: các model làm việc với database như `User`, `Candidate`, `Employer`, `Job`, `Application`, `SavedJob`, `Notification`.
- `app/services/`: chứa service xử lý nghiệp vụ phức tạp, nổi bật là `JobRecommendationService`.
- `app/helpers/`: helper upload avatar, logo công ty, CV và các tiện ích khác.
- `config/`: cấu hình kết nối database, session, base URL, upload path.
- `db/`: schema database, seed script và migration.
- `docker/`: Dockerfile và cấu hình Apache.
- `docker-compose.yml`: định nghĩa các service `app`, `db`, `phpmyadmin`.

## 4. Kiến trúc tổng quan

Project không dùng router trung tâm. Mỗi trang PHP trong `public/` hoặc `admin/` tự include config, controller/model cần thiết rồi render giao diện.

Luồng cơ bản:

```text
Browser
  -> public/*.php hoặc admin/*.php
  -> config/config.php khởi tạo session + database
  -> controller/model xử lý nghiệp vụ
  -> MySQL lưu hoặc đọc dữ liệu
  -> render HTML trả về trình duyệt
```

Các model kế thừa lớp `Database` để dùng kết nối `mysqli`. Thông tin cấu hình database được lấy từ biến môi trường Docker:

- `DB_HOST=db`
- `DB_USER=jobfind`
- `DB_PASS=jobfind_pass`
- `DB_NAME=jobfinder`

## 5. Chức năng chính

### Xác thực và phân quyền

- Người dùng đăng ký và đăng nhập qua form.
- Mật khẩu được hash bằng `password_hash`.
- Sau khi đăng nhập, hệ thống lưu thông tin vào `$_SESSION`.
- Role chính:
  - `1`: Admin
  - `2`: Employer
  - `3`: Candidate
- Admin có kiểm tra permission riêng thông qua middleware.

### Ứng viên

- Cập nhật hồ sơ cá nhân.
- Upload CV.
- Lưu việc làm yêu thích.
- Nộp đơn ứng tuyển.
- Theo dõi trạng thái hồ sơ.
- Rút đơn ứng tuyển.
- Nhận gợi ý việc làm thông minh trên dashboard.

### Nhà tuyển dụng

- Cập nhật hồ sơ công ty.
- Tạo và quản lý tin tuyển dụng.
- Chuyển trạng thái job: draft, published, closed.
- Xem danh sách ứng viên đã ứng tuyển.
- Cập nhật trạng thái hồ sơ: applied, viewed, shortlisted, rejected, hired, withdrawn.
- Gửi thông báo cho ứng viên khi trạng thái thay đổi.

### Admin

- Truy cập khu vực `/admin`.
- Quản lý người dùng, role, permission.
- Xem dashboard thống kê.
- Quản lý dữ liệu hệ thống.

### Gợi ý việc làm

Dashboard ứng viên dùng `JobRecommendationService` để phân tích:

- Kỹ năng trong hồ sơ ứng viên.
- Địa điểm ứng viên.
- Lịch sử ứng tuyển.
- Việc làm đã lưu.
- Ngành nghề liên quan.

Điểm gợi ý được tính dựa trên độ khớp địa điểm, kỹ năng và ngành nghề. Nếu hồ sơ chưa đủ dữ liệu, hệ thống fallback sang danh sách việc làm nổi bật hoặc mới cập nhật.

## 6. Database chính

Các bảng quan trọng:

- `users`: tài khoản đăng nhập và role.
- `roles`: danh sách vai trò.
- `permissions`: quyền trong hệ thống.
- `role_permissions`: liên kết role và permission.
- `candidates`: hồ sơ ứng viên.
- `employers`: hồ sơ nhà tuyển dụng/công ty.
- `jobs`: tin tuyển dụng.
- `job_category_map`: liên kết job với ngành nghề.
- `applications`: hồ sơ ứng tuyển.
- `saved_jobs`: việc làm ứng viên đã lưu.
- `notifications`: thông báo nội bộ.
- `job_views`: lượt xem việc làm.

## 7. Cách chạy project bằng Docker

### Yêu cầu

Cần cài:

- Docker Desktop
- Docker Compose

### Bước 1: Mở terminal tại thư mục project

```powershell
cd C:\Users\Admin\FindJob
```

### Bước 2: Build và chạy container

```powershell
docker compose up -d --build
```

Lệnh này sẽ tạo và chạy 3 service:

- `jobfind_app`: PHP 8.2 + Apache, port `8080`.
- `jobfind_db`: MySQL 8.0, port `3307`.
- `jobfind_pma`: phpMyAdmin, port `8081`.

### Bước 3: Truy cập website

Mở trình duyệt:

```text
http://localhost:8080
```

Trang đăng nhập:

```text
http://localhost:8080/account/login.php
```

Trang đăng ký:

```text
http://localhost:8080/account/register.php
```

Trang admin:

```text
http://localhost:8080/admin
```

phpMyAdmin:

```text
http://localhost:8081
```

Thông tin đăng nhập phpMyAdmin:

- Server: `db`
- User: `root`
- Password: `root_pass`

Hoặc dùng user ứng dụng:

- User: `jobfind`
- Password: `jobfind_pass`
- Database: `jobfinder`

## 8. Lệnh quản lý Docker thường dùng

Xem container đang chạy:

```powershell
docker compose ps
```

Xem log:

```powershell
docker compose logs -f
```

Dừng project:

```powershell
docker compose down
```

Dừng và xóa luôn dữ liệu database volume:

```powershell
docker compose down -v
```

Chạy lại seed dữ liệu nếu cần:

```powershell
docker compose exec app php db/seed.php
```

## 9. Tài khoản test

Theo tài liệu hiện có, có thể dùng các tài khoản test sau nếu database đã được seed:

- Ứng viên:
  - Email: `user@test.com`
  - Password: `123456`
- Nhà tuyển dụng:
  - Email: `employer@test.com`
  - Password: `123456`
- Admin:
  - Email: `admin@test.com`
  - Password: `123456`

Nếu đăng nhập không được, hãy chạy:

```powershell
docker compose exec app php db/seed.php
```

## 10. Quy trình demo nhanh

1. Mở `http://localhost:8080/account/login.php`.
2. Đăng nhập tài khoản ứng viên.
3. Cập nhật hồ sơ, kỹ năng, địa điểm và CV.
4. Xem danh sách việc làm, lưu việc làm hoặc nộp đơn.
5. Mở dashboard để xem gợi ý việc làm.
6. Đăng nhập tài khoản nhà tuyển dụng.
7. Tạo tin tuyển dụng và xem danh sách ứng viên ứng tuyển.
8. Đổi trạng thái hồ sơ ứng viên để demo thông báo.
9. Đăng nhập admin tại `http://localhost:8080/admin` để demo quản trị và phân quyền.

## 11. Lưu ý khi chạy

- Nếu port `8080`, `8081` hoặc `3307` đang bị chiếm, cần đổi port trong `docker-compose.yml`.
- Database chỉ tự import `db/schema.sql` khi volume MySQL được tạo lần đầu. Nếu đã chạy trước đó và muốn reset dữ liệu, dùng `docker compose down -v` rồi chạy lại.
- File upload được lưu trong `public/uploads`.
- Khi chạy bằng Docker, `DocumentRoot` là thư mục `public/`, còn thư mục `admin/` được cấu hình alias thành `/admin`.
