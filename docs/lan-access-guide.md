# 🌐 Hướng dẫn kết nối API Backend từ máy FE (LAN)

> Hướng dẫn này dành cho FE dev cần truy cập API backend đang chạy trên máy BE trong cùng mạng LAN.

## Thông tin kết nối

| Factory    | URL                                          |
|------------|----------------------------------------------|
| FlashShip  | `https://api-dashboard-fls.local:2443`       |
| PrintDash  | `https://api-dashboard-pd.local:2443`        |

**IP máy BE**: Hỏi BE dev (ví dụ: `192.168.1.156`)

---

## Bước 1: Thêm domain vào file hosts

Thay `<IP_MÁY_BE>` bằng IP thực tế của máy BE.

### macOS / Linux

```bash
sudo sh -c 'echo "<IP_MÁY_BE>  api-dashboard-fls.local api-dashboard-pd.local" >> /etc/hosts'
```

Kiểm tra lại:
```bash
cat /etc/hosts | grep api-dashboard
```

### Windows

1. Mở **Notepad** với quyền **Administrator**
2. Mở file: `C:\Windows\System32\drivers\etc\hosts`
3. Thêm dòng cuối:
   ```
   <IP_MÁY_BE>  api-dashboard-fls.local api-dashboard-pd.local
   ```
4. Lưu file

---

## Bước 2: Xử lý SSL Certificate

Backend dùng **self-signed certificate** (Caddy internal TLS). Trình duyệt và Node.js sẽ báo lỗi SSL nếu không trust cert.

### Cách A: Bỏ qua SSL verify (nhanh, dùng khi dev)

**Next.js / Node.js** — thêm biến môi trường khi start:
```bash
NODE_TLS_REJECT_UNAUTHORIZED=0 npm run dev
```

Hoặc thêm vào file `.env.local`:
```env
NODE_TLS_REJECT_UNAUTHORIZED=0
```

**Axios** — config riêng cho dev:
```js
// Chỉ dùng khi dev, KHÔNG dùng production
const https = require('https');
const agent = new https.Agent({ rejectUnauthorized: false });
axios.get(url, { httpsAgent: agent });
```

**Trình duyệt** — khi mở URL lần đầu sẽ cảnh báo, chọn:
- Chrome: `Advanced → Proceed to api-dashboard-xxx.local (unsafe)`
- Firefox: `Advanced → Accept the Risk and Continue`

### Cách B: Trust certificate (đúng chuẩn, hết cảnh báo)

1. Xin file `caddy-root-ca.crt` từ BE dev
2. Import vào system:

**macOS**:
```bash
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain caddy-root-ca.crt
```

**Windows**:
- Double-click file `.crt` → **Install Certificate**
- Chọn **Local Machine** → **Trusted Root Certification Authorities** → Finish

**Linux**:
```bash
sudo cp caddy-root-ca.crt /usr/local/share/ca-certificates/
sudo update-ca-certificates
```

---

## Bước 3: Cấu hình API URL trong project FE

Cập nhật file `.env.local` (hoặc `.env.development`):

```env
# FlashShip
NEXT_PUBLIC_API_URL=https://api-dashboard-fls.local:2443

# Hoặc PrintDash
NEXT_PUBLIC_API_URL=https://api-dashboard-pd.local:2443
```

---

## Kiểm tra kết nối

```bash
# Test từ terminal (bỏ qua SSL check)
curl -k https://api-dashboard-fls.local:2443/api/health

# Test DNS resolve
ping api-dashboard-fls.local
```

Kết quả mong đợi:
- `ping` → trả về IP máy BE
- `curl` → trả về response từ API

---

## Troubleshooting

| Vấn đề | Nguyên nhân | Fix |
|---------|-------------|-----|
| `Could not resolve host` | Chưa thêm hosts hoặc sai IP | Kiểm tra lại file `/etc/hosts` |
| `Connection refused` | BE chưa start hoặc firewall chặn | Hỏi BE dev start server, tắt firewall port 2443 |
| `SSL certificate problem` | Chưa trust cert | Dùng **Cách A** hoặc **Cách B** ở Bước 2 |
| `CORS error` | Backend chưa allow origin | Hỏi BE dev thêm origin FE vào CORS config |
| IP máy BE thay đổi | DHCP cấp IP mới | Cập nhật lại IP trong `/etc/hosts` |

---

> **Lưu ý**: File hosts chỉ map domain → IP cố định. Nếu IP máy BE thay đổi (do DHCP), bạn cần cập nhật lại. Có thể hỏi BE dev set static IP để tránh vấn đề này.
