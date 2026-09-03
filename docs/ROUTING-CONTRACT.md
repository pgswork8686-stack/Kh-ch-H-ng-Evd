# EZEV ROUTING CONTRACT & URL ARCHITECTURE (FROZEN)

> **Document Status:** FROZEN / ARCHITECTURAL SPECIFICATION  
> **Baseline Commit:** 3d20ec28c75249669a08bced491e3943dd542837  
> **Target Branch:** develop/ezev-v1  
> **Implementation Phase:** Phase 4.0

---

## 1. Core Principles & Isolation Boundaries

Kien truc routing cua EZEV duoc xay dung dua tren 4 vung khong gian tach biet tuyet doi:

`	ext
[CLIENT BROWSER REQUEST]
      │
      ├── 1. PUBLIC MARKETING & DISCOVERY
      │      Route: / , /find-a-charger , /stations/[slug] , /charging-network , ...
      │      Access: Unauthenticated (Public), SEO Indexed
      │
      ├── 2. UNIFIED AUTHENTICATION ENGINE
      │      Route: /login , /logout , /forgot-password , /reset-password , /invite/[token]
      │      Access: Public entry, strict noindex
      │
      ├── 3. PRIVATE APPLICATION PORTALS
      │      Route: /account/*           (Customer Portal)
      │      Route: /portal/business/*   (Business Organization Portal)
      │      Route: /portal/partner/*    (Partner / Investor Portal)
      │      Route: /admin/*             (EZEV Internal Operations Portal)
      │      Access: Authenticated Only, Role/Membership Enforced, strict noindex
      │
      └── 4. TECHNICAL WORDPRESS CMS BACKEND
             Route: /wp-admin/*
             Access: System Administrators (manage_options) only
`

### Quy tac bat bien:
1. **Khong trung lap URL (Zero Overlap):** Tuyet doi khong dung chung mot URL slug cho ca trang tiep thi cong khai va ung dung private dashboard.
   - Public: /business (Trang tiep thi giai phap B2B).
   - Private: /portal/business (Cong quan tri to chuc doanh nghiep).
   - Public: /partners (Trang gioi thieu doi tac).
   - Private: /portal/partner (Cong theo doi hieu suat doi tac & nha dau tu).
