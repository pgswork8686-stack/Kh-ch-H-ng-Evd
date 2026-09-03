# EZEV SITEMAP ARCHITECTURE V1 (FROZEN)

> **Document Status:** FROZEN / ARCHITECTURAL BASELINE  
> **Baseline Commit:** 3d20ec28c75249669a08bced491e3943dd542837  
> **Target Branch:** develop/ezev-v1  
> **Implementation Phase:** Phase 4.0

---

## 1. Executive Summary & Architectural Scope

Tai lieu nay la **Single Source of Truth (SSOT)** dinh nghia toan bo cau truc phan cap trang (Information Architecture - IA), route hierarchy, dinh dang template, quyen truy cap va vai tro cua tung trang tren nen tang **EZEV Global**.

Frontend va Theme developers bat buoc phai tuan theo route hierarchy duoc dinh nghia tai day. Khong tu y suy doan, rut ngan hoac thay doi URL structure trong qua trinh trien khai.

---

## 2. Master Sitemap Hierarchy

`	ext
EZEV GLOBAL ECOSYSTEM
│
├── [PUBLIC EXPERIENCES]
│   ├── 01. Home / Trang chu [P0]
│   │   └── /
│   │
│   ├── 02. Find a Charger / Tim tram sac [P0]
│   │   └── /find-a-charger
│   │
│   ├── 03. Station Detail / Chi tiet tram sac [P0]
│   │   └── /stations/[slug]
│   │
│   ├── 04. Charging Network / Mang luoi tram sac [P0]
│   │   └── /charging-network
│   │
│   ├── 05. How to Charge / Huong dan sac [P1]
│   │   └── /how-to-charge
│   │
│   ├── 06. Charging Rates / Bieu gia dich vu sac [P1]
│   │   └── /charging-rates
│   │
│   ├── 07. For Drivers / Danh cho tai xe [P1]
│   │   └── /drivers
│   │
│   ├── 08. For Business / Giai phap doanh nghiep [P1]
│   │   └── /business
│   │
│   ├── 09. Partners / Doi tac tram sac [P1]
│   │   └── /partners
│   │
│   ├── 10. Become a Partner / Dang ky hop tac [P1]
│   │   └── /partners/register
│   │
│   ├── 11. Solutions / Giai phap cong nghe [P1]
│   │   ├── /solutions
│   │   └── /solutions/[slug]
│   │
│   ├── 12. Projects / Du an tieu bieu [P1]
│   │   ├── /projects
│   │   └── /projects/[slug]
│   │
│   ├── 13. News & Insights / Tin tuc & Bai viet chuyen gia [P1]
│   │   ├── /news
│   │   └── /news/[slug]
│   │
│   ├── 14. About EVD / Ve tap doan EVD & EZEV [P1]
│   │   └── /about
│   │
│   ├── 15. Support / Trung tam ho tro [P1]
│   │   └── /support
│   │
│   ├── 16. Contact / Lien he [P1]
│   │   └── /contact
│   │
│   └── 17. Policies / Chinh sach & Dieu khoan [P1]
│       ├── /policies/privacy
│       ├── /policies/terms
│       ├── /policies/charging
│       └── /policies/[slug]
│
├── [AUTHENTICATION ENGINE] (Single Shared Auth Engine) [P2]
│   ├── Login / Dang nhap
│   │   ├── /login (Default / Customer Entry)
│   │   ├── /login?type=customer (Driver / Customer Entry)
│   │   ├── /login?type=partner (Partner / Investor Entry)
│   │   └── /login?type=internal (Internal Staff Entry)
│   ├── Forgot Password / Quen mat khau
│   │   └── /forgot-password
│   ├── Reset Password / Dat lai mat khau
│   │   └── /reset-password
│   ├── Invitation Claim / Chap nhan thu moi tham gia to chuc
│   │   └── /invite/[token]
│   └── Logout / Dang xuat
│       └── /logout
│
├── [PRIVATE PORTAL: CUSTOMER] [P2]
│   ├── /account (Overview dashboard)
│   ├── /account/saved-stations (Tram da luu)
│   ├── /account/recent-stations (Tram da xem gan day)
│   ├── /account/notifications (Thong bao he thong)
│   ├── /account/support (Yeu cau ho tro)
│   ├── /account/security (Mat khau & bao mat)
│   └── /account/settings (Cai dat tai khoan)
│
├── [PRIVATE PORTAL: BUSINESS] [P2]
│   ├── /portal/business (Tong quan tai chinh & hoat dong to chuc)
│   ├── /portal/business/sites (Danh sach dia diem tram)
│   ├── /portal/business/sites/[site_id] (Chi tiet & quan ly dia diem)
│   ├── /portal/business/stations (Tram thuoc quyen quan ly)
│   ├── /portal/business/stations/[station_id] (Chi tiet & trang thai ky thuat tram)
│   ├── /portal/business/energy (Bao cao dien nang tieu thu)
│   ├── /portal/business/reports (Bao cao van hanh & doanh thu)
│   ├── /portal/business/team (Thanh vien to chuc & phan quyen)
│   ├── /portal/business/invitations (Quan ly thu moi thanh vien)
│   ├── /portal/business/support (Ho tro ky thuat B2B)
│   └── /portal/business/settings (Cai dat ho so doanh nghiep)
│
├── [PRIVATE PORTAL: PARTNER / INVESTOR] [P2]
│   ├── /portal/partner (Dashboard doi tac / nha dau tu)
│   ├── /portal/partner/stations (Danh sach tram dau tu/hop tac)
│   ├── /portal/partner/performance (Chi so hieu suat & suat sinh loi)
│   ├── /portal/partner/energy (Tong san luong sac)
│   ├── /portal/partner/reports (Bao cao dinh ky)
│   ├── /portal/partner/documents (Hop dong & tai lieu phap ly)
│   ├── /portal/partner/team (Quan ly quyen xem)
│   ├── /portal/partner/support (Kenh ho tro VIP)
│   └── /portal/partner/settings (Cau hinh nhan bao cao)
│
├── [PRIVATE PORTAL: EZEV INTERNAL / OPERATIONS] [P2]
│   ├── /admin (Dashboard giam sat toan mang luoi EZEV)
│   ├── /admin/network (Ban do tram sac thoi gian thuc toan quoc)
│   ├── /admin/organizations (Quan ly tat ca to chuc khach hang)
│   ├── /admin/organizations/[organization_id] (Chi tiet to chuc)
│   ├── /admin/sites (Quan ly toan bo dia diem Site)
│   ├── /admin/sites/[site_id] (Chi tiet dia diem Site)
│   ├── /admin/stations (Quan ly tat ca tru sac Station)
│   ├── /admin/stations/[station_id] (Chi tiet ky thuat tram)
│   ├── /admin/chargers (Danh muc thiet bi sac Charger)
│   ├── /admin/connectors (Danh muc dau cam sac Connector)
│   ├── /admin/sessions (Phien sac tren toan he thong)
│   ├── /admin/energy (Giam sat tieu thu & phu tai luoi)
│   ├── /admin/alerts (Canh bao su co & loi van hanh)
│   ├── /admin/maintenance (Phieu sua chua & bao tri ky thuat)
│   ├── /admin/reports (Bao cao cap do toan mang luoi)
│   ├── /admin/users (Phan quyen nguoi dung noi bo)
│   ├── /admin/integrations (Quan ly webhook & nha cung cap du lieu)
│   └── /admin/settings (Cau hinh tham so he thong EZEV)
│
└── [TECHNICAL WORDPRESS BACKEND] (Internal Engineering & Technical Only)
    └── /wp-admin/* (WordPress CMS, Plugins, DB Migrations, Core Settings)
`

