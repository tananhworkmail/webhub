# Web Hub — trang tổng hợp website cho InfinityFree

Trang PHP thuần để lưu, phân loại và mở nhanh các website/app cá nhân. Giao diện dựa trên ý tưởng của WebManager trong ZIP: danh mục bên trái, bảng tổng quan, thẻ website, tìm kiếm và chế độ sáng/tối. Trang chạy trực tiếp trên InfinityFree, không cần Go, Node.js hay MySQL.

## Chức năng

- Thêm, sửa, xóa và ghim website bằng URL; không cần đăng nhập.
- Tìm kiếm, lọc theo danh mục và mở nhanh website.
- Mặc định tự lấy thumbnail từ URL qua dịch vụ image.thum.io; có thể upload ảnh JPG/PNG/WEBP/GIF riêng (tối đa 5 MB). Nếu thumbnail không tải được, thẻ dùng màu và chữ cái đại diện.
- Dữ liệu lưu tại data/sites.json; ảnh upload nằm trong uploads/.
- Giao diện responsive cho máy tính và điện thoại.

## Đưa lên InfinityFree

1. Giải nén WebHub-InfinityFree.zip.
2. Upload **nội dung bên trong** vào thư mục htdocs/ qua File Manager hoặc FTP. Giữ nguyên các file .htaccess trong thư mục gốc, data/ và uploads/.
3. Mở domain của bạn bằng HTTPS, chọn **Thêm website** và nhập URL của các trang đã deploy.

Nếu hosting báo không thể ghi dữ liệu, kiểm tra quyền ghi của data/ và uploads/. Hãy sao lưu data/sites.json và uploads/ trước khi cập nhật để giữ website và ảnh đã lưu.

## Cấu trúc

    index.php                Trang chủ
    api.php                  API danh sách, quản lý website và upload ảnh
    config.php               Hàm dữ liệu và kiểm tra đầu vào
    assets/css/style.css     Giao diện
    assets/js/app.js         Tương tác giao diện
    data/sites.json          Danh sách website
    uploads/                 Ảnh đại diện đã upload

Yêu cầu PHP 8.1+ với extension fileinfo và session. File data/.htaccess chặn tải trực tiếp dữ liệu. ZIP WebManager ban đầu không chứa cơ sở dữ liệu các website cá nhân, nên bộ cài bắt đầu với danh sách trống.

Trang không yêu cầu đăng nhập, vì vậy bất kỳ ai truy cập URL cũng có thể thêm, sửa hoặc xóa website.