2. **Tach biet EZEV Operations UI va WordPress Admin:**
   - /admin/* la giao dien ung dung Single Page / Frontend Portal hien dai danh cho can bo van hanh, ky thuat vien va dieu hanh mang luoi EZEV.
   - /wp-admin/* chi phuc vu ky thuat: quan tri CMS, viet bai blog, cai dat plugin, database migration. Nguoi dung khong co capability manage_options khi truy cap /wp-admin se bi he thong chan va dieu huong ngay lap tuc ve portal tuong ung cua ho.

---

## 2. Domain Entity Identifiers Contract

Moi thuc the nghiep vu trong EZEV deu so huu **Stable String Identifier** doc lap voi Auto-Increment ID hay WordPress Post ID:

| Entity Domain | Stable String Identifier Format | Vi du Identifier | API Identity Role | Cam su dung lam REST identity |
| :--- | :--- | :--- | :--- | :--- |
| **Organization** | EZEV-ORG-[A-Z0-9]{12} | EZEV-ORG-8K2M9X1A4L7B | Primary key nghiep vu | Numeric id (e.g. 1, 2) |
| **Site** | EZEV-SITE-[A-Z0-9]{12} | EZEV-SITE-P9L4N2X8K1M7 | Primary key dia diem | Numeric id |
| **Station** | EZEV-VN-[A-Z0-9-]+ | EZEV-VN-SGN-001 | Station Code toan mang | WordPress Post ID (post_id) |
| **Membership** | EZEV-MEM-[A-Z0-9]{12} | EZEV-MEM-K9X2M1A7L4B8 | Dinh danh thanh vien to chuc | Numeric id |
| **Invitation** | EZEV-INV-[A-Z0-9]{12} | EZEV-INV-T5B8M2X1K4L9 | Ma tham chieu thu moi | Numeric id |
| **Charger** | CHG-[A-Z0-9-]+ | CHG-SGN-001-A | Dinh danh thiet bi tru sac | Hardware serial |
| **Connector** | CON-[A-Z0-9-]+ | CON-SGN-001-A-1 | Dinh danh dau cam sac | Internal array index |
| **Session** | SES-[A-Z0-9-]+ | SES-20260903-8841 | Phien sac toan he thong | Auto-increment primary key |
| **Alert** | ALT-[A-Z0-9-]+ | ALT-2026-9912 | Ma dinh danh canh bao | Temporary log ID |
| **Maintenance** | TKT-[A-Z0-9-]+ | TKT-ALT-82914 | Ma phieu sua chua/bao tri | Numeric auto-increment |

### Quy tac cho Frontend:
- Route public /stations/[slug] su dung slug than thien SEO cho nguoi dung. Tuy nhien, data layer tren frontend phai resolve slug thanh station_id (hoac API tra ve kem station_id), va moi query/mutation tiep theo den REST API bat buoc phai su dung station_id.
- Theme va Frontend developers **khong duoc goi truc tiep $post_id** vao cac REST API endpoint hoac truyen $post_id vao cac modal nghiep vu.

---

## 3. SEO vs Public vs Private Route Classification

`	ext
/ (Home)                                 -> [Public / Index]
/find-a-charger                          -> [Public / Index]
/stations/[slug]                         -> [Public / Index]
/charging-network                        -> [Public / Index]
/how-to-charge                           -> [Public / Index]
/charging-rates                          -> [Public / Index]
/drivers                                 -> [Public / Index]
/business                                -> [Public / Index]
/partners                                -> [Public / Index]
/partners/register                       -> [Public / Index]
/solutions                               -> [Public / Index]
/solutions/[slug]                        -> [Public / Index]
/projects                                -> [Public / Index]
/projects/[slug]                         -> [Public / Index]
/news                                    -> [Public / Index]
/news/[slug]                             -> [Public / Index]
/about                                   -> [Public / Index]
/support                                 -> [Public / Index]
/contact                                 -> [Public / Index]
/policies/privacy                        -> [Public / Index]
/policies/terms                          -> [Public / Index]
/policies/charging                       -> [Public / Index]
/policies/[slug]                         -> [Public / Index]

/login                                   -> [Public / Noindex]
/forgot-password                         -> [Public / Noindex]
/reset-password                          -> [Public / Noindex]
/invite/[token]                          -> [Public / Noindex]
/logout                                  -> [Public / Noindex]

/account/*                               -> [Private / Noindex / Auth Required]
/portal/business/*                       -> [Private / Noindex / Auth Required]
/portal/partner/*                        -> [Private / Noindex / Auth Required]
/admin/*                                 -> [Private / Noindex / Auth Required]
/wp-admin/*                              -> [Technical CMS / Noindex / Manage Options Required]
`

---

## 4. Authentication Redirection Matrix

Khi nguoi dung dang nhap thanh cong thong qua /login (hoac submit form POST /ezev/v1/auth/login), he thong xac thuc tra ve edirect_url dua tren vai tro cao nhat cua tai khoan:

| Role cua User | Primary Portal Destination | Route Redirection |
| :--- | :--- | :--- |
| dministrator (co manage_options) | Technical WordPress Admin | /wp-admin/ |
| ezev_internal_ops / ezev_internal_technical | EZEV Operations Portal | /admin/ |
| ezev_business | Business Organization Portal | /portal/business/ |
| ezev_partner / ezev_investor | Partner & Investor Portal | /portal/partner/ |
| ezev_customer / Mac dinh | Customer Portal | /account/ |

---

## 5. Lifecycle & Unpublish 404 Handling

He thong routing phai tuan thu vong doi Master Data:

1. **Station Unpublished / Trashed:**
   - Public REST API /ezev/v1/stations lap tuc loai bo trạm khoi danh sach public collection.
   - Trạm lap tuc bien mat khoi ban do Find a Charger va Mini map tren Home.
   - Khi nguoi dung truy cap truc tiep URL /stations/[slug] cua tram da bi unpublish, he thong phai tra ve ma loi **HTTP 404 Not Found** kem thong bao "Tram sac hien khong hoat dong hoac da ngung cung cap dich vu".
   - Cac chi so tong hop (Total Stations, Network KPIs) tren Home va Charging Network lap tuc cap nhat giam so luong.
2. **Station Updated:**
   - Moi thay doi ve ten, cong suat, danh sach cong sac, gia sac phai duoc phan anh dong bo xuyen suot ca 4 trang P0 (Home, Find Charger, Station Detail, Charging Network).