---

## 3. P0 - The 4 Core Public Pages

Bon trang sau day duoc chi dinh la **P0: CORE PUBLIC EXPERIENCE**. Toan bo kien truc du lieu, ket noi REST API va trai nghiem ban do so phai duoc hoan thien va nghiem thu o 4 trang nay truoc khi chuyen sang bat ky trang nao khac:

1. **01. Home (/):** Khoi dau hanh trinh nguoi dung. Tich hop du lieu thong ke mang luoi thoi gian thuc (KPIs), mini Google Map, danh sach tram noi bat hoac tram gan nhat theo Geolocation.
2. **02. Find a Charger (/find-a-charger):** Ban do tim kiem tram sac toan dien voi Google Maps JS API + Google Places Autocomplete. Ho tro loc theo chuan cong, cong suat, tinh trang hoat dong (live availability). Ty le hien thi Desktop 35% danh sach - 65% ban do; Mobile ban do toan man hinh kem bottom sheet.
3. **03. Station Detail (/stations/[slug]):** Chi tiet chuyen sau ve tram sac: cong suat, tinh trang tung dau sac (Connector), bieu gia, tien ich xung quanh, chi duong, va metadata nguon du lieu (data_mode, last_updated, reshness_seconds). Internal identity dinh danh bang station_id (khong dung WordPress post ID).
4. **04. Charging Network (/charging-network):** Trang gioi thieu quy mo ha tang mang luoi tram sac EZEV tai Viet Nam, Philippines, Trung Quoc va quoc te. Trinh bay ban do do phu vi mo va chi so nang luc ky thuat cua EZEV.

---

## 4. Public Content Architecture (05 to 17)

De dam bao cau truc URL sach, toi uu hoa cong cu tim kiem (SEO) va ro rang theo tung phan he domain:
- **Khong co trang generic mang ten "Content Detail":**
  - Chi tiet giai phap nam duoi /solutions/[slug] (thuoc phan he Solutions).
  - Chi tiet du an nam duoi /projects/[slug] (thuoc phan he Projects).
  - Chi tiet bai viet tin tuc nam duoi /news/[slug] (thuoc phan he News & Insights).
  - Chinh sach nam duoi /policies/[slug] (thuoc phan he Policies).

---

## 5. Portal Isolation & Route Disambiguation

De loai tru triet de xung dot giua cac trang tiep thi cong khai va cac cong dich vu noi bo (Private Portals):

| Phan he | Route cong khai (Public) | Cong thong tin rieng tu (Private Portal) | Muc dich phan tach |
| :--- | :--- | :--- | :--- |
| **Business** | /business<br>(Gioi thieu giai phap sac cho DN) | /portal/business<br>(Quan ly to chuc, tram, dien nang) | Ngan chan viec private dashboard chiem dung slug tiep thi /business. |
| **Partner** | /partners<br>(Trang tiep thi danh cho doi tac) | /portal/partner<br>(Quan ly hieu suat dau tu, bao cao) | Tranh nham lan giua trang gioi thieu doi tac va bang dieu khien tai chinh cua nha dau tu. |
| **Internal / Admin** | - | /admin<br>(Giao dien van hanh ung dung EZEV) | Tach biet giao dien van hanh nghiep vu EZEV khoi giao dien quan tri ky thuat WordPress CMS /wp-admin. |
| **WordPress CMS** | - | /wp-admin<br>(Ky thuat WordPress) | Chi danh cho ky thuat vien va developers quan tri CMS, plugin, theme. |
