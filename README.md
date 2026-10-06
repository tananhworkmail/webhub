# Web Hub cho InfinityFree

Trang PHP thuần để quản lý các website/app cá nhân bằng URL. Không cần đăng nhập, Node.js hay MySQL.

## Các trang

- `index.php`: trang mở mặc định, hiển thị tất cả website. Hệ thống tự kiểm tra trạng thái; website online có thể bấm để mở, website offline bị vô hiệu hóa.
- `dashboard.php`: tổng số website, số online, số offline/lỗi, phản hồi trung bình, phân bố trạng thái, DNS/SSL, danh sách lỗi và các website phản hồi chậm.
- `manager.php`: thêm, sửa, xóa website; tạo nhóm, đổi tên nhóm và xóa nhóm.

Mỗi website có thể thuộc một nhóm. Khi xóa nhóm, website trong nhóm được chuyển về **Chưa phân nhóm** và không bị xóa.

Ảnh đại diện mặc định được tạo từ URL qua dịch vụ WordPress.com mShots. Bạn cũng có thể upload ảnh JPG, PNG, WEBP hoặc GIF tối đa 5 MB.

## Deploy lên InfinityFree

1. Giải nén WebHub-InfinityFree.zip.
2. Upload toàn bộ nội dung bên trong vào thư mục htdocs/.
3. Giữ nguyên các file .htaccess trong thư mục gốc, data/ và uploads/.
4. Mở domain. Trang **Tất cả web** sẽ xuất hiện đầu tiên.

Nếu không lưu được dữ liệu, kiểm tra quyền ghi của data/ và uploads/. Trước khi cập nhật source, sao lưu:

- data/sites.json
- data/groups.json
- data/status.json
- thư mục uploads/

## Yêu cầu

- PHP 8.1 trở lên.
- Extension fileinfo và session. Nên bật cURL để kiểm tra nhiều website nhanh hơn.

Việc kiểm tra trạng thái cần hosting cho phép PHP kết nối ra ngoài. Trang không có đăng nhập nên người truy cập URL đều có thể thay đổi dữ liệu.
