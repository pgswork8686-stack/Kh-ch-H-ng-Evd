# EZEV SEO ROUTING, XML SITEMAP & MULTILINGUAL ARCHITECTURE (FROZEN)

> **Document Status:** FROZEN / ARCHITECTURAL SPECIFICATION  
> **Baseline Commit:** 3d20ec28c75249669a08bced491e3943dd542837  
> **Target Branch:** develop/ezev-v1  
> **Implementation Phase:** Phase 4.0

---

## 1. Information Architecture Sitemap vs XML SEO Sitemap

Can phan biet ro 2 khai niem nay trong qua trinh phat trien Frontend:
- **Information Architecture Sitemap (IA Sitemap - SITEMAP.md):** Ban do toan dien mo ta tat ca cac man hinh, portal noi bo, trang xac thuc va flow nguoi dung cua toan bo he thong EZEV.
- **XML SEO Sitemap (sitemap.xml):** Tep tin may chu dac thu chi danh rieng cho cac bot tim kiem (Googlebot, Bingbot...) thu thap du lieu va lap chi muc cac trang cong khai co gia tri tiep thi.

---

## 2. XML Sitemap Inclusion & Exclusion Rules

### 2.1 Cac trang BAT BUOC INCLUDE vao XML Sitemap:
- Trang chu: /
- Tim tram sac: /find-a-charger
- Chi tiet tung tram sac cong khai: /stations/[slug]
- Mang luoi tram sac: /charging-network
- Huong dan sac: /how-to-charge
- Bieu gia dich vu: /charging-rates
- Trang danh cho tai xe: /drivers
- Trang giai phap doanh nghiep: /business
- Trang he sinh thai doi tac: /partners
- Trang dang ky doi tac: /partners/register
- Danh muc va chi tiet giai phap: /solutions, /solutions/[slug]
- Danh muc va chi tiet du an: /projects, /projects/[slug]
- Danh muc va chi tiet tin tuc: /news, /news/[slug]
- Trang gioi thieu tap doan: /about
- Trung tam ho tro: /support
- Trang lien he: /contact
- Cac trang chinh sach phap ly: /policies/privacy, /policies/terms, /policies/charging, /policies/[slug]

### 2.2 Cac trang TUYET DOI LOAI TRU khoi XML Sitemap:
- He thong xac thuc: /login, /logout, /forgot-password, /reset-password, /invite/*
- Cong khach hang ca nhan: /account/*
- Cong doanh nghiep B2B: /portal/business/*
- Cong doi tac & nha dau tu: /portal/partner/*
- Cong dieu hanh mang luoi EZEV: /admin/*
- Quan tri ky thuat WordPress: /wp-admin/*

---

## 3. Robots Meta Tag Enforcement Matrix

Moi template trang khi render phai sinh ra the meta obots tuong ung trong <head>:

| Nhom trang | Template Family | Robots Meta Tag Output | Muc dich |
| :--- | :--- | :--- | :--- |
| **Public Marketing & Content** | 	emplate-home<br>	emplate-find-charger<br>	emplate-station-detail<br>	emplate-charging-network<br>	emplate-content<br>	emplate-listing<br>	emplate-detail | <meta name="robots" content="index, follow, max-image-preview:large"> | Toi uu hoa thu hang SEO toan dien tren cong cu tim kiem. |
| **Authentication Engine** | 	emplate-auth<br>	emplate-auth-invite | <meta name="robots" content="noindex, nofollow"> | Ngan chan Google index form dang nhap hoac link moi rieng tu. |
| **Private Application Portals** | 	emplate-customer-portal<br>	emplate-business-portal<br>	emplate-partner-portal<br>	emplate-admin-portal | <meta name="robots" content="noindex, nofollow, noarchive"> | Bao mat tuyet doi thong tin kinh doanh, khong cache trang noi bo. |

---

## 4. Multilingual Readiness Strategy (EN / VI / ZH)

He thong EZEV huong den thi truong khu vuc (Viet Nam, Philippines, Trung Quoc), yeu cau ho tro 3 ngon ngu: **Tieng Anh (EN), Tieng Viet (VI), va Tieng Trung (ZH)**.

### 4.1 Nguyen tac can ban:
1. **Canonical Route Slugs:** V1 su dung Canonical English URL slugs lam tieu chuan goc (vi du: /find-a-charger, /charging-network, /business).
2. **Khong hardcode copy van ban:** Toan bo text giao dien (labels, placeholder, thong bao loi) phai duoc boc trong ham ban dia hoa quoc te (__() hoac i18n dictionary object), tuyet doi khong viet chuoi tieng Anh cung vao cac ham logic nghiep vu.
3. **Prefix Routing cho tuong lai:** Kien truc routing duoc thiet ke san de khi kich hoat che do da ngon ngu se su dung URL prefix ma khong pha vo REST API hay business identifiers:
   - Tieng Anh: /en/find-a-charger hoac default /find-a-charger
   - Tieng Viet: /vi/find-a-charger
   - Tieng Trung: /zh/find-a-charger
4. **Giu nguyen Identifier Domain:** Du giao dien hien thi ngon ngu nao, cac ma dinh danh station_id (e.g. EZEV-VN-SGN-001), site_id, organization_id van giu nguyen 100%, khong bi dich thuat hoa.
