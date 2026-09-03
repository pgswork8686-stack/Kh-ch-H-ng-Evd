# EZEV NAVIGATION ARCHITECTURE (FROZEN)

> **Document Status:** FROZEN / ARCHITECTURAL SPECIFICATION  
> **Baseline Commit:** 3d20ec28c75249669a08bced491e3943dd542837  
> **Target Branch:** develop/ezev-v1  
> **Implementation Phase:** Phase 4.0

---

## 1. Global Header Navigation

Header duoc thiet ke toi uu cho ca trai nghiem tiep thi va chuyen doi nguoi dung (Conversion Rate Optimization - CRO), giu cho giao dien thanh thoat nhung van bao quat duoc toan bo he sinh thai EZEV.

### 1.1 Desktop Header Layout

`	ext
[ EZEV LOGO ]   [Find a Charger]   [Charging ▾]   [Business ▾]   [Discover ▾]   [Support]       [CTA: Find a Charger]   [Account ▾]
`

### 1.2 Chi tiet cac Dropdown Menu

#### A. Menu "Charging" (Ha tang & Tien ich nguoi dung)
- **Charging Network:** Mang luoi tram sac & quy mo do phu (/charging-network)
- **How to Charge:** Huong dan cac buoc sac xe dien an toan (/how-to-charge)
- **Charging Rates:** Bieu phi dich vu va gia dien sac (/charging-rates)
- **For Drivers:** Ung dung va tien ich danh rieng cho chu xe (/drivers)

#### B. Menu "Business" (Giai phap danh cho doi tac B2B)
- **For Business:** Giai phap tram sac cho toa nha, khu do thi, trung tam thuong mai (/business)
- **Partners:** Mang luoi chu mat bang va nha dau tu (/partners)
- **Become a Partner:** Dang ky hop tac lap dat tram sac EZEV (/partners/register)

#### C. Menu "Discover" (Kham pha EZEV & He sinh thai EVD)
- **Solutions:** Cong nghe tram sac, he thong quan ly nang luong & IoT (/solutions)
- **Projects:** Cac du an tieu bieu da trien khai tai Viet Nam & Dong Nam A (/projects)
- **News & Insights:** Tin tuc nganh xe dien va bai viet chuyen gia (/news)
- **About EVD:** Tam nhin, su menh va nang luc tap doan EVD (/about)

#### D. Direct Links & Call-to-Action (CTA)
- **Find a Charger (Main Nav Item & Primary Button):** Direct link to /find-a-charger.
- **Support:** Direct link to /support.

---

## 2. Account Dropdown & Authentication State

Nut "Account" tren Header xu ly theo 2 trang thai:

### A. Trang thai Guest (Chua dang nhap)
Khi re chuot hoac nhap vao "Account", he thong hien thi Dropdown voi 3 lua chon ro rang theo boi canh nguoi dung:

1. **Customer Login**
   - *Subtitle:* For drivers and customers
   - *Target URL:* /login?type=customer
2. **Partner Login**
   - *Subtitle:* For partners and investors
   - *Target URL:* /login?type=partner
3. **EZEV Internal**
   - *Subtitle:* For EZEV staff and operations team
   - *Target URL:* /login?type=internal

*(Luu y: Ca 3 lua chon tren deu mo cung mot trang dang nhap he thong /login nhung mang theo context de giao dien frontend co the hien thi logo, loi chao va huong dan phu hop).*

### B. Trang thai Authenticated (Da dang nhap)
Khi nguoi dung da dang nhap, nut "Account" hien thi Ten / Avatar nguoi dung kem menu ngu canh:
- **My Dashboard:** Dan den portal phu hop (/account, /portal/business, /portal/partner, hoac /admin).
- **Profile & Settings:** Tuy chon thiet lap tai khoan.
- **Log Out:** Goi action /logout de huy cookie phien lam viec va chuyen huong ve Trang chu (/).

---

## 3. Global Footer Architecture

Footer duoc to chuc thanh 5 cot lon bao phu toan bo 17 nhom trang cua Sitemap, ket hop thong tin phap ly va lien he:

`	ext
====================================================================================================
[ EZEV LOGO & MISSION STATEMENT ]
Tap doan Nang luong EVD - Tien phong ha tang tram sac xe dien thong minh tieu chuan quoc te.
Hotline: 1900-xxxx | Email: contact@ezev.vn
----------------------------------------------------------------------------------------------------
COT 1: CHARGING          COT 2: BUSINESS          COT 3: DISCOVER          COT 4: SUPPORT & LEGAL
- Find a Charger         - For Business           - Solutions              - Help Center / FAQ
- Charging Network       - Partners Ecosystem     - Featured Projects      - Contact Us
- How to Charge          - Become a Partner       - News & Insights        - Privacy Policy
- Charging Rates         - Business Portal        - About EVD              - Terms of Service
- For Drivers            - Partner Portal         - Careers                - Charging Policy
====================================================================================================
COT 5: APP & SOCIAL
- Download EZEV Mobile App (App Store / Google Play)
- Social Icons: Facebook, LinkedIn, YouTube
- Copyright (c) 2026 EZEV Global / EVD Group. All rights reserved.
====================================================================================================
`

---

## 4. Mobile Navigation & Drawer Architecture

Tren thiet bi di dong (Viewport < 1024px):
- Header co gon lai gom: [ EZEV LOGO ], nut icon [ Search / Map ] (/find-a-charger), va nut hamburger [ Menu ].
- Khi mo Hamburger Menu, hien thi Full-height Navigation Drawer:
  - **Quick Action Bar:** Nut lon Find a Charger (Noi bat).
  - **Accordion Phap ly & Danh muc:**
    - Charging (Mo rong: Network, How to charge, Rates, Drivers)
    - Business (Mo rong: Solutions for business, Partners, Register)
    - Discover (Mo rong: Solutions, Projects, News, About)
    - Support & Contact
  - **Account Section:** Hien thi truc tiep 3 nut Login rieng biet (Customer, Partner, Internal).
  - **Language Selector:** Chon ngon ngu EN / VI / ZH.

---

## 5. Breadcrumbs Navigation Hierarchy

Moi trang con (Level 2 va Level 3) deu phai tich hop breadcrumbs hop chuan Schema.org:

- Home > Find a Charger > **[Tên Trạm Sạc]**
- Home > Solutions > **[Tên Giải Pháp]**
- Home > Projects > **[Tên Dự Án]**
- Home > News & Insights > **[Tiêu Đề Bài Viết]**
- Home > Policies > **[Tên Chính Sách]**
- Home > Portal > Business > **[Tên Site / Tên Trạm]**
