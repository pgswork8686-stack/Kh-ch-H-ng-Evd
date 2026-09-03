# EZEV FRONTEND IMPLEMENTATION ORDER & ACCEPTANCE GATES (FROZEN)

> **Document Status:** FROZEN / ARCHITECTURAL SPECIFICATION  
> **Baseline Commit:** 3d20ec28c75249669a08bced491e3943dd542837  
> **Target Branch:** develop/ezev-v1  
> **Implementation Phase:** Phase 4.0

---

## 1. Phased Execution Roadmap

Toan bo qua trinh xay dung Theme va Frontend cua EZEV phai tuan theo trinh tu 3 giai doan bat bien sau day. **Cam tuyet doi viec nhay coc sang P2 khi P0 chua duoc nghiem thu PASS tren moi truong that.**

`	ext
========================================================================================
PHASE P0: THE 4 CORE PUBLIC EXPERIENCES (PRIORITY HIGHEST)
1. Minimal Theme Foundation (Typography, Colors, Layout Shell, Google Maps SDK loader)
2. 01. Home (Trang chu) [/]
3. 02. Find a Charger (Tim tram sac) [/find-a-charger]
4. 03. Station Detail (Chi tiet tram) [/stations/[slug]]
5. 04. Charging Network (Mang luoi) [/charging-network]
========================================================================================
                                      │
                                      ▼ (GATE P0 VERIFIED & PASSED)
========================================================================================
PHASE P1: PUBLIC CONTENT, MARKETING & MARKETING ECOSYSTEM
6. 05. How to Charge [/how-to-charge]
7. 06. Charging Rates [/charging-rates]
8. 07. For Drivers [/drivers]
9. 08. For Business [/business]
10. 09. Partners [/partners]
11. 10. Become a Partner [/partners/register]
12. 11. Solutions & Solution Detail [/solutions, /solutions/[slug]]
13. 12. Projects & Project Detail [/projects, /projects/[slug]]
14. 13. News & Insights & Article Detail [/news, /news/[slug]]
15. 14. About EVD [/about]
16. 15. Support & FAQ [/support]
17. 16. Contact Us [/contact]
18. 17. Policies (Privacy, Terms, Charging, Detail) [/policies/*]
========================================================================================
                                      │
                                      ▼ (GATE P1 VERIFIED & PASSED)
========================================================================================
PHASE P2: AUTHENTICATION ENGINE & PRIVATE APPLICATION PORTALS
19. Shared Authentication Engine [/login, /logout, /forgot-password, /reset-password, /invite]
20. Customer Portal [/account/*] (Saved stations, notifications, security)
21. Business Portal [/portal/business/*] (Sites, stations, team, energy, reports)
22. Partner & Investor Portal [/portal/partner/*] (Performance, energy, reports)
23. EZEV Internal / Admin Portal [/admin/*] (Live network GIS, full CRUD operations)
========================================================================================
`

---

## 2. P0 End-to-End Acceptance Verification Flow

Sau khi hoan thanh P0, he thong phai vuot qua bai kiem tra vong doi Master Data xuyen suot giua Backend va 4 trang Core:

`	ext
[STEP 1: CREATION]
Admin tao Station moi tren backend (hoac POST /ezev/v1/stations)
         ↓
Du lieu ghi nhan thanh cong vao WordPress + MySQL
         ↓
REST API GET /ezev/v1/stations tra ve station moi trong danh sach
         ↓
Trang Home [/]: Tong so tram tang +1, mini map xuat hien pin moi
         ↓
Trang Find Charger [/find-a-charger]: Google Maps xuat hien Marker moi, click mo InfoWindow
         ↓
Trang Station Detail [/stations/[slug]]: Mo chi tiet dung thong tin, hien thi cong sac
         ↓
Trang Charging Network [/charging-network]: Tong chi so do phu toan quoc/tinh tang +1

[STEP 2: MODIFICATION]
Sua ten tram hoac cap nhat them cong sac tren backend
         ↓
Ca 4 trang (Home, Find Charger, Detail, Network) lap tuc dong bo thong tin moi khi tai lai trang

[STEP 3: UNPUBLISH / DELETION]
Chuyen trang thai Station sang 'draft' hoac 'trash'
         ↓
REST API public /ezev/v1/stations loai bo tram khoi danh sach
         ↓
Marker tren Find Charger va Home mini map bien mat
         ↓
Truy cap truc tiep /stations/[slug] tra ve HTTP 404 Not Found chuan
         ↓
Cac dong ho do dem (Network KPIs) tren Home va Charging Network giam dung 1
`

Chi khi toan bo quy trinh 3 buoc tren chay hoan hao tren WordPress that moi duoc cong nhan **P0 PASS** va chuyen sang **P1**.
